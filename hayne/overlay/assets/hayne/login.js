(() => {
  const SVG_NS = 'http://www.w3.org/2000/svg';

  const iconPaths = {
    user: [
      ['circle', { cx: '12', cy: '8', r: '3.5' }],
      ['path', { d: 'M5 20v-1.5A5.5 5.5 0 0 1 10.5 13h3A5.5 5.5 0 0 1 19 18.5V20Z' }],
    ],
    lock: [
      ['rect', { x: '5', y: '10', width: '14', height: '11', rx: '2' }],
      ['path', { d: 'M8 10V7a4 4 0 0 1 8 0v3M12 14v3' }],
    ],
    eye: [
      ['path', { d: 'M2.5 12s3.4-5 9.5-5 9.5 5 9.5 5-3.4 5-9.5 5-9.5-5-9.5-5Z' }],
      ['circle', { cx: '12', cy: '12', r: '2.4' }],
    ],
  };

  const makeIcon = (name, className = '') => {
    const span = document.createElement('span');
    span.className = `hayne-login-icon${className ? ` ${className}` : ''}`;
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

  const wrapInput = (input, iconName, isPassword = false) => {
    if (!input || input.closest('.hayne-login-control')) return;
    const control = document.createElement('div');
    control.className = 'hayne-login-control';
    input.parentNode.insertBefore(control, input);
    control.appendChild(makeIcon(iconName, 'hayne-login-control__leading'));
    control.appendChild(input);

    if (isPassword) {
      const toggle = document.createElement('button');
      toggle.type = 'button';
      toggle.className = 'hayne-password-toggle';
      toggle.setAttribute('aria-label', 'Pokaż hasło');
      toggle.appendChild(makeIcon('eye'));
      toggle.addEventListener('click', () => {
        const reveal = input.type === 'password';
        input.type = reveal ? 'text' : 'password';
        toggle.classList.toggle('is-revealed', reveal);
        toggle.setAttribute('aria-label', reveal ? 'Ukryj hasło' : 'Pokaż hasło');
      });
      control.appendChild(toggle);
    }
  };

  const enhance = () => {
    const shell = document.querySelector('.hayne-login-shell');
    if (!shell || shell.dataset.hayneLogin === 'target-v1') return;
    shell.dataset.hayneLogin = 'target-v1';
    document.body.classList.add('hayne-login-target');

    const columns = shell.querySelector('.row-fluid');
    const formPanel = columns ? columns.querySelector('.span6:first-child') : null;
    const brandPanel = shell.querySelector('.hayne-login-brand-panel');
    const brand = shell.querySelector('.hayne-login-brand');
    const title = formPanel ? formPanel.querySelector('h2') : null;
    const form = shell.querySelector('#loginFrom');
    if (!formPanel || !brandPanel || !brand || !form) return;

    if (title) {
      title.classList.add('hayne-login-legacy-title');
      title.textContent = '';
    }

    brandPanel.classList.add('hayne-login-card-brand');
    const product = brand.querySelector('.hayne-login-product-name');
    if (product) product.textContent = 'HAYNE Leave';

    if (!brand.querySelector('.hayne-login-subtitle')) {
      const subtitle = document.createElement('p');
      subtitle.className = 'hayne-login-subtitle';
      subtitle.textContent = 'Zaloguj się, aby zarządzać urlopami i nieobecnościami.';
      brand.appendChild(subtitle);
    }

    const login = form.querySelector('#login');
    const password = form.querySelector('#password');
    const loginLabel = formPanel.querySelector('label[for="login"]');
    const passwordLabel = formPanel.querySelector('label[for="password"]');
    if (loginLabel) loginLabel.textContent = 'Login';
    if (passwordLabel) passwordLabel.textContent = 'Hasło';
    if (login) {
      login.placeholder = 'Wpisz login';
      login.autocomplete = 'username';
      wrapInput(login, 'user');
    }
    if (password) {
      password.placeholder = 'Wpisz hasło';
      password.autocomplete = 'current-password';
      wrapInput(password, 'lock', true);
    }

    const submit = form.querySelector('button[type="submit"]');
    if (submit) {
      submit.className = 'btn btn-primary hayne-login-submit';
      submit.textContent = 'Zaloguj się';
    }

    if (!form.querySelector('.hayne-login-help')) {
      const help = document.createElement('p');
      help.className = 'hayne-login-help';
      help.textContent = 'Problemy z logowaniem? Skontaktuj się z administratorem.';
      form.appendChild(help);
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', enhance, { once: true });
  } else {
    enhance();
  }
})();
