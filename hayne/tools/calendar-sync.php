#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * HAYNE Leave work-calendar synchronizer.
 *
 * Builds company non-working days from:
 *   - every Saturday and Sunday (local, deterministic),
 *   - Polish public holidays fetched from Nager.Date over HTTPS.
 *
 * Default mode is READ ONLY. Writes require both --apply and
 * HAYNE_CALENDAR_APPLY_ENABLED=TRUE. Only rows whose title starts with the
 * HAYNE managed prefix are updated/deleted; manually maintained Jorani dayoffs
 * are preserved and take precedence for a date.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "STOP: CLI only.\n");
    exit(64);
}

const MANAGED_PREFIX = 'HAYNE-CALENDAR|';

$options = getopt('', ['apply', 'self-test', 'years:']);
$applyMode = array_key_exists('apply', $options);
$selfTest = array_key_exists('self-test', $options);
$yearsArg = isset($options['years']) ? trim((string) $options['years']) : '';

function envRaw(string $name, ?string $default = null): string
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        if ($default !== null) {
            return $default;
        }
        throw new RuntimeException("Missing environment variable: {$name}");
    }
    return (string) $value;
}

function envString(string $name, ?string $default = null): string
{
    return trim(envRaw($name, $default));
}

function envBool(string $name, bool $default = false): bool
{
    $raw = getenv($name);
    if ($raw === false || trim((string) $raw) === '') {
        return $default;
    }
    $value = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if ($value === null) {
        throw new RuntimeException("Invalid boolean environment variable: {$name}");
    }
    return $value;
}

function envInt(string $name, int $default, int $min, int $max): int
{
    $raw = getenv($name);
    if ($raw === false || trim((string) $raw) === '') {
        return $default;
    }
    if (!preg_match('/^\d+$/', trim((string) $raw))) {
        throw new RuntimeException("Invalid integer environment variable: {$name}");
    }
    $value = (int) $raw;
    if ($value < $min || $value > $max) {
        throw new RuntimeException("Out-of-range environment variable: {$name}");
    }
    return $value;
}

/** @return list<int> */
function parsePositiveIntList(string $value, string $label): array
{
    $items = array_values(array_filter(array_map('trim', explode(',', $value)), static fn(string $v): bool => $v !== ''));
    if ($items === []) {
        throw new RuntimeException("{$label} is empty");
    }
    $result = [];
    foreach ($items as $item) {
        if (!preg_match('/^\d+$/', $item) || (int) $item < 1) {
            throw new RuntimeException("Invalid {$label} value: {$item}");
        }
        $result[] = (int) $item;
    }
    $result = array_values(array_unique($result));
    sort($result, SORT_NUMERIC);
    return $result;
}

/** @return list<int> */
function resolveYears(string $yearsArg): array
{
    if ($yearsArg !== '') {
        $years = parsePositiveIntList($yearsArg, '--years');
        foreach ($years as $year) {
            if ($year < 2000 || $year > 2100) {
                throw new RuntimeException("Unsupported year: {$year}");
            }
        }
        return $years;
    }

    $currentYear = (int) (new DateTimeImmutable('now', new DateTimeZone('Europe/Warsaw')))->format('Y');
    $ahead = envInt('HAYNE_CALENDAR_YEARS_AHEAD', 1, 0, 5);
    $years = [];
    for ($offset = 0; $offset <= $ahead; $offset++) {
        $years[] = $currentYear + $offset;
    }
    return $years;
}

function validYmd(string $date): bool
{
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('Europe/Warsaw'));
    return $parsed !== false && $parsed->format('Y-m-d') === $date;
}

function managedTitle(string $kind, string $label): string
{
    $label = trim(preg_replace('/\s+/u', ' ', $label) ?? $label);
    $title = MANAGED_PREFIX . $kind . '|' . $label;
    if (function_exists('mb_substr')) {
        return mb_substr($title, 0, 128, 'UTF-8');
    }
    return substr($title, 0, 128);
}

/** @return array<string, array{date:string,type:int,title:string,kind:string}> */
function buildWeekendDays(int $year): array
{
    $tz = new DateTimeZone('Europe/Warsaw');
    $date = new DateTimeImmutable(sprintf('%04d-01-01', $year), $tz);
    $end = new DateTimeImmutable(sprintf('%04d-12-31', $year), $tz);
    $days = [];
    while ($date <= $end) {
        $dayOfWeek = (int) $date->format('N');
        if ($dayOfWeek === 6 || $dayOfWeek === 7) {
            $ymd = $date->format('Y-m-d');
            $label = $dayOfWeek === 6 ? 'Sobota' : 'Niedziela';
            $days[$ymd] = [
                'date' => $ymd,
                'type' => 1,
                'title' => managedTitle('WEEKEND', $label),
                'kind' => 'weekend',
            ];
        }
        $date = $date->modify('+1 day');
    }
    return $days;
}

/** @return array{status:int,body:string} */
function httpGet(string $url, int $timeout): array
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $timeout,
            'ignore_errors' => true,
            'header' => "Accept: application/json\r\nUser-Agent: HAYNE-Leave-Calendar-Sync/1.0\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    $headers = $http_response_header ?? [];
    $status = 0;
    if (isset($headers[0]) && preg_match('#\s(\d{3})\s#', $headers[0], $match)) {
        $status = (int) $match[1];
    }
    if ($body === false) {
        throw new RuntimeException("Holiday API request failed: {$url}");
    }
    return ['status' => $status, 'body' => $body];
}

/** @return array<string, array{date:string,type:int,title:string,kind:string}> */
function fetchPublicHolidays(int $year, string $countryCode, string $urlTemplate, int $timeout): array
{
    $url = sprintf($urlTemplate, rawurlencode($countryCode), $year);
    $parts = parse_url($url);
    if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
        throw new RuntimeException('Holiday API URL must use HTTPS');
    }

    $response = httpGet($url, $timeout);
    if ($response['status'] !== 200) {
        throw new RuntimeException("Holiday API returned HTTP {$response['status']} for {$year}");
    }

    try {
        $rows = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException("Holiday API returned invalid JSON for {$year}: " . $e->getMessage());
    }
    if (!is_array($rows) || !array_is_list($rows)) {
        throw new RuntimeException("Holiday API returned an unexpected payload for {$year}");
    }

    $holidays = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $date = trim((string) ($row['date'] ?? ''));
        if (!validYmd($date) || (int) substr($date, 0, 4) !== $year) {
            throw new RuntimeException("Holiday API returned invalid date for {$year}");
        }

        $holidayTypes = $row['holidayTypes'] ?? null;
        if (is_array($holidayTypes)) {
            $normalizedTypes = array_map(static fn($v): string => strtolower(trim((string) $v)), $holidayTypes);
            if (!in_array('public', $normalizedTypes, true)) {
                continue;
            }
        }

        $label = trim((string) ($row['localName'] ?? $row['name'] ?? 'Święto ustawowe'));
        if ($label === '') {
            $label = 'Święto ustawowe';
        }
        $holidays[$date] = [
            'date' => $date,
            'type' => 1,
            'title' => managedTitle('HOLIDAY', $label),
            'kind' => 'holiday',
        ];
    }

    // Poland normally has substantially more than ten statutory holidays.
    // A low count is treated as an upstream/API schema failure, not as valid data.
    if (count($holidays) < 10 || count($holidays) > 25) {
        throw new RuntimeException("Suspicious public-holiday count for {$year}: " . count($holidays));
    }
    ksort($holidays, SORT_STRING);
    return $holidays;
}

/** @return array<string, array{date:string,type:int,title:string,kind:string}> */
function buildTargetCalendar(array $years, string $countryCode, string $urlTemplate, int $timeout, array &$sourceStats): array
{
    $target = [];
    foreach ($years as $year) {
        $weekends = buildWeekendDays($year);
        $holidays = fetchPublicHolidays($year, $countryCode, $urlTemplate, $timeout);
        $sourceStats[$year] = ['weekends' => count($weekends), 'holidays' => count($holidays)];
        foreach ($weekends as $date => $entry) {
            $target[$date] = $entry;
        }
        // Holiday label wins when a statutory holiday falls on a weekend.
        foreach ($holidays as $date => $entry) {
            $target[$date] = $entry;
        }
    }
    ksort($target, SORT_STRING);
    return $target;
}

function connectDb(): PDO
{
    if (!extension_loaded('pdo_mysql')) {
        throw new RuntimeException('PDO MySQL extension is not loaded');
    }
    $host = envString('HAYNE_DB_HOST', envString('MYSQL_HOST', 'mysql'));
    $port = envInt('HAYNE_DB_PORT', envInt('MYSQL_PORT', 3306, 1, 65535), 1, 65535);
    $database = envString('HAYNE_DB_DATABASE', envString('MYSQL_DATABASE'));
    $user = envString('HAYNE_DB_USER', envString('MYSQL_USER'));
    $password = envRaw('HAYNE_DB_PASSWORD', envRaw('MYSQL_PASSWORD', ''));
    if ($database === '' || $user === '') {
        throw new RuntimeException('Database environment is incomplete');
    }
    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
    return new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

/** @return array<int, string> */
function fetchContracts(PDO $pdo, array $contractIds): array
{
    $placeholders = implode(',', array_fill(0, count($contractIds), '?'));
    $stmt = $pdo->prepare("SELECT id, name FROM contracts WHERE id IN ({$placeholders}) ORDER BY id");
    $stmt->execute($contractIds);
    $found = [];
    foreach ($stmt->fetchAll() as $row) {
        $found[(int) $row['id']] = (string) $row['name'];
    }
    foreach ($contractIds as $id) {
        if (!isset($found[$id])) {
            throw new RuntimeException("Configured contract does not exist: {$id}");
        }
    }
    return $found;
}

/** @return list<array{id:int,contract:int,date:string,type:int,title:string}> */
function fetchExistingDayoffs(PDO $pdo, int $contractId, string $minDate, string $maxDate): array
{
    $stmt = $pdo->prepare(
        'SELECT id, contract, DATE_FORMAT(date, \'%Y-%m-%d\') AS date, type, title ' .
        'FROM dayoffs WHERE contract = ? AND date >= ? AND date <= ? ORDER BY date, id'
    );
    $stmt->execute([$contractId, $minDate, $maxDate]);
    return array_map(static function (array $row): array {
        return [
            'id' => (int) $row['id'],
            'contract' => (int) $row['contract'],
            'date' => (string) $row['date'],
            'type' => (int) $row['type'],
            'title' => (string) $row['title'],
        ];
    }, $stmt->fetchAll());
}

/**
 * @return array{operations:list<array<string,mixed>>,stats:array<string,int>}
 */
function buildPlan(int $contractId, array $existing, array $target): array
{
    $byDate = [];
    foreach ($existing as $row) {
        $byDate[$row['date']][] = $row;
    }

    $operations = [];
    $stats = [
        'insert' => 0,
        'update' => 0,
        'delete' => 0,
        'unchanged' => 0,
        'manual_covered' => 0,
    ];

    $allDates = array_values(array_unique(array_merge(array_keys($byDate), array_keys($target))));
    sort($allDates, SORT_STRING);

    foreach ($allDates as $date) {
        $rows = $byDate[$date] ?? [];
        $manual = [];
        $managed = [];
        foreach ($rows as $row) {
            if (str_starts_with($row['title'], MANAGED_PREFIX)) {
                $managed[] = $row;
            } else {
                $manual[] = $row;
            }
        }

        $desired = $target[$date] ?? null;
        if ($desired === null) {
            foreach ($managed as $row) {
                $operations[] = ['action' => 'delete', 'id' => $row['id'], 'contract' => $contractId, 'date' => $date];
                $stats['delete']++;
            }
            continue;
        }

        if ($manual !== []) {
            $stats['manual_covered']++;
            foreach ($managed as $row) {
                $operations[] = ['action' => 'delete', 'id' => $row['id'], 'contract' => $contractId, 'date' => $date];
                $stats['delete']++;
            }
            continue;
        }

        if ($managed === []) {
            $operations[] = [
                'action' => 'insert',
                'contract' => $contractId,
                'date' => $date,
                'type' => 1,
                'title' => $desired['title'],
            ];
            $stats['insert']++;
            continue;
        }

        $keep = array_shift($managed);
        if ($keep['type'] !== 1 || $keep['title'] !== $desired['title']) {
            $operations[] = [
                'action' => 'update',
                'id' => $keep['id'],
                'contract' => $contractId,
                'date' => $date,
                'type' => 1,
                'title' => $desired['title'],
            ];
            $stats['update']++;
        } else {
            $stats['unchanged']++;
        }

        foreach ($managed as $duplicate) {
            $operations[] = ['action' => 'delete', 'id' => $duplicate['id'], 'contract' => $contractId, 'date' => $date];
            $stats['delete']++;
        }
    }

    return ['operations' => $operations, 'stats' => $stats];
}

function applyOperations(PDO $pdo, array $operations): void
{
    $insert = $pdo->prepare('INSERT INTO dayoffs (contract, date, type, title) VALUES (?, ?, ?, ?)');
    $update = $pdo->prepare('UPDATE dayoffs SET type = ?, title = ? WHERE id = ? AND contract = ?');
    $delete = $pdo->prepare('DELETE FROM dayoffs WHERE id = ? AND contract = ?');

    $pdo->beginTransaction();
    try {
        foreach ($operations as $op) {
            if ($op['action'] === 'insert') {
                $insert->execute([$op['contract'], $op['date'], $op['type'], $op['title']]);
            } elseif ($op['action'] === 'update') {
                $update->execute([$op['type'], $op['title'], $op['id'], $op['contract']]);
            } elseif ($op['action'] === 'delete') {
                $delete->execute([$op['id'], $op['contract']]);
            } else {
                throw new RuntimeException('Unknown plan operation');
            }
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function runSelfTest(): void
{
    $weekends2026 = buildWeekendDays(2026);
    if (count($weekends2026) !== 104) {
        throw new RuntimeException('Self-test failed: unexpected 2026 weekend count');
    }
    if (!isset($weekends2026['2026-08-15']) || !isset($weekends2026['2026-08-16'])) {
        throw new RuntimeException('Self-test failed: known weekend dates missing');
    }
    if (isset($weekends2026['2026-08-17'])) {
        throw new RuntimeException('Self-test failed: weekday marked as weekend');
    }

    $target = [
        '2026-01-03' => ['date' => '2026-01-03', 'type' => 1, 'title' => managedTitle('WEEKEND', 'Sobota'), 'kind' => 'weekend'],
        '2026-01-04' => ['date' => '2026-01-04', 'type' => 1, 'title' => managedTitle('WEEKEND', 'Niedziela'), 'kind' => 'weekend'],
    ];
    $existing = [
        ['id' => 10, 'contract' => 1, 'date' => '2026-01-03', 'type' => 1, 'title' => 'Ręczny dzień wolny'],
        ['id' => 11, 'contract' => 1, 'date' => '2026-01-04', 'type' => 1, 'title' => managedTitle('WEEKEND', 'Niedziela')],
        ['id' => 12, 'contract' => 1, 'date' => '2026-01-05', 'type' => 1, 'title' => managedTitle('WEEKEND', 'stale')],
    ];
    $plan = buildPlan(1, $existing, $target);
    if ($plan['stats']['manual_covered'] !== 1 || $plan['stats']['unchanged'] !== 1 || $plan['stats']['delete'] !== 1) {
        throw new RuntimeException('Self-test failed: plan safety invariants');
    }
    echo "SELF-TEST: PASS\n";
}

try {
    if ($selfTest) {
        runSelfTest();
        exit(0);
    }

    if (!envBool('HAYNE_CALENDAR_SYNC_ENABLED', false)) {
        throw new RuntimeException('HAYNE_CALENDAR_SYNC_ENABLED is not TRUE');
    }

    $years = resolveYears($yearsArg);
    $contractIds = parsePositiveIntList(envString('HAYNE_CALENDAR_CONTRACT_IDS', '1'), 'HAYNE_CALENDAR_CONTRACT_IDS');
    $countryCode = strtoupper(envString('HAYNE_CALENDAR_COUNTRY_CODE', 'PL'));
    if (!preg_match('/^[A-Z]{2}$/', $countryCode)) {
        throw new RuntimeException('HAYNE_CALENDAR_COUNTRY_CODE must be a two-letter country code');
    }
    $urlTemplate = envString('HAYNE_CALENDAR_API_URL_TEMPLATE', 'https://date.nager.at/api/v4/Holidays/%s/%d');
    if (substr_count($urlTemplate, '%s') !== 1 || substr_count($urlTemplate, '%d') !== 1) {
        throw new RuntimeException('HAYNE_CALENDAR_API_URL_TEMPLATE must contain one %s and one %d placeholder');
    }
    $httpTimeout = envInt('HAYNE_CALENDAR_HTTP_TIMEOUT', 10, 2, 60);
    $maxChanges = envInt('HAYNE_CALENDAR_MAX_CHANGES', 500, 1, 5000);

    $sourceStats = [];
    $target = buildTargetCalendar($years, $countryCode, $urlTemplate, $httpTimeout, $sourceStats);
    if ($target === []) {
        throw new RuntimeException('Target calendar is empty');
    }

    $minDate = sprintf('%04d-01-01', min($years));
    $maxDate = sprintf('%04d-12-31', max($years));
    $pdo = connectDb();
    $contracts = fetchContracts($pdo, $contractIds);

    $allOperations = [];
    $totals = ['insert' => 0, 'update' => 0, 'delete' => 0, 'unchanged' => 0, 'manual_covered' => 0];

    echo $applyMode ? "HAYNE CALENDAR SYNC (APPLY)\n" : "HAYNE CALENDAR SYNC PLAN (READ ONLY)\n";
    echo str_repeat('=', 72) . "\n";
    echo 'COUNTRY: ' . $countryCode . "\n";
    echo 'YEARS: ' . implode(',', $years) . "\n";
    echo 'CONTRACTS: ' . implode(',', array_map(static fn(int $id): string => $id . ':' . $contracts[$id], $contractIds)) . "\n";
    foreach ($sourceStats as $year => $stats) {
        echo "SOURCE {$year}: WEEKENDS={$stats['weekends']} | PUBLIC_HOLIDAYS={$stats['holidays']}\n";
    }
    echo 'TARGET UNIQUE NON-WORKING DATES: ' . count($target) . "\n\n";

    foreach ($contractIds as $contractId) {
        $existing = fetchExistingDayoffs($pdo, $contractId, $minDate, $maxDate);
        $plan = buildPlan($contractId, $existing, $target);
        foreach ($plan['operations'] as $op) {
            $allOperations[] = $op;
        }
        foreach ($totals as $key => $_) {
            $totals[$key] += $plan['stats'][$key];
        }
        echo "CONTRACT {$contractId} ({$contracts[$contractId]})\n";
        echo "  INSERT: {$plan['stats']['insert']}\n";
        echo "  UPDATE: {$plan['stats']['update']}\n";
        echo "  DELETE_MANAGED: {$plan['stats']['delete']}\n";
        echo "  UNCHANGED_MANAGED: {$plan['stats']['unchanged']}\n";
        echo "  MANUAL_DATES_PRESERVED: {$plan['stats']['manual_covered']}\n";
    }

    $changeCount = $totals['insert'] + $totals['update'] + $totals['delete'];
    echo "\nPLAN SUMMARY\n";
    echo "INSERT: {$totals['insert']}\n";
    echo "UPDATE: {$totals['update']}\n";
    echo "DELETE MANAGED ONLY: {$totals['delete']}\n";
    echo "UNCHANGED MANAGED: {$totals['unchanged']}\n";
    echo "MANUAL DATES PRESERVED: {$totals['manual_covered']}\n";
    echo "DB CHANGES: {$changeCount}\n";
    echo "MAX CHANGES: {$maxChanges}\n";

    if ($changeCount > $maxChanges) {
        throw new RuntimeException("Change guard exceeded: {$changeCount} > {$maxChanges}");
    }

    if (!$applyMode) {
        echo "APPLY READY: YES\n";
        exit(0);
    }
    if (!envBool('HAYNE_CALENDAR_APPLY_ENABLED', false)) {
        throw new RuntimeException('Apply requested but HAYNE_CALENDAR_APPLY_ENABLED is not TRUE');
    }

    applyOperations($pdo, $allOperations);
    echo "APPLY RESULT: PASS\n";
    echo "APPLIED CHANGES: {$changeCount}\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'STOP: ' . $e->getMessage() . "\n");
    exit(1);
}
