(() => {
  const normalizePath = (value) => String(value || '').replace(/^\/+|\/+$/g, '');

  const currentSurface = () => {
    if (document.querySelector('[data-hayne-home]')) return 'home';
    if (document.querySelector('[data-hayne-view="leave-create-v2"]')) return 'leaves/create';
    if (document.querySelector('[data-hayne-view="my-requests-v2"]')) return 'leaves';
    if (document.querySelector('table.table-bordered.table-hover') && /balance|saldo/i.test(document.title)) return 'leaves/counters';
    return normalizePath(window.location.pathname);
  };

  const icon = (name) => {
    const node = document.createElement('i');
    node.className = `mdi ${name}`;
    node.setAttribute('aria-hidden', 'true');
    return node;
  };

  const initialsFor = (name) => {
    const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return 'HL';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return `${parts[0][0] || ''}${parts[parts.length - 1][0] || ''}`.toUpperCase();
  };

  const enhanceAccount = (wrap) => {
    const account = wrap.querySelector('.nav.pull-right > .brand');
    if (!account || account.querySelector('.hayne-user-avatar')) return;

    const name = account.textContent.replace(/\s+/g, ' ').trim();
    account.textContent = '';

    const avatar = document.createElement('span');
    avatar.className = 'hayne-user-avatar';
    avatar.textContent = initialsFor(name);

    const label = document.createElement('span');
    label.className = 'hayne-user-name';
    label.textContent = name;

    account.appendChild(avatar);
    account.appendChild(label);
  };

  const addSidebarFooter = (wrap) => {
    const navbarInner = wrap.querySelector('> .navbar .navbar-inner');
    if (!navbarInner || navbarInner.querySelector('.hayne-sidebar-footer')) return;

    const footer = document.createElement('div');
    footer.className = 'hayne-sidebar-footer';
    footer.appendChild(icon('mdi-shield-check'));

    const title = document.createElement('strong');
    title.textContent = 'HAYNE Leave';
    footer.appendChild(title);

    const version = document.createElement('small');
    version.textContent = 'Wersja 1.0.0';
    footer.appendChild(version);

    navbarInner.appendChild(footer);
  };

  const initNavigation = () => {
    const wrap = document.getElementById('wrap');
    if (!wrap) return;

    enhanceAccount(wrap);
    addSidebarFooter(wrap);

    const nav = wrap.querySelector('.nav-responsive > ul.nav:not(.pull-right)');
    if (!nav || nav.dataset.hayneNavigation === 'target-v1') return;

    const brand = wrap.querySelector('.hayne-navbar-brand');
    const brandHref = brand ? brand.href : `${window.location.origin}/home`;
    const root = brandHref.replace(/home\/?(?:[?#].*)?$/, '');
    const surface = currentSurface();

    const directItems = [
      { path: 'home', label: 'Start', icon: 'mdi-home' },
      { path: 'leaves/create', label: 'Nowy wniosek', icon: 'mdi-plus-box-outline' },
      { path: 'leaves', label: 'Moje wnioski', icon: 'mdi-file-document-outline' },
      { path: 'leaves/counters', label: 'Saldo urlopowe', icon: 'mdi-calendar-blank' },
    ];

    const fragment = document.createDocumentFragment();
    directItems.forEach((item) => {
      const li = document.createElement('li');
      li.className = `hayne-nav-direct hayne-nav-${item.path.replace(/\//g, '-')}`;
      if (surface === item.path) li.classList.add('is-active');

      const link = document.createElement('a');
      link.href = `${root}${item.path}`;
      link.appendChild(icon(item.icon));

      const label = document.createElement('span');
      label.textContent = item.label;
      link.appendChild(label);

      li.appendChild(link);
      fragment.appendChild(li);
    });
    nav.insertBefore(fragment, nav.firstChild);

    const groups = [
      {
        label: 'Kalendarz',
        className: 'hayne-nav-calendar',
        icon: 'mdi-calendar-blank',
        active: surface.startsWith('calendar'),
      },
      {
        label: 'Zespół',
        className: 'hayne-nav-team',
        icon: 'mdi-account-multiple-outline',
        active: /^(hr|organization|contracts|positions|reports)(\/|$)/.test(surface),
      },
      {
        label: 'Do akceptacji',
        className: 'hayne-nav-approvals',
        icon: 'mdi-check-circle-outline',
        active: /^(requests|overtime)(\/|$)/.test(surface),
      },
      {
        label: 'Administracja',
        className: 'hayne-nav-admin',
        icon: 'mdi-settings-outline',
        active: /^(admin|users|leavetypes)(\/|$)/.test(surface) && !surface.startsWith('users/myprofile'),
      },
    ];

    Array.from(nav.children).forEach((li) => {
      if (!li.classList.contains('dropdown')) return;
      const toggle = li.querySelector(':scope > a.dropdown-toggle');
      if (!toggle) return;
      const text = toggle.textContent.replace(/\s+/g, ' ').trim();

      if (text.startsWith('Urlopy')) {
        li.classList.add('hayne-nav-legacy-requests');
        return;
      }

      const group = groups.find((candidate) => text.startsWith(candidate.label));
      if (!group) return;

      li.classList.add('hayne-nav-group', group.className);
      if (group.active) li.classList.add('is-active');
      if (!toggle.querySelector('.mdi')) toggle.insertBefore(icon(group.icon), toggle.firstChild);

      const labelNode = document.createElement('span');
      labelNode.className = 'hayne-nav-label';
      labelNode.textContent = group.label;

      Array.from(toggle.childNodes).forEach((child) => {
        if (child.nodeType === Node.TEXT_NODE) child.remove();
      });
      const caret = toggle.querySelector('.caret');
      if (caret) toggle.insertBefore(labelNode, caret);
      else toggle.appendChild(labelNode);
    });

    const legacyListItem = Array.from(nav.children).find((li) => {
      if (li.classList.contains('hayne-nav-direct')) return false;
      const link = li.querySelector(':scope > a');
      return link && /\/leaves\/?(?:[?#].*)?$/.test(link.href) && !link.textContent.trim();
    });
    if (legacyListItem) legacyListItem.classList.add('hayne-nav-legacy-list');

    Array.from(nav.children).forEach((li) => {
      if (li.querySelector(':scope > .navbar-form')) li.classList.add('hayne-nav-legacy-create');
    });

    nav.dataset.hayneNavigation = 'target-v1';
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNavigation, { once: true });
  } else {
    initNavigation();
  }
})();
