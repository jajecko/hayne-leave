(() => {
  const isLeaveValidateRequest = (url) => String(url || '').includes('leaves/validate');

  const ensureValidationError = () => {
    let alert = document.getElementById('hayneLeaveValidationError');
    if (alert) return alert;

    const form = document.getElementById('frmLeaveForm');
    if (!form) return null;

    alert = document.createElement('div');
    alert.id = 'hayneLeaveValidationError';
    alert.className = 'alert alert-error hayne-leave-validation-error';
    alert.setAttribute('role', 'alert');
    alert.hidden = true;
    alert.textContent = 'Nie udało się przeliczyć liczby dni. Spróbuj ponownie lub odśwież stronę.';

    const actions = form.querySelector('.hayne-request-actions');
    if (actions) form.insertBefore(alert, actions);
    else form.appendChild(alert);
    return alert;
  };

  const hideWaitModal = () => {
    if (!window.jQuery) return;
    const modal = window.jQuery('#frmModalAjaxWait');
    if (modal.length && typeof modal.modal === 'function') modal.modal('hide');
  };

  const installValidationGuard = () => {
    if (!window.jQuery || document.documentElement.dataset.hayneLeaveValidateGuard === 'v1') return;

    const $ = window.jQuery;
    document.documentElement.dataset.hayneLeaveValidateGuard = 'v1';

    // Never allow the stock wait modal to spin forever when /leaves/validate
    // fails or the server stops responding.
    if (typeof $.ajaxPrefilter === 'function') {
      $.ajaxPrefilter((options) => {
        if (isLeaveValidateRequest(options && options.url) && !options.timeout) {
          options.timeout = 15000;
        }
      });
    }

    $(document).on('ajaxError.hayneLeaveValidateGuard', (event, xhr, settings) => {
      if (!isLeaveValidateRequest(settings && settings.url)) return;
      hideWaitModal();
      const alert = ensureValidationError();
      if (alert) alert.hidden = false;
    });

    $(document).on('ajaxSuccess.hayneLeaveValidateGuard', (event, xhr, settings) => {
      if (!isLeaveValidateRequest(settings && settings.url)) return;
      const alert = document.getElementById('hayneLeaveValidationError');
      if (alert) alert.hidden = true;
    });
  };

  const enforceFullDays = () => {
    const pairs = [
      ['startdatetype', 'Morning'],
      ['enddatetype', 'Afternoon'],
      ['leaveStartdatetype', 'Morning'],
      ['leaveEnddatetype', 'Afternoon'],
    ];

    pairs.forEach(([id, value]) => {
      const control = document.getElementById(id);
      if (!control) return;
      control.value = value;
      control.style.display = 'none';
      control.setAttribute('aria-hidden', 'true');
      control.setAttribute('tabindex', '-1');
    });

    // The stock leave validator still reads #startdatetype/#enddatetype when
    // calculating a selected date range. Hide the HAYNE day-part row without
    // removing those controls from the DOM; removing it makes the AJAX request
    // omit both values and the PHP validator fails before returning JSON.
    document.querySelectorAll('.hayne-request-grid--dayparts').forEach((node) => {
      node.hidden = true;
      node.style.display = 'none';
      node.setAttribute('aria-hidden', 'true');
    });

    const duration = document.getElementById('duration');
    if (duration) {
      duration.setAttribute('step', '1');
      duration.setAttribute('inputmode', 'numeric');
    }

    const hrDuration = document.getElementById('leaveDuration');
    if (hrDuration) {
      hrDuration.setAttribute('step', '1');
      hrDuration.setAttribute('inputmode', 'numeric');
    }
  };

  const run = () => {
    installValidationGuard();
    enforceFullDays();
    window.setTimeout(enforceFullDays, 0);
    window.setTimeout(enforceFullDays, 100);
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run, { once: true });
  } else {
    run();
  }
})();
