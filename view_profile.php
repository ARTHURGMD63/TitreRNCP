<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';
requireStudent();
$me_uid = currentUser()['id'];

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: explore.php?view=people');
    exit;
}

// Fetch user data
$stmt = $pdo->prepare("
    SELECT id, prenom, nom, ecole, promo, interests, created_at,
           (SELECT COUNT(*) FROM follows_users WHERE follower_id = ? AND followed_id = ?) AS is_following,
           (SELECT COUNT(*) FROM follows_users WHERE followed_id = ?) AS nb_followers,
           (SELECT COUNT(*) FROM follows_users WHERE follower_id = ?) AS nb_following
    FROM users WHERE id = ? AND type='etudiant'
");
$stmt->execute([$me_uid, $id, $id, $id, $id]);
$u = $stmt->fetch();

if (!$u) {
    echo "Étudiant introuvable.";
    exit;
}

$initials = strtoupper(mb_substr($u['prenom'], 0, 1) . mb_substr($u['nom'], 0, 1));
$interests = $u['interests'] ? explode(',', $u['interests']) : [];

// Fetch common squads
$stmtSq = $pdo->prepare("
    SELECT s.titre, s.type, s.date_heure
    FROM squads s
    JOIN squad_membres sm1 ON sm1.squad_id = s.id
    JOIN squad_membres sm2 ON sm2.squad_id = s.id
    WHERE sm1.user_id = ? AND sm2.user_id = ? AND s.date_heure >= NOW()
");
$stmtSq->execute([$me_uid, $id]);
$commonSquads = $stmtSq->fetchAll();

// Fetch upcoming events for this user
$stmtEv = $pdo->prepare("
    SELECT e.titre, e.date_heure, et.nom AS etablissement_nom, et.type AS etab_type
    FROM inscriptions i
    JOIN evenements e ON e.id = i.evenement_id
    JOIN etablissements et ON et.id = e.etablissement_id
    WHERE i.user_id = ? AND i.statut = 'inscrit' AND e.date_heure >= NOW()
    ORDER BY e.date_heure ASC LIMIT 3
");
$stmtEv->execute([$id]);
$upcomingEvents = $stmtEv->fetchAll();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($u['prenom']) ?> — StudentLink</title>
<link rel="stylesheet" href="<?= baseUrl() ?>/assets/css/style.css">
<link rel="icon" type="image/png" href="/Logo.png">
<link rel="apple-touch-icon" href="/Logo.png">
<link rel="manifest" href="/manifest.json">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="StudentLink">
<style>
  .profile-header-bg {
    background: var(--bleu);
    height: 160px;
    margin: -20px -20px 0;
    position: relative;
    border-bottom: 3px solid var(--noir);
  }
  .profile-avatar-large {
    width: 100px;
    height: 100px;
    background: var(--blanc);
    border: 3px solid var(--noir);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Playfair Display', serif;
    font-weight: 900;
    font-size: 2.2rem;
    position: absolute;
    bottom: -50px;
    left: 20px;
    box-shadow: 6px 6px 0 rgba(0,0,0,0.2);
  }
  .stats-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin: 60px 0 24px;
  }
  .stat-card {
    background: var(--blanc);
    border: 2px solid var(--noir);
    box-shadow: 4px 4px 0 var(--noir);
    padding: 12px;
    text-align: center;
  }
  .section-card {
    background: var(--blanc);
    border: 2px solid var(--noir);
    box-shadow: 4px 4px 0 var(--noir);
    padding: 20px;
    margin-bottom: 24px;
  }
  .interest-pill {
    display: inline-block;
    padding: 6px 12px;
    background: var(--bleu-clair);
    border: 2px solid var(--noir);
    font-size: 12px;
    font-weight: 800;
    margin: 0 6px 8px 0;
  }
  .event-strip {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid var(--gris-clair);
  }
  .event-strip:last-child { border-bottom: none; }
</style>
</head>
<body>
<div class="app-shell">

  <div class="page-content">
    
    <!-- Header -->
    <div class="profile-header-bg">
      <a href="javascript:history.back()" style="position:absolute; top:60px; left:20px; background:var(--blanc); width:36px; height:36px; border-radius:50%; border:2px solid var(--noir); display:flex; align-items:center; justify-content:center; color:var(--noir); box-shadow:3px 3px 0 var(--noir);">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
      </a>
      <div class="profile-avatar-large"><?= $initials ?></div>
    </div>

    <!-- Info Block -->
    <div class="stats-grid">
      <div style="grid-column: span 2;">
        <div class="display" style="font-size:2.2rem; line-height:1;"><?= htmlspecialchars($u['prenom']) ?></div>
        <div class="display-italic" style="font-size:2.2rem; line-height:1;"><?= htmlspecialchars($u['nom']) ?></div>
        <div style="font-weight:800; text-transform:uppercase; font-size:11px; color:var(--gris); margin-top:8px; letter-spacing:0.05em;">
          <?= htmlspecialchars($u['ecole']) ?> · PROMO <?= htmlspecialchars($u['promo']) ?>
        </div>
      </div>
      
      <div class="stat-card">
        <div style="font-family:'Playfair Display',serif; font-weight:900; font-size:1.4rem;"><?= $u['nb_followers'] ?></div>
        <div style="font-size:9px; font-weight:700; text-transform:uppercase; opacity:0.6;">Abonnés</div>
      </div>
      <div class="stat-card">
        <div style="font-family:'Playfair Display',serif; font-weight:900; font-size:1.4rem;"><?= $u['nb_following'] ?></div>
        <div style="font-size:9px; font-weight:700; text-transform:uppercase; opacity:0.6;">Abonnements</div>
      </div>
    </div>

    <!-- Action Button -->
    <button class="btn btn-primary btn-full btn-follow-user mb-24" 
            data-user-id="<?= $u['id'] ?>" 
            data-following="<?= $u['is_following']?'1':'0' ?>"
            style="font-size:14px; padding:16px; background:<?= $u['is_following']?'var(--noir)':'var(--bleu)' ?>; color:var(--blanc);">
      <?= $u['is_following'] ? '✓ SUIVI' : '+ SUIVRE CET ÉTUDIANT' ?>
    </button>

    <!-- Mutual Section -->
    <?php if (!empty($commonSquads)): ?>
    <div class="section-card" style="background:var(--lime-clair); border-color:var(--noir);">
      <div style="font-weight:900; font-size:13px; text-transform:uppercase; margin-bottom:12px; display:flex; align-items:center; gap:8px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path></svg>
        En commun
      </div>
      <div style="font-size:13px; font-weight:600;">
        Vous faites partie de **<?= count($commonSquads) ?> Squads** ensemble. 
      </div>
      <div style="margin-top:10px; display:flex; flex-wrap:wrap; gap:6px;">
        <?php foreach ($commonSquads as $cs): ?>
          <span style="font-size:10px; background:var(--blanc); padding:2px 8px; border:1px solid var(--noir); font-weight:700;"><?= strtoupper($cs['type']) ?></span>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Interests -->
    <div class="section-card">
      <div style="font-weight:900; font-size:13px; text-transform:uppercase; margin-bottom:16px;">Ses Passions</div>
      <?php if (empty($interests)): ?>
        <div style="font-size:13px; color:var(--gris);">Cet étudiant n'a pas encore ajouté d'intérêts.</div>
      <?php else: ?>
        <div style="display:flex; flex-wrap:wrap;">
          <?php foreach ($interests as $interest): ?>
            <span class="interest-pill"><?= htmlspecialchars($interest) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Upcoming Activity -->
    <div class="section-card">
      <div style="font-weight:900; font-size:13px; text-transform:uppercase; margin-bottom:16px;">Ses prochaines sorties</div>
      <?php if (empty($upcomingEvents)): ?>
        <div style="font-size:13px; color:var(--gris);">Aucune sortie prévue pour le moment.</div>
      <?php else: ?>
        <?php foreach ($upcomingEvents as $ev): ?>
          <div class="event-strip">
            <div style="width:10px; height:10px; border-radius:50%; background:var(--rouge); border:1px solid var(--noir);"></div>
            <div style="flex:1;">
              <div style="font-weight:800; font-size:13px;"><?= htmlspecialchars($ev['titre']) ?></div>
              <div style="font-size:11px; color:var(--gris);"><?= htmlspecialchars($ev['etablissement_nom']) ?> · <?= date('j M', strtotime($ev['date_heure'])) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>

</div>

<div class="toast" id="toast"></div>

<script src="<?= baseUrl() ?>/assets/js/app.js"></script>
</body>
</html>
