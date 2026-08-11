<?php
if (empty($hayneForceMajeureState['enabled'])) {
    return;
}

$current = !empty($hayneForceMajeureState['current']) ? $hayneForceMajeureState['current'] : [];
$eventCode = (string) ($current['event_code'] ?? '');
$immediatePresence = !empty($current['immediate_presence']);
?>

<div id="hayneForceMajeureFields" class="hayne-force-majeure-fields well"
    data-force-majeure-type-id="<?php echo (int) $hayneForceMajeureState['leave_type_id']; ?>"
    style="display: none; margin-top: 14px;">
    <h4 style="margin-top: 0;">Zwolnienie z powodu siły wyższej</h4>
    <p class="muted">
        Limit <?php echo (int) $hayneForceMajeureState['year']; ?>:
        pozostało <strong><?php echo (float) $hayneForceMajeureState['remaining']; ?> z <?php echo (int) $hayneForceMajeureState['limit']; ?> dni</strong>.
        Wniosek możesz złożyć najpóźniej w dniu korzystania ze zwolnienia.
    </p>
    <p class="muted">
        HAYNE obsługuje wyłącznie pełne dni. Pierwszy wniosek złożony w HAYNE w danym roku
        oznacza wybór wariantu dniowego. Wariant godzinowy jest obsługiwany poza HAYNE przez HR.
        Za czas zwolnienia przysługuje połowa wynagrodzenia; system nie nalicza płac.
    </p>

    <label for="hayne_force_majeure_event">Przyczyna pilnej sprawy rodzinnej</label>
    <select class="input-xlarge" name="hayne_force_majeure_event" id="hayne_force_majeure_event">
        <option value="">Wybierz przyczynę</option>
        <option value="illness" <?php echo $eventCode === 'illness' ? 'selected' : ''; ?>>Choroba</option>
        <option value="accident" <?php echo $eventCode === 'accident' ? 'selected' : ''; ?>>Wypadek</option>
    </select>

    <label class="checkbox" for="hayne_force_majeure_immediate_presence">
        <input type="checkbox" name="hayne_force_majeure_immediate_presence"
            id="hayne_force_majeure_immediate_presence" value="1" <?php echo $immediatePresence ? 'checked' : ''; ?> />
        Potwierdzam, że moja natychmiastowa obecność jest niezbędna.
    </label>
</div>

<script type="text/javascript">
(function () {
    var typeSelect = document.getElementById('type');
    var panel = document.getElementById('hayneForceMajeureFields');
    var eventSelect = document.getElementById('hayne_force_majeure_event');
    var presence = document.getElementById('hayne_force_majeure_immediate_presence');

    if (!typeSelect || !panel || !eventSelect || !presence) {
        return;
    }

    function syncPanel() {
        var active = String(typeSelect.value) === String(panel.getAttribute('data-force-majeure-type-id'));
        panel.style.display = active ? '' : 'none';
        eventSelect.required = active;
        presence.required = active;
    }

    typeSelect.addEventListener('change', syncPanel);
    syncPanel();
})();
</script>
