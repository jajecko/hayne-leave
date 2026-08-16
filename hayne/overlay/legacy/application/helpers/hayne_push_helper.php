<?php
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

if (!function_exists('hayne_push_env_bool')) {
    function hayne_push_env_bool(string $name, bool $default = false): bool
    {
        $value = getenv($name);
        if ($value === false || trim((string) $value) === '') {
            return $default;
        }
        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $parsed === null ? $default : $parsed;
    }
}

if (!function_exists('hayne_push_public_key')) {
    function hayne_push_public_key(): string
    {
        return trim((string) (getenv('VAPID_PUBLIC_KEY') ?: ''));
    }
}

if (!function_exists('hayne_push_is_enabled')) {
    function hayne_push_is_enabled(): bool
    {
        return hayne_push_env_bool('HAYNE_PUSH_ENABLED', false)
            && hayne_push_public_key() !== ''
            && trim((string) (getenv('VAPID_PRIVATE_KEY') ?: '')) !== ''
            && trim((string) (getenv('VAPID_SUBJECT') ?: '')) !== '';
    }
}

if (!function_exists('hayne_push_extract_emails')) {
    function hayne_push_extract_emails(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return [];
        }
        preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $value, $matches);
        $emails = array_map('strtolower', $matches[0] ?? []);
        return array_values(array_unique($emails));
    }
}

if (!function_exists('hayne_push_employee_body_for_mail')) {
    function hayne_push_employee_body_for_mail(string $message): string
    {
        $plain = html_entity_decode(strip_tags($message), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (strpos($plain, 'Twój wniosek urlopowy został zaakceptowany.') !== false) {
            return 'Twój wniosek urlopowy został zaakceptowany.';
        }
        if (strpos($plain, 'Twój wniosek urlopowy został odrzucony.') !== false) {
            return 'Twój wniosek urlopowy został odrzucony.';
        }
        if (strpos($plain, 'Twój wniosek urlopowy został anulowany.') !== false) {
            return 'Anulowanie Twojego wniosku zostało zaakceptowane.';
        }
        if (strpos($plain, 'Prośba o anulowanie została odrzucona.') !== false) {
            return 'Prośba o anulowanie Twojego wniosku została odrzucona.';
        }
        return 'Status Twojego wniosku został zaktualizowany.';
    }
}

if (!function_exists('hayne_push_send_for_mail')) {
    function hayne_push_send_for_mail(
        CI_Controller $controller,
        string $to,
        ?string $cc = null,
        string $subject = '',
        string $message = ''
    ): void {
        $controllerClass = strtolower((new ReflectionClass($controller))->getShortName());
        if (!in_array($controllerClass, ['leaves', 'requests'], true) || !hayne_push_is_enabled()) {
            return;
        }

        $controller->load->database();
        if (!$controller->db->table_exists('hayne_push_subscriptions')) {
            log_message('error', 'HAYNE Push: subscription table is missing; run push-install.php.');
            return;
        }

        $emails = hayne_push_extract_emails($to);
        if ($controllerClass === 'leaves') {
            $emails = array_values(array_unique(array_merge(
                $emails,
                hayne_push_extract_emails($cc)
            )));
        }
        if ($emails === []) {
            return;
        }

        $userIds = [];
        foreach ($emails as $email) {
            $row = $controller->db
                ->select('id')
                ->from('users')
                ->where('email', $email)
                ->limit(1)
                ->get()
                ->row_array();
            if (!empty($row['id'])) {
                $userIds[] = (int) $row['id'];
            }
        }
        $userIds = array_values(array_unique($userIds));
        if ($userIds === []) {
            return;
        }

        $subscriptions = $controller->db
            ->select('id, endpoint, endpoint_hash, p256dh, auth, content_encoding')
            ->from('hayne_push_subscriptions')
            ->where_in('user_id', $userIds)
            ->get()
            ->result_array();
        if ($subscriptions === []) {
            return;
        }

        $ttl = (int) (getenv('HAYNE_PUSH_TTL') ?: 86400);
        $ttl = max(60, min(604800, $ttl));
        $auth = [
            'VAPID' => [
                'subject' => trim((string) getenv('VAPID_SUBJECT')),
                'publicKey' => hayne_push_public_key(),
                'privateKey' => trim((string) getenv('VAPID_PRIVATE_KEY')),
            ],
        ];
        $options = [
            'TTL' => $ttl,
            'urgency' => 'normal',
            'contentType' => 'application/json',
        ];

        $webPush = new \Minishlink\WebPush\WebPush($auth, $options);
        $webPush->setReuseVAPIDHeaders(true);

        if ($controllerClass === 'leaves') {
            $body = 'Masz nowy wniosek wymagający uwagi.';
            $targetPath = 'requests';
        } else {
            $body = hayne_push_employee_body_for_mail($message);
            $targetPath = 'leaves';
        }
        $payload = json_encode([
            'title' => 'HAYNE Leave',
            'body' => $body,
            'url' => rtrim($controller->config->base_url(), '/') . '/' . $targetPath,
            'tag' => 'hayne-leave-workflow',
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        foreach ($subscriptions as $row) {
            try {
                $subscription = \Minishlink\WebPush\Subscription::create([
                    'endpoint' => $row['endpoint'],
                    'keys' => [
                        'p256dh' => $row['p256dh'],
                        'auth' => $row['auth'],
                    ],
                    'contentEncoding' => $row['content_encoding'] ?: 'aes128gcm',
                ]);
                $report = $webPush->sendOneNotification($subscription, (string) $payload);

                if ($report->isSuccess()) {
                    $controller->db
                        ->where('id', (int) $row['id'])
                        ->update('hayne_push_subscriptions', [
                            'last_success_at' => date('Y-m-d H:i:s'),
                            'failure_count' => 0,
                        ]);
                    continue;
                }

                if ($report->isSubscriptionExpired()) {
                    $controller->db
                        ->where('id', (int) $row['id'])
                        ->delete('hayne_push_subscriptions');
                    continue;
                }

                $controller->db
                    ->set('last_failure_at', date('Y-m-d H:i:s'))
                    ->set('failure_count', 'failure_count + 1', false)
                    ->where('id', (int) $row['id'])
                    ->update('hayne_push_subscriptions');
                log_message('error', 'HAYNE Push delivery failed for subscription ' . substr((string) $row['endpoint_hash'], 0, 12));
            } catch (Throwable $error) {
                $controller->db
                    ->set('last_failure_at', date('Y-m-d H:i:s'))
                    ->set('failure_count', 'failure_count + 1', false)
                    ->where('id', (int) $row['id'])
                    ->update('hayne_push_subscriptions');
                log_message('error', 'HAYNE Push exception: ' . $error->getMessage());
            }
        }
    }
}
