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

    // The create redesign owns placement of every HAYNE policy field.
    // Edit keeps the stock layout, so normalize the server-rendered policy
    // to the position directly after the leave-type selector there only.
    if (!document.querySelector('[data-hayne-view="leave-create-v2"]')) {
      type.insertAdjacentElement('afterend', option);
    }

    updateVisibility();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', enhanceOnDemand, { once: true });
  } else {
    enhanceOnDemand();
  }
})();
