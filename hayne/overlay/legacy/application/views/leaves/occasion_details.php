<?php
if (empty($hayneOccasionDetails)) {
    return;
}

$label = (string) ($hayneOccasionDetails['event_label'] ?? $hayneOccasionDetails['event_code'] ?? '');
$eventDate = (string) ($hayneOccasionDetails['event_date'] ?? '');
$maxDays = (int) ($hayneOccasionDetails['max_days'] ?? 0);
?>

<div class="well hayne-occasion-details" data-hayne-occasion-details="v1">
    <h4 style="margin-top: 0;">Dane urlopu okolicznościowego</h4>
    <p>
        <strong>Zdarzenie:</strong>
        <?php echo html_escape($label); ?>
    </p>
    <p>
        <strong>Data zdarzenia:</strong>
        <?php echo html_escape($eventDate); ?>
    </p>
    <?php if ($maxDays > 0) { ?>
        <p style="margin-bottom: 0;">
            <strong>Limit dla zdarzenia:</strong>
            <?php echo $maxDays; ?> <?php echo $maxDays === 1 ? 'dzień' : 'dni'; ?>
        </p>
    <?php } ?>
</div>
