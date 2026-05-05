<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/gamification.php';
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

// Invitations reçues
$stmtInvites = $pdo->prepare("
    SELECT inv.*, u.prenom AS from_prenom, u.nom AS from_nom,
           CASE WHEN inv.type='event' THEN e.titre ELSE s.titre END AS target_nom
    FROM invitations inv
    JOIN users u ON u.id = inv.from_user_id
    LEFT JOIN evenements e ON inv.type='event' AND e.id = inv.target_id
    LEFT JOIN squads s ON inv.type='squad' AND s.id = inv.target_id
    WHERE inv.to_user_id = ? AND inv.statut = 'pending'
    ORDER BY inv.created_at DESC
");
$stmtInvites->execute([$uid]);
$invitations = $stmtInvites->fetchAll();

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
    $allowedInterests = ['Sorties','Boîtes','Running','Muscu','Vélo','Foot','Tennis','Gaming','Cuisine','Voyage','Cinéma','Lecture','Musique','Art','Photo','Animaux','Code','Yoga','Échecs','Mixologie','Bénévolat','Soirées'];
    $interests = isset($_POST['interests']) ? implode(',', array_intersect((array)$_POST['interests'], $allowedInterests)) : '';
    
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
<?= themeBootScript() ?>
<link rel="stylesheet" href="<?= baseUrl() ?>/assets/css/style.css">
<link rel="icon" type="image/png" href="/Logo.png">
<link rel="apple-touch-icon" href="/Logo.png">
<link rel="manifest" href="/manifest.json">
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

    <!-- INVITATIONS REÇUES -->
    <?php if (!empty($invitations)): ?>
    <div style="margin-bottom:24px;">
      <div class="section-title" style="margin-bottom:14px;">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px;"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 11.23 19.79 19.79 0 0 1 1.61 2.6 2 2 0 0 1 3.6.42h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 7.91a16 16 0 0 0 6.18 6.18l1-1a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
        Invitations
        <span style="background:var(--rouge);color:var(--blanc);font-size:10px;font-weight:800;padding:2px 7px;margin-left:8px;"><?= count($invitations) ?></span>
      </div>
      <div style="display:flex;flex-direction:column;gap:10px;">
        <?php foreach ($invitations as $inv): ?>
        <div style="background:var(--blanc);border:2px solid var(--noir);box-shadow:3px 3px 0 var(--noir);padding:14px 16px;">
          <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
            <div>
              <div style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:0.08em;color:<?= $inv['type']==='event'?'var(--rouge)':'var(--bleu)' ?>;margin-bottom:4px;">
                <?= $inv['type']==='event'?'Événement':'Squad sport' ?>
              </div>
              <div style="font-weight:800;font-size:14px;margin-bottom:4px;"><?= htmlspecialchars($inv['target_nom'] ?? 'Inconnu') ?></div>
              <div style="font-size:11px;color:var(--gris);">
                Invité par <strong><?= htmlspecialchars($inv['from_prenom'] . ' ' . $inv['from_nom'][0] . '.') ?></strong>
              </div>
            </div>
            <div style="display:flex;flex-direction:column;gap:6px;flex-shrink:0;">
              <button class="btn-accept-invite" data-id="<?= $inv['id'] ?>"
                      style="background:var(--noir);color:var(--blanc);border:2px solid var(--noir);padding:7px 12px;font-size:11px;font-weight:800;cursor:pointer;text-transform:uppercase;">
                Accepter
              </button>
              <button class="btn-decline-invite" data-id="<?= $inv['id'] ?>"
                      style="background:none;border:2px solid var(--gris);padding:7px 12px;font-size:11px;font-weight:700;cursor:pointer;color:var(--gris);text-transform:uppercase;">
                Refuser
              </button>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
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

    <!-- SECTION GAMIFICATION -->
    <?php
    checkBadges($pdo, $uid);
    $gamStats = getUserStats($pdo, $uid);
    $xp = getXp($gamStats);
    $level = getLevel($xp);
    $xpCurrentLevel = xpForLevel($level);
    $xpNextLevel = xpForLevel($level + 1);
    $xpProgress = max(0, min(100, ($xp - $xpCurrentLevel) / max(1, $xpNextLevel - $xpCurrentLevel) * 100));
    $myBadges = getUserBadges($pdo, $uid);
    $allBadges = getAllBadges($pdo);
    $unlockedCodes = array_column($myBadges, 'code');
    ?>
    <div class="profile-card">
      <div class="section-title">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px;"><circle cx="12" cy="8" r="7"></circle><polyline points="8.21 13.89 7 23 12 20 17 23 15.79 13.88"></polyline></svg>
        Niveau & Badges
      </div>

      <!-- Level + XP -->
      <div style="display:flex;align-items:center;gap:14px;margin-bottom:8px;">
        <div style="width:54px;height:54px;border-radius:50%;background:var(--rouge);border:3px solid var(--noir);display:flex;align-items:center;justify-content:center;font-family:'Playfair Display',serif;font-weight:900;font-size:1.4rem;color:var(--blanc);box-shadow:3px 3px 0 var(--noir);flex-shrink:0;">
          <?= $level ?>
        </div>
        <div style="flex:1;">
          <div style="display:flex;justify-content:space-between;font-size:11px;font-weight:700;text-transform:uppercase;color:var(--gris);margin-bottom:6px;">
            <span>Niveau <?= $level ?></span>
            <span><?= $xp ?> / <?= $xpNextLevel ?> XP</span>
          </div>
          <div style="height:10px;background:var(--gris-clair);border:2px solid var(--noir);overflow:hidden;">
            <div style="height:100%;background:var(--lime);width:<?= round($xpProgress) ?>%;transition:width .4s;"></div>
          </div>
        </div>
      </div>

      <div style="font-size:11px;color:var(--gris);margin-bottom:18px;">
        <?= $gamStats['events'] ?> events · <?= $gamStats['squads'] ?> squads · <?= $gamStats['follows'] ?> abonnements · <?= $gamStats['avis'] ?> avis
      </div>

      <!-- Badges grid -->
      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;">
        <?php foreach ($allBadges as $b):
          $unlocked = in_array($b['code'], $unlockedCodes);
        ?>
        <div style="background:<?= $unlocked ? 'var(--lime)' : 'var(--gris-clair)' ?>;border:2px solid var(--noir);box-shadow:<?= $unlocked ? '3px 3px 0 var(--noir)' : 'none' ?>;padding:12px 6px;text-align:center;opacity:<?= $unlocked ? '1' : '0.45' ?>;">
          <div style="font-size:1.6rem;line-height:1;margin-bottom:4px;filter:<?= $unlocked ? 'none' : 'grayscale(1)' ?>;"><?= htmlspecialchars($b['icon'] ?? '🏆') ?></div>
          <div style="font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.04em;color:var(--noir);line-height:1.2;"><?= htmlspecialchars($b['nom']) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- SECTION APPARENCE -->
    <div class="profile-card">
      <div class="section-title">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px;"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
        Apparence
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <button type="button" onclick="window.setTheme('light')" id="theme-light-btn"
                style="background:var(--blanc);border:2px solid var(--noir);box-shadow:3px 3px 0 var(--noir);padding:14px;font-weight:800;font-size:13px;cursor:pointer;text-transform:uppercase;letter-spacing:.04em;">
          ☀ Clair
        </button>
        <button type="button" onclick="window.setTheme('dark')" id="theme-dark-btn"
                style="background:#1E1E1E;color:#F2EDE3;border:2px solid var(--noir);box-shadow:3px 3px 0 var(--noir);padding:14px;font-weight:800;font-size:13px;cursor:pointer;text-transform:uppercase;letter-spacing:.04em;">
          ☾ Sombre
        </button>
      </div>
    </div>

    <!-- SECTION LÉGAL -->
    <div class="profile-card" style="padding:0;overflow:hidden;">
      <a href="<?= baseUrl('/mentions-legales.php') ?>" style="display:flex;justify-content:space-between;align-items:center;padding:14px 20px;border-bottom:2px solid var(--noir);text-decoration:none;color:var(--noir);font-size:12px;font-weight:700;">
        Mentions légales <span style="color:var(--gris);">→</span>
      </a>
      <a href="<?= baseUrl('/cgu.php') ?>" style="display:flex;justify-content:space-between;align-items:center;padding:14px 20px;border-bottom:2px solid var(--noir);text-decoration:none;color:var(--noir);font-size:12px;font-weight:700;">
        CGU <span style="color:var(--gris);">→</span>
      </a>
      <a href="<?= baseUrl('/confidentialite.php') ?>" style="display:flex;justify-content:space-between;align-items:center;padding:14px 20px;text-decoration:none;color:var(--noir);font-size:12px;font-weight:700;">
        Politique de confidentialité <span style="color:var(--gris);">→</span>
      </a>
    </div>

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
        <a href="/auth/logout.php" style="display:flex;align-items:center;justify-content:center;gap:8px;text-align:center;color:var(--rouge);font-weight:800;text-decoration:none;font-size:13px;text-transform:uppercase;letter-spacing:0.05em;">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
          Se déconnecter
        </a>
      </div>
    </div>

  </div>

</div><!-- .app-shell -->

<!-- Bottom Nav -->
<nav class="bottom-nav">
  <a href="/explore.php" class="nav-item">
    <span class="nav-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg></span>
    <span>Explore</span>
  </a>
  <a href="/squads.php" class="nav-item">
    <span class="nav-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg></span>
    <span>Squads</span>
  </a>
  <a href="/wallet.php" class="nav-item">
    <span class="nav-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg></span>
    <span>Wallet</span>
  </a>
  <a href="/profil.php" class="nav-item active">
    <span class="nav-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></span>
    <span>Moi</span>
  </a>
</nav>

<div class="toast" id="toast"></div>

<script src="<?= baseUrl() ?>/assets/js/app.js"></script>
<script>
const _BASE = location.hostname === 'localhost' || location.hostname === '127.0.0.1' ? '/TitreRNCP' : '';
document.querySelectorAll('.btn-accept-invite, .btn-decline-invite').forEach(btn => {
  btn.addEventListener('click', async () => {
    const id = btn.dataset.id;
    const action = btn.classList.contains('btn-accept-invite') ? 'accept' : 'decline';
    btn.disabled = true; btn.textContent = '...';
    try {
      const res = await fetch(_BASE + '/api/inviter.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action, invite_id: id })
      });
      const data = await res.json();
      if (data.success) {
        const card = btn.closest('div[style*="background:var(--blanc)"]');
        card.style.opacity = '0.4';
        card.style.pointerEvents = 'none';
        if (action === 'accept') {
          showToast('Invitation acceptée ! Tu es inscrit.', 'success');
        } else {
          showToast('Invitation refusée.', '');
        }
        setTimeout(() => card.closest('div[style*="gap:10px"]')?.removeChild(card), 800);
      }
    } catch { btn.disabled = false; btn.textContent = action === 'accept' ? 'Accepter' : 'Refuser'; }
  });
});
</script>
</body>
</html>
