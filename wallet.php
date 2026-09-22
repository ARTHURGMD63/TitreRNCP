<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';
requireStudent();
$user = currentUser();
$uid = $user['id'];

// Monthly savings
$stmt = $pdo->prepare("SELECT COALESCE(SUM(montant),0) FROM economies WHERE user_id=? AND MONTH(date_economie)=MONTH(NOW()) AND YEAR(date_economie)=YEAR(NOW())");
$stmt->execute([$uid]);
$ecoMois = (float)$stmt->fetchColumn();

// Yearly savings
$stmt = $pdo->prepare("SELECT COALESCE(SUM(montant),0) FROM economies WHERE user_id=? AND YEAR(date_economie)=YEAR(NOW())");
$stmt->execute([$uid]);
$ecoAnnee = (float)$stmt->fetchColumn();

// Active passes (current inscriptions) - LEFT JOIN to catch deleted events
$stmt = $pdo->prepare("
    SELECT i.*, 
           e.titre, e.date_heure, e.reduction, e.prix_normal, e.lieu,
           et.nom AS etablissement_nom,
           et.type AS etab_type,
           i.qr_code,
           CASE WHEN e.id IS NULL THEN 1 ELSE 0 END AS event_deleted
    FROM inscriptions i
    LEFT JOIN evenements e ON e.id = i.evenement_id
    LEFT JOIN etablissements et ON et.id = e.etablissement_id
    WHERE i.user_id=? AND i.statut='inscrit'
    AND (e.date_heure >= NOW() OR e.id IS NULL)
    ORDER BY e.date_heure ASC
");
$stmt->execute([$uid]);
$passes = $stmt->fetchAll();

// Past passes
$stmt = $pdo->prepare("
    SELECT i.*, e.titre, e.date_heure, e.reduction, e.prix_normal, et.nom AS etablissement_nom
    FROM inscriptions i
    JOIN evenements e ON e.id = i.evenement_id
    JOIN etablissements et ON et.id = e.etablissement_id
    WHERE i.user_id = :uid AND (i.statut != 'inscrit' OR e.date_heure < NOW())
    ORDER BY e.date_heure DESC LIMIT :limite
");
// L'historique s'allonge sur demande plutot que de s'arreter a 5 : couper
// l'historique sur une app qui met en avant les economies cumulees est
// contre-productif. On demande un element de plus que la tranche pour
// savoir s'il en reste, sans compter toute la table.
$parPage = 10;
$pageHist = max(1, (int)($_GET['h'] ?? 1));
$limite = $parPage * $pageHist;
// PDO n'accepte pas de melanger parametres nommes et positionnels : les deux
// sont nommes.
$stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
$stmt->bindValue(':limite', $limite + 1, PDO::PARAM_INT);
$stmt->execute();
$passesOld = $stmt->fetchAll();
$resteHistorique = count($passesOld) > $limite;
if ($resteHistorique) {
    array_pop($passesOld);
}

// Active Squads
$stmt = $pdo->prepare("
    SELECT s.*, u.prenom AS createur_prenom
    FROM squad_membres sm
    JOIN squads s ON s.id = sm.squad_id
    JOIN users u ON u.id = s.createur_id
    WHERE sm.user_id=? AND s.date_heure >= NOW()
    ORDER BY s.date_heure ASC
");
$stmt->execute([$uid]);
$mySquads = $stmt->fetchAll();

$moisFr = ['January'=>'Janvier','February'=>'Février','March'=>'Mars','April'=>'Avril',
           'May'=>'Mai','June'=>'Juin','July'=>'Juillet','August'=>'Août',
           'September'=>'Septembre','October'=>'Octobre','November'=>'Novembre','December'=>'Décembre'];
$currentMonth = mb_strtoupper($moisFr[date('F')] ?? date('F'));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StudentLink — Wallet</title>
<?= themeBootScript() ?>
<?= metaCsrf() ?>
<link rel="stylesheet" href="<?= asset('/assets/css/style.css') ?>">
<link rel="icon" type="image/png" href="<?= baseUrl('/Logo.png') ?>">
<link rel="apple-touch-icon" href="<?= baseUrl('/Logo.png') ?>">
<link rel="manifest" href="<?= baseUrl('/manifest.json') ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="StudentLink">
</head>
<body>
<a href="#main-content" class="skip-nav">Aller au contenu principal</a>
<div class="app-shell">

  <!-- Header -->
  <div class="page-header">
    <div class="logo" style="margin-bottom:20px;">
      <svg class="logo-icon" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="15" y="20" width="50" height="30" rx="15" stroke="var(--noir)" stroke-width="10"/>
        <rect x="35" y="50" width="50" height="30" rx="15" class="accent" stroke-width="10"/>
        <circle cx="50" cy="50" r="6" fill="var(--noir)"/>
      </svg>
      StudentLink <em>/ Wallet</em>
    </div>
    <h1 class="titre-page">
      <div class="display" style="font-size:var(--fs-9);">Ton pass,</div>
      <div class="display-italic" style="font-size:var(--fs-9);">en poche.</div>
    </h1>
  </div>

  <main id="main-content" class="page-content">
    <!-- Amount cards -->
    <div class="wallet-amounts">
      <div class="amount-card card-rouge">
        <div class="label"><?= $currentMonth ?></div>
        <div class="amount-value"><?= number_format($ecoMois, 0, ',', ' ') ?>€</div>
        <div class="amount-sub">économisé ce mois</div>
      </div>
      <div class="amount-card card-neutre">
        <div class="label">Total année</div>
        <div class="amount-value"><?= number_format($ecoAnnee, 0, ',', ' ') ?>€</div>
        <div class="amount-sub">depuis janvier</div>
      </div>
    </div>

    <?php if (!empty($passes)): ?>
    <style>
      .wallet-carousel { display:flex; overflow-x:auto; scroll-snap-type:x mandatory; gap:16px; padding-bottom:16px; scrollbar-width:none; -ms-overflow-style:none; }
      .wallet-carousel::-webkit-scrollbar { display:none; }
    </style>
    <!-- Active QR Passes Carousel -->
    <div class="wallet-carousel">
      <?php foreach ($passes as $idx => $activePass): ?>

      <?php if ($activePass['event_deleted']): ?>
      <!-- DELETED EVENT CARD -->
      <div class="qr-wrapper" style="flex:0 0 88%; scroll-snap-align:center; opacity:0.6; filter:grayscale(1); position:relative;">
        <div class="qr-header">
          <div>
            <div class="label" style="font-size:var(--fs-1);color:var(--gris);margin-bottom:2px;">Pass étudiant · <?= $idx + 1 ?>/<?= count($passes) ?></div>
            <div class="qr-name">Pass invalide</div>
          </div>
          <button class="btn-cancel-pass" data-id="<?= $activePass['id'] ?>" style="background:none;border:none;cursor:pointer;color:var(--sur-rouge-clair);font-weight:var(--fw-black);font-size:var(--fs-6);line-height:var(--lh-display);display:flex;align-items:center;justify-content:center;width:24px;height:24px;margin-top:-4px;" title="Supprimer ce pass"><?= icon('croix', 'icon-sm') ?></button>
        </div>
        <div class="qr-body" style="background:#ccc; display:flex; align-items:center; justify-content:center; flex-direction:column; gap:8px; cursor:default;">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
          <div style="font-family:var(--font-display);font-weight:var(--fw-black);font-size:var(--fs-5);color:#555;text-align:center;">Événement<br>supprimé</div>
        </div>
        <div style="padding:12px 16px;">
          <div style="font-size:var(--fs-2);font-weight:var(--fw-bold);color:var(--gris);">Cet événement n'existe plus</div>
          <div style="font-size:var(--fs-1);color:var(--gris);margin-top:2px;">Tu peux supprimer ce pass</div>
        </div>
      </div>

      <?php else: ?>
      <!-- NORMAL CARD -->
      <div class="qr-wrapper" style="flex:0 0 88%; scroll-snap-align:center;">
        <div class="qr-header">
          <div>
            <div class="label" style="font-size:var(--fs-1);color:var(--gris);margin-bottom:2px;">Pass étudiant · <?= $idx + 1 ?>/<?= count($passes) ?></div>
            <!-- mb_* et non [0]/strtoupper : l'indexation prend un octet, pas une
                 lettre, et « Émile » sortait en caractère cassé sur le pass. -->
            <div class="qr-name"><?= htmlspecialchars(mb_strtoupper($user['prenom'] . ' ' . mb_substr((string)$user['nom'], 0, 1) . '.')) ?></div>
          </div>
          <button class="btn-cancel-pass" data-id="<?= $activePass['id'] ?>" style="background:none;border:none;cursor:pointer;color:var(--sur-rouge-clair);font-weight:var(--fw-black);font-size:var(--fs-6);line-height:var(--lh-display);display:flex;align-items:center;justify-content:center;width:24px;height:24px;margin-top:-4px;" title="Annuler ce pass"><?= icon('croix', 'icon-sm') ?></button>
        </div>

        <div class="qr-body">
          <div class="qr-reveal-text">
            Appuyer<br>pour afficher.
          </div>
          <div class="qr-canvas" data-code="<?= htmlspecialchars($activePass['qr_code']) ?>"></div>
        </div>

        <div style="padding:12px 16px;">
          <div style="font-size:var(--fs-2);font-weight:var(--fw-bold);color:var(--noir);">
            <?= htmlspecialchars($activePass['etablissement_nom']) ?> — <?= htmlspecialchars($activePass['titre']) ?>
          </div>
          <div style="font-size:var(--fs-1);color:var(--gris);margin-top:2px;">
            <?= dateFr($activePass['date_heure'], 'D j M · H\hi') ?> ·
            <?php if ($activePass['reduction'] > 0): ?>
              -<?= $activePass['reduction'] ?>%
            <?php else: ?>entrée gratuite<?php endif; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="background:var(--blanc);border-radius:var(--radius);padding:32px;text-align:center;margin-bottom:12px;border:1px solid var(--gris-clair);box-shadow:var(--shadow);">
      <div style="display:flex;justify-content:center;margin-bottom:16px;color:var(--gris);">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v2z"></path><line x1="13" y1="5" x2="13" y2="19"></line></svg>
      </div>
      <div style="font-weight:var(--fw-semibold);margin-bottom:6px;">Aucun pass actif</div>
      <div style="font-size:var(--fs-3);color:var(--gris);margin-bottom:16px;">Inscris-toi à un événement pour obtenir ton pass.</div>
      <a href="<?= baseUrl('/explore.php') ?>" class="btn btn-primary">→ Explorer les événements</a>
    </div>
    <?php endif; ?>

    <?php if (!empty($mySquads)): ?>
    <div class="pass-list-header" style="margin-top:24px;">
      <span><?= count($mySquads) ?> Squad<?= count($mySquads) > 1 ? 's' : '' ?> prévu<?= count($mySquads) > 1 ? 's' : '' ?></span>
      <span style="color:var(--gris);">—— Sport ↓</span>
    </div>
    
    <div class="wallet-carousel">
      <?php foreach ($mySquads as $idx => $sq): ?>
      <div style="flex:0 0 88%; scroll-snap-align:center; background:var(--lime);color:var(--sur-media-encre); border:1px solid var(--gris-clair); box-shadow:var(--shadow); display:flex; flex-direction:column; overflow:hidden;">
        <div style="padding:16px; border-bottom: 1px solid var(--gris-clair); display:flex; justify-content:space-between; align-items:flex-start;">
          <div>
            <div style="font-size:var(--fs-1);font-weight:var(--fw-bold);text-transform:uppercase;letter-spacing:var(--ls-wide);margin-bottom:4px;">Session <?= htmlspecialchars($sq['type']) ?></div>
            <div style="font-family:var(--font-display);font-weight:var(--fw-black);font-size:var(--fs-7);line-height:var(--lh-tight);color:var(--noir);"><?= htmlspecialchars($sq['titre']) ?></div>
          </div>
          <div style="display:flex; align-items:center; gap:8px;">
            <div style="color:var(--noir);">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
            </div>
            <button class="btn-leave-squad" data-id="<?= $sq['id'] ?>" style="background:none;border:none;cursor:pointer;color:var(--noir);font-weight:var(--fw-black);font-size:var(--fs-6);line-height:var(--lh-display);display:flex;align-items:center;justify-content:center;width:24px;height:24px;margin-top:-2px;" title="Quitter ce groupe"><?= icon('croix', 'icon-sm') ?></button>
          </div>
        </div>
        <div style="padding:16px; background:var(--lime);color:var(--sur-media-encre);">
          <div style="font-size:var(--fs-4);font-weight:var(--fw-bold);margin-bottom:6px;color:var(--noir);">
            <?= dateFr($sq['date_heure'], 'D j M · H\hi') ?>
          </div>
          <div style="font-size:var(--fs-2);margin-bottom:6px;color:var(--noir);">
            <span class="with-icon"><?= icon('epingle', 'icon-sm') ?><?= htmlspecialchars($sq['lieu']) ?></span>
          </div>
          <div style="font-size:var(--fs-2);font-weight:var(--fw-medium);color:var(--noir);">
            Organisé par <?= htmlspecialchars($sq['createur_prenom']) ?> · Niveau : <?= htmlspecialchars($sq['niveau']) ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- All passes -->
    <div class="pass-list-header" style="margin-top:16px;">
      <span><?= count($passes) ?> passe<?= count($passes) > 1 ? 's' : '' ?> actif<?= count($passes) > 1 ? 's' : '' ?></span>
      <span style="color:var(--gris);">—— Agenda ↓</span>
    </div>

    <?php
    // Même grammaire que les cartes d'événement : la couleur code le type de
    // lieu. Avant, elle tournait par index et ne voulait rien dire, alors que
    // la même pastille servait de statut dans l'historique juste en dessous.
    $couleurType = [
        'bar'       => 'var(--bleu)',
        'boite'     => 'var(--rouge)',
        'resto'     => 'var(--orange)',
        'afterwork' => 'var(--lime)',
    ];
    foreach ($passes as $p): ?>
    <div class="pass-item">
      <div class="pass-dot" title="<?= htmlspecialchars(ucfirst($p['etab_type'] ?? '')) ?>"
           style="background:<?= $couleurType[$p['etab_type'] ?? ''] ?? 'var(--gris)' ?>;"></div>
      <div class="pass-item-info">
        <div class="pass-item-name"><?= htmlspecialchars($p['etablissement_nom']) ?></div>
        <div class="pass-item-sub"><?= htmlspecialchars($p['titre']) ?> · <?= dateFr($p['date_heure'], 'D j M · H\hi') ?></div>
      </div>
      <div class="pass-item-eco">
        <?php if ($p['reduction'] > 0 && $p['prix_normal'] > 0):
          $saved = round($p['prix_normal'] * $p['reduction'] / 100, 2);
          echo '-' . number_format($saved, 2, ',', ' ') . '€';
        else: ?>
          Gratuit
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <?php if (!empty($passesOld)): ?>
    <div class="section-divider"><span class="sd-label">Historique</span></div>
    <?php foreach ($passesOld as $p):
      $canReview = $p['statut'] === 'checkin';
    ?>
    <div class="pass-item" style="opacity:<?= $canReview ? '1' : '0.5' ?>;">
      <div class="pass-dot" style="background:<?= $canReview ? 'var(--succes)' : 'var(--gris)' ?>;"></div>
      <div class="pass-item-info">
        <div class="pass-item-name"><?= htmlspecialchars($p['etablissement_nom']) ?></div>
        <div class="pass-item-sub"><?= htmlspecialchars($p['titre']) ?> · <?= dateFr($p['date_heure'], 'D j M') ?></div>
        <?php if ($canReview): ?>
          <a href="<?= baseUrl('/avis.php?event_id=' . $p['evenement_id']) ?>" style="display:inline-block;margin-top:6px;font-size:var(--fs-1);font-weight:var(--fw-display);text-transform:uppercase;letter-spacing:var(--ls-wide);color:var(--bleu);text-decoration:none;">Laisser un avis →</a>
        <?php endif; ?>
      </div>
      <div class="pass-item-eco" style="color:var(--gris);">
        <?php if ($p['reduction'] > 0 && $p['prix_normal'] > 0):
          echo '-' . number_format($p['prix_normal'] * $p['reduction'] / 100, 2, ',', ' ') . '€';
        else: ?>Gratuit<?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <?php if (!empty($resteHistorique)): ?>
      <?php $suite = $_GET; $suite['h'] = $pageHist + 1; ?>
      <a href="?<?= htmlspecialchars(http_build_query($suite), ENT_QUOTES) ?>#historique"
         class="btn btn-outline btn-full" style="margin-top:14px;">
        Voir plus d'historique
      </a>
    <?php endif; ?>
    <?php endif; ?>
  </div>

</div><!-- .app-shell -->

<!-- Bottom Nav -->
<nav class="bottom-nav" aria-label="Navigation principale">
  <a href="<?= baseUrl('/explore.php') ?>" class="nav-item">
    <span class="nav-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></span>
    <span>Explore</span>
  </a>
  <a href="<?= baseUrl('/squads.php') ?>" class="nav-item">
    <span class="nav-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg></span>
    <span>Squads</span>
  </a>
  <a href="<?= baseUrl('/wallet.php') ?>" class="nav-item active" aria-current="page">
    <span class="nav-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg></span>
    <span>Wallet</span>
  </a>
  <a href="<?= baseUrl('/profil.php') ?>" class="nav-item">
    <span class="nav-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></span>
    <span>Moi</span>
  </a>
</nav>

<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script src="<?= asset('/assets/vendor/qrcode.min.js') ?>"></script>

<script src="<?= asset('/assets/js/app.js') ?>"></script>
</body>
</html>
