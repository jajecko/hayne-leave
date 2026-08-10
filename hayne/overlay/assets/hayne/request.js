(() => {
  const SVG_NS = 'http://www.w3.org/2000/svg';

  const iconPaths = {
    calendar: [
      ['rect', { x: '3', y: '5', width: '18', height: '16', rx: '2' }],
      ['path', { d: 'M16 3v4M8 3v4M3 10h18' }],
    ],
    chevron: [
      ['path', { d: 'm8 10 4 4 4-4' }],
    ],
    send: [
      ['path', { d: 'm22 2-7 20-4-9-9-4Z' }],
      ['path', { d: 'M22 2 11 13' }],
    ],
    plan: [
      ['rect', { x: '3', y: '5', width: '18', height: '16', rx: '2' }],
      ['path', { d: 'M16 3v4M8 3v4M3 10h18' }],
    ],
    sun: [
      ['circle', { cx: '12', cy: '12', r: '4' }],
      ['path', { d: 'M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.66 6.34l1.41-1.41' }],
    ],
    moon: [
      ['path', { d: 'M20.5 14.2A8.5 8.5 0 0 1 9.8 3.5 8.5 8.5 0 1 0 20.5 14.2Z' }],
    ],
  };

  const makeIcon = (name, className = '') => {
    const span = document.createElement('span');
    span.className = `hayne-request-icon${className ? ` ${className}` : ''}`;
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

  const normalizedCredit = (value) => {
    const raw = String(value || '').replace(/[()]/g, '').trim();
    if (!raw) return '—';
    return `${raw} dni`;
  };

  const relabel = (label, text) => {
    if (!label) return;
    const preserved = Array.from(label.children);
    label.textContent = text;
    preserved.forEach((child) => label.appendChild(child));
  };

  const makeField = (className = '') => {
    const field = document.createElement('div');
    field.className = `hayne-request-field${className ? ` ${className}` : ''}`;
    return field;
  };

  const makeControlShell = (control, iconName, className = '') => {
    const shell = document.createElement('div');
    shell.className = `hayne-request-control${className ? ` ${className}` : ''}`;
    if (iconName) shell.appendChild(makeIcon(iconName, 'hayne-request-control__icon'));
    shell.appendChild(control);
    return shell;
  };

  const makeSelectShell = (select, iconName = null) => {
    const shell = makeControlShell(select, iconName, 'hayne-request-control--select');
    shell.appendChild(makeIcon('chevron', 'hayne-request-control__chevron'));
    return shell;
  };

  const updateDayPartIcon = (select, shell) => {
    const current = shell.querySelector('.hayne-request-control__icon');
    const next = makeIcon(select.value === 'Afternoon' ? 'moon' : 'sun', 'hayne-request-control__icon');
    if (current) current.replaceWith(next);
    else shell.insertBefore(next, shell.firstChild);
  };

  const enhanceRequest = () => {
    const page = document.querySelector('[data-hayne-view="leave-create-v2"]');
    if (!page || page.dataset.hayneRequestEnhanced === 'target-v1') return;

    const form = page.querySelector('#frmLeaveForm');
    if (!form) return;

    page.dataset.hayneRequestEnhanced = 'target-v1';
    form.classList.add('hayne-request-form--target');

    const wrap = document.getElementById('wrap');
    if (wrap) wrap.dataset.hayneTopbarTitle = 'Nowy wniosek';

    const header = page.querySelector('.hayne-request-header');
    const eyebrow = header ? header.querySelector('.hayne-request-eyebrow') : null;
    const title = header ? header.querySelector('h1') : null;
    const intro = header ? header.querySelector('p') : null;
    if (eyebrow) eyebrow.remove();
    if (title) title.textContent = 'Nowy wniosek';
    if (intro) intro.textContent = 'Wypełnij formularz poniżej, aby złożyć wniosek o nieobecność. Wniosek zostanie przesłany do osoby akceptującej.';

    const layout = document.createElement('div');
    layout.className = 'hayne-request-layout';

    const type = form.querySelector('#type');
    const typeLabel = form.querySelector('label[for="type"]');
    const creditSource = form.querySelector('#lblCredit');
    if (type && typeLabel) {
      const typeField = makeField('hayne-request-field--type');
      const labelRow = document.createElement('div');
      labelRow.className = 'hayne-request-label-row';

      if (creditSource) creditSource.remove();
      typeLabel.textContent = 'Rodzaj nieobecności';
      labelRow.appendChild(typeLabel);

      if (creditSource) {
        const credit = document.createElement('span');
        credit.className = 'hayne-request-credit hayne-request-credit--target';
        credit.textContent = 'Dostępne saldo: ';

        const value = document.createElement('strong');
        value.className = 'hayne-request-credit-value';
        value.textContent = normalizedCredit(creditSource.textContent);
        credit.appendChild(value);
        credit.appendChild(creditSource);
        labelRow.appendChild(credit);

        creditSource.classList.add('hayne-request-credit-source');
        const syncCredit = () => {
          value.textContent = normalizedCredit(creditSource.textContent);
        };
        new MutationObserver(syncCredit).observe(creditSource, { childList: true, characterData: true, subtree: true });
      }

      const select2 = type.nextElementSibling && type.nextElementSibling.classList.contains('select2-container')
        ? type.nextElementSibling
        : null;
      const typeControl = document.createElement('div');
      typeControl.className = 'hayne-request-type-control';
      typeControl.appendChild(type);
      if (select2) typeControl.appendChild(select2);
      typeControl.appendChild(makeIcon('chevron', 'hayne-request-control__chevron'));

      typeField.appendChild(labelRow);
      typeField.appendChild(typeControl);
      layout.appendChild(typeField);
    }

    const datesGrid = document.createElement('div');
    datesGrid.className = 'hayne-request-grid hayne-request-grid--dates';

    const startInput = form.querySelector('#viz_startdate');
    const startHidden = form.querySelector('#startdate');
    const startLabel = form.querySelector('label[for="viz_startdate"]');
    if (startInput && startLabel) {
      startLabel.textContent = 'Data rozpoczęcia';
      startInput.placeholder = 'Wybierz datę';
      const field = makeField();
      field.appendChild(startLabel);
      const shell = makeControlShell(startInput, 'calendar', 'hayne-request-control--date');
      field.appendChild(shell);
      if (startHidden) field.appendChild(startHidden);
      datesGrid.appendChild(field);
    }

    const endInput = form.querySelector('#viz_enddate');
    const endHidden = form.querySelector('#enddate');
    const endLabel = form.querySelector('label[for="viz_enddate"]');
    if (endInput && endLabel) {
      endLabel.textContent = 'Data zakończenia';
      endInput.placeholder = 'Wybierz datę';
      const field = makeField();
      field.appendChild(endLabel);
      const shell = makeControlShell(endInput, 'calendar', 'hayne-request-control--date');
      field.appendChild(shell);
      if (endHidden) field.appendChild(endHidden);
      datesGrid.appendChild(field);
    }
    if (datesGrid.children.length) layout.appendChild(datesGrid);

    const dayPartsGrid = document.createElement('div');
    dayPartsGrid.className = 'hayne-request-grid hayne-request-grid--dayparts';

    const startType = form.querySelector('#startdatetype');
    if (startType) {
      Array.from(startType.options).forEach((option) => {
        option.textContent = option.value === 'Afternoon' ? 'Po południu' : 'Rano';
      });
      const label = document.createElement('label');
      label.htmlFor = 'startdatetype';
      label.textContent = 'Część dnia (od)';
      const field = makeField();
      const shell = makeSelectShell(startType, startType.value === 'Afternoon' ? 'moon' : 'sun');
      startType.addEventListener('change', () => updateDayPartIcon(startType, shell));
      field.appendChild(label);
      field.appendChild(shell);
      dayPartsGrid.appendChild(field);
    }

    const endType = form.querySelector('#enddatetype');
    if (endType) {
      Array.from(endType.options).forEach((option) => {
        option.textContent = option.value === 'Afternoon' ? 'Po południu' : 'Rano';
      });
      const label = document.createElement('label');
      label.htmlFor = 'enddatetype';
      label.textContent = 'Część dnia (do)';
      const field = makeField();
      const shell = makeSelectShell(endType, endType.value === 'Afternoon' ? 'moon' : 'sun');
      endType.addEventListener('change', () => updateDayPartIcon(endType, shell));
      field.appendChild(label);
      field.appendChild(shell);
      dayPartsGrid.appendChild(field);
    }
    if (dayPartsGrid.children.length) layout.appendChild(dayPartsGrid);

    const duration = form.querySelector('#duration');
    const durationLabel = form.querySelector('label[for="duration"]');
    const dayType = form.querySelector('#spnDayType');
    if (duration && durationLabel) {
      const tooltip = durationLabel.querySelector('#tooltipDayOff');
      durationLabel.textContent = 'Liczba dni';
      if (tooltip) durationLabel.appendChild(tooltip);
      duration.placeholder = '0 dni';

      const field = makeField('hayne-request-field--duration');
      field.appendChild(durationLabel);
      field.appendChild(duration);
      if (dayType) field.appendChild(dayType);
      layout.appendChild(field);
    }

    const alerts = ['lblCreditAlert', 'lblOverlappingAlert', 'lblOverlappingDayOffAlert']
      .map((id) => form.querySelector(`#${id}`))
      .filter(Boolean);
    if (alerts.length) {
      const alertsWrap = document.createElement('div');
      alertsWrap.className = 'hayne-request-alerts';
      alerts.forEach((alert) => alertsWrap.appendChild(alert));
      layout.appendChild(alertsWrap);
    }

    const cause = form.querySelector('textarea[name="cause"]');
    const causeLabel = form.querySelector('label[for="cause"]');
    if (cause && causeLabel) {
      causeLabel.textContent = 'Powód / komentarz';
      cause.placeholder = 'Podaj powód nieobecności lub dodatkowe informacje (opcjonalnie)';
      const field = makeField('hayne-request-field--cause');
      field.appendChild(causeLabel);
      field.appendChild(cause);
      layout.appendChild(field);
    }

    const actions = document.createElement('div');
    actions.className = 'hayne-request-actions hayne-request-actions--target';

    const submit = form.querySelector('button[name="status"][value="2"]');
    if (submit) {
      submit.className = 'btn btn-primary hayne-request-submit';
      submit.textContent = '';
      submit.appendChild(makeIcon('send'));
      const text = document.createElement('span');
      text.textContent = 'Wyślij wniosek';
      submit.appendChild(text);
      actions.appendChild(submit);
    }

    const planned = form.querySelector('button[name="status"][value="1"]');
    if (planned) {
      planned.className = 'btn hayne-request-plan';
      planned.textContent = '';
      planned.appendChild(makeIcon('plan'));
      const text = document.createElement('span');
      text.textContent = 'Zapisz jako plan';
      planned.appendChild(text);
      actions.appendChild(planned);
    }

    const cancel = form.querySelector('a.btn-danger');
    if (cancel) {
      cancel.className = 'hayne-request-cancel';
      cancel.textContent = 'Anuluj';
      actions.appendChild(cancel);
    }

    if (actions.children.length) layout.appendChild(actions);
    form.appendChild(layout);
  };

  const scheduleEnhancement = () => window.setTimeout(enhanceRequest, 0);
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', scheduleEnhancement, { once: true });
  } else {
    scheduleEnhancement();
  }
})();
