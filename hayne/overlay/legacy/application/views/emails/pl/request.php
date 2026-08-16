<?php
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}
$this->load->helper('hayne_mail');
$term = ((string) $StartDate === (string) $EndDate)
    ? (string) $StartDate
    : (string) $StartDate . ' – ' . (string) $EndDate;
$detailsUrl = rtrim((string) $BaseUrl, '/') . '/requests/review/' . rawurlencode((string) $LeaveId);
$isSensitiveCaregiver = filter_var($HayneSensitiveCaregiver ?? FALSE, FILTER_VALIDATE_BOOLEAN);
$reasonLabel = $isSensitiveCaregiver ? 'Dane formalne' : 'Uzasadnienie';
$reasonValue = $isSensitiveCaregiver
    ? 'Dane osoby wymagającej opieki lub wsparcia są dostępne wyłącznie dla kadr.'
    : $Reason;

echo hayne_mail_render(
    'Nowy wniosek urlopowy',
    'Nowy wniosek oczekuje na Twoją decyzję.',
    [
        ['label' => 'Pracownik', 'value' => trim((string) $Firstname . ' ' . (string) $Lastname)],
        ['label' => 'Typ urlopu', 'value' => $Type],
        ['label' => 'Termin', 'value' => $term],
        ['label' => 'Liczba dni', 'value' => $Duration],
        ['label' => $reasonLabel, 'value' => $reasonValue],
    ],
    'Do akceptacji',
    'pending',
    'Zobacz wniosek',
    $detailsUrl
);
