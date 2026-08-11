<?php
if (empty($hayneOccasionState['enabled'])) {
    return;
}

$current = !empty($hayneOccasionState['current']) ? $hayneOccasionState['current'] : [];
$currentEvent = (string) ($current['event_code'] ?? '');
$currentDate = (string) ($current['event_date'] ?? '');
$events = !empty($hayneOccasionState['events']) ? $hayneOccasionState['events'] : [];
?>

<div id="hayneOccasionFields" class="hayne-occasion-fields well"
    data-occasion-type-id="<?php echo (int) $hayneOccasionState['leave_type_id']; ?>"
    style="display: none; margin-top: 14px;">
    <h4 style="margin-top: 0;">Urlop okolicznościowy</h4>
    <p class="muted">
        Limit zależy od konkretnego zdarzenia: 1 albo 2 pełne dni. Dwa dni mogą być wykorzystane
        w dwóch oddzielnych wnioskach. HAYNE nie tworzy rocznej puli urlopu okolicznościowego.
    </p>
    <p class="muted">
        Powiązanie terminu zwolnienia ze zdarzeniem i dokumenty potwierdzające są weryfikowane
        organizacyjnie przez przełożonego lub HR; dokumentów nie zapisujemy w HAYNE.
    </p>

    <label for="hayne_occasion_event">Zdarzenie</label>
    <select class="input-xxlarge" name="hayne_occasion_event" id="hayne_occasion_event">
        <option value="">Wybierz zdarzenie</option>
        <?php foreach ($events as $eventCode => $definition) { ?>
            <option value="<?php echo html_escape((string) $eventCode); ?>"
                data-max-days="<?php echo (int) $definition['days']; ?>"
                <?php echo $currentEvent === (string) $eventCode ? 'selected' : ''; ?>>
                <?php echo html_escape((string) $definition['label']); ?> — <?php echo (int) $definition['days']; ?> <?php echo (int) $definition['days'] === 1 ? 'dzień' : 'dni'; ?>
            </option>
        <?php } ?>
    </select>

    <label for="hayne_occasion_event_date">Data zdarzenia</label>
    <input type="date" class="input-large" name="hayne_occasion_event_date"
        id="hayne_occasion_event_date" value="<?php echo html_escape($currentDate); ?>" />
    <span class="help-block">
        Dla ślubu lub urodzenia wpisz datę zdarzenia. Dla zgonu i pogrzebu wpisz datę zgonu —
        ta sama data identyfikuje oba dni związane z jednym zdarzeniem.
    </span>

    <p id="hayneOccasionLimitHint" class="muted" style="margin-bottom: 0;"></p>
</div>

<script type="text/javascript">
(function () {
    var typeSelect = document.getElementById('type');
    var panel = document.getElementById('hayneOccasionFields');
    var eventSelect = document.getElementById('hayne_occasion_event');
    var eventDate = document.getElementById('hayne_occasion_event_date');
    var hint = document.getElementById('hayneOccasionLimitHint');

    if (!typeSelect || !panel || !eventSelect || !eventDate || !hint) {
        return;
    }

    function syncHint() {
        var option = eventSelect.options[eventSelect.selectedIndex];
        var days = option ? parseInt(option.getAttribute('data-max-days') || '0', 10) : 0;
        if (!days) {
            hint.textContent = '';
            return;
        }
        hint.textContent = days === 1
            ? 'To zdarzenie daje maksymalnie 1 pełny dzień zwolnienia.'
            : 'To zdarzenie daje maksymalnie 2 pełne dni zwolnienia; możesz wykorzystać je razem albo w dwóch wnioskach.';
    }

    function syncPanel() {
        var active = String(typeSelect.value) === String(panel.getAttribute('data-occasion-type-id'));
        panel.style.display = active ? '' : 'none';
        eventSelect.required = active;
        eventDate.required = active;
        syncHint();
    }

    typeSelect.addEventListener('change', syncPanel);
    eventSelect.addEventListener('change', syncHint);
    syncPanel();
})();
</script>
