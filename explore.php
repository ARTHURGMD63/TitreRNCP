<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';
requireStudent();
$user = currentUser();
$uid = $user['id'];

// ── Hub Configuration ────────────────────────────────────────────────────────
$view = $_GET['view'] ?? 'events'; 
$filter = $_GET['type'] ?? 'all';  
$q = trim($_GET['q'] ?? '');       

// ── LOGIC FOR PEOPLE ────────────────────────────────────────────────────────
$students = [];
$myInterests = [];
if ($view === 'people') {
    $stmt = $pdo->prepare("SELECT interests FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $me = $stmt->fetch();
    $myInterests = $me['interests'] ? explode(',', $me['interests']) : [];

    $sqlP = "SELECT id, nom, prenom, ecole, promo, interests,
                    (SELECT COUNT(*) FROM follows_users fu WHERE fu.follower_id=? AND fu.followed_id=users.id) AS is_following
             FROM users 
             WHERE type='etudiant' AND id != ?";
    $paramsP = [$uid, $uid];

    if ($q) {
        $sqlP .= " AND (nom LIKE ? OR prenom LIKE ? OR ecole LIKE ? OR interests LIKE ?)";
        $term = "%$q%";
        $paramsP[] = $term; $paramsP[] = $term; $paramsP[] = $term; $paramsP[] = $term;
    }

    $stmtP = $pdo->prepare($sqlP);
    $stmtP->execute($paramsP);
    $students = $stmtP->fetchAll();

    foreach ($students as &$s) {
        $sInterests = $s['interests'] ? explode(',', $s['interests']) : [];
        $common = array_intersect($myInterests, $sInterests);
        $s['common_interests'] = $common;
        $s['score'] = count($common);
        
        $stmtSq = $pdo->prepare("
            SELECT COUNT(*) FROM squad_membres sm1
            JOIN squad_membres sm2 ON sm1.squad_id = sm2.squad_id
            WHERE sm1.user_id = ? AND sm2.user_id = ?
        ");
        $stmtSq->execute([$uid, $s['id']]);
        $s['shared_squads'] = $stmtSq->fetchColumn();
    }

    if (!$q) {
        usort($students, function($a, $b) {
            if ($a['score'] === $b['score']) return $b['shared_squads'] <=> $a['shared_squads'];
            return $b['score'] <=> $a['score'];
        });
        $students = array_slice($students, 0, 24);
    }
}

// ── LOGIC FOR EVENTS ────────────────────────────────────────────────────────
$evenements = [];
$friendsByEvent = [];
$followedEtabIds = [];
if ($view === 'events') {
    $stmtFe = $pdo->prepare("SELECT etablissement_id FROM follows_etablissements WHERE user_id=?");
    $stmtFe->execute([$uid]);
    $followedEtabIds = $stmtFe->fetchAll(PDO::FETCH_COLUMN);

    $stmtFriends = $pdo->prepare("
        SELECT i.evenement_id, GROUP_CONCAT(u.prenom ORDER BY i.created_at SEPARATOR ',') AS prenoms, COUNT(*) AS nb
        FROM follows_users fu
        JOIN inscriptions i ON i.user_id = fu.followed_id AND i.statut='inscrit'
        JOIN users u ON u.id = fu.followed_id
        WHERE fu.follower_id = ?
        GROUP BY i.evenement_id
    ");
    $stmtFriends->execute([$uid]);
    foreach ($stmtFriends->fetchAll() as $row) {
        $friendsByEvent[$row['evenement_id']] = ['prenoms' => explode(',', $row['prenoms']), 'nb' => $row['nb']];
    }

    $sqlE = "SELECT e.*, et.id AS etab_id, et.nom AS etablissement_nom, et.type AS etab_type, et.ville,
                   (SELECT COUNT(*) FROM inscriptions i WHERE i.evenement_id = e.id AND i.statut != 'annule') AS nb_inscrits,
                   (SELECT COUNT(*) FROM inscriptions i WHERE i.evenement_id = e.id AND i.user_id = ? AND i.statut != 'annule') AS deja_inscrit
            FROM evenements e
            JOIN etablissements et ON et.id = e.etablissement_id
            WHERE e.date_heure >= NOW()";
    $paramsE = [$uid];

    if ($filter === 'pour-moi') {
        $friendEventIds = array_keys($friendsByEvent);
        $etabPlaceholders = !empty($followedEtabIds) ? implode(',', array_fill(0, count($followedEtabIds), '?')) : '0';
        $friendPlaceholders = !empty($friendEventIds) ? implode(',', array_fill(0, count($friendEventIds), '?')) : '0';
        $sqlE .= " AND (et.id IN ($etabPlaceholders) OR e.id IN ($friendPlaceholders))";
        $paramsE = array_merge($paramsE, $followedEtabIds, $friendEventIds);
    } elseif ($filter !== 'all') {
        $sqlE .= " AND e.type = ?";
        $paramsE[] = $filter;
    }

    $sqlE .= " ORDER BY e.is_flash DESC, e.date_heure ASC";
    $stmtE = $pdo->prepare($sqlE);
    $stmtE->execute($paramsE);
    $evenements = $stmtE->fetchAll();
}

$typeLabels = ['bar'=>'Bar','boite'=>'Boîte','resto'=>'Resto','afterwork'=>'Afterwork'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StudentLink — Hub</title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="icon" type="image/png" href="/Logo.png">
<link rel="apple-touch-icon" href="/Logo.png">
<link rel="manifest" href="/manifest.json">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="StudentLink">
<style>
  .hub-toggle {
    display: flex;
    background: var(--noir);
    padding: 4px;
    border: 2px solid var(--noir);
    margin-bottom: 24px;
    box-shadow: 4px 4px 0 var(--noir);
  }
  .hub-toggle a {
    flex: 1;
    text-align: center;
    padding: 12px;
    font-weight: 800;
    font-size: 13px;
    text-transform: uppercase;
    text-decoration: none;
    color: var(--blanc);
  }
  .hub-toggle a.active {
    background: var(--blanc);
    color: var(--noir);
  }
  .match-pill {
    font-size: 10px;
    background: var(--bleu-clair);
    color: var(--bleu);
    padding: 3px 10px;
    border: 1px solid var(--bleu);
    font-weight: 700;
    text-transform: uppercase;
  }
</style>
</head>
<body>
<div class="app-shell">

  <!-- Hub Header -->
  <div class="page-header" style="padding-bottom:10px;">
    <div class="logo" style="margin-bottom:16px;">
      <svg class="logo-icon" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="15" y="20" width="50" height="30" rx="15" stroke="var(--noir)" stroke-width="10"/>
        <rect x="35" y="50" width="50" height="30" rx="15" class="accent" stroke-width="10"/>
        <circle cx="50" cy="50" r="6" fill="var(--noir)"/>
      </svg>
      StudentLink <em>/ Hub</em>
    </div>
    
    <div class="hub-toggle">
      <a href="?view=events" class="<?= $view==='events'?'active':'' ?>">Événements</a>
      <a href="?view=people" class="<?= $view==='people'?'active':'' ?>">Personnes</a>
    </div>

    <?php if ($view === 'events'): ?>
      <div class="display" style="font-size:2.4rem; line-height:1.1;">Les bons plans</div>
      <div class="display-italic" style="font-size:2.4rem; line-height:1.1;">du moment.</div>
    <?php else: ?>
      <div class="display" style="font-size:2.4rem; line-height:1.1;">Trouve tes</div>
      <div class="display-italic" style="font-size:2.4rem; line-height:1.1;">futurs potes.</div>
    <?php endif; ?>
  </div>

  <div class="page-content" style="padding-top:20px;">

    <?php if ($view === 'events'): ?>
      <!-- Event Filters (Pills) -->
      <div class="filter-scroll" style="margin: 0 -20px 24px; padding: 0 20px;">
        <button class="pill <?= $filter==='all'?'active':'' ?>" onclick="window.location='?view=events&type=all'">Tout</button>
        <button class="pill <?= $filter==='pour-moi'?'active':'' ?>" onclick="window.location='?view=events&type=pour-moi'" style="<?= $filter==='pour-moi'?'':'border-color:var(--rouge);color:var(--rouge);' ?>">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:text-bottom;margin-right:4px;"><path d="M12 3l1.912 5.813h6.111l-4.943 3.591 1.887 5.804-4.967-3.607-4.967 3.607 1.887-5.804-4.943-3.591h6.111z"></path></svg>
          Pour moi
        </button>
        <button class="pill <?= $filter==='bar'?'active':'' ?>" onclick="window.location='?view=events&type=bar'">Bars</button>
        <button class="pill <?= $filter==='boite'?'active':'' ?>" onclick="window.location='?view=events&type=boite'">Boîtes</button>
        <button class="pill <?= $filter==='resto'?'active':'' ?>" onclick="window.location='?view=events&type=resto'">Restos</button>
      </div>

      <!-- Rich Event Cards (Restored Original Design) -->
      <?php foreach ($evenements as $e): 
        $isFlash = $e['is_flash'] && strtotime($e['flash_expiry'] ?? '') > time();
        $isFull = $e['nb_inscrits'] >= $e['quota'];
        $pct = $e['quota'] > 0 ? round($e['nb_inscrits'] / $e['quota'] * 100) : 0;
        $friends = $friendsByEvent[$e['id']] ?? null;
        $isFollowedEtab = in_array($e['etab_id'], $followedEtabIds);
        $expiryTs = strtotime($e['flash_expiry'] ?? '');
      ?>

        <?php if ($isFlash): ?>
          <div class="event-card event-card-flash" style="margin-bottom:20px; position:relative;">
            <a href="view_event.php?id=<?= $e['id'] ?>" style="text-decoration:none; color:inherit; display:block;">
              <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                <div class="label" style="opacity:0.8;">CE SOIR · <?= date('H\hi', strtotime($e['date_heure'])) ?></div>
                <div class="flash-badge" data-expiry="<?= $expiryTs ?>">FLASH</div>
              </div>
              <div class="event-title"><?= htmlspecialchars($e['titre']) ?></div>
              <div class="event-lieu"><?= htmlspecialchars($e['etablissement_nom']) ?> — <?= htmlspecialchars($e['ville']) ?></div>
            </a>
            
            <button class="btn-follow-etab" data-etab-id="<?= $e['etab_id'] ?>" data-following="<?= $isFollowedEtab?'1':'0' ?>"
                    style="position:absolute; top:16px; right:16px; background:rgba(255,255,255,<?= $isFollowedEtab?'0.3':'0.15' ?>); border:1px solid rgba(255,255,255,0.5); color:#fff; font-size:10px; font-weight:800; padding:3px 8px; cursor:pointer;">
              <?= $isFollowedEtab?'✓ SUIVI':'+ SUIVRE' ?>
            </button>
            
            <?php if ($friends): ?>
              <div style="margin-top:8px;font-size:11px;font-weight:700;color:rgba(255,255,255,0.9);display:flex;align-items:center;gap:4px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                <?= implode(', ', array_slice($friends['prenoms'], 0, 2)) ?><?= $friends['nb']>2?' +'.($friends['nb']-2):'' ?> y vont
              </div>
            <?php endif; ?>

            <div style="display:flex;align-items:flex-end;justify-content:space-between;margin-top:16px;">
              <div>
                <div class="event-reduction">-<?= $e['reduction'] ?>%</div>
                <div style="font-size:11px;opacity:0.8;"><?= $e['is_gratuit']?'entrée gratuite':'sur conso' ?></div>
              </div>
              <button class="btn btn-outline-blanc btn-join-event" data-event-id="<?= $e['id'] ?>" <?= $e['deja_inscrit']?'disabled':'' ?>>
                <?= $e['deja_inscrit']?'✓ Inscrit':'→ Je fonce' ?>
              </button>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:14px; margin-bottom:4px; font-size:10px; font-weight:800; color:rgba(255,255,255,0.9); text-transform:uppercase; letter-spacing:0.05em;">
              <span>Remplissage</span>
              <span><?= $pct ?>%</span>
            </div>
            <div class="progress-bar" style="margin-top:0;background:rgba(0,0,0,0.2);">
              <div class="progress-bar-fill" style="width:<?= $pct ?>%;background:var(--blanc);"></div>
            </div>
          </div>

        <?php else: ?>
          <div class="event-card event-card-regular type-<?= $e['type'] ?>" style="margin-bottom:20px; position:relative;">
            <a href="view_event.php?id=<?= $e['id'] ?>" style="text-decoration:none; color:inherit; display:block;">
              <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
                <span class="event-meta"><?= strtoupper($typeLabels[$e['type']]) ?> · <?= date('D j M', strtotime($e['date_heure'])) ?></span>
                <?php if ($e['is_gratuit']): ?><span class="badge badge-bar">GRATUIT</span><?php endif; ?>
              </div>
              <div class="event-title" style="font-size:1.3rem; padding-right:80px;"><?= htmlspecialchars($e['titre']) ?></div>
              <div class="event-lieu"><?= htmlspecialchars($e['etablissement_nom']) ?></div>
            </a>
            
            <button class="btn-follow-etab" data-etab-id="<?= $e['etab_id'] ?>" data-following="<?= $isFollowedEtab?'1':'0' ?>"
                    style="position:absolute; top:40px; right:16px; background:<?= $isFollowedEtab?'var(--noir)':'transparent' ?>;color:<?= $isFollowedEtab?'var(--blanc)':'var(--noir)' ?>;border:2px solid var(--noir);font-size:10px;font-weight:800;padding:4px 8px;">
              <?= $isFollowedEtab?'✓ SUIVI':'+ SUIVRE' ?>
            </button>

            <?php if ($friends): ?>
              <div style="font-size:11px;font-weight:700;color:var(--rouge);margin-top:8px;display:flex;align-items:center;gap:4px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                <?= implode(', ', array_slice($friends['prenoms'], 0, 2)) ?> y vont
              </div>
            <?php endif; ?>

            <div style="font-size:13px;color:var(--gris-fonce);margin:12px 0;line-height:1.4;">
              <?= htmlspecialchars(mb_substr($e['description'] ?? '', 0, 100)) ?>...
            </div>

            <div style="display:flex;align-items:center;justify-content:space-between;">
              <div style="font-size:11px;font-weight:700;"><?= $e['nb_inscrits'] ?>/<?= $e['quota'] ?> places</div>
              <button class="btn btn-primary btn-join-event" data-event-id="<?= $e['id'] ?>" style="padding:8px 16px;font-size:12px;" <?= $e['deja_inscrit']?'disabled':'' ?>>
                <?= $e['deja_inscrit']?'✓ Inscrit':'Rejoindre' ?>
              </button>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; margin-bottom:4px; font-size:10px; font-weight:800; color:var(--noir); text-transform:uppercase; letter-spacing:0.05em;">
              <span>Taux d'inscription</span>
              <span><?= $pct ?>%</span>
            </div>
            <div class="progress-bar" style="margin-top:0;background:var(--gris-clair);">
              <div class="progress-bar-fill dark" style="width:<?= $pct ?>%"></div>
            </div>
          </div>
        <?php endif; ?>

      <?php endforeach; ?>

    <?php else: ?>
      <!-- People Section (Matching Original Design) -->
      <form method="GET" style="margin-bottom:24px;">
        <input type="hidden" name="view" value="people">
        <div style="position:relative;">
          <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Chercher un nom ou une passion..." 
                 style="width:100%;padding:14px 16px;border:3px solid var(--noir);box-shadow:4px 4px 0 var(--noir);font-size:14px;outline:none;">
          <button type="submit" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--noir);display:flex;align-items:center;justify-content:center;">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
          </button>
        </div>
      </form>

      <div style="display:flex;flex-direction:column;gap:16px;">
        <?php foreach ($students as $s): ?>
          <div style="background:var(--blanc);border:2px solid var(--noir);box-shadow:4px 4px 0 var(--noir);padding:16px;">
            <div style="display:flex;align-items:center;gap:16px;margin-bottom:12px;">
              <a href="view_profile.php?id=<?= $s['id'] ?>" style="display:flex;align-items:center;gap:16px;flex:1;text-decoration:none;color:inherit;">
                <div style="width:48px;height:48px;border-radius:50%;background:var(--bleu);border:2px solid var(--noir);display:flex;align-items:center;justify-content:center;font-family:'Playfair Display',serif;font-weight:900;color:var(--blanc);font-size:1.2rem;flex-shrink:0;">
                  <?= strtoupper(mb_substr($s['prenom'], 0, 1)) ?>
                </div>
                <div style="flex:1;">
                  <div style="font-weight:900;font-size:15px;"><?= htmlspecialchars($s['prenom'] . ' ' . $s['nom'][0] . '.') ?></div>
                  <div style="font-size:11px;color:var(--gris);text-transform:uppercase;font-weight:700;letter-spacing:0.05em;"><?= htmlspecialchars($s['ecole']) ?> · <?= htmlspecialchars($s['promo']) ?></div>
                </div>
                <div style="color:var(--gris-clair);">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"></polyline></svg>
                </div>
              </a>
              <button class="btn-follow-user" data-user-id="<?= $s['id'] ?>" data-following="<?= $s['is_following']?'1':'0' ?>"
                      style="background:<?= $s['is_following']?'var(--noir)':'transparent' ?>;color:<?= $s['is_following']?'var(--blanc)':'var(--noir)' ?>;border:2px solid var(--noir);font-size:11px;font-weight:800;padding:6px 12px;cursor:pointer;white-space:nowrap;">
                <?= $s['is_following']?'✓ SUIVI':'+ SUIVRE' ?>
              </button>
            </div>
            
            <?php if ($s['score'] > 0 || $s['shared_squads'] > 0): ?>
              <div style="display:flex;flex-wrap:wrap;gap:6px;padding-top:10px;border-top:2px solid var(--gris-clair);">
                <?php if ($s['shared_squads'] > 0): ?>
                  <span class="match-pill" style="background:var(--lime);color:var(--noir);border-color:var(--noir);display:flex;align-items:center;gap:4px;">
                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path></svg>
                    SQUAD COMMUN
                  </span>
                <?php endif; ?>
                <?php foreach (array_slice($s['common_interests'], 0, 4) as $interest): ?>
                  <span class="match-pill">#<?= $interest ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>
</div>

<!-- Navigation -->
<nav class="bottom-nav">
  <a href="/explore.php" class="nav-item active">
    <span class="nav-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></span>
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
  <a href="/profil.php" class="nav-item">
    <span class="nav-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></span>
    <span>Moi</span>
  </a>
</nav>

<div class="toast" id="toast"></div>
<script src="/assets/js/router.js"></script>
<script src="/assets/js/app.js"></script>
</body>
</html>
