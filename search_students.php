<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';
requireStudent();
$user = currentUser();
$uid = $user['id'];

// Get current user data for matching
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$uid]);
$me = $stmt->fetch();
$myInterests = $me['interests'] ? explode(',', $me['interests']) : [];

$q = trim($_GET['q'] ?? '');

// Base query for students
$sql = "SELECT id, nom, prenom, ecole, promo, interests,
               (SELECT COUNT(*) FROM follows_users fu WHERE fu.follower_id=? AND fu.followed_id=users.id) AS is_following
        FROM users 
        WHERE type='etudiant' AND id != ?";

$params = [$uid, $uid];

if ($q) {
    $sql .= " AND (nom LIKE ? OR prenom LIKE ? OR ecole LIKE ?)";
    $term = "%$q%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Process matches
foreach ($students as &$s) {
    $sInterests = $s['interests'] ? explode(',', $s['interests']) : [];
    $common = array_intersect($myInterests, $sInterests);
    $s['common_interests'] = $common;
    $s['score'] = count($common);
    
    // Count shared squads
    $stmtSq = $pdo->prepare("
        SELECT COUNT(*) FROM squad_membres sm1
        JOIN squad_membres sm2 ON sm1.squad_id = sm2.squad_id
        WHERE sm1.user_id = ? AND sm2.user_id = ?
    ");
    $stmtSq->execute([$uid, $s['id']]);
    $s['shared_squads'] = $stmtSq->fetchColumn();
}

// Sorting logic
if (!$q) {
    // If no search, show best matches first (score + shared squads)
    usort($students, function($a, $b) {
        if ($a['score'] === $b['score']) {
            return $b['shared_squads'] <=> $a['shared_squads'];
        }
        return $b['score'] <=> $a['score'];
    });
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StudentLink — Découvrir des étudiants</title>
<link rel="stylesheet" href="<?= baseUrl() ?>/assets/css/style.css">
</head>
<body>
<div class="app-shell">

  <div class="page-header">
    <div class="brand" style="margin-bottom:16px;">StudentLink <em>/ Social</em></div>
    <div class="display" style="font-size:2rem;">Trouve tes</div>
    <div class="display-italic" style="font-size:2rem;">futurs potes.</div>
  </div>

  <div class="page-content" style="padding-top:20px;">
    
    <!-- Search Bar -->
    <form method="GET" style="margin-bottom:24px;">
      <div style="position:relative;">
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" 
               placeholder="Rechercher un nom, une école..." 
               style="width:100%;padding:14px 16px;border:2px solid var(--noir);box-shadow:4px 4px 0 var(--noir);font-family:inherit;font-size:14px;outline:none;">
        <button type="submit" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;font-size:18px;cursor:pointer;">🔍</button>
      </div>
    </form>

    <?php if (empty($students)): ?>
      <div style="text-align:center;padding:40px 0;color:var(--gris);">
        <div style="font-size:2.5rem;margin-bottom:12px;">🤷‍♂️</div>
        <div style="font-weight:700;">Aucun étudiant trouvé.</div>
      </div>
    <?php else: ?>
      
      <div class="section-divider">
        <span class="sd-label"><?= $q ? 'Résultats de recherche' : 'Suggestions de rencontres' ?></span>
      </div>

      <div style="display:flex;flex-direction:column;gap:16px;">
        <?php foreach ($students as $s): ?>
          <div style="border:2px solid var(--noir);box-shadow:4px 4px 0 var(--noir);background:var(--blanc);padding:16px;display:flex;flex-direction:column;gap:12px;">
            <div style="display:flex;align-items:center;gap:12px;">
              <div style="width:48px;height:48px;border-radius:50%;background:var(--bleu);border:2px solid var(--noir);display:flex;align-items:center;justify-content:center;font-family:'Playfair Display',serif;font-weight:900;color:var(--blanc);font-size:1.2rem;flex-shrink:0;">
                <?= strtoupper(mb_substr($s['prenom'], 0, 1)) ?>
              </div>
              <div style="flex:1;">
                <div style="font-weight:900;font-size:1rem;color:var(--noir);"><?= htmlspecialchars($s['prenom'] . ' ' . $s['nom']) ?></div>
                <div style="font-size:12px;color:var(--gris);"><?= htmlspecialchars($s['ecole'] ?? 'Étudiant') ?> · <?= htmlspecialchars($s['promo'] ?? '') ?></div>
              </div>
              <button class="btn-follow-user" data-user-id="<?= $s['id'] ?>" data-following="<?= $s['is_following'] ? '1' : '0' ?>"
                      style="background:<?= $s['is_following'] ? 'var(--noir)' : 'transparent' ?>;color:<?= $s['is_following'] ? 'var(--blanc)' : 'var(--noir)' ?>;border:2px solid var(--noir);font-size:11px;font-weight:700;padding:6px 12px;cursor:pointer;white-space:nowrap;">
                <?= $s['is_following'] ? '✓ Suivi' : '+ Suivre' ?>
              </button>
            </div>

            <!-- Matching indicators -->
            <?php if ($s['score'] > 0 || $s['shared_squads'] > 0): ?>
              <div style="display:flex;flex-wrap:wrap;gap:6px;padding-top:8px;border-top:1px solid var(--gris-clair);">
                <?php if ($s['shared_squads'] > 0): ?>
                  <div style="font-size:11px;background:var(--lime);padding:2px 8px;border:1px solid var(--noir);font-weight:700;">
                    🔥 <?= $s['shared_squads'] ?> Squad<?= $s['shared_squads'] > 1 ? 's' : '' ?> en commun
                  </div>
                <?php endif; ?>
                <?php foreach ($s['common_interests'] as $interest): ?>
                  <div style="font-size:10px;background:var(--blanc);border:1px solid var(--noir);padding:2px 8px;font-weight:600;color:var(--bleu);">
                    #<?= $interest ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <a href="/profil.php" class="btn btn-outline btn-full mt-24">
      ← Retour à mon profil
    </a>
  </div>

</div>

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
</body>
</html>
