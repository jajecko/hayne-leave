#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * HAYNE Leave AD directory preview.
 *
 * READ ONLY by design: this tool binds to Active Directory, searches employees,
 * validates replication/failover and prints an audit. It never connects to the
 * Jorani database and never performs LDAP write operations.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "STOP: CLI only.\n");
    exit(64);
}

$options = getopt('', ['check', 'json', 'self-test']);
$jsonOutput = array_key_exists('json', $options);
$checkOnly = array_key_exists('check', $options);

function envString(string $name, ?string $default = null): string
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        if ($default !== null) {
            return $default;
        }
        throw new RuntimeException("Missing environment variable: {$name}");
    }

    return trim((string) $value);
}

function envInt(string $name, int $default, int $min, int $max): int
{
    $raw = getenv($name);
    if ($raw === false || $raw === '') {
        return $default;
    }

    if (!preg_match('/^\d+$/', (string) $raw)) {
        throw new RuntimeException("Invalid integer environment variable: {$name}");
    }

    $value = (int) $raw;
    if ($value < $min || $value > $max) {
        throw new RuntimeException("Out-of-range environment variable: {$name}");
    }

    return $value;
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

function escapeFilterValue(string $value): string
{
    if (function_exists('ldap_escape') && defined('LDAP_ESCAPE_FILTER')) {
        return ldap_escape($value, '', LDAP_ESCAPE_FILTER);
    }

    return strtr($value, [
        "\\" => '\\5c',
        '*' => '\\2a',
        '(' => '\\28',
        ')' => '\\29',
        "\0" => '\\00',
    ]);
}

function binaryGuidToString(string $binary): string
{
    if (strlen($binary) !== 16) {
        throw new RuntimeException('objectGUID is not 16 bytes');
    }

    $hex = bin2hex($binary);

    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 6, 2) . substr($hex, 4, 2) . substr($hex, 2, 2) . substr($hex, 0, 2),
        substr($hex, 10, 2) . substr($hex, 8, 2),
        substr($hex, 14, 2) . substr($hex, 12, 2),
        substr($hex, 16, 4),
        substr($hex, 20, 12)
    );
}

function textValue(array $entry, string $attribute): string
{
    $key = strtolower($attribute);
    return isset($entry[$key][0]) ? trim((string) $entry[$key][0]) : '';
}

function rawValue(array $entry, string $attribute): string
{
    $key = strtolower($attribute);
    return isset($entry[$key][0]) ? (string) $entry[$key][0] : '';
}

function normalizedDn(string $dn): string
{
    return strtolower(trim($dn));
}

function duplicateKeys(array $counts): array
{
    $duplicates = [];
    foreach ($counts as $key => $count) {
        if ($key !== '' && $count > 1) {
            $duplicates[$key] = $count;
        }
    }
    ksort($duplicates, SORT_NATURAL | SORT_FLAG_CASE);
    return $duplicates;
}

function increment(array &$counts, string $value): void
{
    if ($value === '') {
        return;
    }
    $counts[$value] = ($counts[$value] ?? 0) + 1;
}

function queryHost(
    string $host,
    int $port,
    string $baseDn,
    string $bindDn,
    string $password,
    string $filter,
    int $networkTimeout,
    int $timeLimit
): array {
    $ldap = @ldap_connect("ldaps://{$host}:{$port}");
    if ($ldap === false) {
        throw new RuntimeException('ldap_connect failed');
    }

    ldap_set_option($ldap, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($ldap, LDAP_OPT_REFERRALS, 0);
    ldap_set_option($ldap, LDAP_OPT_NETWORK_TIMEOUT, $networkTimeout);
    ldap_set_option($ldap, LDAP_OPT_TIMELIMIT, $timeLimit);

    if (!@ldap_bind($ldap, $bindDn, $password)) {
        $error = ldap_error($ldap);
        ldap_unbind($ldap);
        throw new RuntimeException("bind failed: {$error}");
    }

    $attributes = [
        'objectGUID',
        'distinguishedName',
        'sAMAccountName',
        'givenName',
        'sn',
        'mail',
        'manager',
        'department',
        'title',
        'employeeType',
        'userAccountControl',
    ];

    $result = @ldap_search(
        $ldap,
        $baseDn,
        $filter,
        $attributes,
        0,
        0,
        $timeLimit
    );

    if ($result === false) {
        $error = ldap_error($ldap);
        ldap_unbind($ldap);
        throw new RuntimeException("search failed: {$error}");
    }

    $entries = ldap_get_entries($ldap, $result);
    ldap_unbind($ldap);

    $users = [];
    for ($i = 0; $i < $entries['count']; $i++) {
        $entry = $entries[$i];
        $login = textValue($entry, 'sAMAccountName');
        if ($login === '') {
            continue;
        }

        $guidBinary = rawValue($entry, 'objectGUID');
        $guid = '';
        if ($guidBinary !== '') {
            try {
                $guid = binaryGuidToString($guidBinary);
            } catch (RuntimeException $e) {
                $guid = '';
            }
        }

        $uac = (int) textValue($entry, 'userAccountControl');

        $users[strtolower($login)] = [
            'login' => $login,
            'object_guid' => $guid,
            'dn' => textValue($entry, 'distinguishedName'),
            'given_name' => textValue($entry, 'givenName'),
            'surname' => textValue($entry, 'sn'),
            'mail' => textValue($entry, 'mail'),
            'manager_dn' => textValue($entry, 'manager'),
            'department' => textValue($entry, 'department'),
            'title' => textValue($entry, 'title'),
            'employee_type' => textValue($entry, 'employeeType'),
            'enabled' => (($uac & 2) !== 2),
        ];
    }

    ksort($users, SORT_STRING);

    return $users;
}

function auditUsers(array $users): array
{
    $missing = [
        'given_name' => [],
        'surname' => [],
        'mail' => [],
        'manager' => [],
        'department' => [],
        'title' => [],
        'object_guid' => [],
    ];
    $departments = [];
    $titles = [];
    $loginCounts = [];
    $mailCounts = [];
    $guidCounts = [];
    $dnSet = [];
    $enabled = 0;
    $disabled = 0;

    foreach ($users as $user) {
        $user['enabled'] ? $enabled++ : $disabled++;
        $dnSet[normalizedDn($user['dn'])] = true;

        increment($loginCounts, strtolower($user['login']));
        increment($mailCounts, strtolower($user['mail']));
        increment($guidCounts, strtolower($user['object_guid']));
        increment($departments, $user['department']);
        increment($titles, $user['title']);

        if ($user['given_name'] === '') {
            $missing['given_name'][] = $user['login'];
        }
        if ($user['surname'] === '') {
            $missing['surname'][] = $user['login'];
        }
        if ($user['mail'] === '') {
            $missing['mail'][] = $user['login'];
        }
        if ($user['manager_dn'] === '') {
            $missing['manager'][] = $user['login'];
        }
        if ($user['department'] === '') {
            $missing['department'][] = $user['login'];
        }
        if ($user['title'] === '') {
            $missing['title'][] = $user['login'];
        }
        if ($user['object_guid'] === '') {
            $missing['object_guid'][] = $user['login'];
        }
    }

    $managerOutsideScope = [];
    foreach ($users as $user) {
        if ($user['manager_dn'] !== '' && !isset($dnSet[normalizedDn($user['manager_dn'])])) {
            $managerOutsideScope[$user['login']] = $user['manager_dn'];
        }
    }

    ksort($departments, SORT_NATURAL | SORT_FLAG_CASE);
    ksort($titles, SORT_NATURAL | SORT_FLAG_CASE);
    ksort($managerOutsideScope, SORT_NATURAL | SORT_FLAG_CASE);
    foreach ($missing as &$items) {
        natcasesort($items);
        $items = array_values($items);
    }
    unset($items);

    return [
        'total' => count($users),
        'enabled' => $enabled,
        'disabled' => $disabled,
        'missing' => $missing,
        'departments' => $departments,
        'titles' => $titles,
        'manager_outside_scope' => $managerOutsideScope,
        'duplicate_login' => duplicateKeys($loginCounts),
        'duplicate_mail' => duplicateKeys($mailCounts),
        'duplicate_object_guid' => duplicateKeys($guidCounts),
    ];
}

function printHuman(array $result, bool $checkOnly): void
{
    echo "HAYNE AD READ-ONLY " . ($checkOnly ? 'CHECK' : 'PREVIEW') . PHP_EOL;
    echo str_repeat('=', 64) . PHP_EOL;

    foreach ($result['hosts'] as $host => $hostResult) {
        if (!$hostResult['ok']) {
            echo "{$host} | FAIL | {$hostResult['error']}" . PHP_EOL;
            continue;
        }
        echo sprintf(
            "%s | OK | USERS=%d | SHA256=%s\n",
            $host,
            $hostResult['count'],
            $hostResult['sha256']
        );
    }

    echo "PARITY: " . $result['parity'] . PHP_EOL;
    echo "SELECTED SOURCE: " . ($result['selected_source'] ?? '<none>') . PHP_EOL;

    if ($checkOnly || !isset($result['audit'])) {
        echo "RESULT: " . $result['result'] . PHP_EOL;
        return;
    }

    $audit = $result['audit'];
    echo PHP_EOL . "EMPLOYEE SCOPE" . PHP_EOL;
    echo "TOTAL:    {$audit['total']}" . PHP_EOL;
    echo "ENABLED:  {$audit['enabled']}" . PHP_EOL;
    echo "DISABLED: {$audit['disabled']}" . PHP_EOL;

    echo PHP_EOL . "MISSING FIELDS" . PHP_EOL;
    foreach ($audit['missing'] as $field => $items) {
        echo sprintf("%-16s %d", strtoupper($field) . ':', count($items));
        if ($items) {
            echo ' | ' . implode(', ', $items);
        }
        echo PHP_EOL;
    }

    echo "MANAGER OUTSIDE EMPLOYEE SCOPE: " . count($audit['manager_outside_scope']) . PHP_EOL;
    foreach ($audit['manager_outside_scope'] as $login => $managerDn) {
        echo "  - {$login} -> {$managerDn}" . PHP_EOL;
    }

    echo "DUPLICATE LOGIN: " . count($audit['duplicate_login']) . PHP_EOL;
    echo "DUPLICATE MAIL: " . count($audit['duplicate_mail']) . PHP_EOL;
    echo "DUPLICATE OBJECTGUID: " . count($audit['duplicate_object_guid']) . PHP_EOL;

    echo PHP_EOL . 'DEPARTMENTS (' . count($audit['departments']) . ')' . PHP_EOL;
    foreach ($audit['departments'] as $department => $count) {
        echo "  {$count} | {$department}" . PHP_EOL;
    }

    echo PHP_EOL . 'TITLES (' . count($audit['titles']) . ')' . PHP_EOL;
    foreach ($audit['titles'] as $title => $count) {
        echo "  {$count} | {$title}" . PHP_EOL;
    }

    echo PHP_EOL . "RESULT: {$result['result']}" . PHP_EOL;
}

if (array_key_exists('self-test', $options)) {
    $guid = binaryGuidToString(hex2bin('33221100554477668899aabbccddeeff'));
    if ($guid !== '00112233-4455-6677-8899-aabbccddeeff') {
        fwrite(STDERR, "SELF-TEST FAIL: GUID conversion\n");
        exit(70);
    }

    $escaped = escapeFilterValue('Employee*)(x=1');
    if (str_contains($escaped, '*') || str_contains($escaped, '(') || str_contains($escaped, ')')) {
        fwrite(STDERR, "SELF-TEST FAIL: LDAP filter escaping\n");
        exit(71);
    }

    echo "SELF-TEST PASS\n";
    exit(0);
}

try {
    if (!extension_loaded('ldap')) {
        throw new RuntimeException('PHP LDAP extension is not loaded');
    }
    if (!envBool('HAYNE_AD_SYNC_ENABLED', false)) {
        throw new RuntimeException('HAYNE_AD_SYNC_ENABLED is not TRUE');
    }

    $hosts = array_values(array_filter(array_map('trim', explode(',', envString('HAYNE_AD_HOSTS')))));
    if (!$hosts) {
        throw new RuntimeException('HAYNE_AD_HOSTS is empty');
    }

    $port = envInt('HAYNE_AD_PORT', 636, 1, 65535);
    $baseDn = envString('HAYNE_AD_BASE_DN');
    $bindDn = envString('HAYNE_AD_BIND_DN');
    $password = envString('HAYNE_AD_BIND_PASSWORD');
    $caFile = envString('HAYNE_AD_CA_FILE', '/opt/hayne/certs/hayne-ad-ca-chain.pem');
    $employeeType = envString('HAYNE_AD_EMPLOYEE_TYPE', 'Employee');
    $networkTimeout = envInt('HAYNE_AD_NETWORK_TIMEOUT', 5, 1, 30);
    $timeLimit = envInt('HAYNE_AD_TIME_LIMIT', 10, 1, 60);

    if (!is_readable($caFile)) {
        throw new RuntimeException("CA file is not readable: {$caFile}");
    }

    ldap_set_option(null, LDAP_OPT_X_TLS_CACERTFILE, $caFile);
    ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, LDAP_OPT_X_TLS_HARD);

    $filter = sprintf(
        '(&(objectCategory=person)(objectClass=user)(employeeType=%s)(sAMAccountName=*))',
        escapeFilterValue($employeeType)
    );

    $hostResults = [];
    $successful = [];
    foreach ($hosts as $host) {
        try {
            $users = queryHost(
                $host,
                $port,
                $baseDn,
                $bindDn,
                $password,
                $filter,
                $networkTimeout,
                $timeLimit
            );
            $identityRows = [];
            foreach ($users as $user) {
                $identityRows[] = strtolower($user['login']) . '|' . strtolower($user['object_guid']);
            }
            sort($identityRows, SORT_STRING);
            $sha = hash('sha256', implode("\n", $identityRows));
            $hostResults[$host] = [
                'ok' => true,
                'count' => count($users),
                'sha256' => $sha,
            ];
            $successful[$host] = $users;
        } catch (Throwable $e) {
            $hostResults[$host] = [
                'ok' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    if (!$successful) {
        throw new RuntimeException('All configured AD hosts failed');
    }

    $hashes = [];
    foreach ($hostResults as $hostResult) {
        if ($hostResult['ok']) {
            $hashes[] = $hostResult['sha256'];
        }
    }
    $parity = count(array_unique($hashes)) <= 1 ? 'PASS' : 'MISMATCH';
    if (count($successful) < count($hosts)) {
        $parity = 'DEGRADED';
    }

    $selectedSource = array_key_first($successful);
    $result = [
        'mode' => $checkOnly ? 'check' : 'preview',
        'read_only' => true,
        'employee_type_filter' => $employeeType,
        'hosts' => $hostResults,
        'parity' => $parity,
        'selected_source' => $selectedSource,
    ];

    if (!$checkOnly) {
        $result['audit'] = auditUsers($successful[$selectedSource]);
    }

    $result['result'] = $parity === 'MISMATCH' ? 'REVIEW REQUIRED' : ($parity === 'DEGRADED' ? 'PASS WITH FAILOVER' : 'PASS');

    if ($jsonOutput) {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        printHuman($result, $checkOnly);
    }

    exit($parity === 'MISMATCH' ? 3 : 0);
} catch (Throwable $e) {
    if ($jsonOutput) {
        echo json_encode([
            'read_only' => true,
            'result' => 'FAIL',
            'error' => $e->getMessage(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        fwrite(STDERR, 'STOP: ' . $e->getMessage() . PHP_EOL);
    }
    exit(2);
}
