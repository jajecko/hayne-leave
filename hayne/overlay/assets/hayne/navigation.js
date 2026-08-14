(() => {
  const normalizePath = (value) => String(value || '').replace(/^\/+|\/+$/g, '');

  const svgPaths = {
    home: '<path d="M3 10.5 12 3l9 7.5v9a1.5 1.5 0 0 1-1.5 1.5h-5v-6h-5v6h-5A1.5 1.5 0 0 1 3 19.5z"/>',
    plus: '<rect x="4" y="4" width="16" height="16" rx="2"/><path d="M12 8v8M8 12h8"/>',
    file: '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v5h5M9 12h6M9 16h6"/>',
    calendar: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/>',
    team: '<circle cx="9" cy="8" r="3"/><path d="M3.5 20c.4-4 2.4-6 5.5-6s5.1 2 5.5 6"/><circle cx="17" cy="9" r="2.3"/><path d="M15.5 14.5c3.2-.7 5.1 1.1 5.5 4"/>',
    approval: '<circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16.5 9"/>',
    settings: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1a1.7 1.7 0 0 0 1.9.3A1.7 1.7 0 0 0 10 3V2.8h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1z"/>',
    shield: '<path d="M12 3 20 6v5c0 5.1-3.1 8.3-8 10-4.9-1.7-8-4.9-8-10V6z"/><path d="m8.5 12 2.2 2.2 4.8-5"/>',
    chevron: '<path d="m8 10 4 4 4-4"/>',
    user: '<circle cx="12" cy="8" r="3"/><path d="M5.5 20c.4-4.2 2.6-6.2 6.5-6.2s6.1 2 6.5 6.2"/>',
    lock: '<rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
    logout: '<path d="M10 5H5v14h5M14 8l4 4-4 4M18 12H9"/>',
    menu: '<path d="M4 7h16M4 12h16M4 17h16"/>',
    close: '<path d="m6 6 12 12M18 6 6 18"/>'
  };

  const icon = (name, className = '') => {
    const node = document.createElement('span');
    node.className = `hayne-line-icon${className ? ` ${className}` : ''}`;
    node.setAttribute('aria-hidden', 'true');
    node.innerHTML = `<svg viewBox="0 0 24 24" focusable="false">${svgPaths[name] || ''}</svg>`;
    return node;
  };

  const currentSurface = () => {
    if (document.querySelector('[data-hayne-home]')) return 'home';
    if (document.querySelector('[data-hayne-view="leave-create-v2"]')) return 'leaves/create';
    if (document.querySelector('[data-hayne-view="my-requests-v2"]')) return 'leaves';
    if (document.querySelector('[data-hayne-view="leave-balance-v1"]')) return 'leaves/counters';
    if (document.querySelector('[data-hayne-view="calendar-individual-v1"]')) return 'calendar/individual';
    if (document.querySelector('table.table-bordered.table-hover') && /balance|saldo|summary/i.test(document.title)) return 'leaves/counters';
    return normalizePath(window.location.pathname);
  };

  const initialsFor = (name) => {
    const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return 'HL';
    if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
    return `${parts[0][0] || ''}${parts[parts.length - 1][0] || ''}`.toUpperCase();
  };

  const enhanceAccount = (wrap) => {
    const list = wrap.querySelector('.nav.pull-right');
    const account = list ? list.querySelector(':scope > .brand') : null;
    if (!list || !account || list.querySelector('.hayne-user-menu')) return;

    const name = account.textContent.replace(/\s+/g, ' ').trim();
    const profileHref = account.href;
    const originalActions = Array.from(list.children).filter((node) => node.tagName === 'LI');

    const menuItem = document.createElement('li');
    menuItem.className = 'dropdown hayne-user-menu';

    const toggle = document.createElement('a');
    toggle.href = '#';
    toggle.className = 'brand dropdown-toggle hayne-user-toggle';
    toggle.setAttribute('data-toggle', 'dropdown');
    toggle.setAttribute('aria-label', name || 'Konto użytkownika');
    toggle.setAttribute('aria-haspopup', 'true');

    const avatar = document.createElement('span');
    avatar.className = 'hayne-user-avatar';
    avatar.textContent = initialsFor(name);

    const label = document.createElement('span');
    label.className = 'hayne-user-name';
    label.textContent = name;

    toggle.appendChild(avatar);
    toggle.appendChild(label);
    toggle.appendChild(icon('chevron', 'hayne-user-chevron'));

    const menu = document.createElement('ul');
    menu.className = 'dropdown-menu pull-right hayne-user-dropdown';

    const labels = ['Mój profil', 'Zmień hasło', 'Wyloguj'];
    const iconNames = ['user', 'lock', 'logout'];
    originalActions.forEach((action, index) => {
      const link = action.querySelector('a');
      if (!link) return;
      link.textContent = '';
      link.prepend(icon(iconNames[index] || 'user'));
      const text = document.createElement('span');
      text.textContent = labels[index] || link.title || 'Opcja';
      link.appendChild(text);
      action.className = index === originalActions.length - 1 ? 'hayne-user-action hayne-user-action--logout' : 'hayne-user-action';
      menu.appendChild(action);
    });

    if (!menu.querySelector(`a[href="${profileHref}"]`)) {
      const profile = document.createElement('li');
      profile.className = 'hayne-user-action';
      const link = document.createElement('a');
      link.href = profileHref;
      link.appendChild(icon('user'));
      const text = document.createElement('span');
      text.textContent = 'Mój profil';
      link.appendChild(text);
      profile.appendChild(link);
      menu.prepend(profile);
    }

    menuItem.appendChild(toggle);
    menuItem.appendChild(menu);
    account.replaceWith(menuItem);
  };

  const enhanceMobileShell = (wrap) => {
    const navbarInner = wrap.querySelector(':scope > .navbar .navbar-inner');
    const navResponsive = navbarInner ? navbarInner.querySelector('.nav-responsive') : null;
    if (!navbarInner || !navResponsive || navbarInner.querySelector('.hayne-mobile-menu-toggle')) return;

    navResponsive.id = navResponsive.id || 'hayne-mobile-navigation';

    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'hayne-mobile-menu-toggle';
    toggle.setAttribute('aria-controls', navResponsive.id);
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', 'Otwórz menu');
    toggle.appendChild(icon('menu'));

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'hayne-mobile-menu-close';
    close.setAttribute('aria-label', 'Zamknij menu');
    close.appendChild(icon('close'));

    const drawerTitle = document.createElement('span');
    drawerTitle.className = 'hayne-mobile-menu-title';
    drawerTitle.textContent = 'Menu';

    const drawerHeader = document.createElement('div');
    drawerHeader.className = 'hayne-mobile-drawer-header';
    drawerHeader.appendChild(drawerTitle);
    drawerHeader.appendChild(close);
    navResponsive.appendChild(drawerHeader);

    const overlay = document.createElement('button');
    overlay.type = 'button';
    overlay.className = 'hayne-mobile-menu-overlay';
    overlay.setAttribute('aria-label', 'Zamknij menu');
    overlay.setAttribute('tabindex', '-1');

    navbarInner.appendChild(toggle);
    wrap.appendChild(overlay);

    let previouslyFocused = null;

    const focusableInDrawer = () => Array.from(navResponsive.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'))
      .filter((node) => node.offsetParent !== null);

    const setOpen = (isOpen) => {
      if (isOpen) {
        previouslyFocused = document.activeElement;
        wrap.classList.add('hayne-mobile-menu-open');
        document.body.classList.add('hayne-mobile-menu-lock');
        toggle.setAttribute('aria-expanded', 'true');
        toggle.setAttribute('aria-label', 'Zamknij menu');
        window.setTimeout(() => close.focus(), 0);
        return;
      }

      wrap.classList.remove('hayne-mobile-menu-open');
      document.body.classList.remove('hayne-mobile-menu-lock');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-label', 'Otwórz menu');
      if (previouslyFocused && typeof previouslyFocused.focus === 'function') previouslyFocused.focus();
      previouslyFocused = null;
    };

    toggle.addEventListener('click', () => setOpen(!wrap.classList.contains('hayne-mobile-menu-open')));
    close.addEventListener('click', () => setOpen(false));
    overlay.addEventListener('click', () => setOpen(false));

    navResponsive.addEventListener('click', (event) => {
      const link = event.target.closest('a');
      if (!link || link.classList.contains('dropdown-toggle')) return;
      setOpen(false);
    });

    document.addEventListener('keydown', (event) => {
      if (!wrap.classList.contains('hayne-mobile-menu-open')) return;

      if (event.key === 'Escape') {
        event.preventDefault();
        setOpen(false);
        return;
      }

      if (event.key !== 'Tab' || window.matchMedia('(min-width: 980px)').matches) return;
      const focusable = focusableInDrawer();
      if (!focusable.length) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];

      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    });

    window.addEventListener('resize', () => {
      if (window.matchMedia('(min-width: 980px)').matches && wrap.classList.contains('hayne-mobile-menu-open')) {
        setOpen(false);
      }
    });
  };

  const addSidebarFooter = (wrap) => {
    const navbarInner = wrap.querySelector(':scope > .navbar .navbar-inner');
    if (!navbarInner || navbarInner.querySelector('.hayne-sidebar-footer')) return;

    const footer = document.createElement('div');
    footer.className = 'hayne-sidebar-footer';
    footer.appendChild(icon('shield'));

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
    enhanceMobileShell(wrap);
    addSidebarFooter(wrap);

    const nav = wrap.querySelector('.nav-responsive > ul.nav:not(.pull-right)');
    if (!nav || nav.dataset.hayneNavigation === 'target-v2') return;

    const brand = wrap.querySelector('.hayne-navbar-brand');
    const brandHref = brand ? brand.href : `${window.location.origin}/home`;
    const root = brandHref.replace(/home\/?(?:[?#].*)?$/, '');
    const surface = currentSurface();

    const directItems = [
      { path: 'home', label: 'Start', icon: 'home' },
      { path: 'leaves/create', label: 'Nowy wniosek', icon: 'plus' },
      { path: 'leaves', label: 'Moje wnioski', icon: 'file' },
      { path: 'leaves/counters', label: 'Saldo urlopowe', icon: 'calendar' },
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
      { label: 'Kalendarz', className: 'hayne-nav-calendar', icon: 'calendar', active: surface.startsWith('calendar') },
      { label: 'Zespół', className: 'hayne-nav-team', icon: 'team', active: /^(hr|organization|contracts|positions|reports)(\/|$)/.test(surface) },
      { label: 'Do akceptacji', className: 'hayne-nav-approvals', icon: 'approval', active: /^(requests|overtime)(\/|$)/.test(surface) },
      { label: 'Administracja', className: 'hayne-nav-admin', icon: 'settings', active: /^(admin|users|leavetypes)(\/|$)/.test(surface) && !surface.startsWith('users/myprofile') },
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
      toggle.prepend(icon(group.icon));

      Array.from(toggle.childNodes).forEach((child) => {
        if (child.nodeType === Node.TEXT_NODE) child.remove();
      });

      const labelNode = document.createElement('span');
      labelNode.className = 'hayne-nav-label';
      labelNode.textContent = group.label;
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

    nav.dataset.hayneNavigation = 'target-v2';
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNavigation, { once: true });
  } else {
    initNavigation();
  }
})();