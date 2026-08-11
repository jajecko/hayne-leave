<?php
if (empty($hayneHolidayCompensationState['enabled'])) {
    return;
}
$current = !empty($hayneHolidayCompensationState['current']) ? $hayneHolidayCompensationState['current'] : [];
$currentGrantId = (int) ($current['grant_id'] ?? 0);
$grants = !empty($hayneHolidayCompensationState['grants']) ? $hayneHolidayCompensationState['grants'] : [];
?>

<div id="hayneHolidayCompensationFields" class="hayne-holiday-compensation-fields well"
    data-holiday-compensation-type-id="<?php echo (int) $hayneHolidayCompensationState['leave_type_id']; ?>"
    style="display: none; margin-top: 14px;">
    <h4 style="margin-top: 0;">Dzień wolny za święto</h4>
    <p class="muted">
        Wybierz grant przyznany przez HR. Wniosek musi obejmować dokładnie jeden pełny dzień
        mieszczący się w przypisanym okresie rozliczeniowym. Ten dzień nie pomniejsza urlopu wypoczynkowego.
    </p>

    <label for="hayne_holiday_compensation_grant_id">Przyznany dzień za święto</label>
    <select class="input-xxlarge" name="hayne_holiday_compensation_grant_id" id="hayne_holiday_compensation_grant_id">
        <option value="">Wybierz grant</option>
        <?php foreach ($grants as $grant) {
            $grantId = (int) $grant['id'];
            ?>
            <option value="<?php echo $grantId; ?>"
                data-period-start="<?php echo html_escape($grant['period_start']); ?>"
                data-period-end="<?php echo html_escape($grant['period_end']); ?>"
                <?php echo $grantId === $currentGrantId ? 'selected' : ''; ?>>
                Święto <?php echo html_escape($grant['source_holiday_date']); ?> — ważne <?php echo html_escape($grant['period_start']); ?>–<?php echo html_escape($grant['period_end']); ?>
            </option>
        <?php } ?>
    </select>
    <?php if (empty($grants)) { ?>
        <span class="help-block">Brak dostępnego grantu. Skontaktuj się z HR.</span>
    <?php } else { ?>
        <span class="help-block" id="hayneHolidayCompensationHint"></span>
    <?php } ?>
</div>

<script type="text/javascript">
(function () {
    var typeSelect = document.getElementById('type');
    var panel = document.getElementById('hayneHolidayCompensationFields');
    var grantSelect = document.getElementById('hayne_holiday_compensation_grant_id');
    var hint = document.getElementById('hayneHolidayCompensationHint');
    if (!typeSelect || !panel || !grantSelect) {
        return;
    }
    function syncHint() {
        if (!hint) {
            return;
        }
        var option = grantSelect.options[grantSelect.selectedIndex];
        var start = option ? option.getAttribute('data-period-start') : '';
        var end = option ? option.getAttribute('data-period-end') : '';
        hint.textContent = start && end ? 'Dzień wolny musi przypadać między ' + start + ' a ' + end + '.' : '';
    }
    function syncPanel() {
        var active = String(typeSelect.value) === String(panel.getAttribute('data-holiday-compensation-type-id'));
        panel.style.display = active ? '' : 'none';
        grantSelect.required = active;
        syncHint();
    }
    typeSelect.addEventListener('change', syncPanel);
    grantSelect.addEventListener('change', syncHint);
    syncPanel();
})();
</script>
