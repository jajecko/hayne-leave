<?php
$e = static function ($value): string {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
$formatNumber = static function ($value): string {
    $number = (float) $value;
    if (floor($number) == $number) {
        return (string) (int) $number;
    }
    return rtrim(rtrim(number_format($number, 2, '.', ''), '0'), '.');
};
?>
<div class="row-fluid">
  <div class="span12">
    <h2>Raport miesięczny dla kadr</h2>
    <p class="muted">
      Skuteczne nieobecności w wybranym miesiącu. Domyślnie pokazujemy poprzedni miesiąc kalendarzowy.
    </p>

    <form method="get" action="<?php echo base_url(); ?>haynehrreport/monthly" class="form-inline" style="margin: 18px 0;">
      <label for="hayne_hr_report_period"><strong>Miesiąc</strong></label>
      <input id="hayne_hr_report_period" type="month" name="period" value="<?php echo $e($period); ?>" required>
      <button type="submit" class="btn btn-primary">Pokaż</button>
      <a class="btn" href="<?php echo base_url(); ?>haynehrreport/xlsx?period=<?php echo rawurlencode($period); ?>">Eksport XLSX</a>
      <a class="btn" href="<?php echo base_url(); ?>haynehrreport/csv?period=<?php echo rawurlencode($period); ?>">Eksport CSV</a>
    </form>

    <div class="alert alert-info">
      Raport obejmuje wyłącznie wnioski zaakceptowane oraz wnioski z anulowaniem w toku, które nadal są skuteczne.
      Wnioski planowane, oczekujące, odrzucone, anulowane i techniczne korekty wykorzystania nie są eksportowane.
      Uzasadnienia i inne dane wrażliwe nie trafiają do raportu.
    </div>

    <?php if ($missing_payroll_codes) { ?>
      <div class="alert alert-warning">
        Kody płacowe nie są jeszcze skonfigurowane. Kolumna „Kod płacowy” pozostaje pusta do czasu uzgodnienia mapowania z księgowością.
      </div>
    <?php } ?>

    <?php if (empty($rows)) { ?>
      <div class="alert">Brak skutecznych nieobecności dla okresu <?php echo $e($period); ?>.</div>
    <?php } else { ?>
      <div style="overflow-x:auto; width:100%;">
        <table class="table table-bordered table-hover table-condensed" id="hayne_hr_monthly_report">
          <thead>
            <tr>
              <?php foreach ($headers as $header) { ?>
                <th><?php echo $e($header); ?></th>
              <?php } ?>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $row) { ?>
              <tr data-leave-id="<?php echo (int) $row['id']; ?>">
                <td><?php echo (int) $row['id']; ?></td>
                <td><?php echo $e($row['employee']); ?></td>
                <td><?php echo $e($row['department']); ?></td>
                <td><?php echo $e($row['type_name']); ?></td>
                <td><?php echo $e($row['startdate']); ?></td>
                <td><?php echo $e($row['enddate']); ?></td>
                <td><?php echo $e($formatNumber($row['days_month'])); ?></td>
                <td><?php echo $e($formatNumber($row['hours_month'])); ?></td>
                <td><?php echo $e($row['status']); ?></td>
                <td><?php echo $e($row['submitted_at']); ?></td>
                <td><?php echo $e($row['approved_by']); ?></td>
                <td><?php echo $e($row['approved_at']); ?></td>
                <td><?php echo $e($row['cancellation']); ?></td>
                <td><?php echo $e($row['payroll_code']); ?></td>
              </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
    <?php } ?>
  </div>
</div>
