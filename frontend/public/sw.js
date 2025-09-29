// Minimal service worker for PWA install capability only
// No caching - all requests go to network for fresh data

self.addEventListener('install', (event) => {
  // Skip waiting to activate immediately
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  // Claim all clients immediately
  event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', (event) => {
  // Always fetch from network - no caching
  // This ensures data is always fresh from server
  event.respondWith(fetch(event.request));
});