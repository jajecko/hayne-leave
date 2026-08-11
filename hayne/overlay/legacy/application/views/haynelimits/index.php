<?php
/**
 * HAYNE leave limits administration.
 */
$editingEmployee = $edit_profile ? (int) $edit_profile['employee_id'] : 0;
$editingType = $edit_profile ? (int) $edit_profile['vacation_type_id'] : (int) $default_type;
$editingDays = $edit_profile ? (int) $edit_profile['annual_days'] : 26;
$editingAutoRenew = $edit_profile ? ((int) $edit_profile['auto_renew'] === 1) : TRUE;
?>

<main class="hayne-limits-page" data-hayne-view="leave-limits-v1">
    <div class="row-fluid">
        <div class="span12">
            <div class="page-header">
                <h1>Limity urlopowe</h1>
                <p class="muted">Ustaw stały roczny wymiar urlopu oraz ustawowe pule i zasady obsługiwane przez HAYNE.</p>
            </div>

            <?php echo $flash_partial_view; ?>

            <?php $this->load->view('haynelimits/caregiver', [
                'caregiver_policy' => $caregiver_policy,
                'types' => $types,
            ]); ?>

            <?php $this->load->view('haynelimits/force_majeure', [
                'force_majeure_policy' => $force_majeure_policy,
                'types' => $types,
            ]); ?>

            <?php $this->load->view('haynelimits/childcare', [
                'childcare_policy' => $childcare_policy,
                'childcare_allocations' => $childcare_allocations,
                'employees' => $employees,
                'types' => $types,
                'selected_year' => $selected_year,
                'current_year' => $current_year,
            ]); ?>

            <?php $this->load->view('haynelimits/occasion', [
                'occasion_policy' => $occasion_policy,
                'types' => $types,
            ]); ?>

            <div class="well hayne-statutory-policy" data-hayne-policy="holiday_compensation">
                <div class="row-fluid">
                    <div class="span8">
                        <h3 style="margin-top: 0;">Dzień wolny za święto</h3>
                        <p><strong>1 dzień za konkretny grant HR</strong>, ważny wyłącznie w przypisanym okresie rozliczeniowym.</p>
                        <p class="muted" style="margin-bottom: 0;">Nie jest częścią puli 20/26, nie korzysta z FIFO i nie przechodzi na kolejny okres rozliczeniowy.</p>
                    </div>
                    <div class="span4" style="text-align: right;">
                        <a class="btn btn-primary" href="<?php echo base_url(); ?>hayneholidays">Zarządzaj dniami za święta</a>
                    </div>
                </div>
            </div>

            <div class="row-fluid">
                <div class="span5">
                    <div class="well">
                        <h3><?php echo $edit_profile ? 'Edytuj ustawienia pracownika' : 'Dodaj ustawienia pracownika'; ?></h3>
                        <?php echo form_open('haynelimits/save', ['class' => 'form-vertical', 'id' => 'hayneLeaveProfileForm']); ?>

                        <label for="employee_id">Pracownik</label>
                        <select name="employee_id" id="employee_id" class="input-xlarge" required>
                            <option value="">Wybierz pracownika</option>
                            <?php foreach ($employees as $employee) {
                                if ((int) $employee['active'] !== 1) {
                                    continue;
                                }
                                $employeeId = (int) $employee['id'];
                                ?>
                                <option value="<?php echo $employeeId; ?>" <?php echo $employeeId === $editingEmployee ? 'selected' : ''; ?>>
                                    <?php echo html_escape(trim($employee['firstname'] . ' ' . $employee['lastname'])); ?>
                                </option>
                            <?php } ?>
                        </select>

                        <label for="vacation_type_id">Rodzaj urlopu wypoczynkowego</label>
                        <select name="vacation_type_id" id="vacation_type_id" class="input-xlarge" required>
                            <?php foreach ($types as $type) {
                                $typeId = (int) $type['id'];
                                if ($typeId <= 0) {
                                    continue;
                                }
                                ?>
                                <option value="<?php echo $typeId; ?>" <?php echo $typeId === $editingType ? 'selected' : ''; ?>>
                                    <?php echo html_escape($type['name']); ?>
                                </option>
                            <?php } ?>
                        </select>
                        <span class="help-block">Ten typ jest wspólną pulą dla urlopu zwykłego i urlopu na żądanie.</span>

                        <label for="annual_days">Roczny wymiar</label>
                        <div class="input-append">
                            <input type="number" min="0" max="366" step="1" inputmode="numeric" class="input-small"
                                name="annual_days" id="annual_days" value="<?php echo $editingDays; ?>" required />
                            <span class="add-on">dni</span>
                        </div>
                        <span class="help-block">Wpisz gotowy wymiar, np. 20, 26 albo wartość proporcjonalną wyliczoną przez HR.</span>

                        <label class="checkbox" for="auto_renew">
                            <input type="checkbox" name="auto_renew" id="auto_renew" value="1" <?php echo $editingAutoRenew ? 'checked' : ''; ?> />
                            Automatycznie odnawiaj pulę w kolejnych latach i przenoś niewykorzystane dni
                        </label>

                        <div style="margin-top: 18px;">
                            <button type="submit" class="btn btn-primary">Zapisz limit</button>
                            <?php if ($edit_profile) { ?>
                                <a href="<?php echo base_url(); ?>haynelimits" class="btn">Anuluj edycję</a>
                            <?php } ?>
                        </div>
                        </form>
                    </div>
                </div>

                <div class="span7">
                    <div class="well">
                        <div class="row-fluid">
                            <div class="span7">
                                <h3 style="margin-top: 0;">Pule wypoczynkowe <?php echo $selected_year; ?></h3>
                                <p class="muted">Wykorzystanie jest przypisywane FIFO — najpierw najstarszy rok.</p>
                            </div>
                            <div class="span5">
                                <form method="get" action="<?php echo base_url(); ?>haynelimits" class="form-inline pull-right">
                                    <label for="year">Rok&nbsp;</label>
                                    <select name="year" id="year" class="input-small" onchange="this.form.submit()">
                                        <?php for ($year = $current_year - 5; $year <= $current_year + 1; $year++) { ?>
                                            <option value="<?php echo $year; ?>" <?php echo $year === $selected_year ? 'selected' : ''; ?>>
                                                <?php echo $year; ?>
                                            </option>
                                        <?php } ?>
                                    </select>
                                </form>
                            </div>
                        </div>

                        <?php if (empty($profiles)) { ?>
                            <div class="alert alert-info">Nie skonfigurowano jeszcze żadnego pracownika.</div>
                        <?php } else { ?>
                            <table class="table table-bordered table-hover" id="hayneLeaveProfiles">
                                <thead>
                                    <tr>
                                        <th>Pracownik</th>
                                        <th>Roczny</th>
                                        <th>Wykorzystano</th>
                                        <th>Pozostało</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($profiles as $profile) {
                                        $summary = $profile['summary'];
                                        ?>
                                        <tr data-employee-id="<?php echo (int) $profile['employee_id']; ?>"
                                            data-year="<?php echo $selected_year; ?>"
                                            data-granted="<?php echo (float) $summary['granted']; ?>"
                                            data-used="<?php echo (float) $summary['used']; ?>"
                                            data-remaining="<?php echo (float) $summary['remaining']; ?>">
                                            <td>
                                                <strong><?php echo html_escape(trim($profile['firstname'] . ' ' . $profile['lastname'])); ?></strong><br />
                                                <small class="muted"><?php echo html_escape($profile['vacation_type_name']); ?></small>
                                            </td>
                                            <td><?php echo (int) $profile['annual_days']; ?> dni</td>
                                            <td><?php echo (float) $summary['used']; ?> dni</td>
                                            <td><strong><?php echo (float) $summary['remaining']; ?> dni</strong></td>
                                            <td><a class="btn btn-small" href="<?php echo base_url(); ?>haynelimits?edit=<?php echo (int) $profile['employee_id']; ?>&amp;year=<?php echo $selected_year; ?>">Edytuj</a></td>
                                        </tr>
                                        <?php if (!empty($summary['rows'])) { ?>
                                            <tr class="hayne-pool-breakdown">
                                                <td colspan="5">
                                                    <table class="table table-condensed" style="margin-bottom: 0;">
                                                        <thead>
                                                            <tr>
                                                                <th>Źródło puli</th>
                                                                <th>Przyznane</th>
                                                                <th>Rozliczone FIFO</th>
                                                                <th>Pozostało</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($summary['rows'] as $pool) {
                                                                $sourceYear = (int) $pool['source_year'];
                                                                $label = $sourceYear < $selected_year
                                                                    ? 'Zaległy z ' . $sourceYear
                                                                    : 'Bieżący ' . $sourceYear;
                                                                ?>
                                                                <tr data-source-year="<?php echo $sourceYear; ?>"
                                                                    data-kind="<?php echo html_escape($pool['kind']); ?>"
                                                                    data-granted="<?php echo (float) $pool['granted']; ?>"
                                                                    data-used="<?php echo (float) $pool['used']; ?>"
                                                                    data-remaining="<?php echo (float) $pool['remaining']; ?>">
                                                                    <td><?php echo $label; ?></td>
                                                                    <td><?php echo (float) $pool['granted']; ?></td>
                                                                    <td><?php echo (float) $pool['used']; ?></td>
                                                                    <td><strong><?php echo (float) $pool['remaining']; ?></strong></td>
                                                                </tr>
                                                            <?php } ?>
                                                        </tbody>
                                                    </table>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                        <?php if ((float) $summary['unallocated_usage'] > 0) { ?>
                                            <tr>
                                                <td colspan="5">
                                                    <div class="alert alert-error" style="margin-bottom: 0;">
                                                        Wykorzystanie przekracza pule zarządzane przez HAYNE o <?php echo (float) $summary['unallocated_usage']; ?> dni.
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    <?php } ?>
                                </tbody>
                            </table>
                        <?php } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script type="text/javascript">
    var hayneWrap = document.getElementById('wrap');
    if (hayneWrap) {
        hayneWrap.setAttribute('data-hayne-topbar-title', 'Limity urlopowe');
    }
</script>