<?php
/**
 * HAYNE leave limits administration.
 */
$editingEmployee = $edit_profile ? (int) $edit_profile['employee_id'] : 0;
$editingType = $edit_profile ? (int) $edit_profile['vacation_type_id'] : (int) $default_type;
$editingDays = $edit_profile ? (int) $edit_profile['annual_days'] : 26;
$editingAutoRenew = $edit_profile ? ((int) $edit_profile['auto_renew'] === 1) : TRUE;
$defaultTab = 'allocation';

$caregiverEnabled = !empty($caregiver_policy) && (int) $caregiver_policy['enabled'] === 1;
$forceMajeureEnabled = !empty($force_majeure_policy) && (int) $force_majeure_policy['enabled'] === 1;
$childcareEnabled = !empty($childcare_policy) && (int) $childcare_policy['enabled'] === 1;
$occasionEnabled = !empty($occasion_policy) && (int) $occasion_policy['enabled'] === 1;
$officialSummonsEnabled = !empty($official_summons_policy) && (int) $official_summons_policy['enabled'] === 1;
?>

<main class="hayne-limits-page" data-hayne-view="leave-limits-v3" data-hayne-default-tab="<?php echo $defaultTab; ?>">
    <div class="row-fluid">
        <div class="span12">
            <header class="hayne-limits-pagehead">
                <div>
                    <h1>Limity urlopowe</h1>
                    <p>Zarządzaj limitami pracowników i konfiguruj uprawnienia ustawowe.</p>
                </div>
                <form method="get" action="<?php echo base_url(); ?>haynelimits" class="hayne-year-switcher">
                    <label for="year">Rok</label>
                    <select name="year" id="year" onchange="this.form.submit()">
                        <?php for ($year = $current_year - 5; $year <= $current_year + 1; $year++) { ?>
                            <option value="<?php echo $year; ?>" <?php echo $year === $selected_year ? 'selected' : ''; ?>><?php echo $year; ?></option>
                        <?php } ?>
                    </select>
                </form>
            </header>

            <?php echo $flash_partial_view; ?>

            <nav class="hayne-limits-tabs" aria-label="Sekcje zarządzania limitami" data-hayne-limits-tabs>
                <button type="button" class="hayne-limits-tab is-active" data-hayne-tab-target="allocation" aria-selected="true">Limity pracowników</button>
                <button type="button" class="hayne-limits-tab" data-hayne-tab-target="statutory" aria-selected="false">Uprawnienia ustawowe</button>
            </nav>

            <section class="hayne-limits-tabpanel is-active" data-hayne-tab-panel="allocation">
                <?php if ($edit_profile) { ?>
                    <section class="hayne-employee-edit-card hayne-single-edit" id="hayneSingleEdit">
                        <div class="hayne-employee-edit-card__head">
                            <div>
                                <span class="hayne-limits-eyebrow">Edycja limitu</span>
                                <h3>Edytuj ustawienia pracownika</h3>
                                <p>Zmiana dotyczy pojedynczego pracownika. Przydziały grupowe wykonuj bezpośrednio z tabeli poniżej.</p>
                            </div>
                        </div>

                        <?php echo form_open('haynelimits/save', ['class' => 'form-vertical hayne-employee-edit-form', 'id' => 'hayneLeaveProfileForm']); ?>
                            <div class="hayne-edit-grid">
                                <div>
                                    <label for="employee_id">Pracownik</label>
                                    <select name="employee_id" id="employee_id" required>
                                        <?php foreach ($employees as $employee) {
                                            if ((int) $employee['active'] !== 1) {
                                                continue;
                                            }
                                            $employeeId = (int) $employee['id']; ?>
                                            <option value="<?php echo $employeeId; ?>" <?php echo $employeeId === $editingEmployee ? 'selected' : ''; ?>><?php echo html_escape(trim($employee['firstname'] . ' ' . $employee['lastname'])); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>

                                <div>
                                    <label for="vacation_type_id">Rodzaj urlopu wypoczynkowego</label>
                                    <select name="vacation_type_id" id="vacation_type_id" required>
                                        <?php foreach ($types as $type) {
                                            $typeId = (int) $type['id'];
                                            if ($typeId <= 0) {
                                                continue;
                                            } ?>
                                            <option value="<?php echo $typeId; ?>" <?php echo $typeId === $editingType ? 'selected' : ''; ?>><?php echo html_escape($type['name']); ?></option>
                                        <?php } ?>
                                    </select>
                                </div>

                                <div>
                                    <label for="annual_days">Roczny wymiar</label>
                                    <div class="hayne-inline-unit">
                                        <input type="number" min="0" max="366" step="1" inputmode="numeric" name="annual_days" id="annual_days" value="<?php echo $editingDays; ?>" required />
                                        <span>dni</span>
                                    </div>
                                </div>

                                <label class="checkbox hayne-edit-renew" for="auto_renew">
                                    <input type="checkbox" name="auto_renew" id="auto_renew" value="1" <?php echo $editingAutoRenew ? 'checked' : ''; ?> />
                                    <span><strong>Automatyczne odnowienie</strong><small>Odnawiaj pulę w kolejnych latach i przenoś niewykorzystane dni.</small></span>
                                </label>
                            </div>

                            <div class="hayne-edit-actions">
                                <a href="<?php echo base_url(); ?>haynelimits?year=<?php echo $selected_year; ?>" class="btn">Anuluj</a>
                                <button type="submit" class="btn btn-primary">Zapisz zmiany</button>
                            </div>
                        </form>
                    </section>
                <?php } ?>

                <?php $this->load->view('haynelimits/bulk', [
                    'employees' => $employees,
                    'profiles' => $profiles,
                    'types' => $types,
                    'default_type' => $default_type,
                    'selected_year' => $selected_year,
                ]); ?>
            </section>

            <section class="hayne-limits-tabpanel" data-hayne-tab-panel="statutory" hidden>
                <div class="hayne-section-intro">
                    <div>
                        <span class="hayne-limits-eyebrow">Polityki ustawowe</span>
                        <h2>Uprawnienia ustawowe</h2>
                        <p>Na co dzień widzisz tylko stan polityki. Szczegóły i konfiguracja są dostępne po rozwinięciu konkretnej pozycji.</p>
                    </div>
                </div>

                <div class="hayne-policy-list">
                    <details class="hayne-policy-disclosure">
                        <summary>
                            <span class="hayne-policy-summary__copy"><strong>Urlop opiekuńczy</strong><small>5 dni rocznie</small></span>
                            <span class="hayne-policy-summary__state <?php echo $caregiverEnabled ? 'is-enabled' : 'is-disabled'; ?>"><?php echo $caregiverEnabled ? 'Włączone' : 'Wyłączone'; ?></span>
                        </summary>
                        <div class="hayne-policy-disclosure__body">
                            <?php $this->load->view('haynelimits/caregiver', ['caregiver_policy' => $caregiver_policy, 'types' => $types]); ?>
                        </div>
                    </details>

                    <details class="hayne-policy-disclosure">
                        <summary>
                            <span class="hayne-policy-summary__copy"><strong>Siła wyższa</strong><small>2 dni rocznie</small></span>
                            <span class="hayne-policy-summary__state <?php echo $forceMajeureEnabled ? 'is-enabled' : 'is-disabled'; ?>"><?php echo $forceMajeureEnabled ? 'Włączone' : 'Wyłączone'; ?></span>
                        </summary>
                        <div class="hayne-policy-disclosure__body">
                            <?php $this->load->view('haynelimits/force_majeure', ['force_majeure_policy' => $force_majeure_policy, 'types' => $types]); ?>
                        </div>
                    </details>

                    <details class="hayne-policy-disclosure">
                        <summary>
                            <span class="hayne-policy-summary__copy"><strong>Opieka nad dzieckiem do 14 lat</strong><small>Indywidualna pula 0 / 1 / 2 dni</small></span>
                            <span class="hayne-policy-summary__state <?php echo $childcareEnabled ? 'is-enabled' : 'is-disabled'; ?>"><?php echo $childcareEnabled ? 'Włączone' : 'Wyłączone'; ?></span>
                        </summary>
                        <div class="hayne-policy-disclosure__body">
                            <?php $this->load->view('haynelimits/childcare', [
                                'childcare_policy' => $childcare_policy,
                                'childcare_allocations' => $childcare_allocations,
                                'employees' => $employees,
                                'types' => $types,
                                'selected_year' => $selected_year,
                                'current_year' => $current_year,
                            ]); ?>
                        </div>
                    </details>

                    <details class="hayne-policy-disclosure">
                        <summary>
                            <span class="hayne-policy-summary__copy"><strong>Urlop okolicznościowy</strong><small>Limit zależny od zdarzenia</small></span>
                            <span class="hayne-policy-summary__state <?php echo $occasionEnabled ? 'is-enabled' : 'is-disabled'; ?>"><?php echo $occasionEnabled ? 'Włączone' : 'Wyłączone'; ?></span>
                        </summary>
                        <div class="hayne-policy-disclosure__body">
                            <?php $this->load->view('haynelimits/occasion', ['occasion_policy' => $occasion_policy, 'types' => $types]); ?>
                        </div>
                    </details>

                    <details class="hayne-policy-disclosure">
                        <summary>
                            <span class="hayne-policy-summary__copy"><strong>Wezwanie sądu / urzędu / innego organu</strong><small>Bez rocznej puli dni</small></span>
                            <span class="hayne-policy-summary__state <?php echo $officialSummonsEnabled ? 'is-enabled' : 'is-disabled'; ?>"><?php echo $officialSummonsEnabled ? 'Włączone' : 'Wyłączone'; ?></span>
                        </summary>
                        <div class="hayne-policy-disclosure__body">
                            <?php $this->load->view('haynelimits/official_summons', ['official_summons_policy' => $official_summons_policy, 'types' => $types]); ?>
                        </div>
                    </details>

                    <details class="hayne-policy-disclosure">
                        <summary>
                            <span class="hayne-policy-summary__copy"><strong>Dzień wolny za święto</strong><small>1 dzień za konkretny grant HR</small></span>
                            <span class="hayne-policy-summary__state is-neutral">Granty</span>
                        </summary>
                        <div class="hayne-policy-disclosure__body">
                            <div class="well hayne-statutory-policy hayne-holiday-policy-card" data-hayne-policy="holiday_compensation">
                                <div class="row-fluid">
                                    <div class="span8">
                                        <h3 style="margin-top: 0;">Dzień wolny za święto</h3>
                                        <p><strong>1 dzień za konkretny grant HR</strong>, ważny wyłącznie w przypisanym okresie rozliczeniowym.</p>
                                        <p class="muted" style="margin-bottom: 0;">Nie jest częścią puli 20/26, nie korzysta z FIFO i nie przechodzi na kolejny okres rozliczeniowy.</p>
                                    </div>
                                    <div class="span4 hayne-policy-actions">
                                        <a class="btn btn-primary" href="<?php echo base_url(); ?>hayneholidays">Zarządzaj dniami za święta</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </details>
                </div>
            </section>
        </div>
    </div>
</main>

<script type="text/javascript">
    var hayneWrap = document.getElementById('wrap');
    if (hayneWrap) {
        hayneWrap.setAttribute('data-hayne-topbar-title', 'Limity urlopowe');
    }
</script>