<main class="hayne-home" data-hayne-home="v1">
    <section class="hayne-dashboard-hero" aria-labelledby="hayne-home-title">
        <div class="hayne-dashboard-hero__copy">
            <h1 id="hayne-home-title">Time off, in one place.</h1>
            <p>Zarządzaj wnioskami urlopowymi i nieobecnościami w jednym miejscu — szybko, przejrzyście i bez zbędnych formalności.</p>
            <a class="btn btn-primary hayne-dashboard-hero__action" href="<?php echo base_url(); ?>leaves/create">Nowy wniosek</a>
        </div>
        <div class="hayne-dashboard-hero__visual" aria-hidden="true">
            <span class="hayne-dashboard-hero__orb hayne-dashboard-hero__orb--one"></span>
            <span class="hayne-dashboard-hero__orb hayne-dashboard-hero__orb--two"></span>
            <div class="hayne-dashboard-hero__calendar">
                <i class="mdi mdi-calendar"></i>
                <span><i class="mdi mdi-check"></i></span>
            </div>
            <div class="hayne-dashboard-hero__plant">
                <span></span><span></span><span></span>
            </div>
        </div>
    </section>

    <section class="hayne-kpi-grid" aria-label="Podsumowanie">
        <a class="hayne-kpi-card" href="<?php echo base_url(); ?>leaves/counters">
            <span class="hayne-kpi-card__icon"><i class="mdi mdi-beach"></i></span>
            <span class="hayne-kpi-card__content">
                <span class="hayne-kpi-card__label">Pozostało dni</span>
                <strong class="hayne-kpi-card__value">&mdash;</strong>
                <span class="hayne-kpi-card__meta">Sprawdź aktualne saldo</span>
            </span>
        </a>

        <a class="hayne-kpi-card" href="<?php echo base_url(); ?>leaves">
            <span class="hayne-kpi-card__icon"><i class="mdi mdi-clock-outline"></i></span>
            <span class="hayne-kpi-card__content">
                <span class="hayne-kpi-card__label">Wnioski oczekujące</span>
                <strong class="hayne-kpi-card__value">&mdash;</strong>
                <span class="hayne-kpi-card__meta">Przejdź do swoich wniosków</span>
            </span>
        </a>

        <a class="hayne-kpi-card" href="<?php echo base_url(); ?>calendar/individual">
            <span class="hayne-kpi-card__icon"><i class="mdi mdi-calendar"></i></span>
            <span class="hayne-kpi-card__content">
                <span class="hayne-kpi-card__label">Zaplanowane nieobecności</span>
                <strong class="hayne-kpi-card__value">&mdash;</strong>
                <span class="hayne-kpi-card__meta">Otwórz kalendarz nieobecności</span>
            </span>
        </a>
    </section>

    <section class="hayne-dashboard-grid">
        <article class="hayne-dashboard-card hayne-dashboard-card--wide">
            <div class="hayne-dashboard-card__header">
                <h2>Nadchodzące nieobecności</h2>
                <a class="btn" href="<?php echo base_url(); ?>calendar/individual">Zobacz kalendarz</a>
            </div>

            <div class="hayne-upcoming-empty">
                <span class="hayne-upcoming-empty__icon"><i class="mdi mdi-calendar"></i></span>
                <div>
                    <strong>Brak danych do wyświetlenia na panelu</strong>
                    <p>Pełny plan zatwierdzonych i planowanych nieobecności znajdziesz w kalendarzu.</p>
                </div>
            </div>
            <a class="hayne-upcoming-footer" href="<?php echo base_url(); ?>calendar/individual">Zobacz wszystkie nieobecności <i class="mdi mdi-chevron-right"></i></a>
        </article>

        <article class="hayne-dashboard-card hayne-dashboard-card--actions">
            <div class="hayne-dashboard-card__header">
                <h2>Szybkie akcje</h2>
            </div>

            <nav class="hayne-quick-actions" aria-label="Szybkie akcje">
                <a class="hayne-quick-action hayne-quick-action--primary" href="<?php echo base_url(); ?>leaves/create">
                    <i class="mdi mdi-plus-box-outline"></i>
                    <span><strong>Nowy wniosek</strong><small>Złóż wniosek o urlop lub inną nieobecność</small></span>
                    <i class="mdi mdi-chevron-right"></i>
                </a>
                <a class="hayne-quick-action" href="<?php echo base_url(); ?>calendar/individual">
                    <i class="mdi mdi-calendar"></i>
                    <span><strong>Sprawdź kalendarz</strong><small>Zobacz kalendarz i nadchodzące nieobecności</small></span>
                    <i class="mdi mdi-chevron-right"></i>
                </a>
                <a class="hayne-quick-action" href="<?php echo base_url(); ?>leaves">
                    <i class="mdi mdi-format-list-bulleted"></i>
                    <span><strong>Moje wnioski</strong><small>Sprawdź status złożonych wniosków</small></span>
                    <i class="mdi mdi-chevron-right"></i>
                </a>
            </nav>
        </article>
    </section>
</main>
