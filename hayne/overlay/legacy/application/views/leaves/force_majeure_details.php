<?php
if (empty($hayneForceMajeureDetails)) {
    return;
}

$eventLabels = [
    'illness' => 'Choroba',
    'accident' => 'Wypadek',
];
$eventCode = (string) ($hayneForceMajeureDetails['event_code'] ?? '');
$eventLabel = $eventLabels[$eventCode] ?? $eventCode;
?>

<div class="well hayne-force-majeure-details" data-hayne-force-majeure-details="v1" style="margin-top: 14px;">
    <h4 style="margin-top: 0;">Dane zwolnienia z powodu siły wyższej</h4>
    <dl class="dl-horizontal" style="margin-bottom: 0;">
        <dt>Przyczyna</dt>
        <dd><?php echo html_escape($eventLabel); ?></dd>
        <dt>Natychmiastowa obecność</dt>
        <dd><?php echo !empty($hayneForceMajeureDetails['immediate_presence']) ? 'Potwierdzona' : 'Niepotwierdzona'; ?></dd>
        <dt>Tryb</dt>
        <dd>Dniowy — HAYNE obsługuje wyłącznie pełne dni</dd>
        <dt>Wynagrodzenie</dt>
        <dd>50% — informacja ustawowa; HAYNE nie nalicza płac</dd>
    </dl>
</div>
