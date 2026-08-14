(() => {
  const install = () => {
    const wrap = document.getElementById('wrap');
    const drawer = wrap ? wrap.querySelector('.nav-responsive') : null;
    if (!drawer || drawer.dataset.hayneMobileNavigationHotfix === 'v1') return;

    drawer.dataset.hayneMobileNavigationHotfix = 'v1';

    // navigation.js closes the whole drawer synchronously for every regular link.
    // On touch devices that can interfere with Bootstrap dropdown link activation.
    // Stop bubbling only on real submenu links; their native navigation then owns
    // the transition and the next page naturally starts with a closed drawer.
    drawer.querySelectorAll('.dropdown-menu a[href]').forEach((link) => {
      const href = String(link.getAttribute('href') || '').trim();
      if (!href || href === '#' || href.toLowerCase().startsWith('javascript:')) return;

      link.addEventListener('click', (event) => {
        if (window.matchMedia('(min-width: 980px)').matches) return;
        event.stopPropagation();
      });
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', install, { once: true });
  } else {
    install();
  }
})();
