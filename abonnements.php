<?php
/**
 * Listes d'abonnés et d'abonnements.
 *
 * L'annuaire d'Explore ne montre plus les comptes déjà suivis : c'est ici
 * qu'on les retrouve, en cliquant sur l'un des deux compteurs du profil.
 * Les deux vues partagent la rangée compacte de l'annuaire (.ligne-personne)
 * et le sélecteur segmenté du hub — même composant, même comportement.
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/social.php';
require_once __DIR__ . '/includes/uploads.php';
require_once __DIR__ . '/includes/icons.php';
requireStudent();
$user = currentUser();
$uid  = (int) $user['id'];

// « abonnes » = ceux qui me suivent ; « abonnements » = ceux que je suis.
$vue = ($_GET['type'] ?? 'abonnements') === 'abonnes' ? 'abonnes' : 'abonnements';
$q   = trim($_GET['q'] ?? '');

// Les deux compteurs sont toujours calculés : ils étiquettent les deux
// onglets, et un onglet sans son nombre oblige à cliquer pour savoir s'il
// vaut la peine d'être ouvert.
$stmt = $pdo->prepare("SELECT COUNT(*) FROM follows_users WHERE followed_id = ? AND statut = 'accepted'");
$stmt->execute([$uid]);
$nbAbonnes = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM follows_users WHERE follower_id = ? AND statut = 'accepted'");
$stmt->execute([$uid]);
$nbAbonnements = (int) $stmt->fetchColumn();

// La colonne qui porte « l'autre » personne change d'un onglet à l'autre ;
// tout le reste de la requête est commun.
$colAutre = $vue === 'abonnes' ? 'f.follower_id' : 'f.followed_id';
$colMoi   = $vue === 'abonnes' ? 'f.followed_id' : 'f.follower_id';

// follow_statut : mon lien vers cette personne. Dans l'onglet « abonnés » il
// vaut souvent null — c'est ce qui permet de s'abonner en retour sans quitter
// la page.
$sql = "SELECT u.id, u.prenom, u.nom, u.ecole, u.promo, u.photo, f.created_at,
               (SELECT fm.statut FROM follows_users fm
                WHERE fm.follower_id = ? AND fm.followed_id = u.id) AS follow_statut
        FROM follows_users f
        JOIN users u ON u.id = $colAutre
        WHERE $colMoi = ? AND f.statut = 'accepted' AND u.type = 'etudiant'";
$params = [$uid, $uid];

// Un blocage, dans un sens ou dans l'autre, retire la personne de la liste.
[$sqlBlock, $paramsBlock] = blockedFilterSql($pdo, $uid, 'u.id');
$sql   .= $sqlBlock;
$params = array_merge($params, $paramsBlock);

if ($q) {
    $sql .= " AND (u.nom LIKE ? OR u.prenom LIKE ? OR u.ecole LIKE ?)";
    $terme = '%' . addcslashes($q, '%_') . '%';
    $params[] = $terme; $params[] = $terme; $params[] = $terme;
}

// Les plus récents d'abord : sur une liste qui grossit, c'est la fin qui
// bouge, et c'est elle qu'on vient revoir.
$parPage = 30;
$page    = max(1, (int) ($_GET['p'] ?? 1));
$limite  = $parPage * $page;
$sql    .= " ORDER BY f.created_at DESC, u.prenom ASC LIMIT " . ($limite + 1);

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$personnes = $stmt->fetchAll();

// Une ligne de plus que demandé : sa présence dit qu'il en reste, sans payer
// un COUNT(*) sur toute la table à chaque affichage.
$encore = count($personnes) > $limite;
if ($encore) {
    array_pop($personnes);
}

$totalVue = $vue === 'abonnes' ? $nbAbonnes : $nbAbonnements;
$titre    = $vue === 'abonnes' ? 'Abonnés' : 'Abonnements';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StudentLink — <?= $titre ?></title>
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

  <div class="page-header" style="padding-bottom:10px;">
    <a href="<?= baseUrl('/profil.php') ?>"
       style="display:inline-flex;align-items:center;gap:6px;margin-bottom:14px;font-size:var(--fs-1);font-weight:var(--fw-bold);text-transform:uppercase;letter-spacing:var(--ls-wide);color:var(--gris-fonce);text-decoration:none;">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"></polyline></svg>
      Mon profil
    </a>

    <div class="hub-toggle">
      <a href="?type=abonnements" class="<?= $vue === 'abonnements' ? 'active' : '' ?>">Abonnements · <?= $nbAbonnements ?></a>
      <a href="?type=abonnes" class="<?= $vue === 'abonnes' ? 'active' : '' ?>">Abonnés · <?= $nbAbonnes ?></a>
    </div>

    <h1 class="titre-page">
      <?php if ($vue === 'abonnes'): ?>
        <div class="display" style="font-size:var(--fs-9);line-height:var(--lh-tight);">Ceux qui</div>
        <div class="display-italic" style="font-size:var(--fs-9);line-height:var(--lh-tight);">te suivent.</div>
      <?php else: ?>
        <div class="display" style="font-size:var(--fs-9);line-height:var(--lh-tight);">Ceux que</div>
        <div class="display-italic" style="font-size:var(--fs-9);line-height:var(--lh-tight);">tu suis.</div>
      <?php endif; ?>
    </h1>
  </div>

  <main id="main-content" class="page-content page-grid" style="padding-top:20px;">

    <!-- Recherche : au-delà de quelques dizaines de noms, faire défiler
         n'est plus une façon de retrouver quelqu'un. -->
    <form method="GET">
      <input type="hidden" name="type" value="<?= $vue ?>">
      <div style="position:relative;margin-bottom:16px;">
        <input type="text" name="q" value="<?= htmlspecialchars($q) ?>"
               placeholder="Chercher un nom ou une école..."
               aria-label="Chercher dans <?= $titre ?>"
               style="width:100%;padding:14px 44px 14px 16px;border:1px solid var(--gris-clair);box-shadow:var(--shadow);font-size:var(--fs-4);outline:none;background:var(--blanc);">
        <button type="submit" aria-label="Chercher"
                style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--noir);cursor:pointer;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </button>
      </div>
      <?php if ($q): ?>
        <div style="margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;">
          <span style="font-size:var(--fs-2);font-weight:var(--fw-bold);color:var(--gris);"><?= count($personnes) ?><?= $encore ? '+' : '' ?> résultat<?= count($personnes) > 1 ? 's' : '' ?></span>
          <a href="?type=<?= $vue ?>" style="font-size:var(--fs-1);font-weight:var(--fw-bold);color:var(--sur-rouge-clair);text-transform:uppercase;letter-spacing:var(--ls-wide);text-decoration:none;">Réinitialiser</a>
        </div>
      <?php endif; ?>
    </form>

    <div class="list-grid liste-personnes">
      <?php foreach ($personnes as $p): ?>
        <?php
          $fs      = $p['follow_statut'] ?? null;
          $libelle = $fs === 'accepted' ? icon('check', 'icon-sm') . ' Suivi' : ($fs === 'pending' ? 'En attente' : '+ Suivre');
          $fond    = $fs === 'accepted' ? 'var(--noir)' : ($fs === 'pending' ? 'var(--surface-2)' : 'transparent');
          $encre   = $fs === 'accepted' ? 'var(--blanc)' : ($fs === 'pending' ? 'var(--gris-fonce)' : 'var(--noir)');
        ?>
        <div class="ligne-personne">
          <a class="ligne-personne__lien" href="<?= baseUrl('/view_profile.php?id=' . (int)$p['id']) ?>">
            <?= avatarHtml($p['photo'] ?? null, $p['prenom'], 40) ?>
            <span class="ligne-personne__infos">
              <span class="ligne-personne__nom"><?= htmlspecialchars($p['prenom'] . ' ' . mb_substr((string)$p['nom'], 0, 1) . '.') ?></span>
              <span class="ligne-personne__meta"><?= htmlspecialchars((string)$p['ecole']) ?><?= $p['promo'] ? ' · ' . htmlspecialchars((string)$p['promo']) : '' ?></span>
            </span>
          </a>
          <button class="btn-follow-user ligne-personne__suivre" data-user-id="<?= (int)$p['id'] ?>" data-etat="<?= $fs ?: 'none' ?>"
                  style="background:<?= $fond ?>;color:<?= $encre ?>;">
            <?= $libelle ?>
          </button>
        </div>
      <?php endforeach; ?>

      <?php if (!$personnes): ?>
        <p class="liste-personnes__vide">
          <?php if ($q): ?>
            Personne à ce nom dans tes <?= mb_strtolower($titre) ?>.
          <?php elseif ($vue === 'abonnes'): ?>
            Personne ne te suit encore.<br>
            <a href="<?= baseUrl('/explore.php?view=people') ?>" style="color:var(--sur-rouge-clair);font-weight:var(--fw-bold);">Va te faire connaître →</a>
          <?php else: ?>
            Tu ne suis personne pour l'instant.<br>
            <a href="<?= baseUrl('/explore.php?view=people') ?>" style="color:var(--sur-rouge-clair);font-weight:var(--fw-bold);">Trouve des étudiants à suivre →</a>
          <?php endif; ?>
        </p>
      <?php endif; ?>
    </div>

    <?php if ($encore): ?>
      <?php $suite = $_GET; $suite['type'] = $vue; $suite['p'] = $page + 1; ?>
      <a href="?<?= htmlspecialchars(http_build_query($suite), ENT_QUOTES) ?>"
         class="btn btn-outline btn-full" style="margin-top:4px;">
        Voir plus<?= $q ? '' : ' (' . ($totalVue - count($personnes)) . ' restants)' ?>
      </a>
    <?php endif; ?>

  </main>
</div>

<nav class="bottom-nav" aria-label="Navigation principale">
  <a href="<?= baseUrl('/explore.php') ?>" class="nav-item">
    <span class="nav-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></span>
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
  <a href="<?= baseUrl('/profil.php') ?>" class="nav-item active" aria-current="page">
    <span class="nav-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></span>
    <span>Moi</span>
  </a>
</nav>

<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script src="<?= asset('/assets/js/app.js') ?>"></script>
</body>
</html>
