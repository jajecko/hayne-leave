(function () {
  'use strict';

  function initBulkLimits() {
    var root = document.querySelector('[data-hayne-bulk="v1"]');
    if (!root) return;

    var form = document.getElementById('hayneBulkLeaveForm');
    var rows = Array.prototype.slice.call(root.querySelectorAll('[data-hayne-employee-row]'));
    var search = document.getElementById('hayneEmployeeSearch');
    var filters = Array.prototype.slice.call(root.querySelectorAll('[data-hayne-filter]'));
    var master = document.getElementById('hayneSelectAllVisible');
    var selectVisible = document.getElementById('hayneSelectVisible');
    var selectedCount = document.getElementById('hayneSelectedCount');
    var safetyText = document.getElementById('hayneBulkSafetyText');
    var overwrite = document.getElementById('bulk_overwrite_existing');
    var typeSelect = document.getElementById('bulk_vacation_type_id');
    var daysInput = document.getElementById('bulk_annual_days');
    var customWrap = document.getElementById('hayneCustomDaysWrap');
    var submit = document.getElementById('hayneBulkSubmit');
    var empty = document.getElementById('hayneEmployeeFilterEmpty');
    var presets = Array.prototype.slice.call(root.querySelectorAll('[data-hayne-days]'));
    var activeFilter = 'all';
    var bulkEditable = root.getAttribute('data-bulk-editable') !== '0';

    function normalize(value) {
      return String(value || '').toLocaleLowerCase('pl-PL').trim();
    }

    function visibleRows() {
      return rows.filter(function (row) { return !row.hidden; });
    }

    function checkedRows() {
      return rows.filter(function (row) {
        var box = row.querySelector('.hayne-employee-checkbox');
        return box && box.checked;
      });
    }

    function applyFilter() {
      var query = normalize(search ? search.value : '');
      var shown = 0;
      rows.forEach(function (row) {
        var matchesSearch = !query || normalize(row.getAttribute('data-name')).indexOf(query) !== -1;
        var configured = row.getAttribute('data-configured') === '1';
        var matchesStatus = activeFilter === 'all' ||
          (activeFilter === 'configured' && configured) ||
          (activeFilter === 'missing' && !configured);
        row.hidden = !(matchesSearch && matchesStatus);
        if (!row.hidden) shown += 1;
      });
      if (empty) empty.hidden = shown !== 0;
      syncMaster();
    }

    function syncMaster() {
      if (!master) return;
      var visible = visibleRows();
      var selected = visible.filter(function (row) {
        var box = row.querySelector('.hayne-employee-checkbox');
        return box && box.checked;
      }).length;
      master.checked = visible.length > 0 && selected === visible.length;
      master.indeterminate = selected > 0 && selected < visible.length;
    }

    function syncSelection() {
      var checked = checkedRows();
      var configured = checked.filter(function (row) {
        return row.getAttribute('data-configured') === '1';
      });
      var typeMismatch = configured.filter(function (row) {
        return row.getAttribute('data-type-id') !== String(typeSelect.value);
      });

      selectedCount.textContent = checked.length + (checked.length === 1 ? ' zaznaczony' : ' zaznaczonych');
      submit.textContent = checked.length ? 'Przydziel limit (' + checked.length + ')' : 'Przydziel limit';
      submit.disabled = !bulkEditable || checked.length === 0;

      if (!bulkEditable) {
        safetyText.textContent = 'Zapis jest wyłączony dla historycznego widoku.';
      } else if (!overwrite.checked) {
        safetyText.textContent = configured.length
          ? configured.length + ' zaznaczonych osób ma już limit i zostanie pominiętych.'
          : 'Istniejące konfiguracje zostaną pominięte.';
      } else if (typeMismatch.length) {
        safetyText.textContent = typeMismatch.length + ' osób ma inny rodzaj urlopu i zostanie bezpiecznie pominiętych.';
      } else if (configured.length) {
        safetyText.textContent = configured.length + ' istniejących konfiguracji zostanie zaktualizowanych.';
      } else {
        safetyText.textContent = 'Nie zaznaczono osób z istniejącą konfiguracją.';
      }

      rows.forEach(function (row) {
        var box = row.querySelector('.hayne-employee-checkbox');
        row.classList.toggle('is-selected', !!(box && box.checked));
      });
      syncMaster();
    }

    function setDaysMode(value) {
      var custom = value === 'custom';
      if (customWrap) customWrap.hidden = !custom;

      presets.forEach(function (button) {
        var isActive = button.getAttribute('data-hayne-days') === value;
        button.classList.toggle('is-active', isActive);
        button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
      });

      if (custom) {
        if (daysInput) {
          daysInput.focus();
          daysInput.select();
        }
      } else if (daysInput) {
        daysInput.value = value;
      }
    }

    if (search) search.addEventListener('input', applyFilter);

    filters.forEach(function (button) {
      button.addEventListener('click', function () {
        activeFilter = button.getAttribute('data-hayne-filter') || 'all';
        filters.forEach(function (item) {
          var isActive = item === button;
          item.classList.toggle('is-active', isActive);
          item.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
        applyFilter();
      });
    });

    rows.forEach(function (row) {
      var box = row.querySelector('.hayne-employee-checkbox');
      if (box) box.addEventListener('change', syncSelection);
    });

    if (master) {
      master.addEventListener('change', function () {
        visibleRows().forEach(function (row) {
          var box = row.querySelector('.hayne-employee-checkbox');
          if (box) box.checked = master.checked;
        });
        syncSelection();
      });
    }

    if (selectVisible) {
      selectVisible.addEventListener('click', function () {
        var visible = visibleRows();
        var allSelected = visible.length > 0 && visible.every(function (row) {
          var box = row.querySelector('.hayne-employee-checkbox');
          return box && box.checked;
        });
        visible.forEach(function (row) {
          var box = row.querySelector('.hayne-employee-checkbox');
          if (box) box.checked = !allSelected;
        });
        syncSelection();
      });
    }

    presets.forEach(function (button) {
      button.addEventListener('click', function () {
        setDaysMode(button.getAttribute('data-hayne-days'));
      });
    });

    if (overwrite) overwrite.addEventListener('change', syncSelection);
    if (typeSelect) typeSelect.addEventListener('change', syncSelection);

    if (form) {
      form.addEventListener('submit', function (event) {
        if (!bulkEditable || checkedRows().length === 0) {
          event.preventDefault();
          if (!bulkEditable) return;
          safetyText.textContent = 'Zaznacz co najmniej jednego pracownika.';
          var firstVisible = visibleRows()[0];
          if (firstVisible) {
            var firstBox = firstVisible.querySelector('.hayne-employee-checkbox');
            if (firstBox) firstBox.focus();
          }
        }
      });
    }

    applyFilter();
    setDaysMode('26');
    syncSelection();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initBulkLimits);
  } else {
    initBulkLimits();
  }
}());
