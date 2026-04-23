<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';
requireStudent();
$user = currentUser();
$uid = $user['id'];

$stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$uid]);
$u = $stmt->fetch();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM inscriptions WHERE user_id=? AND statut != 'annule'");
$stmt->execute([$uid]);
$nbInscriptions = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(montant),0) FROM economies WHERE user_id=?");
$stmt->execute([$uid]);
$ecoTotal = (float)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM squad_membres WHERE user_id=?");
$stmt->execute([$uid]);
$nbSquads = $stmt->fetchColumn();

$initials = strtoupper(mb_substr($u['prenom'], 0, 1) . mb_substr($u['nom'], 0, 1));

$stmt = $pdo->prepare("SELECT COUNT(*) FROM follows_users WHERE follower_id=?");
$stmt->execute([$uid]);
$nbFollowing = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM follows_users WHERE followed_id=?");
$stmt->execute([$uid]);
$nbFollowers = $stmt->fetchColumn();

// Social feed (activity of followed users)
$stmtFeed = $pdo->prepare("
    SELECT 'event' AS type, u.prenom, u.nom, e.titre, et.nom AS lieu, i.created_at AS ts
    FROM follows_users fu
    JOIN inscriptions i ON i.user_id=fu.followed_id AND i.statut='inscrit'
    JOIN evenements e ON e.id=i.evenement_id AND e.date_heure>=NOW()
    JOIN users u ON u.id=fu.followed_id
    JOIN etablissements et ON et.id=e.etablissement_id
    WHERE fu.follower_id=?
    
    UNION ALL
    
    SELECT 'squad' AS type, u.prenom, u.nom, s.titre, CONCAT('Session ',s.type) AS lieu, sm.joined_at AS ts
    FROM follows_users fu
    JOIN squad_membres sm ON sm.user_id=fu.followed_id
    JOIN squads s ON s.id=sm.squad_id AND s.date_heure>=NOW()
    JOIN users u ON u.id=fu.followed_id
    WHERE fu.follower_id=?
    
    ORDER BY ts DESC LIMIT 5
");
$stmtFeed->execute([$uid, $uid]);
$socialFeed = $stmtFeed->fetchAll();

$allInterests = [
    'Sorties', 'Boîtes', 'Running', 'Muscu', 'Vélo', 'Foot', 'Tennis', 'Gaming', 
    'Cuisine', 'Voyage', 'Cinéma', 'Lecture', 'Musique', 'Art', 'Photo', 
    'Animaux', 'Code', 'Yoga', 'Échecs', 'Mixologie', 'Bénévolat', 'Soirées'
];
$userInterests = $u['interests'] ? explode(',', $u['interests']) : [];

$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profil'])) {
    $ecole = trim($_POST['ecole'] ?? '');
    $promo = trim($_POST['promo'] ?? '');
    $interests = isset($_POST['interests']) ? implode(',', $_POST['interests']) : '';
    
    $pdo->prepare("UPDATE users SET ecole=?, promo=?, interests=? WHERE id=?")->execute([$ecole, $promo, $interests, $uid]);
    
    $_SESSION['user_ecole'] = $ecole;
    $u['ecole'] = $ecole;
    $u['promo'] = $promo;
    $u['interests'] = $interests;
    $userInterests = $interests ? explode(',', $interests) : [];
    $success = 'Profil mis à jour.';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StudentLink — Moi</title>
<link rel="stylesheet" href="/TitreRNCP/assets/css/style.css">
<link rel="icon" type="image/png" href="/TitreRNCP/Logo.png">
<link rel="apple-touch-icon" href="/TitreRNCP/Logo.png">
<link rel="manifest" href="/TitreRNCP/manifest.json">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="StudentLink">
<style>
  .profile-card {
    background: var(--blanc);
    border: 2px solid var(--noir);
    box-shadow: 4px 4px 0 var(--noir);
    padding: 20px;
    margin-bottom: 24px;
  }
  .section-title {
    font-family: 'Playfair Display', serif;
    font-weight: 900;
    font-size: 1.4rem;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
  }
</style>
</head>
<body>
<div class="app-shell">

  <!-- Header -->
  <div class="page-header" style="margin-bottom:24px;">
    <div class="logo" style="margin-bottom:20px;">
      <svg class="logo-icon" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="15" y="20" width="50" height="30" rx="15" stroke="var(--noir)" stroke-width="10"/>
        <rect x="35" y="50" width="50" height="30" rx="15" class="accent" stroke-width="10"/>
        <circle cx="50" cy="50" r="6" fill="var(--noir)"/>
      </svg>
      StudentLink <em>/ Moi</em>
    </div>

    <!-- Identity block -->
    <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px;">
      <div style="width:72px;height:72px;border-radius:50%;background:var(--rouge);border:3px solid var(--noir);display:flex;align-items:center;justify-content:center;font-family:'Playfair Display',serif;font-weight:900;font-size:1.6rem;color:var(--blanc);flex-shrink:0;box-shadow:4px 4px 0 var(--noir);">
        <?= $initials ?>
      </div>
      <div>
        <div class="display" style="font-size:1.8rem;line-height:1.1;"><?= htmlspecialchars($u['prenom']) ?></div>
        <div class="display-italic" style="font-size:1.8rem;line-height:1.1;"><?= htmlspecialchars($u['nom']) ?></div>
        <div style="font-size:12px;font-weight:600;color:var(--gris);margin-top:4px;letter-spacing:0.05em;"><?= htmlspecialchars($u['ecole'] ?? '—') ?> · <?= htmlspecialchars($u['promo'] ?? '—') ?></div>
      </div>
    </div>

    <!-- Followers row -->
    <div style="display:flex;gap:24px;padding:12px 0;border-top:2px solid rgba(0,0,0,0.15);margin-bottom:10px;">
      <div style="text-align:center;">
        <div style="font-family:'Playfair Display',serif;font-weight:900;font-size:1.4rem;color:var(--blanc);"><?= $nbFollowers ?></div>
        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;opacity:0.7;">Abonnés</div>
      </div>
      <div style="text-align:center;">
        <div style="font-family:'Playfair Display',serif;font-weight:900;font-size:1.4rem;color:var(--blanc);"><?= $nbFollowing ?></div>
        <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;opacity:0.7;">Abonnements</div>
      </div>
    </div>
  </div>

  <div class="page-content" style="padding-top:0;">
    
    <!-- Stats row (Sticky style top of content) -->
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:-20px;margin-bottom:24px;position:relative;z-index:2;">
      <div style="background:var(--rouge);border:2px solid var(--noir);box-shadow:3px 3px 0 var(--noir);padding:12px 4px;text-align:center;">
        <div style="font-size:8px;font-weight:700;text-transform:uppercase;color:var(--blanc);margin-bottom:2px;">Events</div>
        <div style="font-family:'Playfair Display',serif;font-weight:900;font-size:1.6rem;color:var(--blanc);line-height:1;"><?= $nbInscriptions ?></div>
      </div>
      <div style="background:var(--bleu);border:2px solid var(--noir);box-shadow:3px 3px 0 var(--noir);padding:12px 4px;text-align:center;">
        <div style="font-size:8px;font-weight:700;text-transform:uppercase;color:var(--blanc);margin-bottom:2px;">Squads</div>
        <div style="font-family:'Playfair Display',serif;font-weight:900;font-size:1.6rem;color:var(--blanc);line-height:1;"><?= $nbSquads ?></div>
      </div>
      <div style="background:var(--lime);border:2px solid var(--noir);box-shadow:3px 3px 0 var(--noir);padding:12px 4px;text-align:center;">
        <div style="font-size:8px;font-weight:700;text-transform:uppercase;color:var(--noir);margin-bottom:2px;">Économies</div>
        <div style="font-family:'Playfair Display',serif;font-weight:900;font-size:1.4rem;color:var(--noir);line-height:1;"><?= number_format($ecoTotal, 0, ',', '') ?>€</div>
      </div>
    </div>

    <?php if ($success): ?>
    <div style="background:var(--lime);border:2px solid var(--noir);box-shadow:4px 4px 0 var(--noir);padding:12px 16px;font-weight:700;font-size:13px;margin-bottom:24px;">
      ✓ <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <!-- SECTION 1: MON PROFIL -->
    <div class="profile-card">
      <div class="section-title">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
        Mon Profil
      </div>
      <form method="POST">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
          <div class="form-group" style="margin-bottom:0;">
            <label>École</label>
            <select name="ecole">
              <?php foreach (['UCA','SIGMA Clermont','INP Ingénieurs','IFSI','Autre'] as $e): ?>
                <option value="<?= $e ?>" <?= $u['ecole'] === $e ? 'selected' : '' ?>><?= $e ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin-bottom:0;">
            <label>Promo</label>
            <select name="promo">
              <?php foreach (['L1','L2','L3','M1','M2','BUT1','BUT2','BUT3'] as $p): ?>
                <option value="<?= $p ?>" <?= $u['promo'] === $p ? 'selected' : '' ?>><?= $p ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div style="margin-bottom:20px;">
          <label style="display:block;margin-bottom:12px;font-size:12px;font-weight:700;text-transform:uppercase;color:var(--gris);">Centres d'intérêt</label>
          <div style="display:flex;flex-wrap:wrap;gap:8px;">
            <?php foreach ($allInterests as $interest): ?>
              <label style="cursor:pointer;">
                <input type="checkbox" name="interests[]" value="<?= $interest ?>" 
                       <?= in_array($interest, $userInterests) ? 'checked' : '' ?> 
                       style="display:none;">
                <div class="interest-tag <?= in_array($interest, $userInterests) ? 'active' : '' ?>"
                     onclick="this.previousElementSibling.click(); this.classList.toggle('active');">
                  <?= $interest ?>
                </div>
              </label>
            <?php endforeach; ?>
          </div>
        </div>
        
        <button type="submit" name="save_profil" class="btn btn-primary btn-full">Enregistrer les modifications</button>
      </form>
    </div>

    <!-- SECTION 3: ACTIVITÉ -->
    <?php if (!empty($socialFeed)): ?>
    <div class="profile-card">
      <div class="section-title">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
        Activité des amis
      </div>
      <div style="display:flex;flex-direction:column;gap:12px;">
        <?php foreach ($socialFeed as $item): ?>
        <div style="display:flex;gap:12px;align-items:flex-start;">
          <div style="width:32px;height:32px;border-radius:50%;background:<?= $item['type']==='event' ? 'var(--rouge)' : 'var(--lime)' ?>;border:2px solid var(--noir);display:flex;align-items:center;justify-content:center;font-weight:900;font-size:0.8rem;color:<?= $item['type']==='event' ? 'var(--blanc)' : 'var(--noir)' ?>;flex-shrink:0;">
            <?= strtoupper(mb_substr($item['prenom'], 0, 1)) ?>
          </div>
          <div style="flex:1;font-size:12px;">
            <span style="font-weight:700;"><?= htmlspecialchars($item['prenom']) ?></span> 
            <?= $item['type']==='event' ? 'va à' : 'rejoint' ?> 
            <span style="font-style:italic;font-weight:600;"><?= htmlspecialchars($item['titre']) ?></span>
            <div style="color:var(--gris);font-size:10px;margin-top:2px;"><?= date('j M', strtotime($item['ts'])) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- SECTION 4: COMPTE -->
    <div class="profile-card" style="padding:0;overflow:hidden;">
      <div style="padding:16px 20px;border-bottom:2px solid var(--noir);background:var(--blanc);display:flex;justify-content:space-between;align-items:center;">
        <span style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--gris);">Email</span>
        <span style="font-size:12px;font-weight:600;"><?= htmlspecialchars($u['email']) ?></span>
      </div>
      <div style="padding:16px 20px;border-bottom:2px solid var(--noir);background:var(--blanc);display:flex;justify-content:space-between;align-items:center;">
        <span style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--gris);">Depuis</span>
        <span style="font-size:12px;font-weight:600;"><?= date('M Y', strtotime($u['created_at'])) ?></span>
      </div>
      <div style="padding:16px 20px;background:var(--blanc);">
        <a href="/TitreRNCP/auth/logout.php" style="display:flex;align-items:center;justify-content:center;gap:8px;text-align:center;color:var(--rouge);font-weight:800;text-decoration:none;font-size:13px;text-transform:uppercase;letter-spacing:0.05em;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
          Se déconnecter
        </a>
      </div>
    </div>

  </div>

</div><!-- .app-shell -->

<!-- Bottom Nav -->
<nav class="bottom-nav">
  <a href="/TitreRNCP/explore.php" class="nav-item">
    <span class="nav-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg></span>
    <span>Explore</span>
  </a>
  <a href="/TitreRNCP/squads.php" class="nav-item">
    <span class="nav-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg></span>
    <span>Squads</span>
  </a>
  <a href="/TitreRNCP/wallet.php" class="nav-item">
    <span class="nav-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg></span>
    <span>Wallet</span>
  </a>
  <a href="/TitreRNCP/profil.php" class="nav-item active">
    <span class="nav-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></span>
    <span>Moi</span>
  </a>
</nav>

<div class="toast" id="toast"></div>
<script src="/TitreRNCP/assets/js/app.js"></script>
</body>
</html>
