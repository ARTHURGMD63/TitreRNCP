<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/sponsoring.php';
require_once __DIR__ . '/includes/musique.php';
require_once __DIR__ . '/includes/icons.php';
requireStudent();
$me_uid = currentUser()['id'];

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: explore.php');
    exit;
}

// Fetch event data
$stmt = $pdo->prepare("
    SELECT e.*, et.nom AS etablissement_nom, et.ville, et.type AS etab_type,
           (SELECT COUNT(*) FROM inscriptions WHERE evenement_id = e.id AND statut != 'annule') AS nb_inscrits,
           (SELECT COUNT(*) FROM inscriptions WHERE evenement_id = e.id AND user_id = ? AND statut != 'annule') AS deja_inscrit
    FROM evenements e
    JOIN etablissements et ON et.id = e.etablissement_id
    WHERE e.id = ?
");
$stmt->execute([$me_uid, $id]);
$e = $stmt->fetch();

if (!$e) {
    echo "Événement introuvable.";
    exit;
}

require_once __DIR__ . '/includes/uploads.php';

// Photos de l'établissement (la couverture est en position 0)
$stmt = $pdo->prepare("SELECT fichier, legende FROM etablissement_photos WHERE etablissement_id=? ORDER BY position ASC, id ASC LIMIT 8");
$stmt->execute([$e['etablissement_id']]);
$venuePhotos = $stmt->fetchAll();

// Fetch friends going
$stmtF = $pdo->prepare("
    SELECT u.id, u.prenom, u.nom, u.photo
    FROM follows_users fu
    JOIN inscriptions i ON i.user_id = fu.followed_id AND i.statut = 'inscrit'
    JOIN users u ON u.id = fu.followed_id
    WHERE fu.follower_id = ? AND fu.statut = 'accepted' AND i.evenement_id = ?
");
$stmtF->execute([$me_uid, $id]);
$friends = $stmtF->fetchAll();

$pct = $e['quota'] > 0 ? round($e['nb_inscrits'] / $e['quota'] * 100) : 0;
$isFlash = $e['is_flash'] && strtotime($e['flash_expiry'] ?? '') > time();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($e['titre']) ?> — StudentLink</title>
<?= themeBootScript() ?>
<link rel="stylesheet" href="<?= asset('/assets/css/style.css') ?>">
<link rel="icon" type="image/png" href="<?= baseUrl('/Logo.png') ?>">
<link rel="apple-touch-icon" href="<?= baseUrl('/Logo.png') ?>">
<link rel="manifest" href="<?= baseUrl('/manifest.json') ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="StudentLink">
<style>
  /* Le hero porte la photo du lieu quand elle existe ; l'aplat de couleur
     n'est plus que le repli. Le texte reste lisible grâce au voile dégradé,
     jamais grâce à un assombrissement global de l'image.                  */
  .event-hero {
    position: relative;
    isolation: isolate;
    color: var(--sur-media);
    padding: 120px 20px 32px;
    margin-left: calc(var(--gutter) * -1);
    margin-right: calc(var(--gutter) * -1);
    margin-top: -20px;
    background: <?= $isFlash ? 'var(--rouge)' : 'var(--bleu)' ?>;
    border-radius: 0 0 var(--radius-xl) var(--radius-xl);
    overflow: hidden;
  }
  .event-hero-media {
    position: absolute; inset: 0; z-index: -2;
    background-size: cover; background-position: center;
  }
  /* Voile : dense en bas où se trouve le texte, transparent en haut. */
  .event-hero-scrim {
    position: absolute; inset: 0; z-index: -1;
    background: linear-gradient(to top,
      rgba(12,10,8,.92) 0%, rgba(12,10,8,.72) 32%,
      rgba(12,10,8,.34) 62%, rgba(12,10,8,.18) 100%);
  }
  .event-hero .hero-eyebrow {
    font-weight: var(--fw-bold); text-transform: uppercase;
    font-size: var(--fs-2); letter-spacing: var(--ls-label);
    opacity: .85; margin-bottom: 10px;
  }
  .event-badge-large {
    background: rgba(255,255,255,.14);
    -webkit-backdrop-filter: blur(12px); backdrop-filter: blur(12px);
    border: 1px solid rgba(255,255,255,.28);
    color: var(--sur-media);
    padding: 8px 18px;
    border-radius: var(--radius-pill);
    font-family: var(--font-display);
    font-weight: var(--fw-black);
    font-size: var(--fs-7);
    display: inline-block;
    margin-top: 18px;
  }
  .info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-top: 24px;
  }

  /* Sur ordinateur la fiche cessait d'etre un telephone au milieu du vide :
     le recit passe a gauche, la decision reste a droite, visible pendant
     qu'on fait defiler. Sur telephone, rien ne change : une seule colonne. */
  @media (min-width: 1000px) {
    .ev-grille {
      display: grid;
      grid-template-columns: minmax(0, 1fr) 330px;
      gap: 36px;
      align-items: start;
      margin-top: 8px;
    }
    .ev-aside { position: sticky; top: 24px; }
    .ev-aside .info-grid { grid-template-columns: 1fr; margin-top: 0; }
    .ev-cta { margin: 20px 0 0 !important; }
    .ev-cta .btn { padding: 18px !important; font-size: var(--fs-5) !important; }
  }
  .info-box {
    background: var(--blanc);
    border: 1px solid var(--gris-clair);
    border-radius: var(--radius);
    box-shadow: var(--shadow-xs);
    padding: 14px;
  }
  .info-box .info-label {
    display: flex; align-items: center; gap: 7px;
    font-size: var(--fs-1); font-weight: var(--fw-bold);
    text-transform: uppercase; letter-spacing: var(--ls-wide);
    color: var(--gris); margin-bottom: 8px;
  }
</style>
</head>
<body>
<div class="app-shell">

  <main id="main-content" class="page-content">
    
    <div class="event-hero">
      <?php if (!empty($venuePhotos)): ?>
        <div class="event-hero-media" style="background-image:url('<?= venuePhotoUrl($venuePhotos[0]['fichier']) ?>');" role="img"
             aria-label="<?= htmlspecialchars($venuePhotos[0]['legende'] ?? ('Photo de ' . $e['etablissement_nom'])) ?>"></div>
        <div class="event-hero-scrim"></div>
      <?php endif; ?>

      <a href="javascript:history.back()" aria-label="Retour"
         style="position:absolute; top:56px; left:20px; z-index:2; background:rgba(255,255,255,.18); -webkit-backdrop-filter:blur(12px); backdrop-filter:blur(12px); width:40px; height:40px; border-radius:50%; border:1px solid rgba(255,255,255,.3); display:flex; align-items:center; justify-content:center; color:var(--sur-media);">
        <svg class="icon" viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
      </a>

      <div class="hero-eyebrow">
        <?= mb_strtoupper($e['etab_type']) ?> · <?= dateFr($e['date_heure'], 'D j M') ?><?php
          // Le style se lit dès l'en-tête : c'est ce qui décide d'y aller ou non,
          // au même titre que la date.
          $style = libelleStyleMusique($e['style_musique'] ?? null);
          echo $style ? ' · ' . htmlspecialchars(mb_strtoupper($style)) : '';
        ?>
      </div>
      <?php if (sponsoringActif($e)): ?>
        <!-- La mention reste sur la fiche, pas seulement dans le fil : un
             contenu paye doit s'annoncer partout ou il est lu. -->
        <div class="badge badge-sponsorise-media" style="margin-bottom:10px;">Sponsorisé</div>
      <?php endif; ?>
      <h1 class="titre-page">
        <div class="display" style="font-size:var(--fs-9); line-height:var(--lh-display);"><?= htmlspecialchars($e['titre']) ?></div>
        <div class="display-italic" style="font-size:var(--fs-8); color:var(--sur-media); margin-top:4px;"><?= htmlspecialchars($e['etablissement_nom']) ?></div>
      </h1>
      
      <?php if ((int) $e['reduction'] > 0): ?>
        <div class="event-badge-large">
          -<?= (int) $e['reduction'] ?>%
        </div>
      <?php elseif (!empty($e['is_gratuit'])): ?>
        <div class="event-badge-large">Gratuit</div>
      <?php endif; ?>
    </div>

    <!-- Main Content -->
    <div style="margin-top:24px;">
      
      <!-- Stats bar -->
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
        <div class="with-icon" style="font-weight:var(--fw-bold); font-size:var(--fs-4);">
          <svg class="icon" viewBox="0 0 24 24" style="color:var(--gris-fonce);" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
          <span><?= $e['nb_inscrits'] ?> / <?= $e['quota'] ?> inscrits</span>
        </div>
        <div style="font-weight:var(--fw-bold); font-size:var(--fs-4); color:var(--sur-rouge-clair);"><?= $pct ?>%</div>
      </div>
      <div class="progress-bar" style="height:10px; background:var(--gris-clair);">
        <div class="progress-bar-fill dark" style="width:<?= $pct ?>%;"></div>
      </div>

      <div class="ev-grille">
      <div class="ev-corps">

      <!-- Description -->
      <div style="margin-top:32px;">
        <div class="with-icon" style="font-weight:var(--fw-bold); font-size:var(--fs-3); text-transform:uppercase; color:var(--gris); margin-bottom:12px; letter-spacing:var(--ls-wide);">
          <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
          À propos de l'événement
        </div>
        <div style="font-size:var(--fs-5); line-height:var(--lh-relaxed); color:var(--noir);">
          <?= nl2br(htmlspecialchars($e['description'])) ?>
        </div>
      </div>


      <!-- Photos de l'établissement -->
      <?php if (!empty($venuePhotos)): ?>
      <div style="margin-top:32px;">
        <div class="with-icon" style="font-weight:var(--fw-bold); font-size:var(--fs-3); text-transform:uppercase; color:var(--gris); margin-bottom:16px; letter-spacing:var(--ls-wide);">
          <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
          Le lieu en photos
        </div>
        <div style="display:flex; gap:12px; overflow-x:auto; -webkit-overflow-scrolling:touch; padding-bottom:8px; scrollbar-width:thin;">
          <?php foreach ($venuePhotos as $vp): ?>
            <figure style="margin:0; flex:0 0 auto;">
              <img src="<?= venuePhotoUrl($vp['fichier']) ?>"
                   alt="<?= htmlspecialchars($vp['legende'] ?? ('Photo de ' . $e['etablissement_nom'])) ?>"
                   loading="lazy"
                   style="height:180px; width:auto; max-width:280px; object-fit:cover; display:block; border:1px solid var(--gris-clair); box-shadow:var(--shadow-sm);">
              <?php if (!empty($vp['legende'])): ?>
                <figcaption style="font-size:var(--fs-1); color:var(--gris); margin-top:6px; max-width:280px;">
                  <?= htmlspecialchars($vp['legende']) ?>
                </figcaption>
              <?php endif; ?>
            </figure>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Friends -->
      <?php if (!empty($friends)): ?>
      <div style="margin-top:32px;">
        <div style="font-weight:var(--fw-bold); font-size:var(--fs-3); text-transform:uppercase; color:var(--gris); margin-bottom:16px;">Tes potes qui y vont</div>
        <div style="display:flex; flex-wrap:wrap; gap:10px;">
          <?php foreach ($friends as $f): ?>
            <a href="view_profile.php?id=<?= $f['id'] ?>" style="display:flex; align-items:center; gap:8px; background:var(--blanc); border:1px solid var(--gris-clair); padding:6px 12px; text-decoration:none; color:inherit; box-shadow:var(--shadow-sm);">
              <?= avatarHtml($f['photo'] ?? null, $f['prenom'], 24) ?>
              <span style="font-size:var(--fs-2); font-weight:var(--fw-bold);"><?= htmlspecialchars($f['prenom']) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      </div><!-- /.ev-corps -->

      <aside class="ev-aside">
      <!-- Practical Info -->
      <div class="info-grid">
        <div class="info-box">
          <div class="info-label">
            <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Date &amp; heure
          </div>
          <div style="font-weight:var(--fw-bold); font-size:var(--fs-3);"><?= dateFr($e['date_heure'], 'j M Y') ?></div>
          <div style="font-weight:var(--fw-bold); font-size:var(--fs-3);"><?= date('H\hi', strtotime($e['date_heure'])) ?></div>
        </div>
        <div class="info-box">
          <div class="info-label">
            <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            Lieu
          </div>
          <div style="font-weight:var(--fw-bold); font-size:var(--fs-3);"><?= htmlspecialchars($e['etablissement_nom']) ?></div>
          <div style="font-size:var(--fs-1); color:var(--gris);"><?= htmlspecialchars($e['ville']) ?></div>
        </div>
      </div>

      <!-- Join Button -->
      <div class="ev-cta" style="margin:40px 0 60px;">
        <button class="btn btn-primary btn-full btn-join-event" 
                data-event-id="<?= $e['id'] ?>" 
                style="padding:20px; font-size:var(--fs-6); <?= $e['deja_inscrit']?'background:var(--noir);':'' ?>"
                <?= $e['deja_inscrit']?'disabled':'' ?>>
          <?= $e['deja_inscrit'] ? icon('check','icon-sm').' TU ES INSCRIT·E' : 'REJOINDRE L\'ÉVÉNEMENT' ?>
        </button>
      </div>
      </aside>
      </div><!-- /.ev-grille -->

    </div>

  </main>
</div>

<nav class="bottom-nav nav-ordinateur" aria-label="Navigation principale">
  <a href="<?= baseUrl('/explore.php') ?>" class="nav-item">
    <span class="nav-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></span>
    <span>Explore</span>
  </a>
  <a href="<?= baseUrl('/squads.php') ?>" class="nav-item">
    <span class="nav-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg></span>
    <span>Squads</span>
  </a>
  <a href="<?= baseUrl('/wallet.php') ?>" class="nav-item">
    <span class="nav-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg></span>
    <span>Wallet</span>
  </a>
  <a href="<?= baseUrl('/profil.php') ?>" class="nav-item">
    <span class="nav-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></span>
    <span>Moi</span>
  </a>
</nav>

<div class="toast" id="toast" role="status" aria-live="polite"></div>

<script src="<?= asset('/assets/js/app.js') ?>"></script>
</body>
</html>
