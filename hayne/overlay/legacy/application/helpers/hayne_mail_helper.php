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
                . '<td style="padding:13px 16px;border-top:1px solid #E6E6E1;color:#74746F;font-size:13px;line-height:1.45;width:34%;vertical-align:top;">'
                . hayne_mail_escape($label)
                . '</td>'
                . '<td style="padding:13px 16px;border-top:1px solid #E6E6E1;color:#171715;font-size:14px;line-height:1.5;font-weight:600;vertical-align:top;">'
                . nl2br(hayne_mail_escape($value), false)
                . '</td>'
                . '</tr>';
        }

        $safeUrl = hayne_mail_escape($ctaUrl);
        $logoHtml = '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="border-collapse:collapse;">'
            . '<tr><td style="padding:0;color:#111111;font-family:Arial,Helvetica,sans-serif;font-size:24px;line-height:24px;font-weight:800;letter-spacing:4px;">HAYNE</td></tr>'
            . '<tr><td style="padding:7px 0 0 0;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border-collapse:collapse;">'
            . '<tr>'
            . '<td style="width:34%;border-top:1px solid #111111;font-size:1px;line-height:1px;">&nbsp;</td>'
            . '<td style="padding:0 7px;color:#111111;font-family:Arial,Helvetica,sans-serif;font-size:8px;line-height:9px;font-weight:700;letter-spacing:2px;text-align:center;white-space:nowrap;">LEAVE</td>'
            . '<td style="width:34%;border-top:1px solid #111111;font-size:1px;line-height:1px;">&nbsp;</td>'
            . '</tr></table>'
            . '</td></tr></table>';

        return '<!doctype html>'
            . '<html lang="pl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
            . '<body style="margin:0;padding:0;background:#F5F5F2;font-family:Arial,Helvetica,sans-serif;color:#111111;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;background:#F5F5F2;margin:0;padding:0;">'
            . '<tr><td align="center" style="padding:32px 12px;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:620px;background:#FFFFFF;border:1px solid #E2E2DC;border-collapse:separate;border-spacing:0;border-radius:14px;overflow:hidden;">'
            . '<tr><td style="padding:22px 26px;border-bottom:1px solid #ECECE7;background:#FFFFFF;">' . $logoHtml . '</td></tr>'
            . '<tr><td style="padding:30px 26px 12px 26px;">'
            . '<div style="display:inline-block;padding:6px 10px;border-radius:999px;background:' . $tone['background'] . ';color:' . $tone['foreground'] . ';font-size:12px;line-height:1.2;font-weight:700;">'
            . hayne_mail_escape($statusLabel)
            . '</div>'
            . '<h1 style="margin:18px 0 10px 0;font-size:26px;line-height:1.22;font-weight:700;color:#171715;letter-spacing:-.3px;">' . hayne_mail_escape($headline) . '</h1>'
            . '<p style="margin:0;color:#686863;font-size:15px;line-height:1.6;">' . hayne_mail_escape($intro) . '</p>'
            . '</td></tr>'
            . '<tr><td style="padding:14px 26px 4px 26px;">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;border:1px solid #E6E6E1;border-collapse:separate;border-spacing:0;border-radius:10px;overflow:hidden;">'
            . $rowsHtml
            . '</table></td></tr>'
            . '<tr><td style="padding:24px 26px 30px 26px;">'
            . '<a href="' . $safeUrl . '" style="display:inline-block;background:#111111;color:#FFFFFF;text-decoration:none;font-size:14px;line-height:1;font-weight:700;padding:15px 20px;border-radius:8px;">' . hayne_mail_escape($ctaLabel) . '</a>'
            . '</td></tr>'
            . '<tr><td style="padding:18px 26px 22px 26px;border-top:1px solid #ECECE7;color:#8A8A84;font-size:11px;line-height:1.6;background:#FAFAF8;">'
            . 'Wiadomość została wygenerowana automatycznie przez HAYNE Leave. Nie odpowiadaj na tę wiadomość.'
            . '</td></tr>'
            . '</table></td></tr></table></body></html>';
    }
}
