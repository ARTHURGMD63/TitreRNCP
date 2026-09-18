/* StudentLink — service worker
 *
 * Deux objectifs :
 *  1. rendre l'application réellement installable (un manifeste seul ne suffit pas) ;
 *  2. tenir debout sur un réseau faible — on ouvre l'app dans un bar, pas au bureau.
 *
 * Stratégie volontairement simple :
 *  - les fichiers statiques sont servis depuis le cache en priorité (ils sont
 *    versionnés par empreinte, donc une nouvelle version a une nouvelle URL) ;
 *  - les pages passent par le réseau d'abord, avec repli sur le cache puis sur
 *    une page hors-ligne. Jamais de contenu périmé servi silencieusement.
 */

const VERSION = 'v1';
const CACHE_COQUILLE = 'studentlink-coquille-' + VERSION;
const CACHE_PAGES = 'studentlink-pages-' + VERSION;

// Résolu à l'installation : le service worker est servi depuis la racine du projet.
const RACINE = new URL('./', self.location).pathname;

const COQUILLE = [
  RACINE + 'assets/css/style.css',
  RACINE + 'assets/js/app.js',
  RACINE + 'Logo.png',
  RACINE + 'manifest.json',
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_COQUILLE)
      // Une ressource absente ne doit pas faire échouer toute l'installation.
      .then(cache => Promise.allSettled(COQUILLE.map(url => cache.add(url))))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(
    caches.keys()
      .then(noms => Promise.all(
        noms.filter(n => n.startsWith('studentlink-') && !n.endsWith(VERSION))
            .map(n => caches.delete(n))
      ))
      .then(() => self.clients.claim())
  );
});

const estStatique = url =>
  /\.(css|js|png|jpg|jpeg|webp|svg|woff2?)$/i.test(url.pathname);

self.addEventListener('fetch', event => {
  const req = event.request;

  // On ne touche ni aux écritures, ni aux appels d'API, ni aux autres domaines :
  // une inscription ou un scan doit toujours partir sur le réseau réel.
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;
  if (url.pathname.includes('/api/')) return;

  if (estStatique(url)) {
    // Cache d'abord : l'empreinte ?v= garantit qu'une nouvelle version
    // correspond à une nouvelle entrée, donc jamais de fichier périmé.
    event.respondWith(
      caches.match(req).then(hit => hit || fetch(req).then(res => {
        if (res.ok) {
          const copie = res.clone();
          caches.open(CACHE_COQUILLE).then(c => c.put(req, copie));
        }
        return res;
      }))
    );
    return;
  }

  // Pages : réseau d'abord, cache en secours, page hors-ligne en dernier recours.
  event.respondWith(
    fetch(req)
      .then(res => {
        if (res.ok) {
          const copie = res.clone();
          caches.open(CACHE_PAGES).then(c => c.put(req, copie));
        }
        return res;
      })
      .catch(() => caches.match(req).then(hit => hit || caches.match(RACINE + 'hors-ligne.html')))
  );
});
