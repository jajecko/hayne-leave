(function () {
  'use strict';

  if (!('serviceWorker' in navigator)) {
    return;
  }

  if (!window.isSecureContext && window.location.hostname !== 'localhost' && window.location.hostname !== '127.0.0.1') {
    return;
  }

  var script = document.currentScript;
  if (!script || !script.src) {
    return;
  }

  var serviceWorkerUrl = new URL('../../service-worker.js', script.src);
  var scopeUrl = new URL('../../', script.src);

  window.addEventListener('load', function () {
    navigator.serviceWorker.register(serviceWorkerUrl.href, { scope: scopeUrl.pathname }).catch(function (error) {
      if (window.console && typeof window.console.warn === 'function') {
        window.console.warn('HAYNE Leave: service worker registration failed.', error);
      }
    });
  });
}());
