(() => {
  const currentScriptSrc = document.currentScript?.src || '';
  const appRoot = currentScriptSrc
    ? currentScriptSrc.replace(/assets\/hayne\/leave-detail\.js(?:\?.*)?$/, '')
    : `${window.location.origin}/`;
  const assetRoot = `${appRoot}assets/hayne/`;

  const directChild = (parent, predicate) => Array.from(parent?.children || []).find(predicate) || null;

  const ensureReviewStyles = () => {
    if (!document.querySelector('link[data-hayne-approval-review-css]')) {
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = `${assetRoot}approval-review.css?v=3`;
      link.dataset.hayneApprovalReviewCss = '1';
      document.head.appendChild(link);
    }
    if (!document.querySelector('link[data-hayne-leave-review-css]')) {
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = `${assetRoot}leave-detail-review.css?v=1`;
      link.dataset.hayneLeaveReviewCss = '1';
      document.head.appendChild(link);
    }
  };

  const statusClass = (status) => {
    if (!status) return 'neutral';
    if (status.classList.contains('dropdown-requested')) return 'pending';
    if (status.classList.contains('dropdown-accepted')) return 'accepted';
    if (status.classList.contains('dropdown-rejected')) return 'rejected';
    return 'neutral';
  };

  const textValue = (root, selector) => {
    const node = root.querySelector(selector);
    if (!node) return '';
    if ('value' in node) return String(node.value || '').trim();
    return String(node.textContent || '').trim();
  };

  const summaryItem = (label, value) => {
    const item = document.createElement('div');
    item.className = 'hayne-approval-review__item';
    const caption = document.createElement('span');
    caption.textContent = label;
    const strong = document.createElement('strong');
    strong.textContent = value || '—';
    item.append(caption, strong);
    return item;
  };

  const classifyAction = (node) => {
    const text = String(node.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
    const href = node.getAttribute('href') || '';
    if (text.includes('wróć') || href.endsWith('/leaves') || href.endsWith('/leaves/')) return 'back';
    if (href.includes('/reminder/') || href.includes('/cancellation/') || href.includes('/request/')) return 'primary';
    return 'secondary';
  };

  const enhanceView = (scope) => {
    const title = directChild(scope, (node) => node.tagName === 'H2');
    const row = directChild(scope, (node) => node.classList?.contains('row'));
    if (!title || !row) return false;

    const panes = row.querySelectorAll(':scope > .span6');
    if (panes.length < 2) return false;

    const detailsPane = panes[0];
    const commentsPane = panes[1];
    const fields = detailsPane.querySelector('.span12, .span8') || detailsPane;

    const start = textValue(fields, 'input[name="startdate"]');
    const end = textValue(fields, 'input[name="enddate"]');
    const type = textValue(fields, 'select[name="type"] option:checked') || textValue(fields, 'select[name="type"]');
    const durationRaw = textValue(fields, 'input[name="duration"]');
    const cause = textValue(fields, 'textarea[name="cause"]');
    const status = fields.querySelector('select[name="status"]');
    const statusLabel = status ? textValue(status, 'option:checked') || textValue(fields, 'select[name="status"]') : '';
    const requestIdMatch = String(title.textContent || '').match(/(\d+)\s*$/);
    const requestId = requestIdMatch ? requestIdMatch[1] : '';
    const person = String(title.querySelector('.muted')?.textContent || '').replace(/[()]/g, '').trim();
    const term = start && end && start !== end ? `${start} – ${end}` : (start || end || '—');
    const durationNumber = Number.parseFloat(String(durationRaw).replace(',', '.'));
    const duration = Number.isFinite(durationNumber)
      ? `${durationNumber.toLocaleString('pl-PL', { maximumFractionDigits: 1 })} ${durationNumber === 1 ? 'dzień' : 'dni'}`
      : (durationRaw || '—');

    const actionNodes = Array.from(fields.querySelectorAll(':scope > a.btn, :scope > button.btn'));
    const backAction = actionNodes.find((node) => classifyAction(node) === 'back') || null;
    const specialDetails = Array.from(fields.querySelectorAll(
      '[data-hayne-caregiver-details], [data-hayne-force-majeure-details], [data-hayne-occasion-details], [data-hayne-holiday-compensation-details]'
    ));

    ensureReviewStyles();
    scope.dataset.hayneView = 'leave-detail-v2';
    scope.className = 'hayne-approval-review hayne-leave-review-surface';

    const wrap = document.getElementById('wrap');
    if (wrap) wrap.dataset.hayneTopbarTitle = 'Podgląd wniosku';

    const fragment = document.createDocumentFragment();

    const back = document.createElement('a');
    back.className = 'hayne-approval-review__back';
    back.href = backAction?.href || `${appRoot}leaves`;
    back.textContent = '← Wróć do listy';
    fragment.appendChild(back);

    const header = document.createElement('header');
    header.className = 'hayne-approval-review__header';
    const headerInner = document.createElement('div');
    const badge = document.createElement('span');
    badge.className = `hayne-approval-review__status hayne-approval-review__status--${statusClass(status)}`;
    badge.textContent = statusLabel || 'Wniosek';
    const h1 = document.createElement('h1');
    h1.textContent = 'Wniosek urlopowy';
    const meta = document.createElement('p');
    const metaParts = [];
    if (person) metaParts.push(person);
    if (requestId) metaParts.push(`wniosek #${requestId}`);
    meta.textContent = metaParts.join(' · ') || 'Szczegóły wniosku';
    headerInner.append(badge, h1, meta);
    header.appendChild(headerInner);
    fragment.appendChild(header);

    const layout = document.createElement('div');
    layout.className = 'hayne-approval-review__layout';

    const details = document.createElement('section');
    details.className = 'hayne-approval-review__card hayne-approval-review__details';
    const detailsTitle = document.createElement('h2');
    detailsTitle.textContent = 'Szczegóły wniosku';
    const summary = document.createElement('div');
    summary.className = 'hayne-approval-review__summary';
    summary.append(
      summaryItem('Rodzaj nieobecności', type),
      summaryItem('Termin', term),
      summaryItem('Liczba dni', duration),
      summaryItem('Status', statusLabel)
    );
    details.append(detailsTitle, summary);

    const hasCaregiverDetails = specialDetails.some((node) => node.hasAttribute('data-hayne-caregiver-details'));
    if (!hasCaregiverDetails) {
      const reason = document.createElement('div');
      reason.className = 'hayne-approval-review__reason';
      const reasonLabel = document.createElement('span');
      reasonLabel.textContent = 'Uzasadnienie';
      const reasonText = document.createElement('p');
      reasonText.textContent = cause || 'Nie podano uzasadnienia.';
      reason.append(reasonLabel, reasonText);
      details.appendChild(reason);
    }

    specialDetails.forEach((node) => {
      node.classList.add('hayne-leave-review-special');
      details.appendChild(node);
    });

    const aside = document.createElement('aside');
    aside.className = 'hayne-approval-review__decision';
    const actionCard = document.createElement('section');
    actionCard.className = 'hayne-approval-review__card hayne-approval-review__decision-card';
    const eyebrow = document.createElement('span');
    eyebrow.className = 'hayne-approval-review__eyebrow';
    eyebrow.textContent = 'Akcje';
    const actionTitle = document.createElement('h2');
    actionTitle.textContent = 'Twój wniosek';
    const actionCopy = document.createElement('p');
    actionCopy.textContent = statusLabel ? `Aktualny status: ${statusLabel}.` : 'Zarządzaj tym wnioskiem.';
    actionCard.append(eyebrow, actionTitle, actionCopy);

    actionNodes.filter((node) => node !== backAction).forEach((node) => {
      const kind = classifyAction(node);
      node.classList.remove('btn', 'btn-primary', 'btn-danger', 'btn-success', 'btn-warning');
      node.classList.add('hayne-approval-review__action');
      node.classList.add(kind === 'primary' ? 'hayne-approval-review__action--accept' : 'hayne-approval-review__action--secondary');
      actionCard.appendChild(node);
    });

    const backButton = document.createElement('a');
    backButton.className = 'hayne-approval-review__action hayne-approval-review__action--secondary hayne-leave-review-back-button';
    backButton.href = back.href;
    backButton.textContent = 'Wróć do listy';
    actionCard.appendChild(backButton);
    aside.appendChild(actionCard);

    const history = document.createElement('section');
    history.className = 'hayne-approval-review__card hayne-approval-review__history hayne-leave-review-history';
    const historyTitle = commentsPane.querySelector('h4');
    if (historyTitle) historyTitle.textContent = 'Historia i komentarze';
    commentsPane.classList.remove('span6');
    commentsPane.classList.add('hayne-leave-detail-card--comments');
    commentsPane.querySelectorAll('.row-fluid, .span12, .span8').forEach((node) => {
      node.classList.add('hayne-leave-detail-fluid');
    });
    commentsPane.querySelectorAll('textarea[name="comment"]').forEach((textarea) => {
      textarea.classList.add('hayne-leave-detail-comment-input');
      textarea.setAttribute('placeholder', 'Dodaj komentarz do wniosku…');
    });
    commentsPane.querySelectorAll('button[type="submit"].btn').forEach((button) => {
      button.classList.remove('btn-primary');
      button.classList.add('hayne-leave-detail-comment-submit');
    });
    history.appendChild(commentsPane);

    layout.append(details, aside, history);
    fragment.appendChild(layout);

    scope.replaceChildren(fragment);
    return true;
  };

  const isWhitespaceNode = (node) => node.nodeType === Node.TEXT_NODE
    && String(node.textContent || '').replace(/\u00a0/g, '').trim() === '';

  const removeLegacySpacing = (container) => {
    Array.from(container.childNodes).forEach((node) => {
      if (isWhitespaceNode(node) || (node.nodeType === Node.ELEMENT_NODE && node.tagName === 'BR')) node.remove();
    });
  };

  const enhanceEdit = (scope, form) => {
    const title = directChild(scope, (node) => node.tagName === 'H2');
    const row = form.querySelector(':scope > .row');
    if (!title || !row) return false;

    scope.dataset.hayneView = 'leave-detail-v1';
    scope.classList.add('hayne-leave-detail-page', 'hayne-leave-detail-page--edit');
    const wrap = document.getElementById('wrap');
    if (wrap) wrap.dataset.hayneTopbarTitle = 'Edycja wniosku';
    title.classList.add('hayne-leave-detail-title');

    const subtitle = document.createElement('p');
    subtitle.className = 'hayne-leave-detail-subtitle';
    subtitle.textContent = 'Zaktualizuj dane wniosku. Zmiany pozostają widoczne w historii.';
    title.insertAdjacentElement('afterend', subtitle);

    row.classList.add('hayne-leave-detail-layout');
    const panes = row.querySelectorAll(':scope > .span6');
    if (panes.length < 2) return false;
    const detailsPane = panes[0];
    const commentsPane = panes[1];
    detailsPane.classList.add('hayne-leave-detail-card', 'hayne-leave-detail-card--details');
    commentsPane.classList.add('hayne-leave-detail-card', 'hayne-leave-detail-card--comments');

    const fields = detailsPane.querySelector('.span8, .span12') || detailsPane;
    fields.classList.add('hayne-leave-detail-fields');
    const sectionTitle = document.createElement('h3');
    sectionTitle.className = 'hayne-leave-detail-card-title';
    sectionTitle.textContent = 'Dane wniosku';
    fields.insertBefore(sectionTitle, fields.firstChild);

    ['startdatetype', 'enddatetype'].forEach((name) => {
      const control = fields.querySelector(`select[name="${name}"]`);
      if (!control) return;
      control.classList.add('hayne-leave-detail-daypart-source');
      control.setAttribute('aria-hidden', 'true');
      control.tabIndex = -1;
    });
    fields.querySelectorAll('input[type="text"], select, textarea').forEach((control) => control.classList.add('hayne-leave-detail-control'));
    removeLegacySpacing(fields);

    commentsPane.querySelectorAll('textarea[name="comment"]').forEach((textarea) => {
      textarea.classList.add('hayne-leave-detail-comment-input');
      textarea.setAttribute('placeholder', 'Dodaj komentarz do wniosku…');
    });
    return true;
  };

  const findScope = (anchor, isEdit, form) => {
    let node = isEdit ? form.parentElement : anchor.parentElement;
    const wrap = document.getElementById('wrap');
    while (node) {
      const hasTitle = Boolean(directChild(node, (child) => child.tagName === 'H2'));
      const hasBody = isEdit
        ? Boolean(directChild(node, (child) => child === form))
        : Boolean(directChild(node, (child) => child.classList?.contains('row')));
      if (hasTitle && hasBody) return node;
      if (node === wrap) break;
      node = node.parentElement;
    }
    return null;
  };

  const enhance = () => {
    if (document.querySelector('[data-hayne-view="leave-create-v2"]')) return;
    if (document.querySelector('[data-hayne-view="leave-detail-v2"]')) return;

    const editForm = document.getElementById('frmLeaveForm');
    const commentForm = document.getElementById('frmLeaveNewCommentForm');
    const isEdit = Boolean(editForm);
    const isView = !isEdit && Boolean(commentForm);
    if (!isEdit && !isView) return;

    const scope = findScope(isEdit ? editForm : commentForm, isEdit, editForm);
    if (!scope) return;

    if (isView) enhanceView(scope);
    else enhanceEdit(scope, editForm);
  };

  const schedule = () => window.setTimeout(enhance, 0);
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', schedule, { once: true });
  else schedule();
})();
