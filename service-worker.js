'use strict';

// PWA-01 intentionally has no fetch handler and no offline cache.
// Leave requests must always use the live application state.
self.addEventListener('install', () => {
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(self.clients.claim());
});
