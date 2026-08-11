<?php
if (empty($hayneHolidayCompensationDetails)) {
    return;
}
?>

<div class="well hayne-holiday-compensation-details" data-hayne-holiday-compensation-details="v1">
    <h4 style="margin-top: 0;">Dzień wolny za święto</h4>
    <p>
        <strong>Święto źródłowe:</strong>
        <?php echo html_escape((string) ($hayneHolidayCompensationDetails['source_holiday_date'] ?? '')); ?>
    </p>
    <p style="margin-bottom: 0;">
        <strong>Okres rozliczeniowy:</strong>
        <?php echo html_escape((string) ($hayneHolidayCompensationDetails['period_start'] ?? '')); ?>
        –
        <?php echo html_escape((string) ($hayneHolidayCompensationDetails['period_end'] ?? '')); ?>
    </p>
</div>
