<?php
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}
$this->load->helper('hayne_mail');
$term = ((string) $StartDate === (string) $EndDate)
    ? (string) $StartDate
    : (string) $StartDate . ' – ' . (string) $EndDate;
$detailsUrl = rtrim((string) $BaseUrl, '/') . '/leaves/requests/' . rawurlencode((string) $LeaveId);

echo hayne_mail_render(
    'Wniosek anulowany',
    'Pracownik anulował wniosek urlopowy.',
    [
        ['label' => 'Pracownik', 'value' => trim((string) $Firstname . ' ' . (string) $Lastname)],
        ['label' => 'Typ urlopu', 'value' => $Type],
        ['label' => 'Termin', 'value' => $term],
        ['label' => 'Liczba dni', 'value' => $Duration],
    ],
    'Anulowany',
    'cancelled',
    'Zobacz wniosek',
    $detailsUrl
);
