<?php
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

if (!function_exists('hayne_mail_escape')) {
    function hayne_mail_escape($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('hayne_mail_render')) {
    /**
     * Render a compact, email-client-safe HAYNE Leave notification.
     *
     * @param array<int, array{label:string,value:mixed}> $rows
     */
    function hayne_mail_render(
        string $headline,
        string $intro,
        array $rows,
        string $statusLabel,
        string $statusTone,
        string $ctaLabel,
        string $ctaUrl
    ): string {
        $tones = [
            'pending' => ['background' => '#FFF4D6', 'foreground' => '#6B4E00'],
            'accepted' => ['background' => '#E8F5EC', 'foreground' => '#1F6B3A'],
            'rejected' => ['background' => '#FDECEC', 'foreground' => '#A12626'],
            'cancelled' => ['background' => '#EEEEEE', 'foreground' => '#333333'],
        ];
        $tone = $tones[$statusTone] ?? $tones['cancelled'];

        $rowsHtml = '';
        foreach ($rows as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $value = trim((string) ($row['value'] ?? ''));
            if ($label === '' || $value === '') {
                continue;
            }
            $rowsHtml .= '<tr>'
                . '<td style="padding:12px 16px;border-top:1px solid #E6E6E6;color:#666666;font-size:13px;line-height:1.4;width:38%;vertical-align:top;">'
                . hayne_mail_escape($label)
                . '</td>'
                . '<td style="padding:12px 16px;border-top:1px solid #E6E6E6;color:#111111;font-size:14px;line-height:1.4;font-weight:600;vertical-align:top;">'
                . hayne_mail_escape($value)
                . '</td>'
                . '</tr>';
        }

        $safeUrl = hayne_mail_escape($ctaUrl);

        return '<!doctype html>'
            . '<html lang="pl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
            . '<body style="margin:0;padding:0;background:#F3F3F3;font-family:Arial,Helvetica,sans-serif;color:#111111;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#F3F3F3;margin:0;padding:0;">'
            . '<tr><td align="center" style="padding:28px 12px;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:620px;background:#FFFFFF;border-collapse:collapse;">'
            . '<tr><td style="padding:20px 24px;background:#111111;color:#FFFFFF;font-size:18px;line-height:1;font-weight:700;letter-spacing:.5px;">HAYNE <span style="font-weight:400;opacity:.86;">Leave</span></td></tr>'
            . '<tr><td style="padding:30px 24px 12px 24px;">'
            . '<div style="display:inline-block;padding:6px 10px;border-radius:999px;background:' . $tone['background'] . ';color:' . $tone['foreground'] . ';font-size:12px;line-height:1.2;font-weight:700;">'
            . hayne_mail_escape($statusLabel)
            . '</div>'
            . '<h1 style="margin:18px 0 10px 0;font-size:25px;line-height:1.25;font-weight:700;color:#111111;">' . hayne_mail_escape($headline) . '</h1>'
            . '<p style="margin:0;color:#555555;font-size:15px;line-height:1.6;">' . hayne_mail_escape($intro) . '</p>'
            . '</td></tr>'
            . '<tr><td style="padding:12px 24px 4px 24px;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border:1px solid #E6E6E6;border-collapse:collapse;">'
            . $rowsHtml
            . '</table></td></tr>'
            . '<tr><td style="padding:24px;">'
            . '<a href="' . $safeUrl . '" style="display:inline-block;background:#111111;color:#FFFFFF;text-decoration:none;font-size:14px;line-height:1;font-weight:700;padding:14px 20px;border-radius:4px;">' . hayne_mail_escape($ctaLabel) . '</a>'
            . '</td></tr>'
            . '<tr><td style="padding:18px 24px 24px 24px;border-top:1px solid #E6E6E6;color:#777777;font-size:11px;line-height:1.6;">'
            . 'Wiadomość została wygenerowana automatycznie przez HAYNE Leave. Nie odpowiadaj na tę wiadomość.'
            . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }
}
