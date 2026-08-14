<?php
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}
$this->load->helper('hayne_mail');
$term = ((string) $StartDate === (string) $EndDate)
    ? (string) $StartDate
    : (string) $StartDate . ' – ' . (string) $EndDate;
$detailsUrl = rtrim((string) $BaseUrl, '/') . '/leaves/leaves/' . rawurlencode((string) $LeaveId);

echo hayne_mail_render(
    'Wniosek odrzucony',
    'Twój wniosek urlopowy został odrzucony. Szczegóły znajdziesz w HAYNE Leave.',
    [
        ['label' => 'Typ urlopu', 'value' => $Type],
        ['label' => 'Termin', 'value' => $term],
        ['label' => 'Liczba dni', 'value' => $Duration],
    ],
    'Odrzucony',
    'rejected',
    'Zobacz wniosek',
    $detailsUrl
);
