<main class="hayne-home" data-hayne-home="v1">
    <section class="hayne-home-hero" aria-labelledby="hayne-home-title">
        <div class="hayne-home-hero__copy">
            <span class="hayne-home-eyebrow">HAYNE Leave</span>
            <h1 id="hayne-home-title">Time off, in one place.</h1>
            <p>Plan your leave, check your balance and follow submitted requests from one clear workspace.</p>
        </div>
        <a class="btn btn-primary hayne-home-hero__action" href="<?php echo base_url(); ?>leaves/create">Request leave</a>
    </section>

    <section class="hayne-home-section" aria-labelledby="hayne-home-quick-actions">
        <div class="hayne-home-section__heading">
            <span class="hayne-home-section__index">01</span>
            <h2 id="hayne-home-quick-actions">Quick actions</h2>
        </div>

        <div class="hayne-home-grid">
            <a class="hayne-home-card" href="<?php echo base_url(); ?>leaves/create">
                <span class="hayne-home-card__label">New request</span>
                <strong>Request leave</strong>
                <span>Submit a new time-off request.</span>
                <span class="hayne-home-card__link">Open &rarr;</span>
            </a>

            <a class="hayne-home-card" href="<?php echo base_url(); ?>leaves/counters">
                <span class="hayne-home-card__label">Balance</span>
                <strong>Leave balance</strong>
                <span>Check your available leave allowance.</span>
                <span class="hayne-home-card__link">Open &rarr;</span>
            </a>

            <a class="hayne-home-card" href="<?php echo base_url(); ?>leaves">
                <span class="hayne-home-card__label">History</span>
                <strong>My requests</strong>
                <span>Review the status of requests you submitted.</span>
                <span class="hayne-home-card__link">Open &rarr;</span>
            </a>
        </div>
    </section>

    <section class="hayne-home-section hayne-home-section--manager" aria-labelledby="hayne-home-manager">
        <div class="hayne-home-section__heading">
            <span class="hayne-home-section__index">02</span>
            <h2 id="hayne-home-manager">Manager workspace</h2>
        </div>

        <div class="hayne-home-manager-links">
            <a href="<?php echo base_url(); ?>requests">Review leave requests <span aria-hidden="true">&rarr;</span></a>
            <?php if ($this->config->item('disable_overtime') == FALSE) { ?>
            <a href="<?php echo base_url(); ?>overtime">Review overtime requests <span aria-hidden="true">&rarr;</span></a>
            <?php } ?>
        </div>
        <p class="hayne-home-note">These tools are intended for line managers responsible for approving employee requests.</p>
    </section>
</main>
