/**
 * ScoreLive : service worker (PWA).
 *
 * - Pages : réseau d'abord (scores toujours frais), page « hors connexion » en secours.
 * - Fichiers statiques (assets/) : servis depuis le cache puis mis à jour en arrière-plan.
 * - Endpoints JSON (/api/) et logos externes : jamais mis en cache.
 */
'use strict';

var VERSION = 'scorelive-v1';
var SCOPE = new URL(self.registration.scope).pathname; // ex. « /scorelive/ »
var OFFLINE_URL = SCOPE + 'offline.html';
var PRECACHE = [
  OFFLINE_URL,
  SCOPE + 'manifest.json',
  SCOPE + 'assets/img/icon.svg',
  SCOPE + 'assets/img/favicon.svg',
  SCOPE + 'assets/img/icon-192.png',
  SCOPE + 'assets/img/crest-placeholder.svg'
];

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches.open(VERSION)
      .then(function (cache) { return cache.addAll(PRECACHE); })
      .then(function () { return self.skipWaiting(); })
  );
});

self.addEventListener('activate', function (event) {
  event.waitUntil(
    caches.keys()
      .then(function (keys) {
        return Promise.all(keys
          .filter(function (key) { return key.indexOf('scorelive-') === 0 && key !== VERSION; })
          .map(function (key) { return caches.delete(key); }));
      })
      .then(function () { return self.clients.claim(); })
  );
});

/** Met en cache une ressource statique et supprime ses anciennes versions (?v=…). */
function store(cache, request, response) {
  var url = new URL(request.url);
  return cache.keys().then(function (keys) {
    return Promise.all(keys.map(function (key) {
      var old = new URL(key.url);
      if (old.pathname === url.pathname && old.search !== url.search) { return cache.delete(key); }
      return null;
    }));
  }).then(function () { return cache.put(request, response); });
}

self.addEventListener('fetch', function (event) {
  var request = event.request;
  if (request.method !== 'GET') { return; }

  var url = new URL(request.url);
  if (url.origin !== self.location.origin) { return; }
  if (url.pathname.indexOf('/api/') !== -1) { return; }

  // Navigation : réseau d'abord, page hors connexion si le réseau est coupé.
  if (request.mode === 'navigate') {
    event.respondWith(
      fetch(request).catch(function () { return caches.match(OFFLINE_URL); })
    );
    return;
  }

  // Fichiers statiques : cache immédiat + mise à jour en arrière-plan.
  if (url.pathname.indexOf(SCOPE + 'assets/') === 0 || url.pathname === SCOPE + 'manifest.json') {
    event.respondWith(
      caches.open(VERSION).then(function (cache) {
        return cache.match(request).then(function (cached) {
          var network = fetch(request).then(function (response) {
            if (response && response.ok) {
              var copy = response.clone();
              event.waitUntil(store(cache, request, copy));
            }
            return response;
          }).catch(function () {
            return cached || cache.match(request, { ignoreSearch: true });
          });
          return cached || network;
        });
      })
    );
  }
});
