<?php
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Push extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        if (!$this->session->userdata('logged_in')) {
            $this->output
                ->set_status_header(401)
                ->set_content_type('application/json', 'utf-8')
                ->set_output(json_encode(['ok' => false, 'error' => 'unauthorized']));
            $this->output->_display();
            exit;
        }

        setUserContext($this);
        $this->load->database();
        $this->load->helper('hayne_push');
    }

    public function settings(): void
    {
        $this->respond([
            'ok' => true,
            'enabled' => hayne_push_is_enabled(),
            'publicKey' => hayne_push_public_key(),
            'csrfTokenName' => $this->security->get_csrf_token_name(),
            'csrfToken' => $this->security->get_csrf_hash(),
        ]);
    }

    public function subscribe(): void
    {
        if (!$this->requirePost()) {
            return;
        }
        if (!hayne_push_is_enabled()) {
            $this->respond(['ok' => false, 'error' => 'push_disabled'], 503);
            return;
        }
        if (!$this->db->table_exists('hayne_push_subscriptions')) {
            $this->respond(['ok' => false, 'error' => 'push_storage_unavailable'], 503);
            return;
        }

        $endpoint = trim((string) $this->input->post('endpoint', true));
        $p256dh = trim((string) $this->input->post('p256dh', true));
        $auth = trim((string) $this->input->post('auth', true));
        $contentEncoding = trim((string) $this->input->post('contentEncoding', true));

        if (!$this->isValidEndpoint($endpoint) || !$this->isValidKey($p256dh) || !$this->isValidKey($auth)) {
            $this->respond(['ok' => false, 'error' => 'invalid_subscription'], 422);
            return;
        }
        if (!in_array($contentEncoding, ['aes128gcm', 'aesgcm'], true)) {
            $contentEncoding = 'aes128gcm';
        }

        $endpointHash = hash('sha256', $endpoint);
        $userAgent = substr((string) $this->input->user_agent(), 0, 500);
        $sql = "INSERT INTO hayne_push_subscriptions
                    (user_id, endpoint, endpoint_hash, p256dh, auth, content_encoding, user_agent, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    user_id = VALUES(user_id),
                    endpoint = VALUES(endpoint),
                    p256dh = VALUES(p256dh),
                    auth = VALUES(auth),
                    content_encoding = VALUES(content_encoding),
                    user_agent = VALUES(user_agent),
                    updated_at = NOW()";

        $this->db->query($sql, [
            (int) $this->user_id,
            $endpoint,
            $endpointHash,
            $p256dh,
            $auth,
            $contentEncoding,
            $userAgent,
        ]);

        $this->respond(['ok' => true]);
    }

    public function unsubscribe(): void
    {
        if (!$this->requirePost()) {
            return;
        }
        if (!$this->db->table_exists('hayne_push_subscriptions')) {
            $this->respond(['ok' => true]);
            return;
        }

        $endpoint = trim((string) $this->input->post('endpoint', true));
        if (!$this->isValidEndpoint($endpoint)) {
            $this->respond(['ok' => false, 'error' => 'invalid_subscription'], 422);
            return;
        }

        $this->db
            ->where('user_id', (int) $this->user_id)
            ->where('endpoint_hash', hash('sha256', $endpoint))
            ->delete('hayne_push_subscriptions');

        $this->respond(['ok' => true]);
    }

    private function requirePost(): bool
    {
        if (strtoupper((string) $this->input->method(true)) === 'POST') {
            return true;
        }
        $this->respond(['ok' => false, 'error' => 'method_not_allowed'], 405);
        return false;
    }

    private function isValidEndpoint(string $endpoint): bool
    {
        if ($endpoint === '' || strlen($endpoint) > 4096) {
            return false;
        }
        $parts = parse_url($endpoint);
        return is_array($parts)
            && isset($parts['scheme'], $parts['host'])
            && strtolower((string) $parts['scheme']) === 'https';
    }

    private function isValidKey(string $value): bool
    {
        return $value !== '' && strlen($value) <= 255 && preg_match('/^[A-Za-z0-9_\-+=\/]+$/', $value) === 1;
    }

    private function respond(array $payload, int $status = 200): void
    {
        $this->output
            ->set_status_header($status)
            ->set_header('Cache-Control: no-store')
            ->set_content_type('application/json', 'utf-8')
            ->set_output((string) json_encode($payload, JSON_UNESCAPED_SLASHES));
    }
}
