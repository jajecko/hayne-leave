(() => {
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
