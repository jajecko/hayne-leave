<?php
$haynePendingApprovals = isset($requests_count) ? (int) $requests_count : 0;
?>
<main class="hayne-home" data-hayne-home="v2">
    <header class="hayne-dashboard-header">
        <div>
            <span class="hayne-dashboard-eyebrow">HAYNE Leave</span>
            <h1>Panel</h1>
            <p>Urlopy, nieobecności i najważniejsze akcje w jednym miejscu.</p>
        </div>
    </header>

    <section class="hayne-dashboard-hero" aria-labelledby="hayne-home-title">
        <div class="hayne-dashboard-hero__copy">
            <span class="hayne-dashboard-hero__eyebrow">Twój czas wolny</span>
            <h2 id="hayne-home-title">Time off, in one place.</h2>
            <p>Zaplanuj urlop, sprawdź saldo i śledź złożone wnioski w jednym przejrzystym miejscu.</p>
        </div>
        <div class="hayne-dashboard-hero__actions">
            <a class="btn btn-primary" href="<?php echo base_url(); ?>leaves/create">
                <i class="mdi mdi-plus"></i>
                Nowy wniosek
            </a>
            <a class="btn" href="<?php echo base_url(); ?>calendar/individual">Otwórz kalendarz</a>
        </div>
    </section>

    <section class="hayne-kpi-grid" aria-label="Podsumowanie">
        <a class="hayne-kpi-card" href="<?php echo base_url(); ?>leaves/counters">
            <span class="hayne-kpi-card__icon"><i class="mdi mdi-chart-pie"></i></span>
            <span class="hayne-kpi-card__label">Saldo urlopowe</span>
            <strong class="hayne-kpi-card__value hayne-kpi-card__value--action">Sprawdź</strong>
            <span class="hayne-kpi-card__meta">Dostępne dni i wykorzystanie</span>
        </a>

        <a class="hayne-kpi-card" href="<?php echo base_url(); ?>leaves">
            <span class="hayne-kpi-card__icon"><i class="mdi mdi-format-list-bulleted"></i></span>
            <span class="hayne-kpi-card__label">Moje wnioski</span>
            <strong class="hayne-kpi-card__value hayne-kpi-card__value--action">Historia</strong>
            <span class="hayne-kpi-card__meta">Status złożonych wniosków</span>
        </a>

        <?php if ($is_manager == TRUE) { ?>
        <a class="hayne-kpi-card" href="<?php echo base_url(); ?>requests">
            <span class="hayne-kpi-card__icon"><i class="mdi mdi-check-circle"></i></span>
            <span class="hayne-kpi-card__label">Do akceptacji</span>
            <strong class="hayne-kpi-card__value"><?php echo $haynePendingApprovals; ?></strong>
            <span class="hayne-kpi-card__meta">Wnioski oczekujące na decyzję</span>
        </a>
        <?php } else { ?>
        <a class="hayne-kpi-card" href="<?php echo base_url(); ?>calendar/individual">
            <span class="hayne-kpi-card__icon"><i class="mdi mdi-calendar"></i></span>
            <span class="hayne-kpi-card__label">Zaplanowane nieobecności</span>
            <strong class="hayne-kpi-card__value hayne-kpi-card__value--action">Kalendarz</strong>
            <span class="hayne-kpi-card__meta">Zobacz swój plan nieobecności</span>
        </a>
        <?php } ?>
    </section>

    <section class="hayne-dashboard-grid">
        <article class="hayne-dashboard-card hayne-dashboard-card--wide">
            <div class="hayne-dashboard-card__header">
                <div>
                    <span class="hayne-dashboard-card__eyebrow">Kalendarz</span>
                    <h2>Nadchodzące nieobecności</h2>
                </div>
                <a href="<?php echo base_url(); ?>calendar/individual">Zobacz kalendarz</a>
            </div>

            <div class="hayne-upcoming-empty">
                <span class="hayne-upcoming-empty__icon"><i class="mdi mdi-calendar"></i></span>
                <div>
                    <strong>Twój plan jest dostępny w kalendarzu</strong>
                    <p>Otwórz widok kalendarza, aby sprawdzić najbliższe zatwierdzone i planowane nieobecności.</p>
                </div>
            </div>
        </article>

        <article class="hayne-dashboard-card">
            <div class="hayne-dashboard-card__header">
                <div>
                    <span class="hayne-dashboard-card__eyebrow">Skróty</span>
                    <h2>Szybkie akcje</h2>
                </div>
            </div>

            <nav class="hayne-quick-actions" aria-label="Szybkie akcje">
                <a href="<?php echo base_url(); ?>leaves/create">
                    <span><i class="mdi mdi-plus"></i>Nowy wniosek</span>
                    <i class="mdi mdi-chevron-right"></i>
                </a>
                <a href="<?php echo base_url(); ?>leaves">
                    <span><i class="mdi mdi-format-list-bulleted"></i>Moje wnioski</span>
                    <i class="mdi mdi-chevron-right"></i>
                </a>
                <a href="<?php echo base_url(); ?>leaves/counters">
                    <span><i class="mdi mdi-chart-pie"></i>Saldo urlopowe</span>
                    <i class="mdi mdi-chevron-right"></i>
                </a>
                <?php if ($is_manager == TRUE) { ?>
                <a href="<?php echo base_url(); ?>requests">
                    <span><i class="mdi mdi-check-circle"></i>Do akceptacji</span>
                    <i class="mdi mdi-chevron-right"></i>
                </a>
                <?php } ?>
            </nav>
        </article>
    </section>
</main>
