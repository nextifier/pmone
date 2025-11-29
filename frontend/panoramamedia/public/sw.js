self.addEventListener("install", () => {
  self.skipWaiting(); // Force the new service worker to take control immediately
});

self.addEventListener("activate", () => {
  self.registration.unregister().then(() => {
    console.log("Service worker unregistered successfully.");
  });
});
