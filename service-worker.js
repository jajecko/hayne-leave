'use strict';

// HAYNE Leave deliberately has no fetch handler and no offline cache.
// Leave requests must always use live application state.
self.addEventListener('install', () => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('push', (event) => {
  let payload = {};
  if (event.data) {
    try {
      payload = event.data.json();
    } catch (error) {
      payload = { body: event.data.text() };
    }
  }

  const title = payload.title || 'HAYNE Leave';
  const options = {
    body: payload.body || 'Masz nowe powiadomienie.',
    icon: './assets/hayne/pwa-icon-192.png',
    badge: './assets/hayne/notification-badge-128.png',
    tag: payload.tag || 'hayne-leave',
    renotify: false,
    data: {
      url: payload.url || './'
    }
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const targetUrl = new URL(event.notification.data && event.notification.data.url
    ? event.notification.data.url
    : './', self.location.origin).href;

  event.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then((clientList) => {
    for (const client of clientList) {
      if (client.url === targetUrl && 'focus' in client) {
        return client.focus();
      }
    }
    if (self.clients.openWindow) {
      return self.clients.openWindow(targetUrl);
    }
    return undefined;
  }));
});
