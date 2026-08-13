(() => {
  const isWhitespaceNode = (node) => node.nodeType === Node.TEXT_NODE
    && String(node.textContent || '').replace(/\u00a0/g, '').trim() === '';

  const removeLegacySpacing = (container) => {
    Array.from(container.childNodes).forEach((node) => {
      if (isWhitespaceNode(node)) {
        node.remove();
        return;
      }
      if (node.nodeType === Node.ELEMENT_NODE && node.tagName === 'BR') {
        node.remove();
      }
    });
  };

  const classifyAction = (node) => {
    const text = String(node.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
    const href = node.getAttribute('href') || '';
    const value = node.getAttribute('value') || '';

    if (
      href.includes('/cancellation/')
      || href.includes('/request/')
      || text.includes('anulowanie')
      || text.includes('wyślij')
      || text.includes('aktualizuj')
      || text.includes('zapisz')
      || value === '2'
    ) {
      return 'primary';
    }

    if (
      href.includes('/edit/')
      || text.includes('edytuj')
      || text.includes('plan')
      || value === '1'
    ) {
      return 'secondary';
    }

    if (
      node.classList.contains('btn-danger')
      || text.includes('wróć')
      || text.includes('anuluj')
      || text.includes('cancel')
    ) {
      return 'secondary';
    }

    return 'secondary';
  };

  const collectActions = (container) => {
    const candidates = Array.from(container.children).filter((node) => {
      if (!(node instanceof HTMLElement)) return false;
      return node.matches('a.btn, button.btn');
    });

    if (!candidates.length) return;

    const actions = document.createElement('div');
    actions.className = 'hayne-leave-detail-actions';

    candidates.forEach((node) => {
      const kind = classifyAction(node);
      node.classList.remove('btn-primary', 'btn-danger', 'btn-success', 'btn-warning');
      node.classList.add('hayne-leave-detail-action', `hayne-leave-detail-action--${kind}`);
      actions.appendChild(node);
    });

    container.appendChild(actions);
  };

  const normalizeViewControls = (container) => {
    container.querySelectorAll('input[readonly], textarea[readonly], select[readonly]').forEach((control) => {
      control.classList.add('hayne-leave-detail-readonly');
      control.setAttribute('aria-readonly', 'true');
    });

    const duration = container.querySelector('input[name="duration"]');
    if (duration && duration.hasAttribute('readonly')) {
      const numeric = Number.parseFloat(duration.value);
      if (Number.isFinite(numeric)) {
        duration.value = Number.isInteger(numeric)
          ? `${numeric} dni`
          : `${numeric.toLocaleString('pl-PL', { maximumFractionDigits: 1 })} dnia`;
      }
    }

    const status = container.querySelector('select[name="status"]');
    if (status) status.classList.add('hayne-leave-detail-status');
  };

  const normalizeEditControls = (container) => {
    container.querySelectorAll('input[type="text"], select, textarea').forEach((control) => {
      control.classList.add('hayne-leave-detail-control');
    });

    const status = container.querySelector('select[name="status"]');
    if (status) status.classList.add('hayne-leave-detail-status');
  };

  const hideDayParts = (container) => {
    ['startdatetype', 'enddatetype'].forEach((name) => {
      const control = container.querySelector(`select[name="${name}"]`);
      if (!control) return;
      control.classList.add('hayne-leave-detail-daypart-source');
      control.setAttribute('aria-hidden', 'true');
      control.tabIndex = -1;
    });
  };

  const enhanceComments = (pane) => {
    const heading = pane.querySelector('h4');
    if (heading) {
      heading.classList.add('hayne-leave-detail-card-title');
      heading.textContent = 'Historia i komentarze';
    }

    const accordion = pane.querySelector('#accordion');
    if (accordion) accordion.classList.add('hayne-leave-detail-timeline');

    pane.querySelectorAll('.accordion-group').forEach((group) => {
      group.classList.add('hayne-leave-detail-timeline-item');
    });

    pane.querySelectorAll('textarea[name="comment"]').forEach((textarea) => {
      textarea.classList.add('hayne-leave-detail-comment-input');
      if (!textarea.getAttribute('placeholder')) {
        textarea.setAttribute('placeholder', 'Dodaj komentarz do wniosku…');
      }
    });

    pane.querySelectorAll('button[type="submit"].btn').forEach((button) => {
      button.classList.remove('btn-primary');
      button.classList.add('hayne-leave-detail-comment-submit');
    });
  };

  const directChild = (parent, predicate) => Array.from(parent?.children || []).find(predicate) || null;

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
    if (document.querySelector('[data-hayne-view="leave-detail-v1"]')) return;

    const editForm = document.getElementById('frmLeaveForm');
    const commentForm = document.getElementById('frmLeaveNewCommentForm');
    const isEdit = Boolean(editForm);
    const isView = !isEdit && Boolean(commentForm);
    if (!isEdit && !isView) return;

    const form = isEdit ? editForm : null;
    const scope = findScope(isEdit ? form : commentForm, isEdit, form);
    if (!scope) return;

    const title = directChild(scope, (node) => node.tagName === 'H2');
    const row = isEdit
      ? form.querySelector(':scope > .row')
      : directChild(scope, (node) => node.classList?.contains('row'));
    if (!title || !row) return;

    scope.dataset.hayneView = 'leave-detail-v1';
    scope.classList.add('hayne-leave-detail-page', isEdit ? 'hayne-leave-detail-page--edit' : 'hayne-leave-detail-page--view');

    const wrap = document.getElementById('wrap');
    if (wrap) wrap.dataset.hayneTopbarTitle = isEdit ? 'Edycja wniosku' : 'Podgląd wniosku';

    title.classList.add('hayne-leave-detail-title');

    const subtitle = document.createElement('p');
    subtitle.className = 'hayne-leave-detail-subtitle';
    subtitle.textContent = isEdit
      ? 'Zaktualizuj dane wniosku. Zmiany pozostają widoczne w historii.'
      : 'Sprawdź szczegóły wniosku, jego status oraz historię zmian.';
    title.insertAdjacentElement('afterend', subtitle);

    row.classList.add('hayne-leave-detail-layout');
    const panes = row.querySelectorAll(':scope > .span6');
    if (panes.length < 2) return;

    const detailsPane = panes[0];
    const commentsPane = panes[1];
    detailsPane.classList.add('hayne-leave-detail-card', 'hayne-leave-detail-card--details');
    commentsPane.classList.add('hayne-leave-detail-card', 'hayne-leave-detail-card--comments');

    const fields = detailsPane.querySelector('.span8, .span12') || detailsPane;
    fields.classList.add('hayne-leave-detail-fields');

    const sectionTitle = document.createElement('h3');
    sectionTitle.className = 'hayne-leave-detail-card-title';
    sectionTitle.textContent = isEdit ? 'Dane wniosku' : 'Szczegóły wniosku';
    fields.insertBefore(sectionTitle, fields.firstChild);

    hideDayParts(fields);
    if (isView) normalizeViewControls(fields);
    if (isEdit) normalizeEditControls(fields);
    removeLegacySpacing(fields);
    collectActions(fields);
    enhanceComments(commentsPane);

    commentsPane.querySelectorAll('.row-fluid, .span12, .span8').forEach((node) => {
      node.classList.add('hayne-leave-detail-fluid');
    });
  };

  const schedule = () => window.setTimeout(enhance, 0);
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', schedule, { once: true });
  } else {
    schedule();
  }
})();
