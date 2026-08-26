(() => {
  'use strict';

  const DRAFT_KEY = 'hayne.leave.create.draft.v1';
  const DRAFT_TTL_MS = 30 * 60 * 1000;

  const byId = (id) => document.getElementById(id);
  const currentPage = () => document.querySelector('[data-hayne-view="leave-create-v2"]');
  const currentForm = () => byId('frmLeaveForm');
  const currentDraftKey = () => {
    const employee = byId('hayne_employee');
    const employeeId = employee ? String(employee.value || 'self') : 'self';
    return `${DRAFT_KEY}.${employeeId}`;
  };

  const setRequired = (field, required) => {
    if (!field) return;
    field.required = Boolean(required);
    if (required) field.setAttribute('aria-required', 'true');
    else field.removeAttribute('aria-required');
  };

  const setPanelVisible = (panel, visible) => {
    if (!panel) return;
    panel.style.display = visible ? '' : 'none';
    panel.setAttribute('aria-hidden', visible ? 'false' : 'true');
  };

  const syncCaregiver = (typeValue) => {
    const panel = byId('hayneCaregiverFields');
    if (!panel) return;

    const active = String(typeValue) === String(panel.getAttribute('data-caregiver-type-id'));
    const person = byId('hayne_caregiver_person_name');
    const relation = byId('hayne_caregiver_relation');
    const addressWrap = byId('hayneCaregiverAddressWrap');
    const address = byId('hayne_caregiver_household_address');
    const reason = byId('hayne_caregiver_reason');
    const household = active && relation && relation.value === 'household';

    setPanelVisible(panel, active);
    setRequired(person, active);
    setRequired(relation, active);
    setRequired(reason, active);
    setRequired(address, household);
    if (addressWrap) addressWrap.style.display = household ? '' : 'none';
  };

  const syncForceMajeure = (typeValue) => {
    const panel = byId('hayneForceMajeureFields');
    if (!panel) return;

    const active = String(typeValue) === String(panel.getAttribute('data-force-majeure-type-id'));
    setPanelVisible(panel, active);
    setRequired(byId('hayne_force_majeure_event'), active);
    setRequired(byId('hayne_force_majeure_immediate_presence'), active);
  };

  const syncChildcare = (typeValue) => {
    const panel = byId('hayneChildcareFields');
    if (!panel) return;
    setPanelVisible(panel, String(typeValue) === String(panel.getAttribute('data-childcare-type-id')));
  };

  const syncOccasion = (typeValue) => {
    const panel = byId('hayneOccasionFields');
    if (!panel) return;

    const active = String(typeValue) === String(panel.getAttribute('data-occasion-type-id'));
    const eventSelect = byId('hayne_occasion_event');
    const eventDate = byId('hayne_occasion_event_date');
    const hint = byId('hayneOccasionLimitHint');

    setPanelVisible(panel, active);
    setRequired(eventSelect, active);
    setRequired(eventDate, active);

    if (hint && eventSelect) {
      const option = eventSelect.options[eventSelect.selectedIndex];
      const days = option ? Number.parseInt(option.getAttribute('data-max-days') || '0', 10) : 0;
      hint.textContent = days === 1
        ? 'To zdarzenie daje maksymalnie 1 pełny dzień zwolnienia.'
        : (days > 1
          ? 'To zdarzenie daje maksymalnie 2 pełne dni zwolnienia; możesz wykorzystać je razem albo w dwóch wnioskach.'
          : '');
    }
  };

  const syncHolidayCompensation = (typeValue) => {
    const panel = byId('hayneHolidayCompensationFields');
    if (!panel) return;

    const active = String(typeValue) === String(panel.getAttribute('data-holiday-compensation-type-id'));
    const grant = byId('hayne_holiday_compensation_grant_id');
    const hint = byId('hayneHolidayCompensationHint');

    setPanelVisible(panel, active);
    setRequired(grant, active);

    if (hint && grant) {
      const option = grant.options[grant.selectedIndex];
      const start = option ? option.getAttribute('data-period-start') : '';
      const end = option ? option.getAttribute('data-period-end') : '';
      hint.textContent = start && end ? `Dzień wolny musi przypadać między ${start} a ${end}.` : '';
    }
  };

  const syncPolicyPanels = () => {
    const type = byId('type');
    if (!type) return;
    const value = type.value;
    syncCaregiver(value);
    syncForceMajeure(value);
    syncChildcare(value);
    syncOccasion(value);
    syncHolidayCompensation(value);
  };

  const isOnDemandActive = () => {
    const option = byId('hayneOnDemandOption');
    const checkbox = byId('hayne_on_demand');
    const type = byId('type');
    if (!option || !checkbox || !type) return false;
    return checkbox.checked && String(type.value) === String(option.dataset.vacationTypeId || '');
  };

  // Jorani links the two pickers (end >= start). HAYNE must not add a
  // calendar-year clamp. This also neutralizes stale cached picker options
  // from an older deployment while preserving the on-demand end-date cap.
  const syncDatePickerBounds = () => {
    if (!window.jQuery || !window.jQuery.fn || typeof window.jQuery.fn.datepicker !== 'function') return;

    const $ = window.jQuery;
    const start = $('#viz_startdate');
    const end = $('#viz_enddate');
    if (!start.length || !end.length || !start.hasClass('hasDatepicker') || !end.hasClass('hasDatepicker')) return;

    const startValue = String(start.val() || '').trim();
    const endValue = String(end.val() || '').trim();

    start.datepicker('option', 'changeYear', true);
    end.datepicker('option', 'changeYear', true);
    start.datepicker('option', 'yearRange', 'c-10:c+10');
    end.datepicker('option', 'yearRange', 'c-10:c+10');

    // Native Jorani never needs a lower bound on the start date.
    start.datepicker('option', 'minDate', null);
    start.datepicker('option', 'maxDate', endValue || null);
    end.datepicker('option', 'minDate', startValue || null);

    // maxDate on the end picker belongs only to the explicit on-demand cap.
    if (!isOnDemandActive()) end.datepicker('option', 'maxDate', null);
  };

  const fieldDefinitions = [
    ['type', '#type'],
    ['viz_startdate', '#viz_startdate'],
    ['startdate', '#startdate'],
    ['startdatetype', '#startdatetype'],
    ['viz_enddate', '#viz_enddate'],
    ['enddate', '#enddate'],
    ['enddatetype', '#enddatetype'],
    ['duration', '#duration'],
    ['cause', 'textarea[name="cause"]'],
    ['hayne_on_demand', '#hayne_on_demand'],
    ['hayne_caregiver_person_name', '#hayne_caregiver_person_name'],
    ['hayne_caregiver_relation', '#hayne_caregiver_relation'],
    ['hayne_caregiver_household_address', '#hayne_caregiver_household_address'],
    ['hayne_caregiver_reason', '#hayne_caregiver_reason'],
    ['hayne_force_majeure_event', '#hayne_force_majeure_event'],
    ['hayne_force_majeure_immediate_presence', '#hayne_force_majeure_immediate_presence'],
    ['hayne_occasion_event', '#hayne_occasion_event'],
    ['hayne_occasion_event_date', '#hayne_occasion_event_date'],
    ['hayne_holiday_compensation_grant_id', '#hayne_holiday_compensation_grant_id'],
  ];

  const readDraftValue = (field) => {
    if (!field) return null;
    if (field.type === 'checkbox' || field.type === 'radio') return Boolean(field.checked);
    return String(field.value ?? '');
  };

  const writeDraftValue = (field, value) => {
    if (!field) return;
    if (field.type === 'checkbox' || field.type === 'radio') {
      field.checked = Boolean(value);
      return;
    }
    field.value = value == null ? '' : String(value);
  };

  const saveDraft = () => {
    const form = currentForm();
    if (!form || !window.sessionStorage) return;

    const values = {};
    fieldDefinitions.forEach(([key, selector]) => {
      const field = form.querySelector(selector);
      if (field) values[key] = readDraftValue(field);
    });

    try {
      window.sessionStorage.setItem(currentDraftKey(), JSON.stringify({ savedAt: Date.now(), values }));
    } catch (error) {
      // Storage is an enhancement only; the server-side request must still work.
    }
  };

  const clearDraft = () => {
    if (!window.sessionStorage) return;
    try {
      window.sessionStorage.removeItem(currentDraftKey());
    } catch (error) {
      // Ignore browsers that block session storage.
    }
  };

  const loadDraft = () => {
    if (!window.sessionStorage) return null;
    try {
      const raw = window.sessionStorage.getItem(currentDraftKey());
      if (!raw) return null;
      const draft = JSON.parse(raw);
      if (!draft || !draft.savedAt || !draft.values || (Date.now() - draft.savedAt) > DRAFT_TTL_MS) {
        clearDraft();
        return null;
      }
      return draft;
    } catch (error) {
      clearDraft();
      return null;
    }
  };

  const dispatchChange = (field) => {
    if (!field) return;
    try {
      field.dispatchEvent(new Event('change', { bubbles: true }));
    } catch (error) {
      // IE-style fallback is not needed for supported browsers; keep restore safe.
    }
  };

  const restoreDraft = () => {
    const form = currentForm();
    const draft = loadDraft();
    if (!form || !draft) return false;

    fieldDefinitions.forEach(([key, selector]) => {
      if (!Object.prototype.hasOwnProperty.call(draft.values, key)) return;
      writeDraftValue(form.querySelector(selector), draft.values[key]);
    });

    const type = byId('type');
    if (type && window.jQuery) {
      window.jQuery(type).val(type.value).trigger('change');
    } else {
      dispatchChange(type);
    }

    dispatchChange(byId('hayne_caregiver_relation'));
    dispatchChange(byId('hayne_occasion_event'));
    dispatchChange(byId('hayne_holiday_compensation_grant_id'));
    dispatchChange(byId('hayne_on_demand'));

    syncPolicyPanels();
    window.setTimeout(syncDatePickerBounds, 0);
    window.setTimeout(syncDatePickerBounds, 120);
    form.dataset.hayneDraftRestored = 'true';
    return true;
  };

  const errorMessages = {
    type: 'Wybierz rodzaj nieobecności.',
    viz_startdate: 'Wybierz datę rozpoczęcia.',
    startdate: 'Wybierz datę rozpoczęcia.',
    viz_enddate: 'Wybierz datę zakończenia.',
    enddate: 'Wybierz datę zakończenia.',
    duration: 'Wybierz poprawny zakres dat, aby obliczyć liczbę dni.',
    hayne_caregiver_person_name: 'Podaj imię i nazwisko osoby wymagającej opieki lub wsparcia.',
    hayne_caregiver_relation: 'Wybierz relację z osobą wymagającą opieki lub wsparcia.',
    hayne_caregiver_household_address: 'Podaj adres zamieszkania osoby z tego samego gospodarstwa domowego.',
    hayne_caregiver_reason: 'Podaj przyczynę konieczności zapewnienia opieki lub wsparcia.',
    hayne_force_majeure_event: 'Wybierz przyczynę pilnej sprawy rodzinnej.',
    hayne_force_majeure_immediate_presence: 'Potwierdź, że Twoja natychmiastowa obecność jest niezbędna.',
    hayne_occasion_event: 'Wybierz zdarzenie uprawniające do urlopu okolicznościowego.',
    hayne_occasion_event_date: 'Podaj datę zdarzenia.',
    hayne_holiday_compensation_grant_id: 'Wybierz przyznany dzień wolny za święto.',
  };

  const errorTarget = (field) => {
    if (!field) return null;
    if (field.id === 'startdate') return byId('viz_startdate');
    if (field.id === 'enddate') return byId('viz_enddate');
    return field;
  };

  const errorAnchor = (target) => {
    if (!target) return null;
    if (target.id === 'type') {
      const select2 = target.nextElementSibling && target.nextElementSibling.classList.contains('select2-container')
        ? target.nextElementSibling
        : document.querySelector('.hayne-request-type-control .select2-container');
      if (select2) return select2;
    }
    if (target.type === 'checkbox' || target.type === 'radio') {
      return target.closest('label') || target;
    }
    return target;
  };

  const clearFieldError = (field) => {
    const target = errorTarget(field);
    if (!target) return;
    const key = target.id || target.name || '';
    target.classList.remove('hayne-request-invalid');
    target.removeAttribute('aria-invalid');

    const anchor = errorAnchor(target);
    if (anchor && anchor !== target) anchor.classList.remove('hayne-request-invalid');

    if (key) {
      document.querySelectorAll('.hayne-request-inline-error').forEach((node) => {
        if (node.getAttribute('data-hayne-error-for') === key) node.remove();
      });
    }
  };

  const clearAllErrors = () => {
    document.querySelectorAll('.hayne-request-invalid').forEach((node) => {
      node.classList.remove('hayne-request-invalid');
      node.removeAttribute('aria-invalid');
    });
    document.querySelectorAll('.hayne-request-inline-error').forEach((node) => node.remove());
  };

  const showFieldError = (field, message) => {
    const target = errorTarget(field);
    if (!target) return;
    clearFieldError(target);

    const key = target.id || target.name || 'field';
    target.classList.add('hayne-request-invalid');
    target.setAttribute('aria-invalid', 'true');

    const anchor = errorAnchor(target);
    if (anchor && anchor !== target) anchor.classList.add('hayne-request-invalid');

    const error = document.createElement('span');
    error.className = 'hayne-request-inline-error';
    error.setAttribute('role', 'alert');
    error.setAttribute('data-hayne-error-for', key);
    error.textContent = message;
    (anchor || target).insertAdjacentElement('afterend', error);

    window.setTimeout(() => {
      try {
        (anchor || target).scrollIntoView({ block: 'center', behavior: 'smooth' });
      } catch (scrollError) {
        (anchor || target).scrollIntoView();
      }
      if (target.id === 'type' && window.jQuery && typeof window.jQuery(target).select2 === 'function') {
        try {
          window.jQuery(target).select2('focus');
        } catch (select2Error) {
          target.focus();
        }
      } else {
        target.focus();
      }
    }, 0);
  };

  const isEmpty = (field) => {
    if (!field) return true;
    if (field.type === 'checkbox' || field.type === 'radio') return !field.checked;
    return String(field.value ?? '').trim() === '';
  };

  const firstClientError = () => {
    const form = currentForm();
    if (!form) return null;

    const core = [
      byId('type'),
      byId('viz_startdate'),
      byId('startdate'),
      byId('viz_enddate'),
      byId('enddate'),
      byId('duration'),
    ].filter(Boolean);

    for (const field of core) {
      const id = field.id || field.name;
      if (isEmpty(field)) return [field, errorMessages[id] || 'Uzupełnij wymagane pole.'];
      if (field.id === 'duration' && Number.parseFloat(field.value) <= 0) {
        return [field, errorMessages.duration];
      }
    }

    const start = byId('startdate');
    const end = byId('enddate');
    if (start && end && start.value && end.value && start.value > end.value) {
      return [end, 'Data zakończenia nie może być wcześniejsza niż data rozpoczęcia.'];
    }

    const required = Array.from(form.querySelectorAll('[required]'));
    for (const field of required) {
      if (field.disabled || isEmpty(field)) {
        const id = field.id || field.name;
        return [field, errorMessages[id] || 'Uzupełnij wymagane pole.'];
      }
    }

    return null;
  };

  const mapServerErrorToField = () => {
    const bodyText = String(document.body && document.body.innerText || '');
    const mappings = [
      ['Podaj imię i nazwisko osoby wymagającej opieki lub wsparcia.', 'hayne_caregiver_person_name'],
      ['Wybierz relację z osobą wymagającą opieki lub wsparcia.', 'hayne_caregiver_relation'],
      ['Podaj przyczynę konieczności zapewnienia opieki lub wsparcia.', 'hayne_caregiver_reason'],
      ['Podaj adres zamieszkania osoby z tego samego gospodarstwa domowego.', 'hayne_caregiver_household_address'],
    ];

    for (const [message, id] of mappings) {
      if (bodyText.includes(message)) return [byId(id), message];
    }
    return null;
  };

  const bindPolicySync = () => {
    const type = byId('type');
    if (!type) return;

    const update = () => {
      syncPolicyPanels();
      window.setTimeout(syncDatePickerBounds, 0);
    };

    type.addEventListener('change', update);
    if (window.jQuery) {
      window.jQuery(type)
        .off('.hayneRequestFormHotfix')
        .on('change.hayneRequestFormHotfix select2-selecting.hayneRequestFormHotfix', () => {
          window.setTimeout(update, 0);
        });
    }

    const relation = byId('hayne_caregiver_relation');
    if (relation) relation.addEventListener('change', () => syncCaregiver(type.value));

    const occasion = byId('hayne_occasion_event');
    if (occasion) occasion.addEventListener('change', () => syncOccasion(type.value));

    const grant = byId('hayne_holiday_compensation_grant_id');
    if (grant) grant.addEventListener('change', () => syncHolidayCompensation(type.value));

    const onDemand = byId('hayne_on_demand');
    if (onDemand) onDemand.addEventListener('change', () => window.setTimeout(syncDatePickerBounds, 0));
  };

  const bindValidationUx = () => {
    const form = currentForm();
    if (!form) return;

    // Own required-field UX so the browser does not show detached native
    // bubbles while HAYNE can point directly at the offending control.
    form.noValidate = true;

    form.addEventListener('input', (event) => clearFieldError(event.target), true);
    form.addEventListener('change', (event) => clearFieldError(event.target), true);

    form.addEventListener('submit', (event) => {
      syncPolicyPanels();
      clearAllErrors();
      const error = firstClientError();
      if (error) {
        event.preventDefault();
        event.stopImmediatePropagation();
        showFieldError(error[0], error[1]);
        return;
      }
      saveDraft();
    }, true);
  };

  const bindDateSync = () => {
    ['viz_startdate', 'viz_enddate'].forEach((id) => {
      const field = byId(id);
      if (field) field.addEventListener('change', () => window.setTimeout(syncDatePickerBounds, 0));
    });

    window.setTimeout(syncDatePickerBounds, 0);
    window.setTimeout(syncDatePickerBounds, 120);
    window.setTimeout(syncDatePickerBounds, 350);
  };

  const run = () => {
    const page = currentPage();
    if (!page) {
      clearDraft();
      return;
    }

    bindPolicySync();
    bindValidationUx();
    bindDateSync();

    const restored = restoreDraft();
    syncPolicyPanels();

    if (restored) {
      window.setTimeout(() => {
        const serverError = mapServerErrorToField();
        if (serverError && serverError[0]) showFieldError(serverError[0], serverError[1]);
      }, 80);
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run, { once: true });
  } else {
    run();
  }
})();
