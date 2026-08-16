<?php
if (empty($hayneCaregiverState['enabled'])) {
    return;
}

$current = !empty($hayneCaregiverState['current']) ? $hayneCaregiverState['current'] : [];
$personName = (string) ($current['person_name'] ?? '');
$relationCode = (string) ($current['relation_code'] ?? '');
$householdAddress = (string) ($current['household_address'] ?? '');
$careReason = (string) ($current['care_reason'] ?? '');
?>

<div id="hayneCaregiverFields" class="hayne-caregiver-fields well"
    data-caregiver-type-id="<?php echo (int) $hayneCaregiverState['leave_type_id']; ?>"
    style="display: none; margin-top: 14px;">
    <h4 style="margin-top: 0;">Dane do urlopu opiekuńczego</h4>
    <p class="muted">
        Limit <?php echo (int) $hayneCaregiverState['year']; ?>:
        pozostało <strong><?php echo (float) $hayneCaregiverState['remaining']; ?> z <?php echo (int) $hayneCaregiverState['limit']; ?> dni</strong>.
        Wniosek złóż co najmniej 1 dzień przed rozpoczęciem urlopu.
    </p>

    <label for="hayne_caregiver_person_name">Osoba wymagająca opieki lub wsparcia</label>
    <input type="text" class="input-xlarge" maxlength="190"
        name="hayne_caregiver_person_name" id="hayne_caregiver_person_name"
        value="<?php echo html_escape($personName); ?>" />

    <label for="hayne_caregiver_relation">Relacja</label>
    <select class="input-xlarge" name="hayne_caregiver_relation" id="hayne_caregiver_relation">
        <option value="">Wybierz relację</option>
        <option value="son" <?php echo $relationCode === 'son' ? 'selected' : ''; ?>>Syn</option>
        <option value="daughter" <?php echo $relationCode === 'daughter' ? 'selected' : ''; ?>>Córka</option>
        <option value="mother" <?php echo $relationCode === 'mother' ? 'selected' : ''; ?>>Matka</option>
        <option value="father" <?php echo $relationCode === 'father' ? 'selected' : ''; ?>>Ojciec</option>
        <option value="spouse" <?php echo $relationCode === 'spouse' ? 'selected' : ''; ?>>Małżonek / małżonka</option>
        <option value="household" <?php echo $relationCode === 'household' ? 'selected' : ''; ?>>Inna osoba z tego samego gospodarstwa domowego</option>
    </select>

    <div id="hayneCaregiverAddressWrap" style="display: none;">
        <label for="hayne_caregiver_household_address">Adres zamieszkania tej osoby</label>
        <input type="text" class="input-xlarge" maxlength="255"
            name="hayne_caregiver_household_address" id="hayne_caregiver_household_address"
            value="<?php echo html_escape($householdAddress); ?>" />
    </div>

    <label for="hayne_caregiver_reason">Przyczyna konieczności zapewnienia opieki lub wsparcia</label>
    <textarea class="input-xxlarge" maxlength="4000" rows="3"
        name="hayne_caregiver_reason" id="hayne_caregiver_reason"><?php echo html_escape($careReason); ?></textarea>
</div>

<script type="text/javascript">
(function () {
    var typeSelect = document.getElementById('type');
    var panel = document.getElementById('hayneCaregiverFields');
    var relation = document.getElementById('hayne_caregiver_relation');
    var addressWrap = document.getElementById('hayneCaregiverAddressWrap');
    var address = document.getElementById('hayne_caregiver_household_address');
    var person = document.getElementById('hayne_caregiver_person_name');
    var reason = document.getElementById('hayne_caregiver_reason');

    if (!typeSelect || !panel || !relation || !addressWrap || !address || !person || !reason) {
        return;
    }

    function syncAddress() {
        var household = relation.value === 'household';
        addressWrap.style.display = household ? '' : 'none';
        address.required = household && panel.style.display !== 'none';
    }

    function syncPanel() {
        var active = String(typeSelect.value) === String(panel.getAttribute('data-caregiver-type-id'));
        panel.style.display = active ? '' : 'none';
        person.required = active;
        relation.required = active;
        reason.required = active;
        syncAddress();
    }

    typeSelect.addEventListener('change', syncPanel);
    relation.addEventListener('change', syncAddress);
    syncPanel();
})();
</script>
