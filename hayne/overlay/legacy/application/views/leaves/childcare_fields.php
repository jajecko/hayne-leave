<?php
if (empty($hayneChildcareState['enabled'])) {
    return;
}

$allocated = !empty($hayneChildcareState['allocated']);
?>

<div id="hayneChildcareFields" class="hayne-childcare-fields well"
    data-childcare-type-id="<?php echo (int) $hayneChildcareState['leave_type_id']; ?>"
    style="display: none; margin-top: 14px;">
    <h4 style="margin-top: 0;">Opieka nad dzieckiem do 14 lat</h4>

    <?php if ($allocated) { ?>
        <p class="muted">
            Limit <?php echo (int) $hayneChildcareState['year']; ?>:
            pozostało <strong><?php echo (float) $hayneChildcareState['remaining']; ?> z <?php echo (int) $hayneChildcareState['limit']; ?> dni</strong>.
            Niewykorzystane dni nie przechodzą na kolejny rok.
        </p>
    <?php } else { ?>
        <div class="alert alert-warning">
            Nie masz przyznanego limitu dniowego opieki nad dzieckiem na <?php echo (int) $hayneChildcareState['year']; ?> rok.
            Skontaktuj się z HR przed złożeniem wniosku.
        </div>
    <?php } ?>

    <p class="muted" style="margin-bottom: 0;">
        HAYNE obsługuje wyłącznie pełne dni. Pierwszy wniosek złożony w HAYNE w danym roku
        oznacza wybór wariantu dniowego z art. 188. Wariant godzinowy jest obsługiwany poza HAYNE przez HR.
    </p>
</div>

<script type="text/javascript">
(function () {
    var typeSelect = document.getElementById('type');
    var panel = document.getElementById('hayneChildcareFields');

    if (!typeSelect || !panel) {
        return;
    }

    function syncPanel() {
        var active = String(typeSelect.value) === String(panel.getAttribute('data-childcare-type-id'));
        panel.style.display = active ? '' : 'none';
    }

    typeSelect.addEventListener('change', syncPanel);
    syncPanel();
})();
</script>
