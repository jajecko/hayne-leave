<?php
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

$startDate = new DateTime($leave['startdate']);
$endDate = new DateTime($leave['enddate']);
$start = $startDate->format(lang('global_date_format'));
$end = $endDate->format(lang('global_date_format'));
$term = $start === $end ? $start : $start . ' – ' . $end;
$duration = rtrim(rtrim(number_format((float) $leave['duration'], 2, ',', ''), '0'), ',');
$statusLabel = lang($leave['status_name']);
$statusClass = 'neutral';
if ($leave['status'] == LMS_REQUESTED || $leave['status'] == LMS_CANCELLATION) {
    $statusClass = 'pending';
} elseif ($leave['status'] == LMS_ACCEPTED) {
    $statusClass = 'accepted';
} elseif ($leave['status'] == LMS_REJECTED) {
    $statusClass = 'rejected';
} elseif ($leave['status'] == LMS_CANCELED) {
    $statusClass = 'cancelled';
}
$isRequested = $leave['status'] == LMS_REQUESTED;
$isCancellation = $leave['status'] == LMS_CANCELLATION;
$historyItems = [];
if (isset($leave['comments']) && is_object($leave['comments']) && isset($leave['comments']->comments) && is_array($leave['comments']->comments)) {
    $historyItems = $leave['comments']->comments;
}
?>
<link rel="stylesheet" href="<?php echo base_url(); ?>assets/hayne/approval-review.css">

<main class="hayne-approval-review" data-hayne-view="approval-review-v2">
    <a class="hayne-approval-review__back" href="<?php echo base_url(); ?>requests/requested">← Wróć do listy</a>

    <header class="hayne-approval-review__header">
        <div>
            <span class="hayne-approval-review__status hayne-approval-review__status--<?php echo $statusClass; ?>">
                <?php echo html_escape($statusLabel); ?>
            </span>
            <h1>Wniosek urlopowy</h1>
            <p><strong><?php echo html_escape($name); ?></strong> · wniosek #<?php echo (int) $leave['id']; ?></p>
        </div>
    </header>

    <div class="hayne-approval-review__layout">
        <section class="hayne-approval-review__card hayne-approval-review__details">
            <h2>Szczegóły wniosku</h2>
            <div class="hayne-approval-review__summary">
                <div class="hayne-approval-review__item">
                    <span>Pracownik</span>
                    <strong><?php echo html_escape($name); ?></strong>
                </div>
                <div class="hayne-approval-review__item">
                    <span>Rodzaj nieobecności</span>
                    <strong><?php echo html_escape($leave['type_name']); ?></strong>
                </div>
                <div class="hayne-approval-review__item">
                    <span>Termin</span>
                    <strong><?php echo html_escape($term); ?></strong>
                </div>
                <div class="hayne-approval-review__item">
                    <span>Liczba dni</span>
                    <strong><?php echo html_escape($duration); ?> dni</strong>
                </div>
            </div>

            <div class="hayne-approval-review__reason">
                <span>Uzasadnienie</span>
                <p><?php echo trim((string) $leave['cause']) !== '' ? nl2br(html_escape($leave['cause']), false) : 'Nie podano uzasadnienia.'; ?></p>
            </div>
        </section>

        <aside class="hayne-approval-review__decision">
            <section class="hayne-approval-review__card hayne-approval-review__decision-card">
                <?php if ($isRequested) { ?>
                    <span class="hayne-approval-review__eyebrow">Decyzja</span>
                    <h2>Rozpatrz wniosek</h2>
                    <p>Sprawdź dane i podejmij decyzję. Wynik zostanie zapisany w historii wniosku.</p>

                    <a class="hayne-approval-review__action hayne-approval-review__action--accept"
                       href="<?php echo base_url(); ?>requests/accept/<?php echo (int) $leave['id']; ?>?source=requests/requested"
                       onclick="if (this.dataset.busy === '1') return false; this.dataset.busy = '1'; this.setAttribute('aria-disabled', 'true');">
                        <i class="mdi mdi-check" aria-hidden="true"></i>
                        <span>Akceptuj wniosek</span>
                    </a>

                    <details class="hayne-approval-review__reject">
                        <summary>
                            <i class="mdi mdi-close" aria-hidden="true"></i>
                            <span>Odrzuć wniosek</span>
                        </summary>
                        <div class="hayne-approval-review__reject-panel">
                            <?php echo form_open('requests/reject/' . (int) $leave['id'] . '?source=requests/requested', ['class' => 'hayne-approval-review__reject-form']); ?>
                                <label for="hayneRejectComment">Komentarz do odrzucenia<?php echo $mandatoryCommentOnReject ? ' *' : ''; ?></label>
                                <textarea id="hayneRejectComment" name="comment" rows="5" <?php echo $mandatoryCommentOnReject ? 'required' : ''; ?> placeholder="Wyjaśnij pracownikowi powód decyzji"></textarea>
                                <button type="submit" class="hayne-approval-review__action hayne-approval-review__action--reject-confirm">Potwierdź odrzucenie</button>
                            </form>
                        </div>
                    </details>
                <?php } elseif ($isCancellation) { ?>
                    <span class="hayne-approval-review__eyebrow">Decyzja</span>
                    <h2>Prośba o anulowanie</h2>
                    <p>Pracownik chce anulować zaakceptowany wniosek. Zdecyduj, czy anulowanie ma zostać zatwierdzone.</p>

                    <a class="hayne-approval-review__action hayne-approval-review__action--accept"
                       href="<?php echo base_url(); ?>requests/cancellation/accept/<?php echo (int) $leave['id']; ?>?source=requests/requested"
                       onclick="if (this.dataset.busy === '1') return false; this.dataset.busy = '1'; this.setAttribute('aria-disabled', 'true');">
                        <i class="mdi mdi-check" aria-hidden="true"></i>
                        <span>Zatwierdź anulowanie</span>
                    </a>

                    <details class="hayne-approval-review__reject">
                        <summary>
                            <i class="mdi mdi-close" aria-hidden="true"></i>
                            <span>Odrzuć anulowanie</span>
                        </summary>
                        <div class="hayne-approval-review__reject-panel">
                            <?php echo form_open('requests/cancellation/reject/' . (int) $leave['id'] . '?source=requests/requested', ['class' => 'hayne-approval-review__reject-form']); ?>
                                <label for="hayneCancellationRejectComment">Komentarz<?php echo $mandatoryCommentOnReject ? ' *' : ''; ?></label>
                                <textarea id="hayneCancellationRejectComment" name="comment" rows="5" <?php echo $mandatoryCommentOnReject ? 'required' : ''; ?> placeholder="Dodaj informację dla pracownika"></textarea>
                                <button type="submit" class="hayne-approval-review__action hayne-approval-review__action--reject-confirm">Potwierdź odrzucenie</button>
                            </form>
                        </div>
                    </details>
                <?php } else { ?>
                    <span class="hayne-approval-review__eyebrow">Status</span>
                    <h2><?php echo html_escape($statusLabel); ?></h2>
                    <p>Ten wniosek nie wymaga już decyzji. Możesz wrócić do listy wniosków.</p>
                    <a class="hayne-approval-review__action hayne-approval-review__action--secondary" href="<?php echo base_url(); ?>requests/requested">Wróć do listy</a>
                <?php } ?>
            </section>
        </aside>

        <section class="hayne-approval-review__card hayne-approval-review__history">
            <h2>Historia i komentarze</h2>
            <?php if (empty($historyItems)) { ?>
                <p class="hayne-approval-review__empty">Brak dodatkowych komentarzy.</p>
            <?php } else { ?>
                <div class="hayne-approval-review__timeline">
                    <?php foreach ($historyItems as $item) {
                        $itemDate = !empty($item->date) ? (new DateTime($item->date))->format(lang('global_date_format')) : '';
                        if ($item->type === 'comment') { ?>
                            <article class="hayne-approval-review__timeline-item">
                                <div class="hayne-approval-review__timeline-meta">
                                    <strong><?php echo html_escape($item->author_name ?? 'Użytkownik'); ?></strong>
                                    <?php if ($itemDate !== '') { ?><span><?php echo html_escape($itemDate); ?></span><?php } ?>
                                </div>
                                <p><?php echo nl2br(html_escape($item->value ?? ''), false); ?></p>
                            </article>
                        <?php } elseif ($item->type === 'change') { ?>
                            <article class="hayne-approval-review__timeline-item hayne-approval-review__timeline-item--change">
                                <div class="hayne-approval-review__timeline-meta">
                                    <strong>Zmiana statusu</strong>
                                    <?php if ($itemDate !== '') { ?><span><?php echo html_escape($itemDate); ?></span><?php } ?>
                                </div>
                                <p><?php echo html_escape($item->status_label ?? ''); ?></p>
                            </article>
                        <?php }
                    } ?>
                </div>
            <?php } ?>
        </section>
    </div>
</main>

<script>
(function () {
    var forms = document.querySelectorAll('.hayne-approval-review__reject-form');
    Array.prototype.forEach.call(forms, function (form) {
        form.addEventListener('submit', function (event) {
            if (form.dataset.busy === '1') {
                event.preventDefault();
                return;
            }
            form.dataset.busy = '1';
            var submit = form.querySelector('button[type="submit"]');
            if (submit) {
                submit.setAttribute('aria-disabled', 'true');
                submit.style.pointerEvents = 'none';
            }
        });
    });
})();
</script>
