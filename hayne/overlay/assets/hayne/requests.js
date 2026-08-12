(() => {
  const SVG_NS = 'http://www.w3.org/2000/svg';

  const icon = (name) => {
    const span = document.createElement('span');
    span.className = 'hayne-requests-icon';
    span.setAttribute('aria-hidden', 'true');
    const svg = document.createElementNS(SVG_NS, 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('focusable', 'false');
    const paths = {
      filter: [
        ['path', { d: 'M4 5h16l-6.5 7.2v5.6l-3 1.7v-7.3Z' }],
      ],
      calendar: [
        ['rect', { x: '3', y: '5', width: '18', height: '16', rx: '2' }],
        ['path', { d: 'M16 3v4M8 3v4M3 10h18' }],
      ],
    };
    (paths[name] || []).forEach(([tag, attrs]) => {
      const node = document.createElementNS(SVG_NS, tag);
      Object.entries(attrs).forEach(([key, value]) => node.setAttribute(key, value));
      svg.appendChild(node);
    });
    span.appendChild(svg);
    return span;
  };

  const statusInputs = {
    planned: '#chkPlanned',
    accepted: '#chkAccepted',
    requested: '#chkRequested',
    rejected: '#chkRejected',
    cancellation: '#chkCancellation',
    canceled: '#chkCanceled',
  };

  const setStatuses = (enabled) => {
    Object.entries(statusInputs).forEach(([key, selector]) => {
      const input = document.querySelector(selector);
      if (input) input.checked = enabled.includes(key);
    });

    // The legacy Jorani filter is wired with jQuery while the HAYNE tabs use
    // native listeners. Dispatch a real DOM change event so both listener
    // systems receive the same state transition.
    const trigger = document.querySelector('#chkPlanned');
    if (trigger) {
      trigger.dispatchEvent(new Event('change', { bubbles: true }));
    }
  };

  const checkedStatuses = () => Object.entries(statusInputs)
    .filter(([, selector]) => {
      const input = document.querySelector(selector);
      return input && input.checked;
    })
    .map(([key]) => key);

  const sameSet = (left, right) => left.length === right.length && left.every((item) => right.includes(item));

  const actionLabel = (link) => {
    const href = String(link.getAttribute('href') || '');
    if (link.classList.contains('confirm-delete')) return 'Usuń';
    if (link.classList.contains('show-history')) return 'Historia';
    if (/\/edit\//.test(href)) return 'Edytuj';
    if (/\/reminder\//.test(href)) return 'Wyślij przypomnienie';
    if (/\/cancellation\//.test(href) || /\/cancel\//.test(href)) return 'Anuluj';
    if (/\/leaves\/leaves\//.test(href)) return 'Szczegóły';
    return link.getAttribute('title') || 'Otwórz';
  };

  const enhanceRowActions = () => {
    document.querySelectorAll('#leaves tbody td:first-child .pull-right').forEach((menu) => {
      if (menu.dataset.hayneActions === 'v1') return;
      menu.dataset.hayneActions = 'v1';
      menu.classList.add('hayne-row-actions');

      const links = Array.from(menu.querySelectorAll(':scope > a'));
      const toggle = document.createElement('button');
      toggle.type = 'button';
      toggle.className = 'hayne-row-actions__toggle';
      toggle.setAttribute('aria-label', 'Działania dla wniosku');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.textContent = '•••';

      const popover = document.createElement('div');
      popover.className = 'hayne-row-actions__popover';
      links.forEach((link) => {
        link.classList.add('hayne-row-actions__item');
        link.textContent = actionLabel(link);
        popover.appendChild(link);
      });

      toggle.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        const open = menu.classList.toggle('is-open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      });

      menu.appendChild(toggle);
      menu.appendChild(popover);
    });
  };

  const enhanceStatusCells = () => {
    const labels = {
      Planned: 'Plan',
      Plan: 'Plan',
      Accepted: 'Zaakceptowany',
      Zaakceptowane: 'Zaakceptowany',
      Requested: 'Oczekuje',
      Oczekujące: 'Oczekuje',
      Rejected: 'Odrzucony',
      Odrzucone: 'Odrzucony',
      Cancellation: 'Anulowanie',
      Anulowanie: 'Anulowanie',
      Canceled: 'Anulowany',
      Anulowane: 'Anulowany',
    };
    document.querySelectorAll('#leaves tbody td:nth-child(7) .label').forEach((node) => {
      const current = node.textContent.replace(/\s+/g, ' ').trim();
      if (labels[current]) node.textContent = labels[current];
    });
  };

  const enhanceTypeCells = () => {
    document.querySelectorAll('#leaves tbody td:nth-child(6)').forEach((cell) => {
      if (cell.dataset.hayneType === 'v1' || cell.classList.contains('dataTables_empty')) return;
      cell.dataset.hayneType = 'v1';
      const label = document.createElement('span');
      label.className = 'hayne-request-type-label';
      label.textContent = cell.textContent.replace(/\s+/g, ' ').trim();
      cell.textContent = '';
      const badge = document.createElement('span');
      badge.className = 'hayne-request-type-icon';
      badge.appendChild(icon('calendar'));
      cell.appendChild(badge);
      cell.appendChild(label);
    });
  };

  const stabilizeGridRows = () => {
    const visibleIndexes = new Set([0, 1, 2, 4, 5, 6]);
    document.querySelectorAll('#leaves thead tr, #leaves tbody tr').forEach((row) => {
      Array.from(row.children).forEach((cell, index) => {
        cell.style.removeProperty('width');
        cell.style.removeProperty('min-width');
        cell.style.removeProperty('max-width');
        if (visibleIndexes.has(index) || cell.classList.contains('dataTables_empty')) {
          cell.style.gridRow = '1';
        }
      });
    });
  };

  const localizeDataTablesChrome = () => {
    const empty = document.querySelector('#leaves tbody td.dataTables_empty');
    if (empty) empty.textContent = 'Brak wniosków do wyświetlenia';

    const infoNode = document.getElementById('leaves_info');
    if (infoNode && window.jQuery && window.jQuery.fn.dataTable.isDataTable('#leaves')) {
      const info = window.jQuery('#leaves').DataTable().page.info();
      infoNode.textContent = info.recordsDisplay === 0
        ? '0 wniosków'
        : `${info.start + 1}–${info.end} z ${info.recordsDisplay} wniosków`;
    }

    const previous = document.querySelector('#leaves_previous');
    const next = document.querySelector('#leaves_next');
    if (previous) {
      previous.textContent = '‹';
      previous.setAttribute('aria-label', 'Poprzednia strona');
    }
    if (next) {
      next.textContent = '›';
      next.setAttribute('aria-label', 'Następna strona');
    }
  };

  const syncStatusTabs = (tabs, filterDetails) => {
    const current = checkedStatuses();
    const presets = {
      all: ['planned', 'accepted', 'requested', 'rejected', 'cancellation', 'canceled'],
      requested: ['requested'],
      accepted: ['accepted'],
      planned: ['planned'],
      rejected: ['rejected'],
    };
    let matched = false;
    tabs.querySelectorAll('[data-status-preset]').forEach((button) => {
      const active = sameSet(current, presets[button.dataset.statusPreset] || []);
      button.classList.toggle('is-active', active);
      button.setAttribute('aria-pressed', active ? 'true' : 'false');
      if (active) matched = true;
    });
    filterDetails.classList.toggle('has-custom-filter', !matched);
  };

  const cleanSearch = (toolbarRight) => {
    const filter = document.getElementById('leaves_filter');
    if (!filter) return;
    filter.classList.add('hayne-requests-search');
    const label = filter.querySelector('label');
    const input = filter.querySelector('input');
    if (label) {
      Array.from(label.childNodes).forEach((node) => {
        if (node.nodeType === Node.TEXT_NODE) node.remove();
      });
    }
    if (input) {
      input.placeholder = 'Szukaj wniosków...';
      input.setAttribute('aria-label', 'Szukaj wniosków');
    }
    toolbarRight.insertBefore(filter, toolbarRight.firstChild);
  };

  const enhanceTablePresentation = () => {
    stabilizeGridRows();
    enhanceRowActions();
    enhanceStatusCells();
    enhanceTypeCells();
    localizeDataTablesChrome();
  };

  const enhance = () => {
    const page = document.querySelector('[data-hayne-view="my-requests-v2"]');
    if (!page || page.dataset.hayneRequestsEnhanced === 'target-v1') return;
    if (!document.getElementById('leaves_wrapper')) {
      window.setTimeout(enhance, 30);
      return;
    }

    page.dataset.hayneRequestsEnhanced = 'target-v1';
    const wrap = document.getElementById('wrap');
    if (wrap) wrap.setAttribute('data-hayne-topbar-title', '');

    const card = page.querySelector('.hayne-requests-card');
    const filters = page.querySelector('.hayne-requests-filters');
    const typeFilter = page.querySelector('.hayne-requests-type-filter');
    const legacyStatuses = page.querySelector('.hayne-status-filters');
    const legacyActions = page.querySelector('.hayne-requests-actions');
    if (!card || !filters || !legacyStatuses) return;

    const toolbar = document.createElement('div');
    toolbar.className = 'hayne-requests-target-toolbar';

    const tabs = document.createElement('nav');
    tabs.className = 'hayne-requests-status-tabs';
    tabs.setAttribute('aria-label', 'Status wniosku');
    [
      ['all', 'Wszystkie'],
      ['requested', 'Oczekujące'],
      ['accepted', 'Zaakceptowane'],
      ['planned', 'Plan'],
      ['rejected', 'Odrzucone'],
    ].forEach(([preset, text]) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'hayne-status-tab';
      button.dataset.statusPreset = preset;
      button.textContent = text;
      button.addEventListener('click', () => {
        const values = {
          all: ['planned', 'accepted', 'requested', 'rejected', 'cancellation', 'canceled'],
          requested: ['requested'],
          accepted: ['accepted'],
          planned: ['planned'],
          rejected: ['rejected'],
        };
        setStatuses(values[preset]);
        syncStatusTabs(tabs, filterDetails);
      });
      tabs.appendChild(button);
    });

    const toolbarRight = document.createElement('div');
    toolbarRight.className = 'hayne-requests-toolbar-right';

    const filterDetails = document.createElement('details');
    filterDetails.className = 'hayne-requests-filter-menu';
    const summary = document.createElement('summary');
    summary.appendChild(icon('filter'));
    const summaryText = document.createElement('span');
    summaryText.textContent = 'Filtry';
    summary.appendChild(summaryText);
    const chevron = document.createElement('span');
    chevron.className = 'hayne-requests-filter-chevron';
    chevron.textContent = '⌄';
    summary.appendChild(chevron);
    filterDetails.appendChild(summary);

    const filterPanel = document.createElement('div');
    filterPanel.className = 'hayne-requests-filter-panel';
    if (typeFilter) filterPanel.appendChild(typeFilter);
    legacyStatuses.classList.add('hayne-advanced-statuses');
    filterPanel.appendChild(legacyStatuses);
    if (legacyActions) filterPanel.appendChild(legacyActions);
    filterDetails.appendChild(filterPanel);
    toolbarRight.appendChild(filterDetails);

    toolbar.appendChild(tabs);
    toolbar.appendChild(toolbarRight);
    card.insertBefore(toolbar, card.firstChild);
    filters.remove();

    cleanSearch(toolbarRight);
    syncStatusTabs(tabs, filterDetails);

    document.querySelectorAll('.filterStatus').forEach((input) => {
      input.addEventListener('change', () => window.setTimeout(() => syncStatusTabs(tabs, filterDetails), 0));
    });

    if (window.jQuery) {
      window.jQuery('#leaves').on('draw.dt', () => window.setTimeout(enhanceTablePresentation, 0));
    }
    enhanceTablePresentation();

    document.addEventListener('click', (event) => {
      document.querySelectorAll('.hayne-row-actions.is-open').forEach((menu) => {
        if (!menu.contains(event.target)) {
          menu.classList.remove('is-open');
          const button = menu.querySelector('.hayne-row-actions__toggle');
          if (button) button.setAttribute('aria-expanded', 'false');
        }
      });
    });
  };

  if (document.readyState === 'complete') {
    window.setTimeout(enhance, 0);
  } else {
    window.addEventListener('load', () => window.setTimeout(enhance, 0), { once: true });
  }
})();
