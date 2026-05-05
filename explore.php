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
$filterEcole = trim($_GET['ecole'] ?? '');
$filterInterest = trim($_GET['interest'] ?? '');
$allEcoles = [];
$allInterests = [];

if ($view === 'people') {
    $stmt = $pdo->prepare("SELECT interests FROM users WHERE id = ?");
    $stmt->execute([$uid]);
    $me = $stmt->fetch();
    $myInterests = $me['interests'] ? explode(',', $me['interests']) : [];

    // Get all distinct schools for filter
    $stmtE = $pdo->query("SELECT DISTINCT ecole FROM users WHERE type='etudiant' AND ecole IS NOT NULL AND ecole != '' ORDER BY ecole");
    $allEcoles = $stmtE->fetchAll(PDO::FETCH_COLUMN);

    // Get all distinct interests for filter
    $stmtI = $pdo->query("SELECT interests FROM users WHERE type='etudiant' AND interests IS NOT NULL AND interests != ''");
    $rawInterests = $stmtI->fetchAll(PDO::FETCH_COLUMN);
    $allInterests = array_unique(array_merge(...array_map(fn($i) => array_map('trim', explode(',', $i)), $rawInterests)));
    sort($allInterests);

    $sqlP = "SELECT id, nom, prenom, ecole, promo, interests,
                    (SELECT COUNT(*) FROM follows_users fu WHERE fu.follower_id=? AND fu.followed_id=users.id) AS is_following
             FROM users
             WHERE type='etudiant' AND id != ?";
    $paramsP = [$uid, $uid];

    if ($q) {
        $sqlP .= " AND (nom LIKE ? OR prenom LIKE ? OR ecole LIKE ? OR interests LIKE ?)";
        $term = '%' . addcslashes($q, '%_') . '%';
        $paramsP[] = $term; $paramsP[] = $term; $paramsP[] = $term; $paramsP[] = $term;
    }
    if ($filterEcole) {
        $sqlP .= " AND ecole = ?";
        $paramsP[] = $filterEcole;
    }
    if ($filterInterest) {
        $sqlP .= " AND FIND_IN_SET(?, REPLACE(interests, ', ', ',')) > 0";
        $paramsP[] = $filterInterest;
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

    if (!$q && !$filterEcole && !$filterInterest) {
        usort($students, function($a, $b) {
            if ($a['score'] === $b['score']) return $b['shared_squads'] <=> $a['shared_squads'];
            return $b['score'] <=> $a['score'];
        });
        $students = array_slice($students, 0, 24);
    }
}

// ── ABONNÉS (pour modal invitation) ─────────────────────────────────────────
$stmtFollowing = $pdo->prepare("SELECT u.id, u.prenom, u.nom FROM follows_users f JOIN users u ON u.id = f.followed_id WHERE f.follower_id = ? AND u.type = 'etudiant'");
$stmtFollowing->execute([$uid]);
$following = $stmtFollowing->fetchAll();

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
                   (SELECT COUNT(*) FROM inscriptions i WHERE i.evenement_id = e.id AND i.user_id = ? AND i.statut != 'annule') AS deja_inscrit,
                   (SELECT ROUND(AVG(a.note),1) FROM avis a JOIN evenements pe ON pe.id = a.evenement_id WHERE pe.etablissement_id = e.etablissement_id) AS etab_note,
                   (SELECT COUNT(*) FROM avis a JOIN evenements pe ON pe.id = a.evenement_id WHERE pe.etablissement_id = e.etablissement_id) AS etab_nb_avis
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
<?= themeBootScript() ?>
<link rel="stylesheet" href="<?= baseUrl() ?>/assets/css/style.css">
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
      <?php $firstCard = true; foreach ($evenements as $e):
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
              <?php if ($firstCard): ?>
              <div style="margin-top:14px;padding:10px 14px;background:rgba(0,0,0,0.25);border-radius:4px;display:flex;align-items:center;gap:10px;">
                <svg width="16" height="16" fill="none" stroke="rgba(255,255,255,0.8)" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span style="font-size:11px;font-weight:700;color:rgba(255,255,255,0.7);text-transform:uppercase;letter-spacing:0.05em;">Commence dans</span>
                <span class="event-countdown" data-ts="<?= strtotime($e['date_heure']) ?>" style="font-size:20px;font-weight:900;color:#fff;letter-spacing:-0.02em;font-family:'DM Sans',sans-serif;">--:--:--</span>
              </div>
              <?php $firstCard = false; endif; ?>
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
              <div class="event-lieu" style="display:flex;align-items:center;gap:8px;">
                <?= htmlspecialchars($e['etablissement_nom']) ?>
                <?php if ($e['etab_nb_avis'] > 0): ?>
                  <span style="color:#F5B400;font-weight:700;font-size:12px;">★ <?= $e['etab_note'] ?></span>
                  <span style="color:var(--gris);font-size:11px;">(<?= $e['etab_nb_avis'] ?>)</span>
                <?php endif; ?>
              </div>
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

            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
              <div style="font-size:11px;font-weight:700;"><?= $e['nb_inscrits'] ?>/<?= $e['quota'] ?> places</div>
              <div style="display:flex;gap:6px;">
                <button onclick="openInviteModal('event', <?= $e['id'] ?>, '<?= htmlspecialchars($e['titre'], ENT_QUOTES) ?>')"
                        style="background:none;border:2px solid var(--noir);padding:7px 10px;font-size:11px;font-weight:800;cursor:pointer;display:flex;align-items:center;gap:4px;">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                  Inviter
                </button>
                <button class="btn btn-primary btn-join-event" data-event-id="<?= $e['id'] ?>" style="padding:8px 16px;font-size:12px;" <?= $e['deja_inscrit']?'disabled':'' ?>>
                  <?= $e['deja_inscrit']?'✓ Inscrit':'Rejoindre' ?>
                </button>
              </div>
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
      <form method="GET" id="people-filters">
        <input type="hidden" name="view" value="people">

        <!-- Search -->
        <div style="position:relative;margin-bottom:14px;">
          <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Chercher un nom ou une passion..."
                 style="width:100%;padding:14px 16px;border:3px solid var(--noir);box-shadow:4px 4px 0 var(--noir);font-size:14px;outline:none;background:var(--blanc);">
          <button type="submit" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--noir);">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          </button>
        </div>

        <!-- Filter row -->
        <div style="display:flex;gap:8px;margin-bottom:20px;">
          <div style="flex:1;position:relative;">
            <select name="ecole" onchange="this.form.submit()"
                    style="width:100%;padding:10px 32px 10px 12px;border:2px solid var(--noir);background:<?= $filterEcole?'var(--noir)':'var(--blanc)' ?>;color:<?= $filterEcole?'var(--blanc)':'var(--noir)' ?>;font-size:12px;font-weight:700;appearance:none;cursor:pointer;">
              <option value="">Toutes les écoles</option>
              <?php foreach ($allEcoles as $e): ?>
                <option value="<?= htmlspecialchars($e) ?>" <?= $filterEcole===$e?'selected':'' ?>><?= htmlspecialchars($e) ?></option>
              <?php endforeach; ?>
            </select>
            <svg style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="<?= $filterEcole?'white':'var(--noir)' ?>" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
          </div>

          <?php if (!empty($allInterests)): ?>
          <div style="flex:1;position:relative;">
            <select name="interest" onchange="this.form.submit()"
                    style="width:100%;padding:10px 32px 10px 12px;border:2px solid var(--noir);background:<?= $filterInterest?'var(--bleu)':'var(--blanc)' ?>;color:<?= $filterInterest?'var(--blanc)':'var(--noir)' ?>;font-size:12px;font-weight:700;appearance:none;cursor:pointer;">
              <option value="">Tous les intérêts</option>
              <?php foreach ($allInterests as $int): ?>
                <option value="<?= htmlspecialchars($int) ?>" <?= $filterInterest===$int?'selected':'' ?>>#<?= htmlspecialchars($int) ?></option>
              <?php endforeach; ?>
            </select>
            <svg style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="<?= $filterInterest?'white':'var(--noir)' ?>" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
          </div>
          <?php endif; ?>
        </div>

        <?php if ($filterEcole || $filterInterest || $q): ?>
          <div style="margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;">
            <span style="font-size:12px;font-weight:700;color:var(--gris);"><?= count($students) ?> résultat<?= count($students)>1?'s':'' ?></span>
            <a href="?view=people" style="font-size:11px;font-weight:800;color:var(--rouge);text-transform:uppercase;letter-spacing:0.05em;text-decoration:none;">Réinitialiser</a>
          </div>
        <?php endif; ?>
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

<!-- Modal Invitation -->
<div id="modal-invite" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:200;align-items:flex-end;">
  <div style="background:var(--bg);border-top:3px solid var(--noir);width:100%;max-height:75vh;overflow-y:auto;padding:24px 20px 40px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
      <div>
        <div style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:0.1em;color:var(--rouge);margin-bottom:4px;">Inviter un ami</div>
        <div class="display" id="invite-target-name" style="font-size:1.2rem;"></div>
      </div>
      <button onclick="closeInviteModal()" style="background:none;border:2px solid var(--noir);width:36px;height:36px;font-size:18px;cursor:pointer;">✕</button>
    </div>
    <div id="invite-user-list" style="display:flex;flex-direction:column;gap:10px;">
      <?php foreach ($following as $f): ?>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:14px;background:var(--blanc);border:2px solid var(--noir);">
        <div style="display:flex;align-items:center;gap:12px;">
          <div style="width:40px;height:40px;background:var(--bleu);border:2px solid var(--noir);display:flex;align-items:center;justify-content:center;font-weight:900;color:var(--blanc);font-size:15px;flex-shrink:0;">
            <?= strtoupper(mb_substr($f['prenom'], 0, 1)) ?>
          </div>
          <div style="font-weight:800;font-size:14px;"><?= htmlspecialchars($f['prenom'] . ' ' . $f['nom'][0] . '.') ?></div>
        </div>
        <button class="btn-send-invite" data-user-id="<?= $f['id'] ?>"
                style="background:var(--noir);color:var(--blanc);border:2px solid var(--noir);padding:8px 14px;font-size:11px;font-weight:800;cursor:pointer;text-transform:uppercase;">
          Inviter
        </button>
      </div>
      <?php endforeach; ?>
      <?php if (empty($following)): ?>
      <div style="text-align:center;padding:24px;color:var(--gris);font-size:14px;">Tu ne suis personne encore.</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>
<script src="<?= baseUrl() ?>/assets/js/app.js"></script>
<script>
const _BASE = location.hostname === 'localhost' || location.hostname === '127.0.0.1' ? '/TitreRNCP' : '';
let inviteType = '', inviteTargetId = 0;

function openInviteModal(type, targetId, targetName) {
  inviteType = type; inviteTargetId = targetId;
  document.getElementById('invite-target-name').textContent = targetName;
  const modal = document.getElementById('modal-invite');
  modal.style.display = 'flex';
  document.querySelectorAll('.btn-send-invite').forEach(b => {
    b.textContent = 'Inviter'; b.disabled = false; b.style.background = 'var(--noir)';
  });
}

function closeInviteModal() {
  document.getElementById('modal-invite').style.display = 'none';
}

document.getElementById('modal-invite').addEventListener('click', e => {
  if (e.target === document.getElementById('modal-invite')) closeInviteModal();
});

document.querySelectorAll('.btn-send-invite').forEach(btn => {
  btn.addEventListener('click', async () => {
    const toUserId = btn.dataset.userId;
    btn.disabled = true; btn.textContent = '...';
    try {
      const res = await fetch(_BASE + '/api/inviter.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'send', to_user_id: toUserId, type: inviteType, target_id: inviteTargetId })
      });
      const data = await res.json();
      if (data.success) {
        btn.textContent = '✓ Envoyé'; btn.style.background = 'var(--bleu)';
        showToast('Invitation envoyée !', 'success');
      } else {
        btn.disabled = false; btn.textContent = 'Inviter';
        showToast(data.message || 'Erreur', 'error');
      }
    } catch { btn.disabled = false; btn.textContent = 'Inviter'; showToast('Erreur réseau', 'error'); }
  });
});
</script>
</body>
</html>
