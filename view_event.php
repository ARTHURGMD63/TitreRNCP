<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';
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

// Fetch friends going
$stmtF = $pdo->prepare("
    SELECT u.id, u.prenom, u.nom
    FROM follows_users fu
    JOIN inscriptions i ON i.user_id = fu.followed_id AND i.statut = 'inscrit'
    JOIN users u ON u.id = fu.followed_id
    WHERE fu.follower_id = ? AND i.evenement_id = ?
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
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="icon" type="image/png" href="/Logo.png">
<link rel="apple-touch-icon" href="/Logo.png">
<link rel="manifest" href="/manifest.json">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="StudentLink">
<style>
  .event-hero {
    background: <?= $isFlash ? 'var(--rouge)' : 'var(--bleu)' ?>;
    color: var(--blanc);
    padding: 110px 20px 40px;
    margin: -20px -20px 0;
    border-bottom: 4px solid var(--noir);
    position: relative;
  }
  .event-badge-large {
    background: var(--blanc);
    color: var(--noir);
    border: 3px solid var(--noir);
    padding: 10px 20px;
    font-family: 'Playfair Display', serif;
    font-weight: 900;
    font-size: 2rem;
    display: inline-block;
    box-shadow: 6px 6px 0 var(--noir);
    margin-top: 20px;
  }
  .info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-top: 24px;
  }
  .info-box {
    background: var(--blanc);
    border: 2px solid var(--noir);
    box-shadow: 4px 4px 0 var(--noir);
    padding: 12px;
  }
</style>
</head>
<body>
<div class="app-shell">

  <div class="page-content">
    
    <div class="event-hero">
      <a href="javascript:history.back()" style="position:absolute; top:60px; left:20px; background:var(--blanc); width:36px; height:36px; border-radius:50%; border:2px solid var(--noir); display:flex; align-items:center; justify-content:center; color:var(--noir); box-shadow:3px 3px 0 var(--noir);">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
      </a>
      
      <div style="font-weight:800; text-transform:uppercase; font-size:12px; letter-spacing:0.1em; opacity:0.8; margin-bottom:8px;">
        <?= strtoupper($e['etab_type']) ?> · <?= date('D j M', strtotime($e['date_heure'])) ?>
      </div>
      <div class="display" style="font-size:2.6rem; line-height:1;"><?= htmlspecialchars($e['titre']) ?></div>
      <div class="display-italic" style="font-size:2rem; color:var(--blanc); margin-top:4px;">au <?= htmlspecialchars($e['etablissement_nom']) ?></div>
      
      <div class="event-badge-large">
        -<?= $e['reduction'] ?>%
      </div>
    </div>

    <!-- Main Content -->
    <div style="margin-top:24px;">
      
      <!-- Stats bar -->
      <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
        <div style="font-weight:900; font-size:14px;"><?= $e['nb_inscrits'] ?> / <?= $e['quota'] ?> inscrits</div>
        <div style="font-weight:900; font-size:14px; color:var(--rouge);"><?= $pct ?>%</div>
      </div>
      <div class="progress-bar" style="height:12px; border:2px solid var(--noir); background:var(--gris-clair);">
        <div class="progress-bar-fill dark" style="width:<?= $pct ?>%;"></div>
      </div>

      <!-- Description -->
      <div style="margin-top:32px;">
        <div style="font-weight:900; font-size:13px; text-transform:uppercase; color:var(--gris); margin-bottom:12px;">À propos de l'événement</div>
        <div style="font-size:15px; line-height:1.6; color:var(--noir);">
          <?= nl2br(htmlspecialchars($e['description'])) ?>
        </div>
      </div>

      <!-- Practical Info -->
      <div class="info-grid">
        <div class="info-box">
          <div style="font-size:9px; font-weight:800; text-transform:uppercase; color:var(--gris); margin-bottom:4px;">Date & Heure</div>
          <div style="font-weight:700; font-size:13px;"><?= date('j M Y', strtotime($e['date_heure'])) ?></div>
          <div style="font-weight:700; font-size:13px;"><?= date('H\hi', strtotime($e['date_heure'])) ?></div>
        </div>
        <div class="info-box">
          <div style="font-size:9px; font-weight:800; text-transform:uppercase; color:var(--gris); margin-bottom:4px;">Lieu</div>
          <div style="font-weight:700; font-size:13px;"><?= htmlspecialchars($e['etablissement_nom']) ?></div>
          <div style="font-size:11px; color:var(--gris);"><?= htmlspecialchars($e['ville']) ?></div>
        </div>
      </div>

      <!-- Friends -->
      <?php if (!empty($friends)): ?>
      <div style="margin-top:32px;">
        <div style="font-weight:900; font-size:13px; text-transform:uppercase; color:var(--gris); margin-bottom:16px;">Tes potes qui y vont</div>
        <div style="display:flex; flex-wrap:wrap; gap:10px;">
          <?php foreach ($friends as $f): ?>
            <a href="view_profile.php?id=<?= $f['id'] ?>" style="display:flex; align-items:center; gap:8px; background:var(--blanc); border:2px solid var(--noir); padding:6px 12px; text-decoration:none; color:inherit; box-shadow:3px 3px 0 var(--noir);">
              <div style="width:24px; height:24px; border-radius:50%; background:var(--bleu); border:1px solid var(--noir); display:flex; align-items:center; justify-content:center; color:var(--blanc); font-size:10px; font-weight:900;">
                <?= strtoupper(mb_substr($f['prenom'], 0, 1)) ?>
              </div>
              <span style="font-size:12px; font-weight:700;"><?= htmlspecialchars($f['prenom']) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Join Button -->
      <div style="margin:40px 0 60px;">
        <button class="btn btn-primary btn-full btn-join-event" 
                data-event-id="<?= $e['id'] ?>" 
                style="padding:20px; font-size:1.1rem; <?= $e['deja_inscrit']?'background:var(--noir);':'' ?>"
                <?= $e['deja_inscrit']?'disabled':'' ?>>
          <?= $e['deja_inscrit'] ? '✓ TU ES INSCRIT·E' : 'REJOINDRE L\'ÉVÉNEMENT' ?>
        </button>
      </div>

    </div>

  </div>
</div>

<div class="toast" id="toast"></div>

<script src="/assets/js/app.js"></script>
</body>
</html>
