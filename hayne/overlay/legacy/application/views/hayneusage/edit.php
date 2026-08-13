<?php
$employeeId = (int) $employee['id'];
$fullName = trim($employee['firstname'] . ' ' . $employee['lastname']);
$manualVacation = (int) $corrections['vacation_regular'] + (int) $corrections['on_demand'];
$manualOnDemand = (int) $corrections['on_demand'];
$manualCaregiver = (int) $corrections['caregiver'];
$manualForce = (int) $corrections['force_majeure'];
$manualChildcare = (int) $corrections['childcare'];

$vacationActual = $vacation_summary === NULL ? 0 : max(0, (float) $vacation_summary['used'] - $manualVacation);
$onDemandActual = $on_demand_summary === NULL ? 0 : max(0, (float) $on_demand_summary['used'] - $manualOnDemand);
$caregiverActual = $caregiver_summary === NULL ? 0 : max(0, (float) $caregiver_summary['used'] - $manualCaregiver);
$forceActual = $force_majeure_summary === NULL ? 0 : max(0, (float) $force_majeure_summary['used'] - $manualForce);
$childcareActual = $childcare_summary === NULL ? 0 : max(0, (float) $childcare_summary['used'] - $manualChildcare);

$formatDays = static function ($value): string {
    $number = (float) $value;
    return floor($number) == $number
        ? (string) (int) $number
        : rtrim(rtrim(number_format($number, 2, ',', ''), '0'), ',');
};

$historyLabels = [
    'vacation_regular' => 'Urlop wypoczynkowy',
    'on_demand' => 'Urlop na żądanie',
    'caregiver' => 'Urlop opiekuńczy',
    'force_majeure' => 'Siła wyższa',
    'childcare' => 'Opieka nad dzieckiem',
    'occasion' => 'Urlop okolicznościowy',
    'holiday_compensation' => 'Dzień wolny za święto',
];
?>

<main class="hayne-limits-page hayne-usage-page" data-hayne-view="leave-usage-correction-v1">
    <div class="row-fluid">
        <div class="span12">
            <div class="hayne-usage-header">
                <div>
                    <a class="hayne-usage-back" href="<?php echo base_url(); ?>haynelimits?year=<?php echo (int) $year; ?>#hayneAnnualLimits">← Limity urlopowe</a>
                    <h1>Korekta wykorzystania</h1>
                    <p><strong><?php echo html_escape($fullName); ?></strong> · <?php echo (int) $year; ?></p>
                </div>
                <span class="hayne-usage-year"><?php echo (int) $year; ?></span>
            </div>

            <?php echo $flash_partial_view; ?>

            <?php if (!$editable) { ?>
                <div class="alert alert-info">
                    To jest podgląd historyczny. Korekty można zapisywać wyłącznie dla bieżącego roku <?php echo (int) $current_year; ?>.
                </div>
            <?php } ?>

            <div class="hayne-usage-callout">
                <strong>Wpisuj faktycznie wykorzystane dni z dokumentacji papierowej.</strong>
                <span>Nie odtwarzamy tu dat wniosków. Korekta zmienia saldo i limity, ale nie pojawia się jako wniosek ani nieobecność w kalendarzu.</span>
            </div>

            <?php echo form_open('hayneusage/save/' . $employeeId, ['class' => 'hayne-usage-form', 'id' => 'hayneUsageCorrectionForm']); ?>
                <input type="hidden" name="year" value="<?php echo (int) $year; ?>" />

                <section class="hayne-usage-card" aria-labelledby="hayneVacationCorrectionTitle">
                    <div class="hayne-usage-card__head">
                        <div>
                            <span class="hayne-limits-eyebrow">Główna pula</span>
                            <h2 id="hayneVacationCorrectionTitle">Urlop wypoczynkowy</h2>
                            <p>„Na żądanie” jest częścią wykorzystanego urlopu wypoczynkowego i nie odejmuje się drugi raz.</p>
                        </div>
                    </div>

                    <?php if ($vacation_summary === NULL) { ?>
                        <div class="alert alert-warning" style="margin-bottom:0;">
                            Brak skonfigurowanej puli urlopu wypoczynkowego. Najpierw przydziel pracownikowi roczny limit.
                        </div>
                    <?php } else { ?>
                        <div class="hayne-usage-metrics">
                            <div><span>Przyznano</span><strong><?php echo $formatDays($vacation_summary['granted']); ?> dni</strong></div>
                            <div><span>Wnioski w HAYNE</span><strong><?php echo $formatDays($vacationActual); ?> dni</strong></div>
                            <div><span>Korekta ręczna</span><strong><?php echo $manualVacation; ?> dni</strong></div>
                            <div><span>Saldo obecnie</span><strong><?php echo $formatDays($vacation_summary['remaining']); ?> dni</strong></div>
                        </div>

                        <div class="hayne-usage-grid hayne-usage-grid--two">
                            <div class="hayne-usage-field">
                                <label for="vacation_used">Łącznie wykorzystano urlopu wypoczynkowego</label>
                                <div class="hayne-usage-number">
                                    <input type="number" id="vacation_used" name="vacation_used" min="0" max="366" step="1" inputmode="numeric" value="<?php echo $manualVacation; ?>" <?php echo $editable ? '' : 'disabled'; ?> />
                                    <span>dni</span>
                                </div>
                                <small>Wpisz całe wykorzystanie z papieru, razem z dniami „na żądanie”.</small>
                            </div>

                            <div class="hayne-usage-field hayne-usage-field--nested">
                                <label for="on_demand_used">W tym urlop na żądanie</label>
                                <div class="hayne-usage-number">
                                    <input type="number" id="on_demand_used" name="on_demand_used" min="0" max="4" step="1" inputmode="numeric" value="<?php echo $manualOnDemand; ?>" <?php echo $editable ? '' : 'disabled'; ?> />
                                    <span>/ 4 dni</span>
                                </div>
                                <small>Ta liczba musi mieścić się w wartości powyżej. Nie jest dodatkową pulą.</small>
                            </div>
                        </div>
                    <?php } ?>
                </section>

                <section class="hayne-usage-card" aria-labelledby="hayneStatutoryCorrectionTitle">
                    <div class="hayne-usage-card__head">
                        <div>
                            <span class="hayne-limits-eyebrow">Pule ustawowe</span>
                            <h2 id="hayneStatutoryCorrectionTitle">Pozostałe limity roczne</h2>
                            <p>Korekta jest dodawana do wniosków już zapisanych w HAYNE i razem z nimi nie może przekroczyć przyznanego limitu.</p>
                        </div>
                    </div>

                    <div class="hayne-usage-grid hayne-usage-grid--three">
                        <div class="hayne-usage-policy">
                            <h3>Urlop opiekuńczy</h3>
                            <?php if ($caregiver_summary === NULL) { ?>
                                <p class="muted">Polityka nieaktywna.</p>
                                <input type="hidden" name="caregiver_used" value="0" />
                            <?php } else { ?>
                                <p><span>Limit</span><strong><?php echo $formatDays($caregiver_summary['limit']); ?> dni</strong></p>
                                <p><span>W HAYNE</span><strong><?php echo $formatDays($caregiverActual); ?> dni</strong></p>
                                <label for="caregiver_used">Wykorzystano papierowo</label>
                                <div class="hayne-usage-number"><input type="number" id="caregiver_used" name="caregiver_used" min="0" max="5" step="1" value="<?php echo $manualCaregiver; ?>" <?php echo $editable ? '' : 'disabled'; ?> /><span>dni</span></div>
                            <?php } ?>
                        </div>

                        <div class="hayne-usage-policy">
                            <h3>Siła wyższa</h3>
                            <?php if ($force_majeure_summary === NULL) { ?>
                                <p class="muted">Polityka nieaktywna.</p>
                                <input type="hidden" name="force_majeure_used" value="0" />
                            <?php } else { ?>
                                <p><span>Limit</span><strong><?php echo $formatDays($force_majeure_summary['limit']); ?> dni</strong></p>
                                <p><span>W HAYNE</span><strong><?php echo $formatDays($forceActual); ?> dni</strong></p>
                                <label for="force_majeure_used">Wykorzystano papierowo</label>
                                <div class="hayne-usage-number"><input type="number" id="force_majeure_used" name="force_majeure_used" min="0" max="2" step="1" value="<?php echo $manualForce; ?>" <?php echo $editable ? '' : 'disabled'; ?> /><span>dni</span></div>
                            <?php } ?>
                        </div>

                        <div class="hayne-usage-policy">
                            <h3>Opieka nad dzieckiem</h3>
                            <?php if ($childcare_summary === NULL) { ?>
                                <p class="muted">Brak przyznanej puli 1/2 dni dla tego pracownika.</p>
                                <input type="hidden" name="childcare_used" value="0" />
                            <?php } else { ?>
                                <p><span>Przyznano</span><strong><?php echo $formatDays($childcare_summary['limit']); ?> dni</strong></p>
                                <p><span>W HAYNE</span><strong><?php echo $formatDays($childcareActual); ?> dni</strong></p>
                                <label for="childcare_used">Wykorzystano papierowo</label>
                                <div class="hayne-usage-number"><input type="number" id="childcare_used" name="childcare_used" min="0" max="<?php echo (int) $childcare_summary['limit']; ?>" step="1" value="<?php echo $manualChildcare; ?>" <?php echo $editable ? '' : 'disabled'; ?> /><span>dni</span></div>
                            <?php } ?>
                        </div>
                    </div>
                </section>

                <section class="hayne-usage-card" aria-labelledby="hayneOccasionCorrectionTitle">
                    <div class="hayne-usage-card__head">
                        <div>
                            <span class="hayne-limits-eyebrow">Per zdarzenie</span>
                            <h2 id="hayneOccasionCorrectionTitle">Urlop okolicznościowy</h2>
                            <p>Tu nie ma jednej puli rocznej. Rejestrujemy konkretne zdarzenie, jego datę i liczbę wykorzystanych dni.</p>
                        </div>
                    </div>

                    <?php if (empty($occasion_policy) || (int) $occasion_policy['enabled'] !== 1) { ?>
                        <p class="muted" style="margin:0;">Polityka urlopu okolicznościowego nie jest aktywna.</p>
                    <?php } else { ?>
                        <?php if (!empty($occasion_corrections)) { ?>
                            <div class="hayne-usage-existing-list">
                                <?php foreach ($occasion_corrections as $correction) { ?>
                                    <label class="hayne-usage-existing-row">
                                        <span>
                                            <strong><?php echo html_escape($correction['event_label']); ?></strong>
                                            <small><?php echo html_escape($correction['event_date']); ?> · wykorzystano <?php echo (int) $correction['days']; ?> dni</small>
                                        </span>
                                        <?php if ($editable) { ?>
                                            <span class="hayne-usage-remove"><input type="checkbox" name="remove_occasion[]" value="<?php echo html_escape($correction['reference_key']); ?>" /> Usuń korektę</span>
                                        <?php } ?>
                                    </label>
                                <?php } ?>
                            </div>
                        <?php } ?>

                        <?php if ($editable) { ?>
                            <div class="hayne-usage-event-add">
                                <h3>Dodaj wykorzystane zdarzenie</h3>
                                <div class="hayne-usage-grid hayne-usage-grid--three">
                                    <div class="hayne-usage-field">
                                        <label for="occasion_event_code">Zdarzenie</label>
                                        <select id="occasion_event_code" name="occasion_event_code">
                                            <option value="">— wybierz —</option>
                                            <?php foreach ($occasion_definitions as $eventCode => $definition) { ?>
                                                <option value="<?php echo html_escape($eventCode); ?>"><?php echo html_escape($definition['label']); ?> (<?php echo (int) $definition['days']; ?> dni)</option>
                                            <?php } ?>
                                        </select>
                                    </div>
                                    <div class="hayne-usage-field">
                                        <label for="occasion_event_date">Data zdarzenia</label>
                                        <input type="date" id="occasion_event_date" name="occasion_event_date" min="<?php echo (int) $year; ?>-01-01" max="<?php echo (int) $year; ?>-12-31" />
                                    </div>
                                    <div class="hayne-usage-field">
                                        <label for="occasion_days">Wykorzystane dni</label>
                                        <div class="hayne-usage-number"><input type="number" id="occasion_days" name="occasion_days" min="1" max="2" step="1" /><span>dni</span></div>
                                    </div>
                                </div>
                            </div>
                        <?php } ?>
                    <?php } ?>
                </section>

                <section class="hayne-usage-card" aria-labelledby="hayneHolidayCorrectionTitle">
                    <div class="hayne-usage-card__head">
                        <div>
                            <span class="hayne-limits-eyebrow">Konkretne uprawnienia</span>
                            <h2 id="hayneHolidayCorrectionTitle">Dni wolne za święto</h2>
                            <p>Zaznacz tylko granty, które pracownik faktycznie wykorzystał papierowo.</p>
                        </div>
                    </div>

                    <?php if (empty($holiday_grants)) { ?>
                        <p class="muted" style="margin:0;">Brak grantów za święto dla pracownika w tym roku.</p>
                    <?php } else { ?>
                        <div class="hayne-usage-existing-list">
                            <?php foreach ($holiday_grants as $grant) {
                                $grantId = (int) $grant['id'];
                                $manualUsed = isset($holiday_manual_usage[$grantId]) && (int) $holiday_manual_usage[$grantId] > 0;
                                $realReserved = max(0, (int) $grant['reserved'] - ($manualUsed ? 1 : 0));
                                ?>
                                <label class="hayne-usage-existing-row <?php echo $realReserved > 0 ? 'is-locked' : ''; ?>">
                                    <span>
                                        <strong>Święto <?php echo html_escape($grant['source_holiday_date']); ?></strong>
                                        <small>Okres: <?php echo html_escape($grant['period_start']); ?> – <?php echo html_escape($grant['period_end']); ?></small>
                                    </span>
                                    <?php if ($realReserved > 0) { ?>
                                        <span class="hayne-limit-status hayne-limit-status--configured">Już użyte / zarezerwowane w HAYNE</span>
                                    <?php } else { ?>
                                        <span class="hayne-usage-remove"><input type="checkbox" name="holiday_grants[]" value="<?php echo $grantId; ?>" <?php echo $manualUsed ? 'checked' : ''; ?> <?php echo $editable ? '' : 'disabled'; ?> /> Wykorzystano papierowo</span>
                                    <?php } ?>
                                </label>
                            <?php } ?>
                        </div>
                    <?php } ?>
                </section>

                <?php if ($editable) { ?>
                    <div class="hayne-usage-actionbar">
                        <div>
                            <strong>Zapis zmieni salda tego pracownika.</strong>
                            <span>Wpisy techniczne są audytowane i nie pojawią się na listach wniosków ani w kalendarzu.</span>
                        </div>
                        <div class="hayne-usage-actions">
                            <a class="btn" href="<?php echo base_url(); ?>haynelimits?year=<?php echo (int) $year; ?>#hayneAnnualLimits">Anuluj</a>
                            <button type="submit" class="btn btn-primary">Zapisz korektę</button>
                        </div>
                    </div>
                <?php } ?>
            </form>

            <?php if (!empty($correction_history)) { ?>
                <section class="hayne-usage-card hayne-usage-history" aria-labelledby="hayneUsageHistoryTitle">
                    <div class="hayne-usage-card__head">
                        <div><h2 id="hayneUsageHistoryTitle">Historia korekt</h2><p>Ostatnie zmiany wartości ręcznych dla tego pracownika.</p></div>
                    </div>
                    <div class="hayne-usage-history-table-wrap">
                        <table class="table table-condensed">
                            <thead><tr><th>Data</th><th>Pozycja</th><th>Zmiana</th><th>Operator ID</th></tr></thead>
                            <tbody>
                                <?php foreach ($correction_history as $item) { ?>
                                    <tr>
                                        <td><?php echo html_escape($item['changed_at']); ?></td>
                                        <td><?php echo html_escape($historyLabels[$item['code']] ?? $item['code']); ?><?php echo $item['reference_key'] !== '' ? '<br /><small class="muted">' . html_escape($item['reference_key']) . '</small>' : ''; ?></td>
                                        <td><?php echo (int) $item['old_days']; ?> → <strong><?php echo (int) $item['new_days']; ?></strong> dni</td>
                                        <td><?php echo empty($item['changed_by']) ? '—' : (int) $item['changed_by']; ?></td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php } ?>
        </div>
    </div>
</main>

<script type="text/javascript">
(function () {
    var wrap = document.getElementById('wrap');
    if (wrap) wrap.setAttribute('data-hayne-topbar-title', 'Korekta wykorzystania');

    var vacation = document.getElementById('vacation_used');
    var onDemand = document.getElementById('on_demand_used');
    if (!vacation || !onDemand) return;
    function syncOnDemandMax() {
        var total = parseInt(vacation.value || '0', 10);
        if (!Number.isFinite(total) || total < 0) total = 0;
        onDemand.max = String(Math.min(4, total));
        if (parseInt(onDemand.value || '0', 10) > Math.min(4, total)) {
            onDemand.value = String(Math.min(4, total));
        }
    }
    vacation.addEventListener('input', syncOnDemandMax);
    syncOnDemandMax();
})();
</script>
