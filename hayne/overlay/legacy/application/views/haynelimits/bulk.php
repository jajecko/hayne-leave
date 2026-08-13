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

<section class="hayne-limits-card hayne-annual-limits" id="hayneAnnualLimits" data-hayne-bulk="v1" data-bulk-editable="<?php echo $bulkEditable ? '1' : '0'; ?>" aria-labelledby="hayneAnnualLimitsTitle">
    <div class="hayne-limits-card__head">
        <div>
            <span class="hayne-limits-eyebrow">Urlop wypoczynkowy</span>
            <h2 id="hayneAnnualLimitsTitle">Przydziel limit pracownikom — <?php echo $currentYear; ?></h2>
            <p>Wybierz osoby, ustaw wymiar i zapisz jednym ruchem. Istniejące konfiguracje są domyślnie chronione przed nadpisaniem.</p>
        </div>
        <div class="hayne-limits-stats" aria-label="Stan konfiguracji">
            <span><strong><?php echo count($activeEmployees); ?></strong> aktywnych</span>
            <span><strong><?php echo $configuredCount; ?></strong> skonfigurowanych</span>
            <span><strong><?php echo $unconfiguredCount; ?></strong> bez limitu</span>
        </div>
    </div>

    <?php if (!$bulkEditable) { ?>
        <div class="alert alert-info hayne-bulk-year-warning">
            Podglądasz rok <strong><?php echo (int) $selected_year; ?></strong>. Grupowe przydzielanie zmienia bieżący profil, dlatego zapis jest dostępny tylko w widoku roku <strong><?php echo $currentYear; ?></strong>.
            <a href="<?php echo base_url(); ?>haynelimits?year=<?php echo $currentYear; ?>#hayneAnnualLimits">Przejdź do <?php echo $currentYear; ?></a>.
        </div>
    <?php } ?>

    <?php echo form_open('haynelimits/save-bulk', ['class' => 'hayne-bulk-form', 'id' => 'hayneBulkLeaveForm']); ?>
        <input type="hidden" name="year" value="<?php echo (int) $selected_year; ?>" />

        <div class="hayne-bulk-settings">
            <div class="hayne-bulk-field hayne-bulk-field--type">
                <label for="bulk_vacation_type_id">Rodzaj urlopu</label>
                <select name="vacation_type_id" id="bulk_vacation_type_id" required>
                    <?php foreach ($types as $type) {
                        $typeId = (int) $type['id'];
                        if ($typeId <= 0) { continue; } ?>
                        <option value="<?php echo $typeId; ?>" <?php echo $typeId === (int) $default_type ? 'selected' : ''; ?>><?php echo html_escape($type['name']); ?></option>
                    <?php } ?>
                </select>
                <span class="help-block">Ta sama pula obsługuje urlop zwykły i urlop na żądanie.</span>
            </div>

            <div class="hayne-bulk-field hayne-bulk-field--days">
                <span class="hayne-field-label" id="hayneAnnualDaysLabel">Roczny wymiar</span>
                <input type="hidden" name="annual_days" id="bulk_annual_days" value="26" />
                <div class="hayne-days-presets" role="group" aria-labelledby="hayneAnnualDaysLabel">
                    <button type="button" class="btn hayne-days-preset" data-hayne-days="20" aria-pressed="false">20 dni</button>
                    <button type="button" class="btn hayne-days-preset is-active" data-hayne-days="26" aria-pressed="true">26 dni</button>
                    <button type="button" class="btn hayne-days-preset" data-hayne-days="custom" aria-pressed="false">Inny wymiar</button>
                </div>
                <div class="hayne-days-custom" id="hayneCustomDaysWrap" hidden>
                    <label for="bulk_annual_days_custom">Liczba dni</label>
                    <input type="number" min="0" max="366" step="1" inputmode="numeric" id="bulk_annual_days_custom" value="26" />
                </div>
                <span class="help-block">Wybierz standardowy wymiar 20 lub 26 dni albo wpisz inną pełnodniową wartość wyliczoną przez HR.</span>
            </div>

            <div class="hayne-bulk-options">
                <label class="checkbox hayne-option-row" for="bulk_auto_renew">
                    <input type="checkbox" name="auto_renew" id="bulk_auto_renew" value="1" checked />
                    <span><strong>Odnawiaj automatycznie</strong><small>Twórz pulę w kolejnych latach i przenoś niewykorzystane dni.</small></span>
                </label>
                <label class="checkbox hayne-option-row hayne-option-row--warning" for="bulk_overwrite_existing">
                    <input type="checkbox" name="overwrite_existing" id="bulk_overwrite_existing" value="1" />
                    <span><strong>Aktualizuj także osoby z istniejącym limitem</strong><small>Bez tej opcji skonfigurowane osoby zostaną pominięte. Innego rodzaju urlopu nigdy nie zmieniamy grupowo.</small></span>
                </label>
            </div>
        </div>

        <div class="hayne-employee-picker">
            <div class="hayne-employee-toolbar">
                <input type="search" id="hayneEmployeeSearch" aria-label="Szukaj pracownika" placeholder="Szukaj pracownika…" autocomplete="off" />
                <div class="hayne-filter-group" role="group" aria-label="Filtr pracowników">
                    <button type="button" class="btn hayne-filter is-active" data-hayne-filter="all" aria-pressed="true">Wszyscy</button>
                    <button type="button" class="btn hayne-filter" data-hayne-filter="missing" aria-pressed="false">Bez limitu <span><?php echo $unconfiguredCount; ?></span></button>
                    <button type="button" class="btn hayne-filter" data-hayne-filter="configured" aria-pressed="false">Skonfigurowani <span><?php echo $configuredCount; ?></span></button>
                </div>
                <button type="button" class="btn" id="hayneSelectVisible">Zaznacz widocznych</button>
            </div>

            <div class="hayne-employee-table-wrap">
                <table class="table table-hover hayne-employee-table" id="hayneEmployeeLimitsTable">
                    <thead>
                        <tr>
                            <th class="hayne-check-col"><input type="checkbox" id="hayneSelectAllVisible" aria-label="Zaznacz wszystkich widocznych pracowników" /></th>
                            <th>Pracownik</th><th>Status</th><th>Roczny</th><th>Wykorzystano <?php echo (int) $selected_year; ?></th><th>Pozostało <?php echo (int) $selected_year; ?></th><th>Akcje</th>
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
        </div>

        <div class="hayne-bulk-actionbar">
            <div><strong id="hayneSelectedCount" aria-live="polite">0 zaznaczonych</strong><span id="hayneBulkSafetyText"><?php echo $bulkEditable ? 'Istniejące konfiguracje zostaną pominięte.' : 'Zapis jest wyłączony dla historycznego widoku.'; ?></span></div>
            <button type="submit" class="btn btn-primary" id="hayneBulkSubmit" <?php echo $bulkEditable ? '' : 'disabled'; ?>>Przydziel limit</button>
        </div>
    </form>
</section>
