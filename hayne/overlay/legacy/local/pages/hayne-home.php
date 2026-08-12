<?php
$hayneCI =& get_instance();
$hayneFirstName = trim((string) $hayneCI->session->userdata('firstname'));
$hayneGreeting = $hayneFirstName === '' ? 'Cześć!' : 'Cześć ' . $hayneFirstName . '!';
$hayneUserId = (int) $hayneCI->session->userdata('id');
$hayneToday = date('Y-m-d');
$hayneCurrentYear = (int) date('Y');
$hayneRemainingDays = NULL;
$haynePendingRequests = 0;
$hayneScheduledAbsences = 0;
$hayneUpcomingAbsences = [];

$hayneFormatNumber = static function ($value): string {
    if ($value === NULL || !is_numeric($value)) {
        return '—';
    }
    $number = (float) $value;
    if (floor($number) == $number) {
        return (string) (int) $number;
    }
    return rtrim(rtrim(number_format($number, 2, ',', ''), '0'), ',');
};

$hayneFormatDateRange = static function (string $startDate, string $endDate): string {
    $start = DateTime::createFromFormat('Y-m-d', $startDate);
    $end = DateTime::createFromFormat('Y-m-d', $endDate);
    if ($start === FALSE || $end === FALSE) {
        return trim($startDate . ' – ' . $endDate, ' –');
    }
    if ($startDate === $endDate) {
        return $start->format('d.m.Y');
    }
    return $start->format('d.m.Y') . ' – ' . $end->format('d.m.Y');
};

try {
    $hayneCI->load->model('hayne_leave_policy_model');
    $hayneVacationSummary = $hayneCI->hayne_leave_policy_model->getYearSummary($hayneUserId, $hayneCurrentYear);
    if (!empty($hayneVacationSummary['configured'])) {
        $hayneRemainingDays = (float) $hayneVacationSummary['remaining'];
    }
} catch (Throwable $exception) {
    log_message('error', 'HAYNE dashboard vacation summary failed for user #' . $hayneUserId . ': ' . $exception->getMessage());
}

try {
    $hayneCI->load->model('leaves_model');
    $hayneLeaves = $hayneCI->leaves_model->getLeavesOfEmployee($hayneUserId);

    foreach ($hayneLeaves as $hayneLeave) {
        $hayneStatus = (int) ($hayneLeave['status'] ?? 0);
        $hayneStartDate = (string) ($hayneLeave['startdate'] ?? '');
        $hayneEndDate = (string) ($hayneLeave['enddate'] ?? '');

        if ($hayneStatus === 2) {
            $haynePendingRequests++;
        }

        if ($hayneEndDate >= $hayneToday && in_array($hayneStatus, [1, 3], TRUE)) {
            $hayneScheduledAbsences++;
            $hayneUpcomingAbsences[] = [
                'id' => (int) ($hayneLeave['id'] ?? 0),
                'startdate' => $hayneStartDate,
                'enddate' => $hayneEndDate,
                'type_name' => (string) ($hayneLeave['type_name'] ?? 'Nieobecność'),
                'status' => $hayneStatus,
            ];
        }
    }

    usort($hayneUpcomingAbsences, static function (array $left, array $right): int {
        $dateCompare = strcmp($left['startdate'], $right['startdate']);
        if ($dateCompare !== 0) {
            return $dateCompare;
        }
        return $left['id'] <=> $right['id'];
    });
    $hayneUpcomingAbsences = array_slice($hayneUpcomingAbsences, 0, 3);
} catch (Throwable $exception) {
    log_message('error', 'HAYNE dashboard leave summary failed for user #' . $hayneUserId . ': ' . $exception->getMessage());
}

$hayneIcons = [
    'leave' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12c1-4 4-6 8-6s7 2 8 6c-1-1-2-1.5-4-1.5S13 11 12 12c-1-1.5-2.5-1.5-4 0-1.5-1.5-3-1.5-4 0Z"/><path d="M12 6V3M12 12v7c0 1.1-.9 2-2 2"/></svg>',
    'clock' => '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>',
    'calendar' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/></svg>',
    'plus' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M12 8v8M8 12h8"/></svg>',
    'file' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h8l4 4v14H6z"/><path d="M14 3v5h5M9 12h6M9 16h6"/></svg>',
    'chevron' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 6 6 6-6 6"/></svg>',
    'check' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m7 12 3.2 3.2L17 8.5"/></svg>'
];
?>
<main class="hayne-home" data-hayne-home="v1">
    <section class="hayne-dashboard-hero" aria-labelledby="hayne-home-title">
        <div class="hayne-dashboard-hero__copy">
            <h1 id="hayne-home-title"><?php echo htmlspecialchars($hayneGreeting, ENT_QUOTES, 'UTF-8'); ?></h1>
            <p>Zarządzaj wnioskami urlopowymi i nieobecnościami w jednym miejscu — szybko, przejrzyście i bez zbędnych formalności.</p>
            <a class="btn btn-primary hayne-dashboard-hero__action" href="<?php echo base_url(); ?>leaves/create">Nowy wniosek</a>
        </div>
        <div class="hayne-dashboard-hero__visual" aria-hidden="true">
            <span class="hayne-dashboard-hero__orb hayne-dashboard-hero__orb--one"></span>
            <span class="hayne-dashboard-hero__orb hayne-dashboard-hero__orb--two"></span>
            <div class="hayne-dashboard-hero__lamp"><span class="hayne-dashboard-hero__lamp-arm"></span><span class="hayne-dashboard-hero__lamp-shade"></span></div>
            <div class="hayne-dashboard-hero__calendar">
                <div class="hayne-dashboard-hero__calendar-grid">
                    <?php for ($i = 0; $i < 15; $i++) { ?><span></span><?php } ?>
                </div>
                <span class="hayne-dashboard-hero__calendar-check"><?php echo $hayneIcons['check']; ?></span>
            </div>
            <div class="hayne-dashboard-hero__plant">
                <span></span><span></span><span></span>
            </div>
        </div>
    </section>

    <section class="hayne-kpi-grid" aria-label="Podsumowanie">
        <a class="hayne-kpi-card" href="<?php echo base_url(); ?>leaves/counters">
            <span class="hayne-kpi-card__icon"><?php echo $hayneIcons['leave']; ?></span>
            <span class="hayne-kpi-card__content">
                <span class="hayne-kpi-card__label">Pozostało dni</span>
                <strong class="hayne-kpi-card__value"><?php echo $hayneFormatNumber($hayneRemainingDays); ?></strong>
                <span class="hayne-kpi-card__meta">Urlop wypoczynkowy — <?php echo $hayneCurrentYear; ?></span>
            </span>
        </a>

        <a class="hayne-kpi-card" href="<?php echo base_url(); ?>leaves?statuses=2">
            <span class="hayne-kpi-card__icon"><?php echo $hayneIcons['clock']; ?></span>
            <span class="hayne-kpi-card__content">
                <span class="hayne-kpi-card__label">Wnioski oczekujące</span>
                <strong class="hayne-kpi-card__value"><?php echo $haynePendingRequests; ?></strong>
                <span class="hayne-kpi-card__meta">Wnioski oczekujące na decyzję</span>
            </span>
        </a>

        <a class="hayne-kpi-card" href="<?php echo base_url(); ?>calendar/individual">
            <span class="hayne-kpi-card__icon"><?php echo $hayneIcons['calendar']; ?></span>
            <span class="hayne-kpi-card__content">
                <span class="hayne-kpi-card__label">Zaplanowane nieobecności</span>
                <strong class="hayne-kpi-card__value"><?php echo $hayneScheduledAbsences; ?></strong>
                <span class="hayne-kpi-card__meta">Przyszłe plany i zaakceptowane nieobecności</span>
            </span>
        </a>
    </section>

    <section class="hayne-dashboard-grid">
        <article class="hayne-dashboard-card hayne-dashboard-card--wide">
            <div class="hayne-dashboard-card__header">
                <h2>Nadchodzące nieobecności</h2>
                <a class="btn" href="<?php echo base_url(); ?>calendar/individual">Zobacz kalendarz</a>
            </div>

            <?php if (empty($hayneUpcomingAbsences)) { ?>
                <div class="hayne-upcoming-empty">
                    <span class="hayne-upcoming-empty__icon"><?php echo $hayneIcons['calendar']; ?></span>
                    <div>
                        <strong>Brak nadchodzących nieobecności</strong>
                        <p>Nie masz obecnie przyszłych planowanych ani zaakceptowanych nieobecności.</p>
                    </div>
                </div>
            <?php } else { ?>
                <nav class="hayne-quick-actions" aria-label="Nadchodzące nieobecności">
                    <?php foreach ($hayneUpcomingAbsences as $hayneAbsence) { ?>
                        <a class="hayne-quick-action" href="<?php echo base_url(); ?>leaves/leaves/<?php echo $hayneAbsence['id']; ?>">
                            <span class="hayne-quick-action__icon"><?php echo $hayneIcons['calendar']; ?></span>
                            <span>
                                <strong><?php echo htmlspecialchars($hayneAbsence['type_name'], ENT_QUOTES, 'UTF-8'); ?></strong>
                                <small><?php echo htmlspecialchars($hayneFormatDateRange($hayneAbsence['startdate'], $hayneAbsence['enddate']), ENT_QUOTES, 'UTF-8'); ?> · <?php echo $hayneAbsence['status'] === 3 ? 'Zaakceptowana' : 'Plan'; ?></small>
                            </span>
                            <span class="hayne-quick-action__chevron"><?php echo $hayneIcons['chevron']; ?></span>
                        </a>
                    <?php } ?>
                </nav>
            <?php } ?>
            <a class="hayne-upcoming-footer" href="<?php echo base_url(); ?>calendar/individual">Zobacz wszystkie nieobecności <span class="hayne-inline-chevron"><?php echo $hayneIcons['chevron']; ?></span></a>
        </article>

        <article class="hayne-dashboard-card hayne-dashboard-card--actions">
            <div class="hayne-dashboard-card__header">
                <h2>Szybkie akcje</h2>
            </div>

            <nav class="hayne-quick-actions" aria-label="Szybkie akcje">
                <a class="hayne-quick-action hayne-quick-action--primary" href="<?php echo base_url(); ?>leaves/create">
                    <span class="hayne-quick-action__icon"><?php echo $hayneIcons['plus']; ?></span>
                    <span><strong>Nowy wniosek</strong><small>Złóż wniosek o urlop lub inną nieobecność</small></span>
                    <span class="hayne-quick-action__chevron"><?php echo $hayneIcons['chevron']; ?></span>
                </a>
                <a class="hayne-quick-action" href="<?php echo base_url(); ?>calendar/individual">
                    <span class="hayne-quick-action__icon"><?php echo $hayneIcons['calendar']; ?></span>
                    <span><strong>Sprawdź kalendarz</strong><small>Zobacz kalendarz i nadchodzące nieobecności</small></span>
                    <span class="hayne-quick-action__chevron"><?php echo $hayneIcons['chevron']; ?></span>
                </a>
                <a class="hayne-quick-action" href="<?php echo base_url(); ?>leaves">
                    <span class="hayne-quick-action__icon"><?php echo $hayneIcons['file']; ?></span>
                    <span><strong>Moje wnioski</strong><small>Sprawdź status złożonych wniosków</small></span>
                    <span class="hayne-quick-action__chevron"><?php echo $hayneIcons['chevron']; ?></span>
                </a>
            </nav>
        </article>
    </section>
</main>
