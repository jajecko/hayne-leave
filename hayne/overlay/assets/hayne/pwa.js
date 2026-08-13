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
  var appBaseUrl = new URL('../../', script.src);
  var promptKey = 'hayne-push-prompt-dismissed-at';
  var promptCooldownMs = 7 * 24 * 60 * 60 * 1000;
  var registration = null;

  function pushSupported() {
    return 'PushManager' in window && 'Notification' in window;
  }

  function urlBase64ToUint8Array(value) {
    var padding = '='.repeat((4 - (value.length % 4)) % 4);
    var base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
    var rawData = window.atob(base64);
    var output = new Uint8Array(rawData.length);
    for (var i = 0; i < rawData.length; i += 1) {
      output[i] = rawData.charCodeAt(i);
    }
    return output;
  }

  async function fetchSettings() {
    var response = await window.fetch(new URL('push/settings', appBaseUrl).href, {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      cache: 'no-store'
    });
    if (!response.ok) {
      return null;
    }
    return response.json();
  }

  async function postSubscription(path, settings, subscription) {
    var json = subscription.toJSON();
    var body = new URLSearchParams();
    body.set(settings.csrfTokenName, settings.csrfToken);
    body.set('endpoint', json.endpoint || subscription.endpoint);
    if (path === 'subscribe') {
      body.set('p256dh', json.keys && json.keys.p256dh ? json.keys.p256dh : '');
      body.set('auth', json.keys && json.keys.auth ? json.keys.auth : '');
      body.set('contentEncoding', PushManager.supportedContentEncodings && PushManager.supportedContentEncodings.length
        ? PushManager.supportedContentEncodings[0]
        : 'aes128gcm');
    }

    var response = await window.fetch(new URL('push/' + path, appBaseUrl).href, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: body.toString()
    });
    if (!response.ok) {
      throw new Error('Push subscription endpoint returned HTTP ' + response.status);
    }
  }

  function removePrompt() {
    var prompt = document.querySelector('[data-hayne-push-prompt]');
    if (prompt) {
      prompt.remove();
    }
  }

  function dismissPrompt() {
    try {
      window.localStorage.setItem(promptKey, String(Date.now()));
    } catch (error) {
      // Storage can be unavailable in hardened browser modes; dismissal still works for this page.
    }
    removePrompt();
  }

  function canShowPrompt() {
    try {
      var dismissedAt = Number(window.localStorage.getItem(promptKey) || 0);
      return !dismissedAt || (Date.now() - dismissedAt) > promptCooldownMs;
    } catch (error) {
      return true;
    }
  }

  function showPrompt() {
    if (document.querySelector('[data-hayne-push-prompt]') || !canShowPrompt()) {
      return;
    }

    var box = document.createElement('section');
    box.className = 'hayne-push-prompt';
    box.setAttribute('data-hayne-push-prompt', 'true');
    box.setAttribute('role', 'dialog');
    box.setAttribute('aria-label', 'Powiadomienia HAYNE Leave');
    box.innerHTML = '<div class="hayne-push-prompt__copy">' +
      '<strong>Włącz powiadomienia</strong>' +
      '<span>Dostaniesz informację o nowych wnioskach i decyzjach.</span>' +
      '</div>' +
      '<div class="hayne-push-prompt__actions">' +
      '<button type="button" class="hayne-push-prompt__later" data-hayne-push-later>Później</button>' +
      '<button type="button" class="hayne-push-prompt__enable" data-hayne-push-enable>Włącz</button>' +
      '</div>';

    box.querySelector('[data-hayne-push-later]').addEventListener('click', dismissPrompt);
    box.querySelector('[data-hayne-push-enable]').addEventListener('click', function () {
      window.HaynePush.enable().catch(function (error) {
        if (window.console && typeof window.console.warn === 'function') {
          window.console.warn('HAYNE Leave: push activation failed.', error);
        }
      });
    });
    document.body.appendChild(box);
  }

  async function enablePush() {
    if (!registration || !pushSupported()) {
      return false;
    }

    var settings = await fetchSettings();
    if (!settings || !settings.enabled || !settings.publicKey) {
      return false;
    }

    var permission = Notification.permission;
    if (permission === 'default') {
      permission = await Notification.requestPermission();
    }
    if (permission !== 'granted') {
      removePrompt();
      return false;
    }

    var subscription = await registration.pushManager.getSubscription();
    if (!subscription) {
      subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(settings.publicKey)
      });
    }
    await postSubscription('subscribe', settings, subscription);
    removePrompt();
    return true;
  }

  async function disablePush() {
    if (!registration || !pushSupported()) {
      return false;
    }
    var subscription = await registration.pushManager.getSubscription();
    if (!subscription) {
      return true;
    }
    var settings = await fetchSettings();
    if (settings) {
      await postSubscription('unsubscribe', settings, subscription);
    }
    await subscription.unsubscribe();
    return true;
  }

  window.HaynePush = {
    enable: enablePush,
    disable: disablePush,
    supported: pushSupported
  };

  window.addEventListener('load', function () {
    navigator.serviceWorker.register(serviceWorkerUrl.href, { scope: scopeUrl.pathname })
      .then(async function (serviceWorkerRegistration) {
        registration = serviceWorkerRegistration;
        if (!pushSupported()) {
          return;
        }

        var settings = await fetchSettings();
        if (!settings || !settings.enabled || !settings.publicKey) {
          return;
        }

        var subscription = await registration.pushManager.getSubscription();
        if (subscription) {
          await postSubscription('subscribe', settings, subscription);
          return;
        }

        if (Notification.permission === 'granted') {
          await enablePush();
          return;
        }

        if (Notification.permission === 'default') {
          showPrompt();
        }
      })
      .catch(function (error) {
        if (window.console && typeof window.console.warn === 'function') {
          window.console.warn('HAYNE Leave: service worker registration failed.', error);
        }
      });
  });
}());
