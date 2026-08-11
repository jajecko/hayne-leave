(() => {
  const enhanceOnDemand = () => {
    const form = document.getElementById('frmLeaveForm');
    const option = document.getElementById('hayneOnDemandOption');
    const type = document.getElementById('type');
    const checkbox = document.getElementById('hayne_on_demand');
    if (!form || !option || !type || !checkbox) return;

    const vacationTypeId = String(option.dataset.vacationTypeId || '');
    const updateVisibility = () => {
      const eligible = String(type.value) === vacationTypeId;
      option.hidden = !eligible;
      option.setAttribute('aria-hidden', eligible ? 'false' : 'true');
      if (!eligible) checkbox.checked = false;
    };

    type.addEventListener('change', updateVisibility);
    if (window.jQuery) {
      window.jQuery(type).on('change.hayneOnDemand select2-selecting.hayneOnDemand', () => {
        window.setTimeout(updateVisibility, 0);
      });
    }

    // The redesigned create form moves known legacy controls into a new layout.
    // Keep this server-rendered policy control directly below the leave type.
    const placeInTargetLayout = () => {
      const typeField = form.querySelector('.hayne-request-field--type');
      if (typeField && option.parentElement !== typeField.parentElement) {
        typeField.insertAdjacentElement('afterend', option);
      } else if (typeField && option.previousElementSibling !== typeField) {
        typeField.insertAdjacentElement('afterend', option);
      }
      updateVisibility();
    };

    updateVisibility();
    window.setTimeout(placeInTargetLayout, 0);
    window.setTimeout(placeInTargetLayout, 100);
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', enhanceOnDemand, { once: true });
  } else {
    enhanceOnDemand();
  }
})();
