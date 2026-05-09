const CACHE_NAME = 'kasihsosial-v2';
const urlsToCache = [
  '/',
  '/index.php',
  '/beranda.php',
  '/login.php',
  '/register.php',
  '/mobile.css',
  '/manifest.json',
  // tambahkan asset penting lainnya
];

// Install
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(urlsToCache))
  );
});

// Activate
self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys().then(keys => {
      return Promise.all(
        keys.filter(key => key !== CACHE_NAME).map(key => caches.delete(key))
      );
    })
  );
});

// Fetch
self.addEventListener('fetch', event => {
  event.respondWith(
    caches.match(event.request).then(response => {
      return response || fetch(event.request);
    })
  );
});