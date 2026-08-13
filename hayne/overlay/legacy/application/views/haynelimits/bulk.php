<?php
$profileByEmployee = [];
foreach ($profiles as $profile) {
    $profileByEmployee[(int) $profile['employee_id']] = $profile;
}

$activeEmployees = [];
foreach ($employees as $employee) {
    if ((int) $employee['active'] === 1) {
        $activeEmployees[] = $employee;
    }
}
usort($activeEmployees, static function (array $a, array $b): int {
    return strcasecmp(trim($a['lastname'] . ' ' . $a['firstname']), trim($b['lastname'] . ' ' . $b['firstname']));
});

$configuredCount = 0;
foreach ($activeEmployees as $employee) {
    if (isset($profileByEmployee[(int) $employee['id']])) {
        $configuredCount++;
    }
}
$unconfiguredCount = count($activeEmployees) - $configuredCount;
$currentYear = (int) date('Y');
$bulkEditable = (int) $selected_year === $currentYear;
?>

<section class="hayne-annual-limits" id="hayneAnnualLimits" data-hayne-bulk="v2" data-bulk-editable="<?php echo $bulkEditable ? '1' : '0'; ?>" aria-labelledby="hayneAnnualLimitsTitle">
    <div class="hayne-kpi-strip" aria-label="Stan konfiguracji limitów">
        <button type="button" class="hayne-kpi-tile hayne-kpi-tile--attention" data-hayne-kpi-filter="missing">
            <strong><?php echo $unconfiguredCount; ?></strong><span>Bez limitu</span><small>Wymaga działania</small>
        </button>
        <button type="button" class="hayne-kpi-tile" data-hayne-kpi-filter="configured">
            <strong><?php echo $configuredCount; ?></strong><span>Skonfigurowane</span><small>Aktywne profile</small>
        </button>
        <button type="button" class="hayne-kpi-tile" data-hayne-kpi-filter="all">
            <strong><?php echo count($activeEmployees); ?></strong><span>Pracownicy</span><small>Aktywni</small>
        </button>
    </div>

    <?php if (!$bulkEditable) { ?>
        <div class="alert alert-info hayne-bulk-year-warning">
            Podglądasz rok <strong><?php echo (int) $selected_year; ?></strong>. Grupowe przydzielanie jest dostępne wyłącznie dla bieżącego roku <strong><?php echo $currentYear; ?></strong>.
        </div>
    <?php } ?>

    <?php echo form_open('haynelimits/save-bulk', ['class' => 'hayne-bulk-form', 'id' => 'hayneBulkLeaveForm']); ?>
        <input type="hidden" name="year" value="<?php echo (int) $selected_year; ?>" />

        <div class="hayne-bulk-workspace">
            <section class="hayne-bulk-main" aria-labelledby="hayneAnnualLimitsTitle">
                <div class="hayne-workspace-head">
                    <div>
                        <h2 id="hayneAnnualLimitsTitle">Pracownicy</h2>
                        <p>Zaznacz osoby, którym chcesz przydzielić lub zaktualizować roczny limit.</p>
                    </div>
                    <button type="button" class="btn" id="hayneSelectVisible">Zaznacz widocznych</button>
                </div>

                <div class="hayne-employee-toolbar">
                    <input type="search" id="hayneEmployeeSearch" aria-label="Szukaj pracownika" placeholder="Szukaj pracownika…" autocomplete="off" />
                    <div class="hayne-filter-group" role="group" aria-label="Filtr pracowników">
                        <button type="button" class="btn hayne-filter is-active" data-hayne-filter="all" aria-pressed="true">Wszyscy <span><?php echo count($activeEmployees); ?></span></button>
                        <button type="button" class="btn hayne-filter" data-hayne-filter="missing" aria-pressed="false">Bez limitu <span><?php echo $unconfiguredCount; ?></span></button>
                        <button type="button" class="btn hayne-filter" data-hayne-filter="configured" aria-pressed="false">Z limitem <span><?php echo $configuredCount; ?></span></button>
                    </div>
                </div>

                <div class="hayne-employee-table-wrap">
                    <table class="table table-hover hayne-employee-table" id="hayneEmployeeLimitsTable">
                        <thead>
                            <tr>
                                <th class="hayne-check-col"><input type="checkbox" id="hayneSelectAllVisible" aria-label="Zaznacz wszystkich widocznych pracowników" /></th>
                                <th>Pracownik</th>
                                <th>Status</th>
                                <th>Roczny</th>
                                <th>Wykorzystano</th>
                                <th>Pozostało</th>
                                <th>Akcje</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activeEmployees as $employee) {
                                $employeeId = (int) $employee['id'];
                                $profile = $profileByEmployee[$employeeId] ?? NULL;
                                $configured = $profile !== NULL;
                                $summary = $configured ? $profile['summary'] : NULL;
                                $fullName = trim($employee['firstname'] . ' ' . $employee['lastname']); ?>
                                <tr data-hayne-employee-row data-name="<?php echo html_escape($fullName); ?>" data-configured="<?php echo $configured ? '1' : '0'; ?>" data-type-id="<?php echo $configured ? (int) $profile['vacation_type_id'] : 0; ?>">
                                    <td class="hayne-check-col"><input type="checkbox" class="hayne-employee-checkbox" name="employee_ids[]" value="<?php echo $employeeId; ?>" aria-label="Wybierz <?php echo html_escape($fullName); ?>" /></td>
                                    <td><strong><?php echo html_escape($fullName); ?></strong><?php if ($configured) { ?><small><?php echo html_escape($profile['vacation_type_name']); ?></small><?php } ?></td>
                                    <td><?php if ($configured) { ?><span class="hayne-limit-status hayne-limit-status--configured">Skonfigurowany</span><?php } else { ?><span class="hayne-limit-status hayne-limit-status--missing">Brak limitu</span><?php } ?></td>
                                    <td><?php echo $configured ? (int) $profile['annual_days'] . ' dni' : '—'; ?></td>
                                    <td><?php echo $configured ? (float) $summary['used'] . ' dni' : '—'; ?></td>
                                    <td><?php echo $configured ? '<strong>' . (float) $summary['remaining'] . ' dni</strong>' : '—'; ?></td>
                                    <td class="hayne-employee-actions"><a class="btn btn-small" href="<?php echo base_url(); ?>hayneusage/edit/<?php echo $employeeId; ?>?year=<?php echo (int) $selected_year; ?>">Koryguj wykorzystanie</a></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                    <div class="hayne-empty-filter" id="hayneEmployeeFilterEmpty" hidden>Brak pracowników pasujących do filtra.</div>
                </div>
            </section>

            <aside class="hayne-bulk-side" aria-label="Ustawienia przydziału grupowego">
                <div class="hayne-bulk-side__head">
                    <strong>Ustaw limit</strong>
                    <span id="hayneSelectedCount" aria-live="polite">0 zaznaczonych</span>
                </div>

                <div class="hayne-bulk-field hayne-bulk-field--type">
                    <label for="bulk_vacation_type_id">Rodzaj urlopu</label>
                    <select name="vacation_type_id" id="bulk_vacation_type_id" required>
                        <?php foreach ($types as $type) {
                            $typeId = (int) $type['id'];
                            if ($typeId <= 0) { continue; } ?>
                            <option value="<?php echo $typeId; ?>" <?php echo $typeId === (int) $default_type ? 'selected' : ''; ?>><?php echo html_escape($type['name']); ?></option>
                        <?php } ?>
                    </select>
                </div>

                <fieldset class="hayne-side-group">
                    <legend>Roczny wymiar</legend>
                    <label class="hayne-choice-card"><input type="radio" name="hayne_days_mode" value="26" data-hayne-days="26" checked /><span><strong>26 dni</strong><small>Standardowy wymiar urlopu</small></span></label>
                    <label class="hayne-choice-card"><input type="radio" name="hayne_days_mode" value="20" data-hayne-days="20" /><span><strong>20 dni</strong><small>Dla pracowników z krótszym stażem</small></span></label>
                    <label class="hayne-choice-card"><input type="radio" name="hayne_days_mode" value="custom" data-hayne-days="custom" /><span><strong>Własny limit</strong><small>Wpisz pełną liczbę dni</small></span></label>
                    <div class="hayne-days-custom" id="hayneCustomDaysWrap">
                        <label for="bulk_annual_days">Liczba dni</label>
                        <input type="number" name="annual_days" min="0" max="366" step="1" inputmode="numeric" id="bulk_annual_days" value="26" required />
                    </div>
                </fieldset>

                <fieldset class="hayne-side-group">
                    <legend>Sposób zastosowania</legend>
                    <label class="hayne-choice-card"><input type="radio" name="hayne_overwrite_mode" value="skip" checked /><span><strong>Uzupełnij tylko brakujące</strong><small>Istniejące limity pozostaną bez zmian.</small></span></label>
                    <label class="hayne-choice-card hayne-choice-card--warning"><input type="radio" name="hayne_overwrite_mode" value="overwrite" /><span><strong>Nadpisz istniejące</strong><small>Zmień także zgodne istniejące profile.</small></span></label>
                    <input type="checkbox" name="overwrite_existing" id="bulk_overwrite_existing" value="1" hidden />
                </fieldset>

                <label class="checkbox hayne-auto-renew" for="bulk_auto_renew">
                    <input type="checkbox" name="auto_renew" id="bulk_auto_renew" value="1" checked />
                    <span><strong>Odnawiaj automatycznie</strong><small>Twórz pulę w kolejnych latach i przenoś niewykorzystane dni.</small></span>
                </label>

                <div class="hayne-bulk-summary">
                    <strong>Podsumowanie</strong>
                    <span id="hayneBulkSafetyText"><?php echo $bulkEditable ? 'Istniejące konfiguracje zostaną pominięte.' : 'Zapis jest wyłączony dla historycznego widoku.'; ?></span>
                </div>

                <button type="submit" class="btn btn-primary hayne-bulk-submit" id="hayneBulkSubmit" <?php echo $bulkEditable ? '' : 'disabled'; ?>>Przydziel limit</button>
            </aside>
        </div>
    </form>
</section>
