(() => {
  const EPSILON = 0.001;

  const parseNumber = (value) => {
    const parsed = Number.parseFloat(String(value ?? ''));
    return Number.isFinite(parsed) ? parsed : null;
  };

  const parseYmd = (value) => {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(value || ''));
    if (!match) return null;
    const date = new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
    if (
      date.getFullYear() !== Number(match[1])
      || date.getMonth() !== Number(match[2]) - 1
      || date.getDate() !== Number(match[3])
    ) return null;
    date.setHours(12, 0, 0, 0);
    return date;
  };

  const formatYmd = (date) => {
    const pad = (value) => String(value).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
  };

  const addDays = (date, count) => {
    const next = new Date(date.getTime());
    next.setDate(next.getDate() + count);
    next.setHours(12, 0, 0, 0);
    return next;
  };

  const calendarDaysInclusive = (start, end) => {
    let count = 0;
    for (let cursor = new Date(start.getTime()); cursor <= end; cursor = addDays(cursor, 1)) count += 1;
    return count;
  };

  const dayOffMapFromValidation = (leaveInfo) => {
    const map = new Map();
    if (!leaveInfo || !Array.isArray(leaveInfo.listDaysOff)) return map;

    leaveInfo.listDaysOff.forEach((row) => {
      const date = String(row && row.date || '').slice(0, 10);
      const length = parseNumber(row && row.length);
      if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || length === null) return;
      const current = map.get(date) || 0;
      map.set(date, Math.min(1, Math.max(0, current + length)));
    });
    return map;
  };

  const inferDeductDaysOff = (start, end, leaveInfo, dayOffMap) => {
    const returnedLength = parseNumber(leaveInfo && leaveInfo.length);
    if (returnedLength === null) return true;

    const calendarDays = calendarDaysInclusive(start, end);
    let daysOff = 0;
    dayOffMap.forEach((length, ymd) => {
      const date = parseYmd(ymd);
      if (date && date >= start && date <= end) daysOff += length;
    });

    const deductedLength = Math.max(0, calendarDays - daysOff);
    if (Math.abs(returnedLength - deductedLength) <= EPSILON) return true;
    if (Math.abs(returnedLength - calendarDays) <= EPSILON) return false;

    // The configured HAYNE vacation type normally deducts non-working days.
    // If a legacy calendar has unusual fractions, prefer the conservative
    // interpretation and let the server revalidate the adjusted range.
    return true;
  };

  const findCappedEndDate = (start, end, maxDays, leaveInfo) => {
    const dayOffMap = dayOffMapFromValidation(leaveInfo);
    const deductDaysOff = inferDeductDaysOff(start, end, leaveInfo, dayOffMap);
    let used = 0;
    let capped = new Date(start.getTime());

    for (let cursor = new Date(start.getTime()); cursor <= end; cursor = addDays(cursor, 1)) {
      const ymd = formatYmd(cursor);
      const dayOff = deductDaysOff ? (dayOffMap.get(ymd) || 0) : 0;
      const contribution = Math.max(0, 1 - dayOff);
      if ((used + contribution) > (maxDays + EPSILON)) break;
      used += contribution;
      capped = new Date(cursor.getTime());
    }

    return capped;
  };

  const dayWord = (days) => days === 1 ? 'dzień' : 'dni';
  const isValidateRequest = (url) => String(url || '').includes('leaves/validate');

  const enhanceOnDemand = () => {
    const form = document.getElementById('frmLeaveForm');
    const option = document.getElementById('hayneOnDemandOption');
    const type = document.getElementById('type');
    const checkbox = document.getElementById('hayne_on_demand');
    if (!form || !option || !type || !checkbox || option.dataset.hayneOnDemandEnhanced === 'v2') return;

    option.dataset.hayneOnDemandEnhanced = 'v2';

    const vacationTypeId = String(option.dataset.vacationTypeId || '');
    const policyYear = Number.parseInt(String(option.dataset.year || ''), 10);
    const rawRemaining = parseNumber(option.dataset.remaining);
    const remainingFullDays = Math.max(0, Math.floor((rawRemaining ?? 0) + EPSILON));
    const duration = document.getElementById('duration');
    let adjustingRange = false;

    const ensureWarning = () => {
      let warning = document.getElementById('hayneOnDemandLimitWarning');
      if (warning) return warning;
      warning = document.createElement('div');
      warning.id = 'hayneOnDemandLimitWarning';
      warning.className = 'hayne-on-demand-option__warning';
      warning.setAttribute('role', 'alert');
      warning.hidden = true;
      option.appendChild(warning);
      return warning;
    };

    const showWarning = (message) => {
      const warning = ensureWarning();
      warning.textContent = message;
      warning.hidden = false;
    };

    const hideWarning = () => {
      const warning = document.getElementById('hayneOnDemandLimitWarning');
      if (warning) warning.hidden = true;
    };

    const clearPickerCap = () => {
      if (!window.jQuery) return;
      const end = window.jQuery('#viz_enddate');
      if (end.length && end.hasClass('hasDatepicker') && typeof end.datepicker === 'function') {
        end.datepicker('option', 'maxDate', null);
      }
    };

    const applyPickerCap = (date) => {
      if (!window.jQuery) return;
      const end = window.jQuery('#viz_enddate');
      if (end.length && end.hasClass('hasDatepicker') && typeof end.datepicker === 'function') {
        end.datepicker('option', 'maxDate', date);
      }
    };

    const setEndDate = (date) => {
      const ymd = formatYmd(date);
      if (window.jQuery) {
        const end = window.jQuery('#viz_enddate');
        if (end.length && end.hasClass('hasDatepicker') && typeof end.datepicker === 'function') {
          end.datepicker('setDate', date);
        }
        window.jQuery('#enddate').val(ymd);
      } else {
        const hiddenEnd = document.getElementById('enddate');
        if (hiddenEnd) hiddenEnd.value = ymd;
      }
      applyPickerCap(date);
    };

    const rangeUsesCurrentPolicyYear = () => {
      const start = parseYmd(document.getElementById('startdate')?.value);
      return !start || !Number.isFinite(policyYear) || policyYear <= 0 || start.getFullYear() === policyYear;
    };

    const triggerValidation = () => {
      if (!checkbox.checked || String(type.value) !== vacationTypeId) return;
      const start = parseYmd(document.getElementById('startdate')?.value);
      const end = parseYmd(document.getElementById('enddate')?.value);
      if (!start || !end || start > end) return;
      if (typeof window.getLeaveInfos === 'function') window.getLeaveInfos(false);
    };

    const enforceFromValidation = (leaveInfo) => {
      if (adjustingRange || !checkbox.checked || String(type.value) !== vacationTypeId) return;

      const startValue = document.getElementById('startdate')?.value || '';
      const endValue = document.getElementById('enddate')?.value || '';
      const start = parseYmd(startValue);
      const end = parseYmd(endValue);
      if (!start || !end || start > end) return;

      if (
        leaveInfo && leaveInfo.RequestStartDate && String(leaveInfo.RequestStartDate) !== startValue
      ) return;
      if (
        leaveInfo && leaveInfo.RequestEndDate && String(leaveInfo.RequestEndDate) !== endValue
      ) return;

      // Form state is calculated for the current calendar year. Requests for a
      // different year remain protected by the backend policy, but we do not
      // apply a potentially stale client-side cap to them.
      if (!rangeUsesCurrentPolicyYear()) return;

      if (remainingFullDays <= 0) {
        checkbox.checked = false;
        clearPickerCap();
        if (duration) duration.removeAttribute('max');
        showWarning('Nie masz już dostępnych pełnych dni urlopu na żądanie w tym roku.');
        return;
      }

      const requestLength = parseNumber(leaveInfo && leaveInfo.length);
      if (requestLength === null || requestLength <= (remainingFullDays + EPSILON)) return;

      const cappedEnd = findCappedEndDate(start, end, remainingFullDays, leaveInfo);
      const cappedYmd = formatYmd(cappedEnd);
      if (cappedYmd === endValue) return;

      adjustingRange = true;
      setEndDate(cappedEnd);
      if (duration) duration.setAttribute('max', String(remainingFullDays));
      showWarning(
        `Możesz wnioskować o maksymalnie ${remainingFullDays} ${dayWord(remainingFullDays)} urlopu na żądanie. Zakres dat został automatycznie skrócony.`
      );

      window.setTimeout(() => {
        adjustingRange = false;
        if (typeof window.getLeaveLength === 'function') window.getLeaveLength(true);
      }, 0);
    };

    const updateVisibility = () => {
      const eligible = String(type.value) === vacationTypeId;
      option.hidden = !eligible;
      option.setAttribute('aria-hidden', eligible ? 'false' : 'true');
      if (!eligible) {
        checkbox.checked = false;
        clearPickerCap();
        hideWarning();
        if (duration) duration.removeAttribute('max');
      }
    };

    checkbox.addEventListener('change', () => {
      if (!checkbox.checked) {
        clearPickerCap();
        hideWarning();
        if (duration) duration.removeAttribute('max');
        return;
      }

      if (!rangeUsesCurrentPolicyYear()) {
        if (duration) duration.removeAttribute('max');
        triggerValidation();
        return;
      }

      if (remainingFullDays <= 0) {
        checkbox.checked = false;
        clearPickerCap();
        if (duration) duration.removeAttribute('max');
        showWarning('Nie masz już dostępnych pełnych dni urlopu na żądanie w tym roku.');
        return;
      }

      if (duration) duration.setAttribute('max', String(remainingFullDays));
      triggerValidation();
    });

    const startInput = document.getElementById('viz_startdate');
    if (startInput) {
      startInput.addEventListener('change', () => {
        clearPickerCap();
        hideWarning();
      });
    }

    type.addEventListener('change', updateVisibility);
    if (window.jQuery) {
      window.jQuery(type).on('change.hayneOnDemand select2-selecting.hayneOnDemand', () => {
        window.setTimeout(updateVisibility, 0);
      });

      window.jQuery(document).on('ajaxSuccess.hayneOnDemand', (event, xhr, settings, data) => {
        if (!isValidateRequest(settings && settings.url)) return;
        const leaveInfo = data && typeof data === 'object'
          ? data
          : (xhr && xhr.responseJSON ? xhr.responseJSON : null);
        if (leaveInfo) enforceFromValidation(leaveInfo);
      });
    }

    // The create redesign owns placement of every HAYNE policy field.
    // Edit keeps the stock layout, so normalize the server-rendered policy
    // to the position directly after the leave-type selector there only.
    if (!document.querySelector('[data-hayne-view="leave-create-v2"]')) {
      type.insertAdjacentElement('afterend', option);
    }

    updateVisibility();
    if (checkbox.checked && duration && rangeUsesCurrentPolicyYear()) {
      duration.setAttribute('max', String(remainingFullDays));
      window.setTimeout(triggerValidation, 0);
    }
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', enhanceOnDemand, { once: true });
  } else {
    enhanceOnDemand();
  }
})();
