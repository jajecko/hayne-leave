<main class="hayne-home" data-hayne-home="v1">
    <section class="hayne-home-hero" aria-labelledby="hayne-home-title">
        <div class="hayne-home-hero__copy">
            <span class="hayne-home-eyebrow">HAYNE Leave</span>
            <h1 id="hayne-home-title">Time off, in one place.</h1>
            <p>Zaplanuj urlop, sprawdź saldo i śledź złożone wnioski w jednym przejrzystym miejscu.</p>
        </div>
        <a class="btn btn-primary hayne-home-hero__action" href="<?php echo base_url(); ?>leaves/create">Nowy wniosek</a>
    </section>

    <section class="hayne-home-section" aria-labelledby="hayne-home-quick-actions">
        <div class="hayne-home-section__heading">
            <span class="hayne-home-section__index">01</span>
            <h2 id="hayne-home-quick-actions">Szybkie akcje</h2>
        </div>

        <div class="hayne-home-grid">
            <a class="hayne-home-card" href="<?php echo base_url(); ?>leaves/create">
                <span class="hayne-home-card__label">Nowy wniosek</span>
                <strong>Złóż wniosek</strong>
                <span>Złóż nowy wniosek o urlop lub inną nieobecność.</span>
                <span class="hayne-home-card__link">Otwórz &rarr;</span>
            </a>

            <a class="hayne-home-card" href="<?php echo base_url(); ?>leaves/counters">
                <span class="hayne-home-card__label">Saldo</span>
                <strong>Saldo urlopowe</strong>
                <span>Sprawdź liczbę dostępnych dni urlopu.</span>
                <span class="hayne-home-card__link">Otwórz &rarr;</span>
            </a>

            <a class="hayne-home-card" href="<?php echo base_url(); ?>leaves">
                <span class="hayne-home-card__label">Historia</span>
                <strong>Moje wnioski</strong>
                <span>Sprawdź status złożonych wniosków.</span>
                <span class="hayne-home-card__link">Otwórz &rarr;</span>
            </a>
        </div>
    </section>

    <section class="hayne-home-section hayne-home-section--manager" aria-labelledby="hayne-home-manager">
        <div class="hayne-home-section__heading">
            <span class="hayne-home-section__index">02</span>
            <h2 id="hayne-home-manager">Panel kierownika</h2>
        </div>

        <div class="hayne-home-manager-links">
            <a href="<?php echo base_url(); ?>requests">Wnioski do akceptacji <span aria-hidden="true">&rarr;</span></a>
            <?php if ($this->config->item('disable_overtime') == FALSE) { ?>
            <a href="<?php echo base_url(); ?>overtime">Nadgodziny do akceptacji <span aria-hidden="true">&rarr;</span></a>
            <?php } ?>
        </div>
        <p class="hayne-home-note">Narzędzia dla osób odpowiedzialnych za akceptację wniosków pracowników.</p>
    </section>
</main>
