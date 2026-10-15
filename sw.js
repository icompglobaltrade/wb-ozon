// Офлайн-режим: файлы приложения сохраняются на телефоне при первом открытии.
const CACHE = "margin-calc-v7";
const FILES = ["./", "index.html", "manifest.webmanifest", "apple-touch-icon.png", "icon-192.png", "icon-512.png"];

self.addEventListener("install", e => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(FILES)).then(() => self.skipWaiting()));
});

self.addEventListener("activate", e => {
  e.waitUntil(
    caches.keys()
      .then(keys => Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener("fetch", e => {
  const req = e.request;
  if (req.method !== "GET") return;
  const url = new URL(req.url);
  // Страница приложения: сначала сеть (чтобы получать обновления), без сети — из кэша.
  if (req.mode === "navigate") {
    e.respondWith(
      fetch(req).then(res => { caches.open(CACHE).then(c => c.put("index.html", res.clone())); return res; })
        .catch(() => caches.match("index.html"))
    );
    return;
  }
  // Иконки и шрифты Google: из кэша, а если нет — скачать и сохранить.
  if (url.origin === location.origin || url.hostname.endsWith("fonts.googleapis.com") || url.hostname.endsWith("fonts.gstatic.com")) {
    e.respondWith(
      caches.match(req).then(hit => hit || fetch(req).then(res => {
        const copy = res.clone();
        caches.open(CACHE).then(c => c.put(req, copy));
        return res;
      }))
    );
  }
});
