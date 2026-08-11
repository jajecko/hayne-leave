<?php
/**
 * HAYNE presentation of the upstream leave counters view.
 * Counter calculations, links and date navigation remain unchanged.
 */
?>

<main class="hayne-balance-page" data-hayne-view="leave-balance-v1">
    <header class="hayne-balance-header">
        <div>
            <h1>Saldo urlopowe</h1>
            <p>Sprawdź dostępne limity nieobecności oraz ich wykorzystanie na wybrany dzień.</p>
        </div>
        <label class="hayne-balance-date" for="refdate">
            <span>Stan na dzień</span>
            <span class="hayne-balance-date__control">
                <input type="text" id="refdate" autocomplete="off" />
                <span class="hayne-balance-date__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <rect x="3" y="5" width="18" height="16" rx="2"></rect>
                        <path d="M16 3v4M8 3v4M3 10h18"></path>
                    </svg>
                </span>
            </span>
        </label>
    </header>

    <section class="hayne-balance-card" aria-label="Saldo nieobecności">
        <div class="hayne-balance-card__intro">
            <div>
                <h2>Twoje limity</h2>
                <p>Wartości są wyliczane przez system dla wskazanego dnia.</p>
            </div>
            <a class="btn hayne-balance-create" href="<?php echo base_url(); ?>leaves/create">Nowy wniosek</a>
        </div>

        <div class="hayne-balance-table-wrap">
            <table class="table table-bordered table-hover hayne-balance-table">
                <thead>
                    <tr>
                        <th rowspan="2">Rodzaj nieobecności</th>
                        <th colspan="2" class="hayne-balance-table__group">Dostępne</th>
                        <th rowspan="2">Przyznane</th>
                        <th rowspan="2">Wykorzystane</th>
                        <th rowspan="2"><span class="hayne-balance-status-label">Zaplanowane</span></th>
                        <th rowspan="2"><span class="hayne-balance-status-label">Oczekujące</span></th>
                    </tr>
                    <tr>
                        <th>Aktualne</th>
                        <th>Po planach</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($hayneCaregiverSummary)) { ?>
                        <tr class="hayne-caregiver-balance" data-hayne-caregiver-summary="v1"
                            data-used="<?php echo (float) $hayneCaregiverSummary['used']; ?>"
                            data-remaining="<?php echo (float) $hayneCaregiverSummary['remaining']; ?>">
                            <td><strong>Urlop opiekuńczy</strong></td>
                            <td class="hayne-balance-value hayne-balance-value--primary"><?php echo (float) $hayneCaregiverSummary['remaining']; ?></td>
                            <td class="hayne-balance-value"><?php echo (float) $hayneCaregiverSummary['remaining']; ?></td>
                            <td class="hayne-balance-value"><?php echo (int) $hayneCaregiverSummary['limit']; ?></td>
                            <td class="hayne-balance-value"><?php echo (float) $hayneCaregiverSummary['used']; ?></td>
                            <td class="hayne-balance-value hayne-balance-value--muted">—</td>
                            <td class="hayne-balance-value hayne-balance-value--muted">—</td>
                        </tr>
                    <?php } ?>
                    <?php if (count($summary) > 0) {
                        foreach ($summary as $key => $value) {
                            if (($value[2] == '') || ($value[2] == 'x')) {
                                $estimated = round(((float) $value[1] - (float) $value[0]), 3, PHP_ROUND_HALF_DOWN);
                                $simulated = $estimated;
                                if (!empty($value[4]))
                                    $simulated -= (float) $value[4];
                                if (!empty($value[5]))
                                    $simulated -= (float) $value[5];
                                ?>
                                <tr>
                                    <td><strong><?php echo $key; ?></strong></td>
                                    <td class="hayne-balance-value hayne-balance-value--primary"><?php echo $estimated; ?></td>
                                    <td class="hayne-balance-value"><?php echo $simulated; ?></td>
                                    <td class="hayne-balance-value"><?php echo ((float) $value[1]); ?></td>
                                    <td class="hayne-balance-value"><a href="<?php echo base_url(); ?>leaves?statuses=3|5&type=<?php echo $value[3]; ?>"
                                            target="_blank"><?php echo ((float) $value[0]); ?></a></td>
                                    <?php if (empty($value[4])) { ?>
                                        <td class="hayne-balance-value hayne-balance-value--muted">—</td>
                                    <?php } else { ?>
                                        <td class="hayne-balance-value"><a href="<?php echo base_url(); ?>leaves?statuses=1&type=<?php echo $value[3]; ?>"
                                                target="_blank"><?php echo ((float) $value[4]); ?></a></td>
                                    <?php } ?>
                                    <?php if (empty($value[5])) { ?>
                                        <td class="hayne-balance-value hayne-balance-value--muted">—</td>
                                    <?php } else { ?>
                                        <td class="hayne-balance-value"><a href="<?php echo base_url(); ?>leaves?statuses=2&type=<?php echo $value[3]; ?>"
                                                target="_blank"><?php echo ((float) $value[5]); ?></a></td>
                                    <?php } ?>
                                </tr>
                            <?php }
                        }
                    } else { ?>
                        <tr class="hayne-balance-empty-row">
                            <td colspan="7">
                                <span class="hayne-balance-empty-icon" aria-hidden="true">
                                    <svg viewBox="0 0 24 24" focusable="false">
                                        <rect x="3" y="5" width="18" height="16" rx="2"></rect>
                                        <path d="M16 3v4M8 3v4M3 10h18"></path>
                                    </svg>
                                </span>
                                <span>
                                    <strong>Brak salda do wyświetlenia</strong>
                                    <small>Nie ma przyznanych ani wykorzystanych dni dla tego okresu. W razie pytań skontaktuj się z HR lub przełożonym.</small>
                                </span>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <footer class="hayne-balance-card__footer">
            <span>Saldo uwzględnia przyznane i wykorzystane nieobecności dla wskazanego dnia.</span>
            <a href="<?php echo base_url(); ?>leaves">Zobacz moje wnioski <span aria-hidden="true">→</span></a>
        </footer>
    </section>
</main>

<link rel="stylesheet"
    href="<?php echo base_url(); ?>assets/bootstrap-datepicker-1.8.0/css/bootstrap-datepicker.min.css">
<script src="<?php echo base_url(); ?>assets/bootstrap-datepicker-1.8.0/js/bootstrap-datepicker.min.js"></script>
<?php //Prevent HTTP-404 when localization isn't needed
if ($language_code != 'en') { ?>
    <script
        src="<?php echo base_url(); ?>assets/bootstrap-datepicker-1.8.0/locales/bootstrap-datepicker.<?php echo $language_code; ?>.min.js"></script>
<?php } ?>

<script type="text/javascript">
    var hayneWrap = document.getElementById('wrap');
    if (hayneWrap) {
        hayneWrap.setAttribute('data-hayne-topbar-title', 'Saldo urlopowe');
    }

    /**
     * Converts a local date to an ISO compliant string
     * Because toISOString converts to UTC causing one day
     * of shift in some zones
     * @param Date $d JavaScript native date object
     */
    function toISODateLocal(d) {
        var z = n => (n < 10 ? '0' : '') + n;
        return d.getFullYear() + '-' + z(d.getMonth() + 1) + '-' + z(d.getDate());
    }

    $(function () {
        var isDefault = <?php echo $isDefault; ?>;
        var reportDate = '<?php $date = new DateTime($refDate);
        echo $date->format(lang('global_date_format')); ?>';
        var dateFormat = { year: 'numeric', month: 'numeric', day: 'numeric' };
        var now = new Date();
        var todayDate = now.toLocaleDateString('<?php echo $language_code; ?>', dateFormat);
        if (isDefault == 1) {
            $("#refdate").val(todayDate);
        } else {
            $("#refdate").val(reportDate);
        }

        $("#refdate").datepicker({
            language: "<?php echo $language_code; ?>",
            autoclose: true
        }).on('changeDate', function (e) {
            isoDate = toISODateLocal(e.date);
            url = "<?php echo base_url(); ?>leaves/counters/" + isoDate;
            window.location = url;
        });
    });
</script>
