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

    // Source markup keeps this policy immediately after the leave-type selector.
    // The redesigned create form moves all HAYNE policy fields as one group.
    // Do not perform a second DOM reparent here: competing reparent operations
    // were the source of layout instability when multiple policies were enabled.
    updateVisibility();
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', enhanceOnDemand, { once: true });
  } else {
    enhanceOnDemand();
  }
})();
