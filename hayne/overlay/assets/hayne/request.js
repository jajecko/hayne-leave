(() => {
  const SVG_NS = 'http://www.w3.org/2000/svg';
  const iconPaths = {
    calendar: [
      ['rect', { x: '3', y: '5', width: '18', height: '16', rx: '2' }],
      ['path', { d: 'M16 3v4M8 3v4M3 10h18' }],
    ],
    chevron: [['path', { d: 'm8 10 4 4 4-4' }]],
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
    moon: [['path', { d: 'M20.5 14.2A8.5 8.5 0 0 1 9.8 3.5 8.5 8.5 0 1 0 20.5 14.2Z' }]],
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

  const makeField = (className = '') => {
    const field = document.createElement('div');
    field.className = `hayne-request-field${className ? ` ${className}` : ''}`;
    return field;
  };

  const makeControl = (control, iconName = null, className = '') => {
    const shell = document.createElement('div');
    shell.className = `hayne-request-control${className ? ` ${className}` : ''}`;
    if (iconName) shell.appendChild(makeIcon(iconName, 'hayne-request-control__icon'));
    shell.appendChild(control);
    return shell;
  };

  const makeSelectControl = (select, iconName = null) => {
    const shell = makeControl(select, iconName, 'hayne-request-control--select');
    shell.appendChild(makeIcon('chevron', 'hayne-request-control__chevron'));
    return shell;
  };

  const normalizeCredit = (value) => {
    const raw = String(value || '').replace(/[()]/g, '').trim();
    return raw ? `${raw} dni` : '—';
  };

  const updateDayPartIcon = (select, shell) => {
    const current = shell.querySelector('.hayne-request-control__icon');
    const next = makeIcon(select.value === 'Afternoon' ? 'moon' : 'sun', 'hayne-request-control__icon');
    if (current) current.replaceWith(next);
    else shell.insertBefore(next, shell.firstChild);
  };

  const cleanLegacySpacing = (form, layout) => {
    Array.from(form.childNodes).forEach((node) => {
      if (node === layout) return;
      if (node.nodeType === Node.TEXT_NODE) {
        const visible = String(node.textContent || '').replace(/\u00a0/g, '').trim();
        if (!visible) node.remove();
        return;
      }
      if (node.nodeType === Node.ELEMENT_NODE && node.tagName === 'BR') node.remove();
    });
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
    if (header) {
      const eyebrow = header.querySelector('.hayne-request-eyebrow');
      const title = header.querySelector('h1');
      const intro = header.querySelector('p');
      if (eyebrow) eyebrow.remove();
      if (title) title.textContent = 'Nowy wniosek';
      if (intro) intro.textContent = 'Wypełnij formularz poniżej, aby złożyć wniosek o nieobecność. Wniosek zostanie przesłany do osoby akceptującej.';
    }

    const layout = document.createElement('div');
    layout.className = 'hayne-request-layout';

    const type = form.querySelector('#type');
    const typeLabel = form.querySelector('label[for="type"]');
    const creditSource = form.querySelector('#lblCredit');
    if (type && typeLabel) {
      const field = makeField('hayne-request-field--type');
      const labelRow = document.createElement('div');
      labelRow.className = 'hayne-request-label-row';

      if (creditSource) creditSource.remove();
      typeLabel.textContent = 'Rodzaj nieobecności';
      labelRow.appendChild(typeLabel);

      if (creditSource) {
        const credit = document.createElement('span');
        credit.className = 'hayne-request-credit hayne-request-credit--target';
        credit.append('Dostępne saldo: ');

        const visualValue = document.createElement('strong');
        visualValue.className = 'hayne-request-credit-value';
        visualValue.textContent = normalizeCredit(creditSource.textContent);
        credit.appendChild(visualValue);

        creditSource.classList.add('hayne-request-credit-source');
        credit.appendChild(creditSource);
        labelRow.appendChild(credit);

        new MutationObserver(() => {
          visualValue.textContent = normalizeCredit(creditSource.textContent);
        }).observe(creditSource, { childList: true, characterData: true, subtree: true });
      }

      type.classList.add('hayne-request-type-select');
      const existingSelect2 = type.nextElementSibling && type.nextElementSibling.classList.contains('select2-container')
        ? type.nextElementSibling
        : null;
      const control = document.createElement('div');
      control.className = 'hayne-request-type-control';
      control.appendChild(type);
      if (existingSelect2) control.appendChild(existingSelect2);
      control.appendChild(makeIcon('chevron', 'hayne-request-control__chevron'));

      field.appendChild(labelRow);
      field.appendChild(control);
      layout.appendChild(field);
    }

    const policyFields = [
      document.getElementById('hayneOnDemandOption'),
      document.getElementById('hayneCaregiverFields'),
      document.getElementById('hayneForceMajeureFields'),
      document.getElementById('hayneChildcareFields'),
      document.getElementById('hayneOccasionFields'),
      document.getElementById('hayneHolidayCompensationFields'),
    ].filter(Boolean);
    if (policyFields.length) {
      const policyGroup = document.createElement('div');
      policyGroup.className = 'hayne-request-policy-fields';
      policyGroup.dataset.haynePolicyFields = 'v1';
      policyFields.forEach((policyField) => {
        policyField.classList.add('hayne-request-policy-field');
        policyGroup.appendChild(policyField);
      });
      layout.appendChild(policyGroup);
    }

    const dates = document.createElement('div');
    dates.className = 'hayne-request-grid hayne-request-grid--dates';
    [
      ['viz_startdate', 'startdate', 'Data rozpoczęcia'],
      ['viz_enddate', 'enddate', 'Data zakończenia'],
    ].forEach(([visibleId, hiddenId, labelText]) => {
      const input = form.querySelector(`#${visibleId}`);
      const hidden = form.querySelector(`#${hiddenId}`);
      const label = form.querySelector(`label[for="${visibleId}"]`);
      if (!input || !label) return;
      label.textContent = labelText;
      input.placeholder = 'Wybierz datę';
      const field = makeField();
      field.appendChild(label);
      field.appendChild(makeControl(input, 'calendar', 'hayne-request-control--date'));
      if (hidden) field.appendChild(hidden);
      dates.appendChild(field);
    });
    if (dates.children.length) layout.appendChild(dates);

    const dayParts = document.createElement('div');
    dayParts.className = 'hayne-request-grid hayne-request-grid--dayparts';
    [
      ['startdatetype', 'Część dnia (od)'],
      ['enddatetype', 'Część dnia (do)'],
    ].forEach(([id, labelText]) => {
      const select = form.querySelector(`#${id}`);
      if (!select) return;
      Array.from(select.options).forEach((option) => {
        option.textContent = option.value === 'Afternoon' ? 'Po południu' : 'Rano';
      });

      const label = document.createElement('label');
      label.htmlFor = id;
      label.textContent = labelText;
      const field = makeField();
      const shell = makeSelectControl(select, select.value === 'Afternoon' ? 'moon' : 'sun');
      select.addEventListener('change', () => updateDayPartIcon(select, shell));
      field.appendChild(label);
      field.appendChild(shell);
      dayParts.appendChild(field);
    });
    if (dayParts.children.length) layout.appendChild(dayParts);

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
      submit.appendChild(document.createTextNode('Wyślij wniosek'));
      actions.appendChild(submit);
    }

    const planned = form.querySelector('button[name="status"][value="1"]');
    if (planned) {
      planned.className = 'btn hayne-request-plan';
      planned.textContent = '';
      planned.appendChild(makeIcon('plan'));
      planned.appendChild(document.createTextNode('Zapisz jako plan'));
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
    cleanLegacySpacing(form, layout);
  };

  const scheduleEnhancement = () => window.setTimeout(enhanceRequest, 0);
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', scheduleEnhancement, { once: true });
  } else {
    scheduleEnhancement();
  }
})();
