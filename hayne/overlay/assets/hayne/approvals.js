(() => {
  const SVG_NS = 'http://www.w3.org/2000/svg';

  const iconPaths = {
    search: [
      ['circle', { cx: '11', cy: '11', r: '7' }],
      ['path', { d: 'm20 20-3.8-3.8' }],
    ],
    filter: [['path', { d: 'M4 5h16l-6.5 7.2v5.6l-3 1.7v-7.3Z' }]],
    calendar: [
      ['rect', { x: '3', y: '5', width: '18', height: '16', rx: '2' }],
      ['path', { d: 'M16 3v4M8 3v4M3 10h18' }],
    ],
  };

  const makeIcon = (name, className = '') => {
    const span = document.createElement('span');
    span.className = `hayne-approvals-icon${className ? ` ${className}` : ''}`;
    span.setAttribute('aria-hidden', 'true');
    const svg = document.createElementNS(SVG_NS, 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('focusable', 'false');
    (iconPaths[name] || []).forEach(([tag, attrs]) => {
      const node = document.createElementNS(SVG_NS, tag);
      Object.entries(attrs).forEach(([key, value]) => node.setAttribute(key, value));
      svg.appendChild(node);
    });
    span.appendChild(svg);
    return span;
  };

  const initialsFor = (name) => {
    const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return '—';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return `${parts[0][0] || ''}${parts[parts.length - 1][0] || ''}`.toUpperCase();
  };

  const rootFromBrand = () => {
    const brand = document.querySelector('.hayne-navbar-brand');
    if (!brand) return `${window.location.origin}/`;
    return brand.href.replace(/home\/?(?:[?#].*)?$/, '');
  };

  const localizeStatus = () => {
    const labels = {
      Planned: 'Plan',
      Accepted: 'Zaakceptowany',
      Requested: 'Oczekuje',
      Rejected: 'Odrzucony',
      Cancellation: 'Anulowanie',
      Canceled: 'Anulowany',
    };
    document.querySelectorAll('#leaves tbody td:nth-child(7) .label').forEach((node) => {
      const value = node.textContent.replace(/\s+/g, ' ').trim();
      if (labels[value]) node.textContent = labels[value];
    });
  };

  const enhanceEmployees = () => {
    document.querySelectorAll('#leaves tbody td:nth-child(2)').forEach((cell) => {
      if (cell.dataset.hayneEmployee === 'v1' || cell.classList.contains('dataTables_empty')) return;
      const name = cell.textContent.replace(/\s+/g, ' ').trim();
      cell.dataset.hayneEmployee = 'v1';
      cell.textContent = '';

      const avatar = document.createElement('span');
      avatar.className = 'hayne-approval-avatar';
      avatar.textContent = initialsFor(name);

      const label = document.createElement('span');
      label.className = 'hayne-approval-employee-name';
      label.textContent = name;

      cell.appendChild(avatar);
      cell.appendChild(label);
    });
  };

  const enhanceTypes = () => {
    document.querySelectorAll('#leaves tbody td:nth-child(6)').forEach((cell) => {
      if (cell.dataset.hayneType === 'v1' || cell.classList.contains('dataTables_empty')) return;
      const text = cell.textContent.replace(/\s+/g, ' ').trim();
      cell.dataset.hayneType = 'v1';
      cell.textContent = '';

      const badge = document.createElement('span');
      badge.className = 'hayne-approval-type-icon';
      badge.appendChild(makeIcon('calendar'));

      const label = document.createElement('span');
      label.className = 'hayne-approval-type-label';
      label.textContent = text;

      cell.appendChild(badge);
      cell.appendChild(label);
    });
  };

  const enhanceActions = () => {
    document.querySelectorAll('#leaves tbody td:first-child').forEach((cell) => {
      if (cell.dataset.hayneActions === 'v1' || cell.classList.contains('dataTables_empty')) return;
      cell.dataset.hayneActions = 'v1';
      const idLink = cell.querySelector(':scope > a:first-child');
      if (idLink) idLink.classList.add('hayne-approval-id-link');

      const menu = cell.querySelector('.pull-right');
      if (!menu) return;
      menu.classList.add('hayne-approval-actions');

      Array.from(menu.querySelectorAll(':scope > a')).forEach((link) => {
        if (link.classList.contains('lnkAccept') || link.classList.contains('lnkCancellationAccept')) {
          link.classList.add('hayne-approval-action', 'hayne-approval-action--accept');
          link.textContent = 'Akceptuj';
          return;
        }
        if (link.classList.contains('lnkReject') || link.classList.contains('lnkCancellationReject')) {
          link.classList.add('hayne-approval-action', 'hayne-approval-action--reject');
          link.textContent = 'Odrzuć';
          return;
        }
        if (link.classList.contains('show-history')) {
          link.classList.add('hayne-approval-action', 'hayne-approval-action--history');
          link.textContent = 'Historia';
        }
      });
    });
  };

  const stabilizeGrid = () => {
    const visible = new Set([0, 1, 2, 3, 4, 5, 6]);
    document.querySelectorAll('#leaves thead tr, #leaves tbody tr').forEach((row) => {
      Array.from(row.children).forEach((cell, index) => {
        cell.style.removeProperty('width');
        cell.style.removeProperty('min-width');
        cell.style.removeProperty('max-width');
        if (visible.has(index) || cell.classList.contains('dataTables_empty')) {
          cell.style.gridRow = '1';
        }
      });
    });
  };

  const localizeTableChrome = () => {
    const headers = ['Akcje', 'Pracownik', 'Data od', 'Data do', 'Dni', 'Typ', 'Status'];
    document.querySelectorAll('#leaves thead th').forEach((th, index) => {
      if (headers[index] && th.dataset.hayneHeader !== 'v1') {
        th.dataset.hayneHeader = 'v1';
        th.textContent = headers[index];
      }
    });

    const empty = document.querySelector('#leaves tbody td.dataTables_empty');
    if (empty) empty.textContent = 'Brak wniosków wymagających działania';

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

  const enhanceRows = () => {
    stabilizeGrid();
    enhanceEmployees();
    enhanceTypes();
    enhanceActions();
    localizeStatus();
    localizeTableChrome();
  };

  const moveSearch = (toolbarRight) => {
    const filter = document.getElementById('leaves_filter');
    if (!filter) return;
    filter.classList.add('hayne-approvals-search');
    const label = filter.querySelector('label');
    const input = filter.querySelector('input');
    if (label) {
      Array.from(label.childNodes).forEach((node) => {
        if (node.nodeType === Node.TEXT_NODE) node.remove();
      });
    }
    if (input) {
      input.placeholder = 'Szukaj pracownika lub wniosku...';
      input.setAttribute('aria-label', 'Szukaj wniosków do akceptacji');
    }
    toolbarRight.insertBefore(filter, toolbarRight.firstChild);
  };

  const buildPage = (table) => {
    const wrapper = document.getElementById('leaves_wrapper');
    const root = wrapper ? wrapper.parentElement : table.parentElement;
    if (!root) return null;
    const oldTitle = root.querySelector(':scope > h2');
    if (!oldTitle) return null;

    const page = document.createElement('main');
    page.className = 'hayne-approvals-page';
    page.dataset.hayneView = 'approvals-v1';
    root.insertBefore(page, oldTitle);

    const header = document.createElement('header');
    header.className = 'hayne-approvals-header';
    header.innerHTML = '<div><h1>Do akceptacji</h1><p>Rozpatruj wnioski urlopowe i prośby o anulowanie od osób, za które odpowiadasz.</p></div>';
    page.appendChild(header);

    oldTitle.remove();

    const oldDescription = Array.from(root.children).find((node) => node.tagName === 'P');
    if (oldDescription) oldDescription.remove();

    Array.from(root.querySelectorAll(':scope > .alert')).forEach((alert) => page.appendChild(alert));

    const card = document.createElement('section');
    card.className = 'hayne-approvals-card';
    page.appendChild(card);

    const legacyFilterRow = Array.from(root.querySelectorAll(':scope > .row')).find((row) => row.querySelector('#cboLeaveType'));
    const legacyActionRow = Array.from(root.querySelectorAll(':scope > .row-fluid')).find((row) => row.querySelector('a[href*="requests/export"]'));

    const toolbar = document.createElement('div');
    toolbar.className = 'hayne-approvals-toolbar';

    const tabs = document.createElement('nav');
    tabs.className = 'hayne-approvals-tabs';
    tabs.setAttribute('aria-label', 'Widok wniosków');
    const showAll = !document.getElementById('chkPlanned')?.disabled;
    const rootUrl = rootFromBrand();
    [
      ['Oczekujące', 'requests/requested', !showAll],
      ['Wszystkie', 'requests/all', showAll],
    ].forEach(([label, path, active]) => {
      const link = document.createElement('a');
      link.href = `${rootUrl}${path}`;
      link.className = `hayne-approvals-tab${active ? ' is-active' : ''}`;
      link.textContent = label;
      if (active) link.setAttribute('aria-current', 'page');
      tabs.appendChild(link);
    });

    const toolbarRight = document.createElement('div');
    toolbarRight.className = 'hayne-approvals-toolbar-right';

    const filters = document.createElement('details');
    filters.className = 'hayne-approvals-filter-menu';
    const summary = document.createElement('summary');
    summary.appendChild(makeIcon('filter'));
    const text = document.createElement('span');
    text.textContent = 'Filtry';
    summary.appendChild(text);
    const chevron = document.createElement('span');
    chevron.className = 'hayne-approvals-filter-chevron';
    chevron.textContent = '⌄';
    summary.appendChild(chevron);
    filters.appendChild(summary);

    const panel = document.createElement('div');
    panel.className = 'hayne-approvals-filter-panel';
    if (legacyFilterRow) {
      const typeFilter = legacyFilterRow.querySelector('.span3');
      const statuses = legacyFilterRow.querySelector('.span8');
      if (typeFilter) {
        typeFilter.className = 'hayne-approvals-type-filter';
        const label = typeFilter.querySelector('label[for="cboLeaveType"]');
        if (label) {
          Array.from(label.childNodes).forEach((node) => {
            if (node.nodeType === Node.TEXT_NODE) node.remove();
          });
          label.insertBefore(document.createTextNode('Rodzaj nieobecności'), label.firstChild);
        }
        panel.appendChild(typeFilter);
      }
      if (statuses && showAll) {
        statuses.className = 'hayne-approvals-status-filters';
        panel.appendChild(statuses);
      }
      legacyFilterRow.remove();
    }

    if (legacyActionRow) {
      legacyActionRow.className = 'hayne-approvals-secondary-actions';
      panel.appendChild(legacyActionRow);
    }
    filters.appendChild(panel);
    toolbarRight.appendChild(filters);

    toolbar.appendChild(tabs);
    toolbar.appendChild(toolbarRight);
    card.appendChild(toolbar);

    if (wrapper) card.appendChild(wrapper);
    moveSearch(toolbarRight);

    Array.from(root.querySelectorAll(':scope > .row-fluid')).forEach((row) => {
      if (!row.querySelector('.modal') && row.textContent.replace(/\u00a0/g, '').trim() === '') row.remove();
    });

    return page;
  };

  const enhance = () => {
    const table = document.getElementById('leaves');
    const rejectForm = document.getElementById('frmRejectLeaveForm');
    if (!table || !rejectForm || document.querySelector('[data-hayne-view="approvals-v1"]')) return;
    if (!document.getElementById('leaves_wrapper')) {
      window.setTimeout(enhance, 40);
      return;
    }

    const wrap = document.getElementById('wrap');
    if (wrap) wrap.setAttribute('data-hayne-topbar-title', 'Do akceptacji');

    const page = buildPage(table);
    if (!page) return;
    moveSearch(page.querySelector('.hayne-approvals-toolbar-right'));
    enhanceRows();

    const host = page.parentElement;
    if (host) {
      host.classList.remove('hayne-approvals-pending');
      host.removeAttribute('aria-busy');
    }

    if (window.jQuery) {
      window.jQuery('#leaves').on('draw.dt', () => window.setTimeout(enhanceRows, 0));
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => window.setTimeout(enhance, 0), { once: true });
  } else {
    window.setTimeout(enhance, 0);
  }
})();
