(() => {
  const cleanDate = (value) => String(value || '')
    .replace(/\s*\([^)]*\)\s*$/, '')
    .replace(/\s+/g, ' ')
    .trim();

  const enhanceApprovalTerms = () => {
    const page = document.querySelector('.hayne-approvals-page');
    const table = document.getElementById('leaves');
    if (!page || !table) return;

    const headerRow = table.querySelector('thead tr');
    if (headerRow && headerRow.children.length >= 4) {
      const startHeader = headerRow.children[2];
      const endHeader = headerRow.children[3];
      startHeader.textContent = 'Termin';
      startHeader.dataset.hayneHeader = 'term-v1';
      endHeader.classList.add('hayne-approval-enddate-source');
      endHeader.setAttribute('aria-hidden', 'true');
    }

    table.querySelectorAll('tbody tr').forEach((row) => {
      const cells = row.children;
      if (cells.length < 7 || row.querySelector('.dataTables_empty')) return;

      const startCell = cells[2];
      const endCell = cells[3];
      if (!startCell || !endCell) return;

      const startText = startCell.dataset.hayneTermStart || cleanDate(startCell.textContent);
      const endText = endCell.dataset.hayneTermEnd || cleanDate(endCell.textContent);
      startCell.dataset.hayneTermStart = startText;
      endCell.dataset.hayneTermEnd = endText;

      startCell.textContent = startText && endText && startText !== endText
        ? `${startText} – ${endText}`
        : (startText || endText || '—');
      startCell.classList.add('hayne-approval-term');

      endCell.classList.add('hayne-approval-enddate-source');
      endCell.setAttribute('aria-hidden', 'true');
    });
  };

  const schedule = () => window.setTimeout(enhanceApprovalTerms, 0);

  const init = () => {
    schedule();
    if (window.jQuery && document.getElementById('leaves')) {
      window.jQuery('#leaves')
        .off('draw.dt.hayneApprovalTerm')
        .on('draw.dt.hayneApprovalTerm', schedule);
    }
  };

  if (document.readyState === 'complete') {
    init();
  } else {
    window.addEventListener('load', init, { once: true });
  }
})();
