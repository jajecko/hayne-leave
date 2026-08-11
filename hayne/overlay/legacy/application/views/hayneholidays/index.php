<?php
$policyEnabled = !empty($policy) && (int) $policy['enabled'] === 1;
$typeName = !empty($managed_type['name']) ? (string) $managed_type['name'] : 'Dzień wolny za święto';
?>

<main class="hayne-holidays-page" data-hayne-view="holiday-compensation-v1">
    <div class="row-fluid">
        <div class="span12">
            <div class="page-header">
                <h1>Dzień wolny za święto</h1>
                <p class="muted">Ręcznie przydzielane dni wolne wynikające z obniżenia wymiaru czasu pracy w konkretnym okresie rozliczeniowym.</p>
            </div>

            <?php echo $flash_partial_view; ?>

            <div class="alert alert-info">
                HAYNE nie ustala automatycznie, komu należy przyznać odrębny dzień wolny. HR przyznaje grant tylko wtedy,
                gdy wynika to z harmonogramu pracownika. Grant nie jest urlopem wypoczynkowym i nie przechodzi na kolejny okres rozliczeniowy.
            </div>

            <div class="row-fluid">
                <div class="span5">
                    <div class="well" data-hayne-holiday-policy="v1">
                        <h3 style="margin-top: 0;">Konfiguracja</h3>
                        <p><strong>Rodzaj nieobecności:</strong> <?php echo html_escape($typeName); ?> (DWS)</p>
                        <p class="muted">Jeżeli typu brakowało, HAYNE utworzył go automatycznie z kolejnym wolnym ID.</p>
                        <?php echo form_open('hayneholidays/savePolicy', ['class' => 'form-vertical']); ?>
                            <input type="hidden" name="holiday_compensation_type_id" value="<?php echo (int) $managed_type_id; ?>" />
                            <label class="checkbox" for="holiday_compensation_enabled">
                                <input type="checkbox" name="holiday_compensation_enabled" id="holiday_compensation_enabled"
                                    value="1" <?php echo $policyEnabled ? 'checked' : ''; ?> />
                                Aktywuj obsługę dnia wolnego za święto
                            </label>
                            <button type="submit" class="btn btn-primary">Zapisz ustawienia</button>
                            <a class="btn" href="<?php echo base_url(); ?>haynelimits">Wróć do limitów urlopowych</a>
                        </form>
                    </div>

                    <div class="well" data-hayne-holiday-grant-form="v1">
                        <h3 style="margin-top: 0;">Przyznaj 1 dzień</h3>
                        <?php echo form_open('hayneholidays/saveGrant', ['class' => 'form-vertical']); ?>
                            <label for="employee_id">Pracownik</label>
                            <select name="employee_id" id="employee_id" class="input-xlarge" required>
                                <option value="">Wybierz pracownika</option>
                                <?php foreach ($employees as $employee) {
                                    if ((int) $employee['active'] !== 1) {
                                        continue;
                                    }
                                    ?>
                                    <option value="<?php echo (int) $employee['id']; ?>">
                                        <?php echo html_escape(trim($employee['firstname'] . ' ' . $employee['lastname'])); ?>
                                    </option>
                                <?php } ?>
                            </select>

                            <label for="source_holiday_date">Data święta źródłowego</label>
                            <input type="date" name="source_holiday_date" id="source_holiday_date" required />

                            <label for="period_start">Początek okresu rozliczeniowego</label>
                            <input type="date" name="period_start" id="period_start" required />

                            <label for="period_end">Koniec okresu rozliczeniowego</label>
                            <input type="date" name="period_end" id="period_end" required />
                            <span class="help-block">Dzień wolny będzie można wskazać tylko pomiędzy tymi datami.</span>

                            <button type="submit" class="btn btn-primary">Przyznaj dzień wolny</button>
                        </form>
                    </div>
                </div>

                <div class="span7">
                    <div class="well">
                        <h3 style="margin-top: 0;">Przyznane dni</h3>
                        <?php if (empty($grants)) { ?>
                            <div class="alert alert-info">Nie przyznano jeszcze żadnego dnia wolnego za święto.</div>
                        <?php } else { ?>
                            <table class="table table-bordered table-hover" id="hayneHolidayCompensationGrants">
                                <thead>
                                    <tr>
                                        <th>Pracownik</th>
                                        <th>Święto</th>
                                        <th>Okres rozliczeniowy</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($grants as $grant) { ?>
                                        <tr data-grant-id="<?php echo (int) $grant['id']; ?>"
                                            data-source-holiday="<?php echo html_escape($grant['source_holiday_date']); ?>"
                                            data-period-start="<?php echo html_escape($grant['period_start']); ?>"
                                            data-period-end="<?php echo html_escape($grant['period_end']); ?>"
                                            data-reserved="<?php echo (int) $grant['reserved']; ?>">
                                            <td><?php echo html_escape(trim(($grant['firstname'] ?? '') . ' ' . ($grant['lastname'] ?? ''))); ?></td>
                                            <td><?php echo html_escape($grant['source_holiday_date']); ?></td>
                                            <td><?php echo html_escape($grant['period_start']); ?> – <?php echo html_escape($grant['period_end']); ?></td>
                                            <td><?php echo (int) $grant['reserved'] > 0 ? '<strong>Wykorzystany / zarezerwowany</strong>' : 'Dostępny'; ?></td>
                                        </tr>
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
        hayneWrap.setAttribute('data-hayne-topbar-title', 'Dzień wolny za święto');
    }
</script>
