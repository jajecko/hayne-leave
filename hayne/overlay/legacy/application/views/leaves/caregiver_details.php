<?php
if (empty($hayneCaregiverDetails)) {
    return;
}

$relationLabels = [
    'son' => 'Syn',
    'daughter' => 'Córka',
    'mother' => 'Matka',
    'father' => 'Ojciec',
    'spouse' => 'Małżonek / małżonka',
    'household' => 'Inna osoba z tego samego gospodarstwa domowego',
];
$relationCode = (string) ($hayneCaregiverDetails['relation_code'] ?? '');
?>

<div class="well hayne-caregiver-details" data-hayne-caregiver-details="v1">
    <h4 style="margin-top: 0;">Dane urlopu opiekuńczego</h4>
    <p>
        <strong>Osoba:</strong>
        <?php echo html_escape((string) $hayneCaregiverDetails['person_name']); ?>
    </p>
    <p>
        <strong>Relacja:</strong>
        <?php echo html_escape($relationLabels[$relationCode] ?? $relationCode); ?>
    </p>
    <?php if ($relationCode === 'household' && !empty($hayneCaregiverDetails['household_address'])) { ?>
        <p>
            <strong>Adres zamieszkania:</strong>
            <?php echo html_escape((string) $hayneCaregiverDetails['household_address']); ?>
        </p>
    <?php } ?>
    <p style="margin-bottom: 0;">
        <strong>Przyczyna opieki lub wsparcia:</strong><br />
        <?php echo nl2br(html_escape((string) $hayneCaregiverDetails['care_reason'])); ?>
    </p>
</div>
