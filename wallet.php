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
    WHERE i.user_id=? AND (i.statut != 'inscrit' OR e.date_heure < NOW())
    ORDER BY e.date_heure DESC LIMIT 5
");
$stmt->execute([$uid]);
$passesOld = $stmt->fetchAll();

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
$currentMonth = strtoupper($moisFr[date('F')] ?? date('F'));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StudentLink — Wallet</title>
<?= themeBootScript() ?>
<link rel="stylesheet" href="<?= baseUrl() ?>/assets/css/style.css">
<link rel="icon" type="image/png" href="/Logo.png">
<link rel="apple-touch-icon" href="/Logo.png">
<link rel="manifest" href="/manifest.json">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="StudentLink">
</head>
<body>
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
    <div class="display" style="font-size:2.6rem;">Ton pass,</div>
    <div class="display-italic" style="font-size:2.6rem;">en poche.</div>
  </div>

  <div class="page-content">
    <!-- Amount cards -->
    <div class="wallet-amounts">
      <div class="amount-card card-rouge">
        <div class="label"><?= $currentMonth ?></div>
        <div class="amount-value"><?= number_format($ecoMois, 0, ',', ' ') ?>€</div>
        <div class="amount-sub">économisé ce mois</div>
      </div>
      <div class="amount-card card-bleu">
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
            <div class="label" style="font-size:9px;color:var(--gris);margin-bottom:2px;">Pass étudiant · <?= $idx + 1 ?>/<?= count($passes) ?></div>
            <div class="qr-name">Pass invalide</div>
          </div>
          <button class="btn-cancel-pass" data-id="<?= $activePass['id'] ?>" style="background:none;border:none;cursor:pointer;color:var(--rouge);font-weight:900;font-size:20px;line-height:1;display:flex;align-items:center;justify-content:center;width:24px;height:24px;margin-top:-4px;" title="Supprimer ce pass">✕</button>
        </div>
        <div class="qr-body" style="background:#ccc; display:flex; align-items:center; justify-content:center; flex-direction:column; gap:8px; cursor:default;">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#666" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
          <div style="font-family:'Playfair Display',serif;font-weight:900;font-size:1rem;color:#555;text-align:center;">Événement<br>supprimé</div>
        </div>
        <div style="padding:12px 16px;">
          <div style="font-size:12px;font-weight:700;color:var(--gris);">Cet événement n'existe plus</div>
          <div style="font-size:11px;color:var(--gris);margin-top:2px;">Tu peux supprimer ce pass</div>
        </div>
      </div>

      <?php else: ?>
      <!-- NORMAL CARD -->
      <div class="qr-wrapper" style="flex:0 0 88%; scroll-snap-align:center;">
        <div class="qr-header">
          <div>
            <div class="label" style="font-size:9px;color:var(--gris);margin-bottom:2px;">Pass étudiant · <?= $idx + 1 ?>/<?= count($passes) ?></div>
            <div class="qr-name"><?= htmlspecialchars(strtoupper($user['prenom'] . ' ' . $user['nom'][0] . '.')) ?></div>
          </div>
          <button class="btn-cancel-pass" data-id="<?= $activePass['id'] ?>" style="background:none;border:none;cursor:pointer;color:var(--rouge);font-weight:900;font-size:20px;line-height:1;display:flex;align-items:center;justify-content:center;width:24px;height:24px;margin-top:-4px;" title="Annuler ce pass">✕</button>
        </div>

        <div class="qr-body">
          <div class="qr-reveal-text">
            Tap<br>to reveal.
          </div>
          <div class="qr-canvas" data-code="<?= htmlspecialchars($activePass['qr_code']) ?>"></div>
        </div>

        <div style="padding:12px 16px;">
          <div style="font-size:12px;font-weight:700;color:var(--noir);">
            <?= htmlspecialchars($activePass['etablissement_nom']) ?> — <?= htmlspecialchars($activePass['titre']) ?>
          </div>
          <div style="font-size:11px;color:var(--gris);margin-top:2px;">
            <?= date('D j M · H\hi', strtotime($activePass['date_heure'])) ?> ·
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
    <div style="background:var(--blanc);border-radius:6px;padding:32px;text-align:center;margin-bottom:12px;border:2px solid var(--noir);box-shadow:4px 4px 0 var(--noir);">
      <div style="display:flex;justify-content:center;margin-bottom:16px;color:var(--gris);">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v2z"></path><line x1="13" y1="5" x2="13" y2="19"></line></svg>
      </div>
      <div style="font-weight:600;margin-bottom:6px;">Aucun pass actif</div>
      <div style="font-size:13px;color:var(--gris);margin-bottom:16px;">Inscris-toi à un événement pour obtenir ton pass.</div>
      <a href="/explore.php" class="btn btn-primary">→ Explorer les événements</a>
    </div>
    <?php endif; ?>

    <?php if (!empty($mySquads)): ?>
    <div class="pass-list-header" style="margin-top:24px;">
      <span><?= count($mySquads) ?> Squad<?= count($mySquads) > 1 ? 's' : '' ?> prévu<?= count($mySquads) > 1 ? 's' : '' ?></span>
      <span style="color:var(--gris);">—— Sport ↓</span>
    </div>
    
    <div class="wallet-carousel">
      <?php foreach ($mySquads as $idx => $sq): ?>
      <div style="flex:0 0 88%; scroll-snap-align:center; background:var(--lime); border:2px solid var(--noir); box-shadow:4px 4px 0px var(--noir); display:flex; flex-direction:column; overflow:hidden;">
        <div style="padding:16px; border-bottom:2px solid var(--noir); display:flex; justify-content:space-between; align-items:flex-start;">
          <div>
            <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:4px;">Session <?= htmlspecialchars($sq['type']) ?></div>
            <div style="font-family:'Playfair Display',serif;font-weight:900;font-size:1.4rem;line-height:1.1;color:var(--noir);"><?= htmlspecialchars($sq['titre']) ?></div>
          </div>
          <div style="display:flex; align-items:center; gap:8px;">
            <div style="color:var(--noir);">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline></svg>
            </div>
            <button class="btn-leave-squad" data-id="<?= $sq['id'] ?>" style="background:none;border:none;cursor:pointer;color:var(--noir);font-weight:900;font-size:20px;line-height:1;display:flex;align-items:center;justify-content:center;width:24px;height:24px;margin-top:-2px;" title="Quitter ce groupe">✕</button>
          </div>
        </div>
        <div style="padding:16px; background:var(--lime);">
          <div style="font-size:14px;font-weight:700;margin-bottom:6px;color:var(--noir);">
            <?= date('D j M · H\hi', strtotime($sq['date_heure'])) ?>
          </div>
          <div style="font-size:12px;margin-bottom:6px;color:var(--noir);">
            📍 <?= htmlspecialchars($sq['lieu']) ?>
          </div>
          <div style="font-size:12px;font-weight:500;color:var(--noir);">
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
    $dotColors = ['#E5331A','#2929E8','#C8E52A','#F07820','#1A1A1A'];
    $di = 0;
    foreach ($passes as $p): ?>
    <div class="pass-item">
      <div class="pass-dot" style="background:<?= $dotColors[$di % count($dotColors)] ?>;"></div>
      <div class="pass-item-info">
        <div class="pass-item-name"><?= htmlspecialchars($p['etablissement_nom']) ?></div>
        <div class="pass-item-sub"><?= htmlspecialchars($p['titre']) ?> · <?= date('D j M · H\hi', strtotime($p['date_heure'])) ?></div>
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
    <?php $di++; endforeach; ?>

    <?php if (!empty($passesOld)): ?>
    <div class="section-divider"><span class="sd-label">Historique</span></div>
    <?php foreach ($passesOld as $p):
      $canReview = $p['statut'] === 'checkin';
    ?>
    <div class="pass-item" style="opacity:<?= $canReview ? '1' : '0.5' ?>;">
      <div class="pass-dot" style="background:<?= $canReview ? 'var(--lime)' : 'var(--gris)' ?>;"></div>
      <div class="pass-item-info">
        <div class="pass-item-name"><?= htmlspecialchars($p['etablissement_nom']) ?></div>
        <div class="pass-item-sub"><?= htmlspecialchars($p['titre']) ?> · <?= date('D j M', strtotime($p['date_heure'])) ?></div>
        <?php if ($canReview): ?>
          <a href="<?= baseUrl('/avis.php?event_id=' . $p['evenement_id']) ?>" style="display:inline-block;margin-top:6px;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:var(--bleu);text-decoration:none;">★ Laisser un avis →</a>
        <?php endif; ?>
      </div>
      <div class="pass-item-eco" style="color:var(--gris);">
        <?php if ($p['reduction'] > 0 && $p['prix_normal'] > 0):
          echo '-' . number_format($p['prix_normal'] * $p['reduction'] / 100, 2, ',', ' ') . '€';
        else: ?>Gratuit<?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
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
  <a href="/wallet.php" class="nav-item active">
    <span class="nav-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg></span>
    <span>Wallet</span>
  </a>
  <a href="/profil.php" class="nav-item">
    <span class="nav-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></span>
    <span>Moi</span>
  </a>
</nav>

<div class="toast" id="toast"></div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<script src="<?= baseUrl() ?>/assets/js/app.js"></script>
</body>
</html>
