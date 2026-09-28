<?php
/**
 * Carte interactive des soirées — nouveauté propre à l'application, sans
 * ancien gabarit à reprendre : explore.php liste les événements, ici on les
 * situe. Une épingle par établissement dont la position est connue (réglée
 * par le partenaire, ou déduite de son adresse — includes/geocodage.php) ;
 * un établissement qui n'a jamais rien réglé reste listé normalement dans
 * explore.php, simplement absent d'ici.
 *
 * Leaflet est auto-hébergé (assets/vendor/leaflet.js) comme html5-qrcode :
 * la CSP du site n'autorise que 'self' en script-src.
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/page.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/hub.php';
requireStudent();
$uid = (int) (currentUser()['id']);

$criteres   = hubCriteres(['type' => 'all']);
$fil        = hubEvenements($pdo, $uid, $criteres, [], []);
$evenements = array_values(array_filter(
    $fil['evenements'],
    static fn (array $e): bool => $e['etab_latitude'] !== null && $e['etab_longitude'] !== null
));

$points = array_map(
    static fn (array $e): array => [
        'id'        => (int) $e['id'],
        'titre'     => $e['titre'],
        'lieu'      => $e['etablissement_nom'],
        'date'      => dateFr($e['date_heure'], 'D j M · H\hi'),
        'en_cours'  => !empty($e['en_cours_calc']),
        'latitude'  => (float) $e['etab_latitude'],
        'longitude' => (float) $e['etab_longitude'],
        'url'       => baseUrl('/view_event.php?id=' . (int) $e['id']),
    ],
    $evenements
);
?>
<?php ob_start(); ?>
<link rel="stylesheet" href="<?= asset('/assets/vendor/leaflet.css') ?>">
<style>
  #carte-soirees { position: fixed; inset: 0; z-index: 0; }
  .carte-entete {
    position: fixed; top: calc(env(safe-area-inset-top, 0px) + 16px); left: 20px; right: 20px;
    z-index: 10; display: flex; align-items: center; justify-content: space-between; gap: 12px;
    pointer-events: none;
  }
  .carte-entete > * { pointer-events: auto; }
  .carte-retour {
    width: 40px; height: 40px; border-radius: 50%; background: rgba(17,16,19,.7);
    display: flex; align-items: center; justify-content: center; color: #F5F1E8; text-decoration: none;
  }
  .carte-compteur {
    background: rgba(17,16,19,.7); color: #F5F1E8; border-radius: var(--radius-pill);
    padding: 8px 16px; font-family: var(--font-mono); font-size: var(--fs-2); font-weight: var(--fw-bold);
  }
  .leaflet-popup-content { font-family: var(--font-text); min-width: 180px; }
  .popup-badge { display:inline-block;font-family:var(--font-mono);font-size:11px;font-weight:700;
    text-transform:uppercase;color:var(--sur-lave);background:var(--lime);border-radius:99px;
    padding:2px 8px;margin-bottom:6px; }
  .popup-titre { font-weight:var(--fw-bold);font-size:var(--fs-4);margin-bottom:2px; }
  .popup-meta { font-size:var(--fs-2);color:var(--gris); }

  /* L'épingle : un rond de couleur avec sa pointe, pas le pin par défaut de
     Leaflet — trop générique pour porter la charte. Même dessin que côté
     mobile (composants/BarreOnglets · carte.tsx). */
  .epingle-linkee { display: flex; flex-direction: column; align-items: center; }
  .epingle-linkee .rond {
    width: 32px; height: 32px; border-radius: 50%; border: 3px solid #F5F1E8;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 2px 4px rgba(0,0,0,.25);
  }
  .epingle-linkee .pointe {
    width: 10px; height: 10px; margin-top: -8px; transform: rotate(45deg);
    border: 3px solid #F5F1E8; border-top: none; border-left: none;
  }
  .epingle-avenir .rond, .epingle-avenir .pointe { background: var(--rouge); }
  .epingle-encours .rond, .epingle-encours .pointe { background: var(--lime); }
  .epingle-encours .rond { animation: epingle-pouls 1.4s ease-in-out infinite; }
  @keyframes epingle-pouls {
    0%, 100% { box-shadow: 0 0 0 0 rgba(200,245,71,.55), 0 2px 4px rgba(0,0,0,.25); }
    50%      { box-shadow: 0 0 0 8px rgba(200,245,71,0), 0 2px 4px rgba(0,0,0,.25); }
  }

  .carte-localiser {
    position: fixed; right: 16px; bottom: calc(env(safe-area-inset-bottom, 0px) + 20px); z-index: 10;
    width: 44px; height: 44px; border-radius: 50%; background: var(--blanc);
    border: 1px solid var(--gris-clair); display: flex; align-items: center; justify-content: center;
    box-shadow: var(--shadow); cursor: pointer;
  }
</style>
<?php pageDebut('Linkee — Carte des soirées', [
    'pwa' => true,
    'scripts' => ['/assets/vendor/leaflet.js'],
    'tete' => ob_get_clean(),
]); ?>

<div class="carte-entete">
  <a href="<?= baseUrl('/explore.php') ?>" class="carte-retour" aria-label="Retour">
    <?= icon('fleche-g', 'icon-sm') ?>
  </a>
  <div class="carte-compteur"><?= count($points) ?> soirée<?= count($points) > 1 ? 's' : '' ?> sur la carte</div>
</div>

<div id="carte-soirees" role="application" aria-label="Carte des soirées"></div>

<button type="button" class="carte-localiser" id="btn-localiser" aria-label="Me localiser">
  <?= icon('epingle', 'icon-sm') ?>
</button>

<script>
(function () {
  var points = <?= json_encode($points, JSON_UNESCAPED_SLASHES) ?>;

  function icone(enCours) {
    return L.divIcon({
      className: '',
      html: '<div class="epingle-linkee ' + (enCours ? 'epingle-encours' : 'epingle-avenir') + '">' +
            '<div class="rond"></div><div class="pointe"></div></div>',
      iconSize: [32, 40],
      iconAnchor: [16, 40],
      popupAnchor: [0, -40]
    });
  }

  var carte = L.map('carte-soirees').setView([45.7772, 3.0870], 13);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
  }).addTo(carte);

  var bornes = [];
  points.forEach(function (p) {
    var marqueur = L.marker([p.latitude, p.longitude], { icon: icone(p.en_cours) }).addTo(carte);
    marqueur.bindPopup(
      '<div>' +
      (p.en_cours ? '<span class="popup-badge">En cours</span><br>' : '') +
      '<div class="popup-titre">' + p.titre.replace(/</g, '&lt;') + '</div>' +
      '<div class="popup-meta">' + p.lieu.replace(/</g, '&lt;') + ' · ' + p.date + '</div>' +
      '<a href="' + p.url + '" style="font-weight:700;font-size:13px;">Voir la soirée →</a>' +
      '</div>'
    );
    bornes.push([p.latitude, p.longitude]);
  });

  if (bornes.length > 0) {
    carte.fitBounds(bornes, { padding: [40, 40], maxZoom: 15 });
  }

  // « Me localiser » : geolocation du navigateur, pas de dépendance externe.
  var marqueurMoi = null;
  document.getElementById('btn-localiser').addEventListener('click', function () {
    if (!navigator.geolocation) return;
    navigator.geolocation.getCurrentPosition(function (pos) {
      var ici = [pos.coords.latitude, pos.coords.longitude];
      if (marqueurMoi) carte.removeLayer(marqueurMoi);
      marqueurMoi = L.circleMarker(ici, {
        radius: 8, color: '#F5F1E8', weight: 3, fillColor: '#5B8CFF', fillOpacity: 1
      }).addTo(carte);
      carte.setView(ici, 15);
    }, function () {
      // Pas de showToast ici : cette page ne charge pas app.js (pas de
      // modales, pas de formulaires) — un échec de géolocalisation reste
      // silencieux plutôt que de charger tout ce fichier pour un seul message.
      console.warn('Géolocalisation refusée ou indisponible.');
    });
  });
})();
</script>

</body>
</html>
