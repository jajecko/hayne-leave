(() => {
  'use strict';

  const isCreatePage = () => Boolean(document.querySelector('[data-hayne-view="leave-create-v2"]'));

  const setCreditVisual = (leaveInfo) => {
    if (!isCreatePage() || !leaveInfo || typeof leaveInfo !== 'object') return;

    const creditAlert = document.getElementById('lblCreditAlert');
    const creditVisual = document.querySelector('.hayne-request-credit-value');
    const creditWrap = document.querySelector('.hayne-request-credit--target');

    if (leaveInfo.creditExempt === true) {
      if (creditAlert) creditAlert.style.display = 'none';
      if (creditVisual) creditVisual.textContent = 'nie dotyczy';
      if (creditWrap) creditWrap.setAttribute('data-hayne-credit-exempt', 'true');
      return;
    }

    if (creditWrap) creditWrap.removeAttribute('data-hayne-credit-exempt');
    if (creditVisual && Object.prototype.hasOwnProperty.call(leaveInfo, 'credit')) {
      const raw = String(leaveInfo.credit == null ? '' : leaveInfo.credit).trim();
      creditVisual.textContent = raw === '' ? '—' : `${raw} dni`;
    }
  };

  const parseResponse = (xhr) => {
    if (!xhr) return null;
    if (xhr.responseJSON && typeof xhr.responseJSON === 'object') return xhr.responseJSON;
    if (typeof xhr.responseText !== 'string' || xhr.responseText.trim() === '') return null;
    try {
      return JSON.parse(xhr.responseText);
    } catch (error) {
      return null;
    }
  };

  const install = () => {
    if (!isCreatePage() || !window.jQuery) return;

    window.jQuery(document)
      .off('ajaxSuccess.hayneOfficialSummons')
      .on('ajaxSuccess.hayneOfficialSummons', (_event, xhr, settings) => {
        const url = settings && typeof settings.url === 'string' ? settings.url : '';
        if (!url.includes('leaves/validate')) return;
        setCreditVisual(parseResponse(xhr));
      });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', install, { once: true });
  } else {
    install();
  }
})();
