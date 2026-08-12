#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * HAYNE Leave AD -> Jorani synchronization planner.
 *
 * Default mode is READ ONLY. It reads Active Directory and Jorani, builds a
 * deterministic plan and prints a SHA256 confirmation token. Database writes
 * are possible only through the explicit --migrate or --apply modes and only
 * when HAYNE_AD_APPLY_ENABLED=TRUE.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "STOP: CLI only.\n");
    exit(64);
}

$options = getopt('', ['json', 'self-test', 'apply', 'migrate', 'confirm:']);
$jsonOutput = array_key_exists('json', $options);
$selfTest = array_key_exists('self-test', $options);
$applyMode = array_key_exists('apply', $options);
$migrateMode = array_key_exists('migrate', $options);
$confirm = isset($options['confirm']) ? (string) $options['confirm'] : '';

if ($applyMode && $migrateMode) {
    fwrite(STDERR, "STOP: --apply and --migrate are mutually exclusive.\n");
    exit(64);
}

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

function envFirst(array $names, ?string $default = null, bool $trim = true): string
{
    foreach ($names as $name) {
        $value = getenv($name);
        if ($value !== false && $value !== '') {
            return $trim ? trim((string) $value) : (string) $value;
        }
    }
    if ($default !== null) {
        return $default;
    }
    throw new RuntimeException('Missing environment variable; tried: ' . implode(', ', $names));
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

function foldText(string $value): string
{
    $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
    if (function_exists('mb_strtolower')) {
        return mb_strtolower($value, 'UTF-8');
    }
    return strtolower($value);
}

function canonicalize(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }
    if (array_is_list($value)) {
        return array_map('canonicalize', $value);
    }
    ksort($value, SORT_STRING);
    foreach ($value as $key => $item) {
        $value[$key] = canonicalize($item);
    }
    return $value;
}

function planHash(array $payload): string
{
    $canonical = canonicalize($payload);
    $json = json_encode($canonical, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    return hash('sha256', $json);
}

function identityTableSql(): string
{
    return <<<'SQL'
CREATE TABLE IF NOT EXISTS `hayne_ad_identity` (
    `user_id` int NOT NULL,
    `object_guid` char(36) NOT NULL,
    `distinguished_name` varchar(1024) NOT NULL,
    `last_seen_at` datetime NOT NULL,
    `last_synced_at` datetime NOT NULL,
    `source_dc` varchar(255) NOT NULL,
    PRIMARY KEY (`user_id`),
    UNIQUE KEY `object_guid` (`object_guid`),
    KEY `distinguished_name` (`distinguished_name`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;
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
        $diag = '';
        @ldap_get_option($ldap, LDAP_OPT_DIAGNOSTIC_MESSAGE, $diag);
        ldap_unbind($ldap);
        throw new RuntimeException('bind failed: ' . $error . ($diag !== '' ? " | {$diag}" : ''));
    }

    $attributes = [
        'objectGUID', 'distinguishedName', 'sAMAccountName', 'givenName', 'sn',
        'mail', 'manager', 'department', 'title', 'employeeType', 'userAccountControl',
    ];
    $result = @ldap_search($ldap, $baseDn, $filter, $attributes, 0, 0, $timeLimit);
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
            } catch (RuntimeException) {
                $guid = '';
            }
        }
        $uac = (int) textValue($entry, 'userAccountControl');
        $users[strtolower($login)] = [
            'login' => $login,
            'object_guid' => strtolower($guid),
            'dn' => textValue($entry, 'distinguishedName'),
            'given_name' => textValue($entry, 'givenName'),
            'surname' => textValue($entry, 'sn'),
            'mail' => textValue($entry, 'mail'),
            'manager_dn' => textValue($entry, 'manager'),
            'department' => textValue($entry, 'department'),
            'title' => textValue($entry, 'title'),
            'enabled' => (($uac & 2) !== 2),
        ];
    }
    ksort($users, SORT_STRING);
    return $users;
}

function fetchAdSnapshot(): array
{
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
    $password = envRaw('HAYNE_AD_BIND_PASSWORD');
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
            $users = queryHost($host, $port, $baseDn, $bindDn, $password, $filter, $networkTimeout, $timeLimit);
            $identityRows = [];
            foreach ($users as $user) {
                $identityRows[] = strtolower($user['login']) . '|' . strtolower($user['object_guid']);
            }
            sort($identityRows, SORT_STRING);
            $sha = hash('sha256', implode("\n", $identityRows));
            $hostResults[$host] = ['ok' => true, 'count' => count($users), 'sha256' => $sha];
            $successful[$host] = $users;
        } catch (Throwable $e) {
            $hostResults[$host] = ['ok' => false, 'error' => $e->getMessage()];
        }
    }
    if (!$successful) {
        $errors = [];
        foreach ($hostResults as $host => $result) {
            $errors[] = $host . ': ' . ($result['error'] ?? 'unknown error');
        }
        throw new RuntimeException('All configured AD hosts failed | ' . implode(' | ', $errors));
    }

    $hashes = [];
    foreach ($hostResults as $result) {
        if ($result['ok']) {
            $hashes[] = $result['sha256'];
        }
    }
    $parity = count(array_unique($hashes)) <= 1 ? 'PASS' : 'MISMATCH';
    if (count($successful) < count($hosts)) {
        $parity = 'DEGRADED';
    }
    if ($parity === 'MISMATCH') {
        throw new RuntimeException('AD parity mismatch; refusing to build an actionable plan');
    }

    $source = array_key_first($successful);
    return [
        'users' => $successful[$source],
        'source' => $source,
        'parity' => $parity,
        'hosts' => $hostResults,
        'identity_hash' => $hostResults[$source]['sha256'],
    ];
}

function connectDb(): PDO
{
    if (!extension_loaded('pdo_mysql')) {
        throw new RuntimeException('PDO MySQL extension is not loaded');
    }
    $host = envFirst(['HAYNE_DB_HOST', 'MYSQL_HOST', 'DB_HOSTNAME'], 'mysql');
    $port = (int) envFirst(['HAYNE_DB_PORT', 'MYSQL_PORT', 'DB_PORT'], '3306');
    $database = envFirst(['HAYNE_DB_DATABASE', 'MYSQL_DATABASE', 'DB_DATABASE']);
    $user = envFirst(['HAYNE_DB_USER', 'MYSQL_USER', 'DB_USERNAME']);
    $password = envFirst(['HAYNE_DB_PASSWORD', 'MYSQL_PASSWORD', 'DB_PASSWORD'], null, false);

    $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
    return new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() === 1;
}

function fetchDbSnapshot(PDO $pdo): array
{
    $users = $pdo->query('SELECT id, firstname, lastname, login, email, role, manager, organization, contract, position, identifier, ldap_path, active FROM users ORDER BY id')->fetchAll();
    $organizations = $pdo->query('SELECT id, name, parent_id, supervisor FROM organization ORDER BY id')->fetchAll();
    $positions = $pdo->query('SELECT id, name, description FROM positions ORDER BY id')->fetchAll();
    $identityTableExists = tableExists($pdo, 'hayne_ad_identity');
    $identities = [];
    if ($identityTableExists) {
        $identities = $pdo->query('SELECT user_id, object_guid, distinguished_name, last_seen_at, last_synced_at, source_dc FROM hayne_ad_identity ORDER BY user_id')->fetchAll();
    }
    return [
        'users' => $users,
        'organizations' => $organizations,
        'positions' => $positions,
        'identity_table_exists' => $identityTableExists,
        'identities' => $identities,
    ];
}

function addIssue(array &$issues, string $severity, string $code, string $subject, string $message): void
{
    $issues[] = [
        'severity' => $severity,
        'code' => $code,
        'subject' => $subject,
        'message' => $message,
    ];
}

function detectDictionaryQuality(array $values, string $kind): array
{
    $issues = [];
    $values = array_values(array_unique(array_filter(array_map('trim', $values), static fn($v) => $v !== '')));
    sort($values, SORT_NATURAL | SORT_FLAG_CASE);

    $foldGroups = [];
    foreach ($values as $value) {
        $foldGroups[foldText($value)][] = $value;
    }
    foreach ($foldGroups as $variants) {
        if (count($variants) > 1) {
            addIssue(
                $issues,
                'BLOCKER',
                strtoupper($kind) . '_NORMALIZED_DUPLICATE',
                implode(' | ', $variants),
                'Values differ only by case or whitespace; correct the source value in AD before apply.'
            );
        }
    }

    if ($kind === 'department') {
        $count = count($values);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $a = foldText($values[$i]);
                $b = foldText($values[$j]);
                if ($a === $b) {
                    continue;
                }
                if (abs(strlen($a) - strlen($b)) <= 2 && levenshtein($a, $b) <= 2) {
                    addIssue(
                        $issues,
                        'BLOCKER',
                        'DEPARTMENT_NEAR_DUPLICATE',
                        $values[$i] . ' | ' . $values[$j],
                        'Department names are near-duplicates; correct AD rather than creating both dictionary entries.'
                    );
                }
            }
        }
    }

    if ($kind === 'title') {
        foreach ($values as $value) {
            if (preg_match('/[A-Z]{2,}[A-Z0-9_-]*\d{6,}/u', $value)) {
                addIssue(
                    $issues,
                    'BLOCKER',
                    'TITLE_SUSPICIOUS_TOKEN',
                    $value,
                    'Title contains a suspicious long alphanumeric token; review the AD source value before apply.'
                );
            }
            if (strlen($value) > 64) {
                addIssue(
                    $issues,
                    'BLOCKER',
                    'TITLE_TOO_LONG',
                    $value,
                    'Jorani positions.name is varchar(64); this title cannot be stored safely.'
                );
            }
        }
    }

    return $issues;
}

function buildPlan(array $adSnapshot, array $dbSnapshot, array $config): array
{
    $adUsers = $adSnapshot['users'];
    $issues = [];
    $actions = [];

    $guidCounts = [];
    $mailCounts = [];
    foreach ($adUsers as $ad) {
        if ($ad['object_guid'] === '') {
            addIssue($issues, 'BLOCKER', 'AD_MISSING_OBJECT_GUID', $ad['login'], 'objectGUID is required for stable identity.');
        } else {
            $guidCounts[$ad['object_guid']] = ($guidCounts[$ad['object_guid']] ?? 0) + 1;
        }
        if ($ad['mail'] !== '') {
            $mailKey = foldText($ad['mail']);
            $mailCounts[$mailKey] = ($mailCounts[$mailKey] ?? 0) + 1;
        }
    }
    foreach ($guidCounts as $guid => $count) {
        if ($count > 1) {
            addIssue($issues, 'BLOCKER', 'AD_DUPLICATE_OBJECT_GUID', $guid, "objectGUID appears {$count} times.");
        }
    }
    foreach ($mailCounts as $mail => $count) {
        if ($count > 1) {
            addIssue($issues, 'WARNING', 'AD_DUPLICATE_MAIL', $mail, "Mail appears {$count} times.");
        }
    }

    $usersById = [];
    $usersByLogin = [];
    foreach ($dbSnapshot['users'] as $row) {
        $id = (int) $row['id'];
        $usersById[$id] = $row;
        $loginKey = foldText((string) ($row['login'] ?? ''));
        if ($loginKey !== '') {
            if (isset($usersByLogin[$loginKey])) {
                addIssue($issues, 'BLOCKER', 'JORANI_DUPLICATE_LOGIN', $loginKey, 'Multiple Jorani users share this login.');
            } else {
                $usersByLogin[$loginKey] = $row;
            }
        }
    }

    $identityByGuid = [];
    $identityByUserId = [];
    foreach ($dbSnapshot['identities'] as $identity) {
        $guid = strtolower((string) $identity['object_guid']);
        $userId = (int) $identity['user_id'];
        $identityByGuid[$guid] = $identity;
        $identityByUserId[$userId] = $identity;
        if (!isset($usersById[$userId])) {
            addIssue($issues, 'BLOCKER', 'ORPHAN_IDENTITY', (string) $userId, 'hayne_ad_identity points to a missing users.id.');
        }
    }

    $protected = [];
    foreach ($config['protected_logins'] as $login) {
        $protected[foldText($login)] = true;
    }

    $activeDepartments = [];
    $activeTitles = [];
    foreach ($adUsers as $ad) {
        if ($ad['enabled']) {
            if ($ad['department'] !== '') {
                $activeDepartments[] = $ad['department'];
            }
            if ($ad['title'] !== '') {
                $activeTitles[] = $ad['title'];
            }
        }
    }
    $issues = array_merge($issues, detectDictionaryQuality($activeDepartments, 'department'));
    $issues = array_merge($issues, detectDictionaryQuality($activeTitles, 'title'));

    $orgByExact = [];
    foreach ($dbSnapshot['organizations'] as $row) {
        $orgByExact[(string) $row['name']] = (int) $row['id'];
    }
    $posByExact = [];
    foreach ($dbSnapshot['positions'] as $row) {
        $posByExact[(string) $row['name']] = (int) $row['id'];
    }

    $departmentsNeeded = array_values(array_unique($activeDepartments));
    sort($departmentsNeeded, SORT_NATURAL | SORT_FLAG_CASE);
    foreach ($departmentsNeeded as $department) {
        if (!array_key_exists($department, $orgByExact)) {
            $actions[] = ['type' => 'CREATE_ORGANIZATION', 'key' => $department, 'name' => $department];
        }
    }
    $titlesNeeded = array_values(array_unique($activeTitles));
    sort($titlesNeeded, SORT_NATURAL | SORT_FLAG_CASE);
    foreach ($titlesNeeded as $title) {
        if (!array_key_exists($title, $posByExact)) {
            $actions[] = ['type' => 'CREATE_POSITION', 'key' => $title, 'name' => $title];
        }
    }

    $dnToAd = [];
    foreach ($adUsers as $ad) {
        $dnToAd[normalizedDn($ad['dn'])] = $ad;
    }

    $adGuidSet = [];
    $adLoginSet = [];
    foreach ($adUsers as $ad) {
        $adGuidSet[$ad['object_guid']] = true;
        $adLoginSet[foldText($ad['login'])] = true;
        $loginKey = foldText($ad['login']);
        if (isset($protected[$loginKey])) {
            addIssue($issues, 'BLOCKER', 'PROTECTED_LOGIN_IN_AD_SCOPE', $ad['login'], 'Protected local login must never be claimed by AD sync.');
        }

        $identity = $ad['object_guid'] !== '' ? ($identityByGuid[$ad['object_guid']] ?? null) : null;
        if ($identity === null) {
            $localCollision = $usersByLogin[$loginKey] ?? null;
            if ($localCollision !== null) {
                addIssue(
                    $issues,
                    'BLOCKER',
                    'LOCAL_LOGIN_CONFLICT',
                    $ad['login'],
                    'A Jorani user with this login exists without objectGUID linkage; automatic adoption is intentionally disabled.'
                );
                $actions[] = ['type' => 'CONFLICT_LOCAL_LOGIN', 'key' => $ad['login'], 'login' => $ad['login'], 'user_id' => (int) $localCollision['id']];
                continue;
            }
            if (!$ad['enabled']) {
                $actions[] = ['type' => 'SKIP_DISABLED_NEW', 'key' => $ad['login'], 'login' => $ad['login'], 'object_guid' => $ad['object_guid']];
                continue;
            }
            if ($ad['given_name'] === '' || $ad['surname'] === '' || $ad['mail'] === '' || $ad['department'] === '' || $ad['title'] === '') {
                addIssue($issues, 'BLOCKER', 'ACTIVE_USER_MISSING_REQUIRED_FIELD', $ad['login'], 'Active new user is missing name, surname, mail, department or title.');
            }
            $actions[] = [
                'type' => 'CREATE_USER',
                'key' => $ad['login'],
                'login' => $ad['login'],
                'object_guid' => $ad['object_guid'],
                'firstname' => $ad['given_name'],
                'lastname' => $ad['surname'],
                'email' => $ad['mail'],
                'department' => $ad['department'],
                'title' => $ad['title'],
                'dn' => $ad['dn'],
                'manager_dn' => $ad['manager_dn'],
            ];
            continue;
        }

        $userId = (int) $identity['user_id'];
        if (!isset($usersById[$userId])) {
            continue;
        }
        $local = $usersById[$userId];
        $changes = [];
        $expected = [
            'login' => $ad['login'],
            'firstname' => $ad['given_name'],
            'lastname' => $ad['surname'],
            'email' => $ad['mail'],
            'ldap_path' => $ad['dn'],
            'active' => $ad['enabled'] ? 1 : 0,
        ];
        foreach ($expected as $field => $value) {
            $current = $field === 'active' ? (int) $local[$field] : (string) ($local[$field] ?? '');
            if ($current !== $value) {
                $changes[$field] = ['from' => $current, 'to' => $value];
            }
        }
        if ($ad['enabled']) {
            if ($ad['department'] === '' || $ad['title'] === '') {
                addIssue($issues, 'BLOCKER', 'ACTIVE_SYNCED_USER_MISSING_DICTIONARY_FIELD', $ad['login'], 'Active synced user is missing department or title.');
            }
            $changes['organization_name'] = ['to' => $ad['department']];
            $changes['position_name'] = ['to' => $ad['title']];
        }
        if ($changes) {
            $type = 'UPDATE_USER';
            if ((int) $local['active'] === 1 && !$ad['enabled']) {
                $type = 'DEACTIVATE_USER';
            } elseif ((int) $local['active'] === 0 && $ad['enabled']) {
                $type = 'REACTIVATE_USER';
            }
            $actions[] = [
                'type' => $type,
                'key' => $ad['login'],
                'login' => $ad['login'],
                'user_id' => $userId,
                'object_guid' => $ad['object_guid'],
                'dn' => $ad['dn'],
                'department' => $ad['department'],
                'title' => $ad['title'],
                'manager_dn' => $ad['manager_dn'],
                'changes' => $changes,
            ];
        } else {
            $actions[] = ['type' => 'UNCHANGED_SYNCED', 'key' => $ad['login'], 'login' => $ad['login'], 'user_id' => $userId, 'object_guid' => $ad['object_guid'], 'dn' => $ad['dn'], 'manager_dn' => $ad['manager_dn']];
        }
    }

    foreach ($dbSnapshot['users'] as $local) {
        $userId = (int) $local['id'];
        $login = (string) ($local['login'] ?? '');
        if (isset($identityByUserId[$userId])) {
            $guid = strtolower((string) $identityByUserId[$userId]['object_guid']);
            if (!isset($adGuidSet[$guid])) {
                addIssue($issues, 'WARNING', 'SYNCED_USER_MISSING_FROM_AD_SCOPE', $login, 'User is linked to AD but absent from current Employee scope; no automatic deactivation or deletion will occur.');
                $actions[] = ['type' => 'PRESERVE_MISSING_FROM_AD', 'key' => $login, 'login' => $login, 'user_id' => $userId, 'object_guid' => $guid];
            }
            continue;
        }
        if (!isset($adLoginSet[foldText($login)])) {
            $actions[] = ['type' => 'PRESERVE_LOCAL', 'key' => $login, 'login' => $login, 'user_id' => $userId, 'role' => (int) ($local['role'] ?? 0)];
        }
    }

    foreach ($adUsers as $ad) {
        if (!$ad['enabled']) {
            continue;
        }
        if ($ad['manager_dn'] === '') {
            addIssue($issues, 'WARNING', 'MANAGER_MISSING', $ad['login'], 'Active employee has no manager in AD; Jorani manager will remain NULL.');
            continue;
        }
        $managerKey = normalizedDn($ad['manager_dn']);
        if (!isset($dnToAd[$managerKey])) {
            addIssue($issues, 'BLOCKER', 'MANAGER_OUTSIDE_EMPLOYEE_SCOPE', $ad['login'], 'Active employee manager is outside the Employee scope: ' . $ad['manager_dn']);
            continue;
        }
        if (!$dnToAd[$managerKey]['enabled']) {
            addIssue($issues, 'BLOCKER', 'MANAGER_DISABLED', $ad['login'], 'Active employee manager is disabled in AD: ' . $dnToAd[$managerKey]['login']);
        }
    }

    usort($actions, static fn(array $a, array $b): int => [$a['type'], $a['key']] <=> [$b['type'], $b['key']]);
    usort($issues, static fn(array $a, array $b): int => [$a['severity'], $a['code'], $a['subject']] <=> [$b['severity'], $b['code'], $b['subject']]);

    $summary = [];
    $userChangeTypes = ['CREATE_USER', 'UPDATE_USER', 'DEACTIVATE_USER', 'REACTIVATE_USER'];
    $userChanges = 0;
    foreach ($actions as $action) {
        $summary[$action['type']] = ($summary[$action['type']] ?? 0) + 1;
        if (in_array($action['type'], $userChangeTypes, true)) {
            $userChanges++;
        }
    }
    ksort($summary, SORT_STRING);
    if ($userChanges > $config['max_user_changes']) {
        addIssue($issues, 'BLOCKER', 'MASS_CHANGE_GUARD', (string) $userChanges, 'Planned user mutations exceed HAYNE_AD_MAX_USER_CHANGES=' . $config['max_user_changes'] . '.');
    }

    if (!$dbSnapshot['identity_table_exists']) {
        addIssue($issues, 'BLOCKER', 'MIGRATION_REQUIRED', 'hayne_ad_identity', 'Run explicit --migrate first, then rebuild the dry-run plan and confirm its new SHA256.');
    }

    usort($issues, static fn(array $a, array $b): int => [$a['severity'], $a['code'], $a['subject']] <=> [$b['severity'], $b['code'], $b['subject']]);
    $blockers = array_values(array_filter($issues, static fn(array $issue): bool => $issue['severity'] === 'BLOCKER'));

    $activeCount = 0;
    foreach ($adUsers as $ad) {
        if ($ad['enabled']) {
            $activeCount++;
        }
    }

    $payload = [
        'schema_version' => 1,
        'ad' => [
            'source' => $adSnapshot['source'],
            'parity' => $adSnapshot['parity'],
            'identity_hash' => $adSnapshot['identity_hash'],
            'total' => count($adUsers),
            'active' => $activeCount,
            'disabled' => count($adUsers) - $activeCount,
        ],
        'jorani' => [
            'users' => count($dbSnapshot['users']),
            'identity_table_exists' => $dbSnapshot['identity_table_exists'],
            'identities' => count($dbSnapshot['identities']),
        ],
        'config' => [
            'default_role_id' => $config['default_role_id'],
            'default_contract_id' => $config['default_contract_id'],
            'max_user_changes' => $config['max_user_changes'],
            'protected_logins' => $config['protected_logins'],
        ],
        'summary' => $summary,
        'actions' => $actions,
        'issues' => $issues,
    ];
    $sha = planHash($payload);

    return [
        'payload' => $payload,
        'sha256' => $sha,
        'apply_ready' => count($blockers) === 0,
        'blockers' => $blockers,
        'user_changes' => $userChanges,
    ];
}

function dictionaryId(PDO $pdo, string $table, string $name): int
{
    if (!in_array($table, ['organization', 'positions'], true)) {
        throw new RuntimeException('Invalid dictionary table');
    }
    $stmt = $pdo->prepare("SELECT id FROM {$table} WHERE name = ? ORDER BY id LIMIT 1");
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();
    if ($id === false) {
        throw new RuntimeException("Dictionary value missing after create: {$table} / {$name}");
    }
    return (int) $id;
}

function userIdByGuid(PDO $pdo, string $guid): ?int
{
    $stmt = $pdo->prepare('SELECT user_id FROM hayne_ad_identity WHERE object_guid = ?');
    $stmt->execute([$guid]);
    $id = $stmt->fetchColumn();
    return $id === false ? null : (int) $id;
}

function upsertIdentity(PDO $pdo, int $userId, array $ad, string $sourceDc): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO hayne_ad_identity (user_id, object_guid, distinguished_name, last_seen_at, last_synced_at, source_dc) '
        . 'VALUES (?, ?, ?, NOW(), NOW(), ?) '
        . 'ON DUPLICATE KEY UPDATE object_guid = VALUES(object_guid), distinguished_name = VALUES(distinguished_name), '
        . 'last_seen_at = NOW(), last_synced_at = NOW(), source_dc = VALUES(source_dc)'
    );
    $stmt->execute([$userId, $ad['object_guid'], $ad['dn'], $sourceDc]);
}

function applyPlan(PDO $pdo, array $plan, array $adSnapshot, array $config): array
{
    if (!$plan['apply_ready']) {
        throw new RuntimeException('Plan contains blockers; apply refused');
    }
    if (!tableExists($pdo, 'hayne_ad_identity')) {
        throw new RuntimeException('hayne_ad_identity is missing; run --migrate and then rebuild the plan');
    }

    $adByGuid = [];
    $adByDn = [];
    foreach ($adSnapshot['users'] as $ad) {
        $adByGuid[$ad['object_guid']] = $ad;
        $adByDn[normalizedDn($ad['dn'])] = $ad;
    }

    $pdo->beginTransaction();
    try {
        foreach ($plan['payload']['actions'] as $action) {
            if ($action['type'] === 'CREATE_ORGANIZATION') {
                $stmt = $pdo->prepare('INSERT INTO organization (name, parent_id, supervisor) SELECT ?, 0, NULL WHERE NOT EXISTS (SELECT 1 FROM organization WHERE name = ?)');
                $stmt->execute([$action['name'], $action['name']]);
            } elseif ($action['type'] === 'CREATE_POSITION') {
                $stmt = $pdo->prepare("INSERT INTO positions (name, description) SELECT ?, 'Synced from Active Directory.' WHERE NOT EXISTS (SELECT 1 FROM positions WHERE name = ?)");
                $stmt->execute([$action['name'], $action['name']]);
            }
        }

        foreach ($plan['payload']['actions'] as $action) {
            if ($action['type'] === 'CREATE_USER') {
                $ad = $adByGuid[$action['object_guid']];
                $orgId = dictionaryId($pdo, 'organization', $ad['department']);
                $positionId = dictionaryId($pdo, 'positions', $ad['title']);
                $randomPasswordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT);
                if ($randomPasswordHash === false) {
                    throw new RuntimeException('Failed to generate local password hash');
                }
                $stmt = $pdo->prepare(
                    'INSERT INTO users (firstname, lastname, login, email, password, role, manager, organization, contract, position, identifier, language, ldap_path, active, timezone) '
                    . 'VALUES (?, ?, ?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, 1, ?)'
                );
                $stmt->execute([
                    $ad['given_name'], $ad['surname'], $ad['login'], $ad['mail'], $randomPasswordHash,
                    $config['default_role_id'], $orgId, $config['default_contract_id'], $positionId,
                    '', $config['default_language'], $ad['dn'], $config['default_timezone'],
                ]);
                $userId = (int) $pdo->lastInsertId();
                upsertIdentity($pdo, $userId, $ad, $adSnapshot['source']);
                continue;
            }

            if (in_array($action['type'], ['UPDATE_USER', 'DEACTIVATE_USER', 'REACTIVATE_USER', 'UNCHANGED_SYNCED'], true)) {
                $ad = $adByGuid[$action['object_guid']];
                $userId = (int) ($action['user_id'] ?? userIdByGuid($pdo, $ad['object_guid']));
                if ($userId <= 0) {
                    throw new RuntimeException('Cannot resolve synced user for ' . $ad['login']);
                }
                if ($action['type'] !== 'UNCHANGED_SYNCED') {
                    $fields = [
                        'firstname' => $ad['given_name'],
                        'lastname' => $ad['surname'],
                        'login' => $ad['login'],
                        'email' => $ad['mail'],
                        'ldap_path' => $ad['dn'],
                        'active' => $ad['enabled'] ? 1 : 0,
                    ];
                    if ($ad['enabled']) {
                        $fields['organization'] = dictionaryId($pdo, 'organization', $ad['department']);
                        $fields['position'] = dictionaryId($pdo, 'positions', $ad['title']);
                    }
                    $assignments = [];
                    $params = [];
                    foreach ($fields as $field => $value) {
                        $assignments[] = "{$field} = ?";
                        $params[] = $value;
                    }
                    $params[] = $userId;
                    $stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $assignments) . ' WHERE id = ?');
                    $stmt->execute($params);
                }
                upsertIdentity($pdo, $userId, $ad, $adSnapshot['source']);
            }
        }

        foreach ($adSnapshot['users'] as $ad) {
            if (!$ad['enabled'] || $ad['object_guid'] === '') {
                continue;
            }
            $userId = userIdByGuid($pdo, $ad['object_guid']);
            if ($userId === null) {
                continue;
            }
            $managerId = null;
            if ($ad['manager_dn'] !== '') {
                $managerAd = $adByDn[normalizedDn($ad['manager_dn'])] ?? null;
                if ($managerAd !== null && $managerAd['enabled'] && $managerAd['object_guid'] !== '') {
                    $managerId = userIdByGuid($pdo, $managerAd['object_guid']);
                }
            }
            if ($managerId === $userId) {
                throw new RuntimeException('Self-manager resolution refused for ' . $ad['login']);
            }
            $stmt = $pdo->prepare('UPDATE users SET manager = ? WHERE id = ?');
            $stmt->execute([$managerId, $userId]);
        }

        $pdo->commit();
        return ['applied' => true, 'user_changes' => $plan['user_changes']];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function printHuman(array $plan, array $adSnapshot): void
{
    $payload = $plan['payload'];
    echo "HAYNE AD -> JORANI SYNC PLAN (READ ONLY)\n";
    echo str_repeat('=', 72) . "\n";
    foreach ($adSnapshot['hosts'] as $host => $result) {
        if ($result['ok']) {
            echo sprintf("%s | OK | USERS=%d | SHA256=%s\n", $host, $result['count'], $result['sha256']);
        } else {
            echo $host . ' | FAIL | ' . $result['error'] . "\n";
        }
    }
    echo 'PARITY: ' . $payload['ad']['parity'] . "\n";
    echo 'SELECTED SOURCE: ' . $payload['ad']['source'] . "\n";
    echo sprintf("AD EMPLOYEES: %d total | %d active | %d disabled\n", $payload['ad']['total'], $payload['ad']['active'], $payload['ad']['disabled']);
    echo sprintf("JORANI USERS: %d | IDENTITIES: %d | IDENTITY TABLE: %s\n", $payload['jorani']['users'], $payload['jorani']['identities'], $payload['jorani']['identity_table_exists'] ? 'YES' : 'NO');

    echo "\nPLAN SUMMARY\n";
    foreach ($payload['summary'] as $type => $count) {
        echo sprintf("%-28s %d\n", $type . ':', $count);
    }
    if (!$payload['summary']) {
        echo "<no actions>\n";
    }

    echo "\nISSUES\n";
    if (!$payload['issues']) {
        echo "NONE\n";
    } else {
        foreach ($payload['issues'] as $issue) {
            echo sprintf("[%s] %s | %s | %s\n", $issue['severity'], $issue['code'], $issue['subject'], $issue['message']);
        }
    }

    echo "\nPLAN SHA256: " . $plan['sha256'] . "\n";
    echo 'APPLY READY: ' . ($plan['apply_ready'] ? 'YES' : 'NO') . "\n";
    echo 'USER MUTATIONS: ' . $plan['user_changes'] . "\n";
    echo "DELETE OPERATIONS: 0\n";
}

function runSelfTest(): void
{
    $guid = binaryGuidToString(hex2bin('33221100554477668899aabbccddeeff'));
    if ($guid !== '00112233-4455-6677-8899-aabbccddeeff') {
        throw new RuntimeException('SELF-TEST FAIL: GUID conversion');
    }
    $escaped = escapeFilterValue('Employee*)(x=1');
    if (str_contains($escaped, '*') || str_contains($escaped, '(') || str_contains($escaped, ')')) {
        throw new RuntimeException('SELF-TEST FAIL: LDAP filter escaping');
    }
    $ad = [
        'source' => 'AD01', 'parity' => 'PASS', 'identity_hash' => 'fixture', 'hosts' => [],
        'users' => [
            'alice' => ['login' => 'alice', 'object_guid' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa', 'dn' => 'CN=Alice,DC=x', 'given_name' => 'Alice', 'surname' => 'A', 'mail' => 'alice@example.com', 'manager_dn' => '', 'department' => 'Sales', 'title' => 'Rep', 'enabled' => true],
            'bob' => ['login' => 'bob', 'object_guid' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb', 'dn' => 'CN=Bob,DC=x', 'given_name' => 'Bob', 'surname' => 'B', 'mail' => 'bob@example.com', 'manager_dn' => 'CN=Alice,DC=x', 'department' => 'Sales', 'title' => 'Rep', 'enabled' => true],
            'old' => ['login' => 'old', 'object_guid' => 'cccccccc-cccc-cccc-cccc-cccccccccccc', 'dn' => 'CN=Old,DC=x', 'given_name' => 'Old', 'surname' => 'C', 'mail' => 'old@example.com', 'manager_dn' => '', 'department' => 'Sales', 'title' => 'Rep', 'enabled' => false],
        ],
    ];
    $db = [
        'users' => [['id' => 4, 'firstname' => 'Jacek', 'lastname' => 'ADMIN', 'login' => 'jadmin', 'email' => 'admin@example.com', 'role' => 1, 'manager' => 4, 'organization' => 0, 'contract' => null, 'position' => null, 'identifier' => '', 'ldap_path' => null, 'active' => 1]],
        'organizations' => [['id' => 0, 'name' => 'LMS root', 'parent_id' => -1, 'supervisor' => null]],
        'positions' => [['id' => 1, 'name' => 'Employee', 'description' => 'Employee.']],
        'identity_table_exists' => true,
        'identities' => [],
    ];
    $config = [
        'protected_logins' => ['jadmin'],
        'default_role_id' => 2,
        'default_contract_id' => 1,
        'default_language' => 'en',
        'default_timezone' => 'Europe/Warsaw',
        'max_user_changes' => 50,
    ];
    $plan = buildPlan($ad, $db, $config);
    $summary = $plan['payload']['summary'];
    if (($summary['CREATE_USER'] ?? 0) !== 2 || ($summary['SKIP_DISABLED_NEW'] ?? 0) !== 1 || ($summary['PRESERVE_LOCAL'] ?? 0) !== 1) {
        throw new RuntimeException('SELF-TEST FAIL: initial provisioning classification');
    }
    if (($summary['CREATE_ORGANIZATION'] ?? 0) !== 1 || ($summary['CREATE_POSITION'] ?? 0) !== 1) {
        throw new RuntimeException('SELF-TEST FAIL: dictionary planning');
    }
    if (!$plan['apply_ready']) {
        throw new RuntimeException('SELF-TEST FAIL: fixture should be apply-ready');
    }
    $sha1 = $plan['sha256'];
    $sha2 = buildPlan($ad, $db, $config)['sha256'];
    if ($sha1 !== $sha2) {
        throw new RuntimeException('SELF-TEST FAIL: plan hash is not deterministic');
    }
    foreach ($plan['payload']['actions'] as $action) {
        if (str_starts_with($action['type'], 'DELETE')) {
            throw new RuntimeException('SELF-TEST FAIL: delete action generated');
        }
    }
    echo "SELF-TEST PASS\n";
}

try {
    if ($selfTest) {
        runSelfTest();
        exit(0);
    }

    $config = [
        'protected_logins' => array_values(array_filter(array_map('trim', explode(',', envString('HAYNE_AD_PROTECTED_LOGINS', 'jadmin'))))),
        'default_role_id' => envInt('HAYNE_AD_DEFAULT_ROLE_ID', 2, 1, 1024),
        'default_contract_id' => envInt('HAYNE_AD_DEFAULT_CONTRACT_ID', 1, 1, 1000000),
        'default_language' => envString('HAYNE_AD_DEFAULT_LANGUAGE', 'en'),
        'default_timezone' => envString('HAYNE_AD_DEFAULT_TIMEZONE', 'Europe/Warsaw'),
        'max_user_changes' => envInt('HAYNE_AD_MAX_USER_CHANGES', 50, 1, 500),
    ];

    $pdo = connectDb();

    if ($migrateMode) {
        if (!envBool('HAYNE_AD_APPLY_ENABLED', false)) {
            throw new RuntimeException('HAYNE_AD_APPLY_ENABLED is not TRUE');
        }
        if ($confirm !== 'MIGRATE_HAYNE_AD_IDENTITY') {
            throw new RuntimeException('Migration requires --confirm=MIGRATE_HAYNE_AD_IDENTITY');
        }
        $pdo->exec(identityTableSql());
        echo "MIGRATION PASS: hayne_ad_identity is present\n";
        exit(0);
    }

    $adSnapshot = fetchAdSnapshot();
    $dbSnapshot = fetchDbSnapshot($pdo);
    $plan = buildPlan($adSnapshot, $dbSnapshot, $config);

    if ($jsonOutput && !$applyMode) {
        echo json_encode([
            'read_only' => true,
            'plan_sha256' => $plan['sha256'],
            'apply_ready' => $plan['apply_ready'],
            'blockers' => $plan['blockers'],
            'plan' => $plan['payload'],
            'hosts' => $adSnapshot['hosts'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
        exit($plan['apply_ready'] ? 0 : 3);
    }

    if (!$applyMode) {
        printHuman($plan, $adSnapshot);
        exit($plan['apply_ready'] ? 0 : 3);
    }

    if (!envBool('HAYNE_AD_APPLY_ENABLED', false)) {
        throw new RuntimeException('HAYNE_AD_APPLY_ENABLED is not TRUE');
    }
    if ($confirm === '') {
        throw new RuntimeException('Apply requires --confirm=<PLAN_SHA256>');
    }
    if (!hash_equals($plan['sha256'], strtolower($confirm))) {
        throw new RuntimeException('Plan confirmation SHA256 mismatch; rerun dry-run and review the current plan');
    }
    if (!$plan['apply_ready']) {
        throw new RuntimeException('Plan contains blockers; apply refused');
    }

    $result = applyPlan($pdo, $plan, $adSnapshot, $config);
    echo "APPLY PASS\n";
    echo 'PLAN SHA256: ' . $plan['sha256'] . "\n";
    echo 'USER MUTATIONS: ' . $result['user_changes'] . "\n";
    echo "DELETE OPERATIONS: 0\n";
    exit(0);
} catch (Throwable $e) {
    if ($jsonOutput) {
        echo json_encode(['result' => 'FAIL', 'error' => $e->getMessage()], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    } else {
        fwrite(STDERR, 'STOP: ' . $e->getMessage() . "\n");
    }
    exit(2);
}
