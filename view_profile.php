<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/social.php';
require_once __DIR__ . '/includes/uploads.php';
requireStudent();
$me_uid = currentUser()['id'];

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: explore.php?view=people');
    exit;
}

// Fetch user data
$stmt = $pdo->prepare("
    SELECT id, prenom, nom, ecole, promo, interests, created_at, photo,
           (SELECT statut FROM follows_users WHERE follower_id = ? AND followed_id = ?) AS follow_statut,
           (SELECT COUNT(*) FROM follows_users WHERE followed_id = ? AND statut='accepted') AS nb_followers,
           (SELECT COUNT(*) FROM follows_users WHERE follower_id = ? AND statut='accepted') AS nb_following
    FROM users WHERE id = ? AND type='etudiant'
");
$stmt->execute([$me_uid, $id, $id, $id, $id]);
$u = $stmt->fetch();

if (!$u) {
    echo "Étudiant introuvable.";
    exit;
}

// Un blocage, dans un sens ou dans l'autre, rend le profil inaccessible.
if (isBlockedBetween($pdo, (int)$me_uid, (int)$id)) {
    header('Location: explore.php?view=people');
    exit;
}

$etatSuivi = $u['follow_statut'] ?: FOLLOW_NONE;
// Règle du modèle : l'activité n'est visible qu'avec un abonnement accepté.
$peutVoirActivite = ($etatSuivi === FOLLOW_ACCEPTED);
$jeSuisBloquant   = false;
$stmtB = $pdo->prepare("SELECT 1 FROM user_blocks WHERE blocker_id=? AND blocked_id=?");
$stmtB->execute([$me_uid, $id]);
$jeSuisBloquant = (bool)$stmtB->fetchColumn();

$initials = mb_strtoupper(mb_substr($u['prenom'], 0, 1) . mb_substr($u['nom'], 0, 1));
$interests = $u['interests'] ? explode(',', $u['interests']) : [];

// Squads communs — activité : réservés aux abonnements acceptés.
$commonSquads = [];
$upcomingEvents = [];
if ($peutVoirActivite) {
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
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($u['prenom']) ?> — StudentLink</title>
<?= themeBootScript() ?>
<link rel="stylesheet" href="<?= asset('/assets/css/style.css') ?>">
<link rel="icon" type="image/png" href="<?= baseUrl('/Logo.png') ?>">
<link rel="apple-touch-icon" href="<?= baseUrl('/Logo.png') ?>">
<link rel="manifest" href="<?= baseUrl('/manifest.json') ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="StudentLink">
<style>
  .profile-header-bg {
    background: var(--bleu);
    height: 160px;
    margin-left: calc(var(--gutter) * -1);
    margin-right: calc(var(--gutter) * -1);
    margin-top: -20px;
    position: relative;
    border-bottom: 1px solid var(--gris-clair);
  }
  .profile-avatar-large {
    width: 100px;
    height: 100px;
    background: var(--blanc);
    border: 1px solid var(--gris-clair);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: var(--font-display);
    font-weight: var(--fw-black);
    font-size: var(--fs-8);
    position: absolute;
    bottom: -50px;
    left: 20px;
    box-shadow: var(--shadow-lg);
  }
  .stats-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin: 60px 0 24px;
  }
  .stat-card {
    background: var(--blanc);
    border-radius: var(--radius);
    border: 1px solid var(--gris-clair);
    box-shadow: var(--shadow);
    padding: 12px;
    text-align: center;
  }
  .section-card {
    background: var(--blanc);
    border-radius: var(--radius);
    border: 1px solid var(--gris-clair);
    box-shadow: var(--shadow);
    padding: 20px;
    margin-bottom: 24px;
  }
  /* .interest-pill vit desormais dans style.css : deux copies d'une meme
     etiquette finissaient par diverger. La marge reste locale a cette page. */
  .interest-pill { margin: 0 6px 8px 0; }
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

  <main id="main-content" class="page-content">
    
    <!-- Header -->
    <div class="profile-header-bg">
      <a href="javascript:history.back()" aria-label="Retour" style="position:absolute; top:60px; left:20px; background:var(--blanc); width:36px; height:36px; border-radius:50%; border:1px solid var(--gris-clair); display:flex; align-items:center; justify-content:center; color:var(--noir); box-shadow:var(--shadow-sm);">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"></line><polyline points="12 19 5 12 12 5"></polyline></svg>
      </a>
      <?= avatarHtml($u['photo'] ?? null, $u['prenom'], 100, 'var(--bleu)') ?>
    </div>

    <!-- Info Block -->
    <div class="stats-grid">
      <div style="grid-column: span 2;">
        <h1 class="titre-page">
          <div class="display" style="font-size:var(--fs-8); line-height:var(--lh-display);"><?= htmlspecialchars($u['prenom']) ?></div>
          <div class="display-italic" style="font-size:var(--fs-8); line-height:var(--lh-display);"><?= htmlspecialchars($u['nom']) ?></div>
        </h1>
        <div style="font-weight:var(--fw-bold); text-transform:uppercase; font-size:var(--fs-1); color:var(--gris); margin-top:8px; letter-spacing:var(--ls-wide);">
          <?= htmlspecialchars($u['ecole']) ?> · PROMO <?= htmlspecialchars($u['promo']) ?>
        </div>
      </div>
      
      <div class="stat-card">
        <div style="font-family:var(--font-display); font-weight:var(--fw-black); font-size:var(--fs-7);"><?= $u['nb_followers'] ?></div>
        <div style="font-size:var(--fs-1); font-weight:var(--fw-bold); text-transform:uppercase; opacity:0.6;">Abonnés</div>
      </div>
      <div class="stat-card">
        <div style="font-family:var(--font-display); font-weight:var(--fw-black); font-size:var(--fs-7);"><?= $u['nb_following'] ?></div>
        <div style="font-size:var(--fs-1); font-weight:var(--fw-bold); text-transform:uppercase; opacity:0.6;">Abonnements</div>
      </div>
    </div>

    <!-- Action Button -->
    <?php
      $libelleSuivi = [
        FOLLOW_ACCEPTED => icon('check','icon-sm') . ' ABONNÉ',
        FOLLOW_PENDING  => 'DEMANDE ENVOYÉE · ANNULER',
        FOLLOW_NONE     => '+ DEMANDER À SUIVRE',
      ][$etatSuivi];
      $fondSuivi = [
        FOLLOW_ACCEPTED => 'var(--noir)',
        FOLLOW_PENDING  => 'var(--surface-2)',
        FOLLOW_NONE     => 'var(--bleu)',
      ][$etatSuivi];
      $encreSuivi = $etatSuivi === FOLLOW_PENDING ? 'var(--gris-fonce)' : 'var(--blanc)';
    ?>
    <button class="btn btn-primary btn-full btn-follow-user"
            data-user-id="<?= $u['id'] ?>"
            data-etat="<?= $etatSuivi ?>"
            style="font-size:var(--fs-4); padding:16px; background:<?= $fondSuivi ?>; color:<?= $encreSuivi ?>;<?= $etatSuivi===FOLLOW_PENDING?' border:1px solid var(--line-2);':'' ?>">
      <?= $libelleSuivi ?>
    </button>
    <div class="mb-24" style="margin-top:10px; display:flex; gap:16px; justify-content:center;">
      <button class="btn-report-user" data-user-id="<?= $u['id'] ?>"
              style="background:none;border:none;color:var(--gris);font-size:var(--fs-2);font-weight:var(--fw-semibold);cursor:pointer;padding:8px;text-decoration:underline;">
        Signaler
      </button>
      <button class="btn-block-user" data-user-id="<?= $u['id'] ?>" data-bloque="<?= $jeSuisBloquant?'1':'0' ?>"
              style="background:none;border:none;color:var(--gris);font-size:var(--fs-2);font-weight:var(--fw-semibold);cursor:pointer;padding:8px;text-decoration:underline;">
        <?= $jeSuisBloquant ? 'Débloquer' : 'Bloquer' ?>
      </button>
    </div>

    <!-- Mutual Section -->
    <?php if (!empty($commonSquads)): ?>
    <div class="section-card" style="background:var(--lime-clair); border-color:var(--noir);">
      <div style="font-weight:var(--fw-bold); font-size:var(--fs-3); text-transform:uppercase; margin-bottom:12px; display:flex; align-items:center; gap:8px;">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path></svg>
        En commun
      </div>
      <div style="font-size:var(--fs-3); font-weight:var(--fw-semibold);">
        Vous faites partie de **<?= count($commonSquads) ?> Squads** ensemble. 
      </div>
      <div style="margin-top:10px; display:flex; flex-wrap:wrap; gap:6px;">
        <?php foreach ($commonSquads as $cs): ?>
          <span style="font-size:var(--fs-1); background:var(--blanc); padding:2px 8px; border:1px solid var(--gris-clair); font-weight:var(--fw-bold);"><?= mb_strtoupper($cs['type']) ?></span>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Interests -->
    <div class="section-card">
      <div style="font-weight:var(--fw-bold); font-size:var(--fs-3); text-transform:uppercase; margin-bottom:16px;">Ses Passions</div>
      <?php if (empty($interests)): ?>
        <div style="font-size:var(--fs-3); color:var(--gris);">Cet étudiant n'a pas encore ajouté d'intérêts.</div>
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
      <div style="font-weight:var(--fw-bold); font-size:var(--fs-3); text-transform:uppercase; margin-bottom:16px;">Ses prochaines sorties</div>
      <?php if (!$peutVoirActivite): ?>
        <div style="display:flex; align-items:flex-start; gap:12px;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="var(--gris)" stroke-width="2" stroke-linecap="round" style="flex-shrink:0; margin-top:2px;"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
          <div>
            <div style="font-size:var(--fs-3); font-weight:var(--fw-semibold);">Visible après acceptation</div>
            <div style="font-size:var(--fs-2); color:var(--gris-fonce); margin-top:4px; line-height:var(--lh-snug);">
              <?= htmlspecialchars($u['prenom']) ?> décide qui voit ses sorties.
              <?= $etatSuivi === FOLLOW_PENDING ? 'Ta demande est en attente de réponse.' : 'Demande à suivre pour y accéder.' ?>
            </div>
          </div>
        </div>
      <?php elseif (empty($upcomingEvents)): ?>
        <div style="font-size:var(--fs-3); color:var(--gris);">Aucune sortie prévue pour le moment.</div>
      <?php else: ?>
        <?php foreach ($upcomingEvents as $ev): ?>
          <div class="event-strip">
            <div style="width:10px; height:10px; border-radius:50%; background:var(--rouge); border:1px solid var(--gris-clair);"></div>
            <div style="flex:1;">
              <div style="font-weight:var(--fw-bold); font-size:var(--fs-3);"><?= htmlspecialchars($ev['titre']) ?></div>
              <div style="font-size:var(--fs-1); color:var(--gris);"><?= htmlspecialchars($ev['etablissement_nom']) ?> · <?= dateFr($ev['date_heure'], 'j M') ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>

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

<!-- Feuille de signalement : remplace le prompt() du navigateur, qui était
     fonctionnel mais hors charte et intraduisible. -->
<div class="modal-overlay" id="modal-signalement">
  <div class="modal-sheet" role="dialog" aria-modal="true" aria-labelledby="titre-signalement">
    <div class="modal-handle"></div>
    <div id="titre-signalement" class="t-section" style="margin-bottom:6px;">Signaler ce profil</div>
    <p class="t-caption" style="margin-bottom:20px;">
      Ton signalement est envoyé à l'équipe de modération. Il reste anonyme pour
      <?= htmlspecialchars($u['prenom']) ?>.
    </p>

    <form id="form-signalement" data-user-id="<?= (int)$u['id'] ?>">
      <div class="form-group">
        <label for="motif">Motif</label>
        <select id="motif" name="motif" required>
          <option value="harcelement">Harcèlement ou intimidation</option>
          <option value="contenu_inapproprie">Contenu inapproprié</option>
          <option value="usurpation">Usurpation d'identité</option>
          <option value="spam">Spam ou publicité</option>
          <option value="autre">Autre</option>
        </select>
      </div>
      <div class="form-group">
        <label for="details">Précisions <span style="text-transform:none;font-weight:var(--fw-regular);">(facultatif)</span></label>
        <textarea id="details" name="details" rows="3" maxlength="500"
                  placeholder="Ce qui s'est passé, si tu veux le préciser."></textarea>
      </div>
      <button type="submit" class="btn btn-rouge btn-full">Envoyer le signalement</button>
      <button type="button" class="btn btn-outline btn-full mt-12" data-modal-close>Annuler</button>
    </form>
  </main>
</div>

<script src="<?= asset('/assets/js/app.js') ?>"></script>
</body>
</html>
