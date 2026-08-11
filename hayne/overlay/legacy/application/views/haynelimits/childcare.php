<?php
$childcareConfigured = !empty($childcare_policy);
$childcareEnabled = $childcareConfigured && (int) $childcare_policy['enabled'] === 1;
$childcareTypeId = $childcareConfigured ? (int) $childcare_policy['leave_type_id'] : 0;
?>

<div class="well hayne-statutory-policy" data-hayne-policy="childcare">
    <div class="row-fluid">
        <div class="span7">
            <h3 style="margin-top: 0;">Opieka nad dzieckiem do 14 lat</h3>
            <p>
                <strong>Do 2 dni w roku kalendarzowym</strong> z zachowaniem prawa do wynagrodzenia,
                bez przenoszenia niewykorzystanych dni na kolejny rok.
            </p>
            <p class="muted">
                HAYNE obsługuje wyłącznie wariant dniowy. Pierwszy wniosek złożony w HAYNE
                w danym roku oznacza wybór korzystania z art. 188 w dniach. Wariant godzinowy
                pozostaje poza HAYNE i jest obsługiwany przez HR.
            </p>
            <p class="muted" style="margin-bottom: 0;">
                HR przydziela pracownikowi na wybrany rok 0, 1 albo 2 dni. Wartość 1 dzień
                pozwala odwzorować podział uprawnienia między rodziców. HAYNE nie przechowuje
                danych dziecka — uprawnienie jest potwierdzane organizacyjnie przez HR.
            </p>
        </div>
        <div class="span5">
            <?php echo form_open('haynelimits/saveChildcarePolicy', [
                'class' => 'form-vertical',
                'id' => 'hayneChildcarePolicyForm',
            ]); ?>
                <label for="childcare_type_id">Rodzaj nieobecności w Jorani</label>
                <select name="childcare_type_id" id="childcare_type_id" class="input-xlarge" required>
                    <option value="">Wybierz rodzaj</option>
                    <?php foreach ($types as $type) {
                        $typeId = (int) $type['id'];
                        if ($typeId <= 0) {
                            continue;
                        }
                        ?>
                        <option value="<?php echo $typeId; ?>" <?php echo $typeId === $childcareTypeId ? 'selected' : ''; ?>>
                            <?php echo html_escape($type['name']); ?>
                        </option>
                    <?php } ?>
                </select>
                <span class="help-block">Wybierz dedykowany typ dla art. 188. HAYNE nie zgaduje jego ID.</span>

                <label class="checkbox" for="childcare_enabled">
                    <input type="checkbox" name="childcare_enabled" id="childcare_enabled" value="1" <?php echo $childcareEnabled ? 'checked' : ''; ?> />
                    Aktywuj obsługę opieki nad dzieckiem do 14 lat
                </label>

                <button type="submit" class="btn btn-primary">Zapisz ustawienia</button>
            </form>
        </div>
    </div>

    <hr />

    <div class="row-fluid">
        <div class="span7">
            <h4 style="margin-top: 0;">Limity pracowników — <?php echo (int) $selected_year; ?></h4>
            <p class="muted">
                Ustaw 2 dni przy pełnym wykorzystaniu uprawnienia w HAYNE, 1 dzień przy podziale
                z drugim rodzicem albo 0 dni, jeżeli pracownik nie korzysta z wariantu dniowego w tym roku.
            </p>
        </div>
        <div class="span5">
            <form method="get" action="<?php echo base_url(); ?>haynelimits" class="form-inline pull-right">
                <label for="childcare_year">Rok&nbsp;</label>
                <select name="year" id="childcare_year" class="input-small" onchange="this.form.submit()">
                    <?php for ($year = $current_year - 5; $year <= $current_year + 1; $year++) { ?>
                        <option value="<?php echo $year; ?>" <?php echo $year === $selected_year ? 'selected' : ''; ?>>
                            <?php echo $year; ?>
                        </option>
                    <?php } ?>
                </select>
            </form>
        </div>
    </div>

    <?php if (!$childcareEnabled) { ?>
        <div class="alert alert-info" style="margin-bottom: 0;">
            Najpierw wybierz rodzaj nieobecności i aktywuj politykę art. 188.
        </div>
    <?php } else { ?>
        <table class="table table-bordered table-hover" id="hayneChildcareAllocations" style="margin-bottom: 0;">
            <thead>
                <tr>
                    <th>Pracownik</th>
                    <th>Limit w <?php echo (int) $selected_year; ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($employees as $employee) {
                    if ((int) $employee['active'] !== 1) {
                        continue;
                    }
                    $employeeId = (int) $employee['id'];
                    $days = isset($childcare_allocations[$employeeId]) ? (int) $childcare_allocations[$employeeId] : 0;
                    ?>
                    <tr data-childcare-employee-id="<?php echo $employeeId; ?>" data-childcare-days="<?php echo $days; ?>">
                        <td><?php echo html_escape(trim($employee['firstname'] . ' ' . $employee['lastname'])); ?></td>
                        <td>
                            <?php echo form_open('haynelimits/saveChildcareAllocation', [
                                'class' => 'form-inline',
                                'style' => 'margin: 0;',
                            ]); ?>
                                <input type="hidden" name="employee_id" value="<?php echo $employeeId; ?>" />
                                <input type="hidden" name="year" value="<?php echo (int) $selected_year; ?>" />
                                <select name="childcare_days" class="input-small" aria-label="Limit opieki nad dzieckiem">
                                    <option value="0" <?php echo $days === 0 ? 'selected' : ''; ?>>0 dni</option>
                                    <option value="1" <?php echo $days === 1 ? 'selected' : ''; ?>>1 dzień</option>
                                    <option value="2" <?php echo $days === 2 ? 'selected' : ''; ?>>2 dni</option>
                                </select>
                        </td>
                        <td>
                                <button type="submit" class="btn btn-small">Zapisz</button>
                            </form>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    <?php } ?>
</div>
