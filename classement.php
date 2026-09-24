<?php
/**
 * Classement XP (maquette 14).
 *
 * Trois périmètres : les gens que je suis (et moi), mon école, toute la ville.
 * L'XP est celui de la page « Moi » — même formule, calculée en SQL pour tous
 * les étudiants d'un coup (classementXp(), includes/gamification.php).
 * Lecture seule : la page n'écrit rien en base.
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/page.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/uploads.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/gamification.php';
require_once __DIR__ . '/includes/social.php';
requireStudent();
$user = currentUser();
$uid  = (int) $user['id'];

$portees = ['amis' => 'Amis', 'ecole' => 'Mon école', 'ville' => 'Clermont'];
$portee  = array_key_exists($_GET['portee'] ?? '', $portees) ? $_GET['portee'] : 'amis';

// L'école vient de la base, pas de la session : elle a pu changer depuis la
// connexion, depuis la page « Moi ».
$stmt = $pdo->prepare("SELECT ecole FROM users WHERE id = ?");
$stmt->execute([$uid]);
$monEcole = trim((string) $stmt->fetchColumn());

$criteres = ['exclus' => blockedIds($pdo, $uid)];
if ($portee === 'amis') {
    $stmt = $pdo->prepare("SELECT followed_id FROM follows_users WHERE follower_id = ? AND statut = 'accepted'");
    $stmt->execute([$uid]);
    $criteres['ids'] = array_merge([$uid], array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN)));
} elseif ($portee === 'ecole' && $monEcole !== '') {
    $criteres['ecole'] = $monEcole;
}

$monXp     = getXp(getUserStats($pdo, $uid));
$sansEcole = $portee === 'ecole' && $monEcole === '';

// Une seule agrégation par minute pour toute l'application (classementGlobal),
// le reste se dérive en mémoire. Mon XP, lui, est toujours celui du moment.
$resultat = $sansEcole
    ? ['lignes' => [], 'moi' => null, 'devant' => null]
    : classementPourEtudiant(classementGlobal($pdo), $criteres,
        ['id' => $uid, 'prenom' => $user['prenom'], 'nom' => $user['nom'], 'ecole' => $monEcole], $monXp, 50);
$lignes = $resultat['lignes'];
$moi    = $resultat['moi'];
$devant = $resultat['devant'];

$podium = array_slice($lignes, 0, 3);
$suite  = array_slice($lignes, 3);

$couleursPodium = ['var(--bleu)', 'var(--orange)', 'var(--rouge)'];
$court    = static fn(array $l): string => $l['prenom'] . ' ' . mb_substr((string) $l['nom'], 0, 1) . '.';
?>
<?php ob_start(); ?>
<style>
  .classement-entete { display: flex; align-items: center; gap: 14px; margin-bottom: 22px; }

  /* Podium : 2 · 1 · 3, la première marche en volt. */
  .podium {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;
    align-items: end; margin: 8px 0 22px;
  }
  .podium__place { display: flex; flex-direction: column; align-items: center; gap: 8px; min-width: 0; text-decoration: none; color: inherit; }
  .podium__prenom {
    font-weight: var(--fw-bold); font-size: var(--fs-3); max-width: 100%;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }
  .podium__marche {
    width: 100%; border-radius: var(--radius-md); background: var(--blanc);
    border: 1px solid var(--gris-clair); color: var(--noir);
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px;
    padding: 12px 6px;
  }
  .podium__rang {
    font-family: var(--font-display); font-weight: var(--fw-black); font-size: var(--fs-8);
    line-height: var(--lh-display); letter-spacing: var(--ls-display);
  }
  .podium__xp { font-family: var(--font-mono); font-size: var(--fs-1); color: var(--gris); white-space: nowrap; }
  .podium__place.est-premier .podium__marche { background: var(--lime); border-color: var(--lime); color: var(--sur-lave); min-height: 118px; }
  .podium__place.est-premier .podium__xp { color: rgba(17,16,19,.72); }
  .podium__place.est-premier .podium__pastille { box-shadow: 0 0 0 3px var(--bg), 0 0 0 5px var(--lime); }
  .podium__place.est-deux .podium__marche { min-height: 92px; }
  .podium__place.est-trois .podium__marche { min-height: 76px; }
  .podium__pastille { border-radius: 50%; }

  .classement-liste { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
  .ligne-classement {
    display: flex; align-items: center; gap: 12px; padding: 12px 16px;
    background: var(--blanc); border: 1px solid var(--gris-clair); border-radius: var(--radius-md);
    text-decoration: none; color: inherit;
  }
  .ligne-classement__rang { font-family: var(--font-mono); font-size: var(--fs-3); color: var(--gris); min-width: 2ch; }
  .ligne-classement__nom { flex: 1; min-width: 0; font-weight: var(--fw-bold); font-size: var(--fs-4);
                           white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .ligne-classement__xp { font-family: var(--font-mono); font-size: var(--fs-3); color: var(--gris-fonce); white-space: nowrap; }
  /* Ma ligne : l'aplat lave de la maquette, texte basalte. */
  .ligne-classement.est-moi { background: var(--rouge); border-color: var(--rouge); color: var(--sur-lave); }
  .ligne-classement.est-moi .ligne-classement__rang,
  .ligne-classement.est-moi .ligne-classement__xp { color: var(--sur-lave); }
  .ligne-classement__separateur { text-align: center; color: var(--gris); font-family: var(--font-mono); font-size: var(--fs-2); padding: 2px 0; }
  .classement-message { margin: 18px 0 0; text-align: center; font-size: var(--fs-3); color: var(--gris); }
  .classement-vide { text-align: center; padding: 36px 18px; color: var(--gris-fonce); }
</style>
<?php pageDebut('Linkee — Classement', ['pwa' => true, 'tete' => ob_get_clean()]); ?>
<a href="#main-content" class="skip-nav">Aller au contenu principal</a>
<div class="app-shell">

  <div class="page-header">
    <div class="classement-entete">
      <a href="<?= baseUrl('/profil.php') ?>" class="bouton-retour" aria-label="Retour à mon profil"><?= icon('fleche-g') ?></a>
      <h1 class="titre-page">
        <div class="display" style="font-size:var(--fs-8);line-height:var(--lh-tight);">Classement</div>
      </h1>
    </div>

    <nav class="hub-toggle" style="margin-bottom:0;" aria-label="Périmètre du classement">
      <?php foreach ($portees as $code => $libelle): ?>
        <a href="?portee=<?= $code ?>" class="<?= $portee === $code ? 'active' : '' ?>"
           <?= $portee === $code ? 'aria-current="page"' : '' ?>><?= $libelle ?></a>
      <?php endforeach; ?>
    </nav>
  </div>

  <main id="main-content" class="page-content" style="padding-top:12px;">

    <?php if ($sansEcole): ?>
      <div class="card classement-vide">
        <p style="margin:0 0 14px;">Ton école n'est pas renseignée : impossible de te classer avec elle.</p>
        <a href="<?= baseUrl('/profil.php') ?>" class="btn btn-primary">Compléter mon profil</a>
      </div>

    <?php elseif (count($lignes) <= 1 && $portee === 'amis'): ?>
      <div class="card classement-vide">
        <p style="margin:0 0 14px;">Tu ne suis encore personne : ton classement entre amis est vide.</p>
        <a href="<?= baseUrl('/explore.php?view=people') ?>" class="btn btn-primary">Trouver des étudiants</a>
      </div>

    <?php else: ?>
      <?php if ($podium): ?>
        <div class="podium" aria-label="Podium">
          <?php foreach ([1, 0, 2] as $i):
            if (!isset($podium[$i])) { echo '<div></div>'; continue; }
            $l = $podium[$i];
            $classe = ['est-premier', 'est-deux', 'est-trois'][$i];
          ?>
            <a class="podium__place <?= $classe ?>" href="<?= $l['id'] === $uid ? baseUrl('/profil.php') : baseUrl('/view_profile.php?id=' . $l['id']) ?>">
              <span class="podium__pastille"><?= avatarHtml($l['photo'] ?? null, $l['prenom'], $i === 0 ? 64 : 52, $couleursPodium[$i]) ?></span>
              <span class="podium__prenom"><?= $l['id'] === $uid ? 'Toi' : htmlspecialchars($l['prenom']) ?></span>
              <span class="podium__marche">
                <span class="podium__rang"><?= (int) $l['rang'] ?></span>
                <span class="podium__xp"><?= number_format($l['xp'], 0, ',', ' ') ?> XP</span>
              </span>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <ol class="classement-liste">
        <?php foreach ($suite as $l): ?>
          <li>
            <a class="ligne-classement<?= $l['id'] === $uid ? ' est-moi' : '' ?>"
               href="<?= $l['id'] === $uid ? baseUrl('/profil.php') : baseUrl('/view_profile.php?id=' . $l['id']) ?>">
              <span class="ligne-classement__rang"><?= (int) $l['rang'] ?></span>
              <?= avatarHtml($l['photo'] ?? null, $l['prenom'], 34, $l['id'] === $uid ? '#F5F1E8' : 'var(--bleu)') ?>
              <span class="ligne-classement__nom"><?= $l['id'] === $uid ? 'Toi' : htmlspecialchars($court($l)) ?></span>
              <span class="ligne-classement__xp"><?= number_format($l['xp'], 0, ',', ' ') ?></span>
            </a>
          </li>
        <?php endforeach; ?>

        <?php if ($moi && !in_array($uid, array_column($lignes, 'id'), true)): ?>
          <li class="ligne-classement__separateur" aria-hidden="true">···</li>
          <li>
            <a class="ligne-classement est-moi" href="<?= baseUrl('/profil.php') ?>">
              <span class="ligne-classement__rang"><?= (int) $moi['rang'] ?></span>
              <?= avatarHtml(null, $moi['prenom'], 34, '#F5F1E8') ?>
              <span class="ligne-classement__nom">Toi</span>
              <span class="ligne-classement__xp"><?= number_format($moi['xp'], 0, ',', ' ') ?></span>
            </a>
          </li>
        <?php endif; ?>
      </ol>

      <?php if ($devant): ?>
        <p class="classement-message">
          Plus que <?= number_format($devant['xp'] - $monXp, 0, ',', ' ') ?> XP pour dépasser <?= htmlspecialchars($devant['prenom']) ?>.
        </p>
      <?php elseif ($moi): ?>
        <p class="classement-message">Tu es en tête. Garde la cadence.</p>
      <?php endif; ?>
    <?php endif; ?>

  </main>
</div>

<nav class="bottom-nav" aria-label="Navigation principale">
  <span class="nav-marque" aria-hidden="true"><?= marqueLinkee() ?></span>
  <a href="<?= baseUrl('/explore.php') ?>" class="nav-item">
    <span class="nav-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></span>
    <span>Explorer</span>
  </a>
  <a href="<?= baseUrl('/squads.php') ?>" class="nav-item">
    <span class="nav-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg></span>
    <span>Squads</span>
  </a>
  <a href="<?= baseUrl('/wallet.php') ?>" class="nav-item">
    <span class="nav-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg></span>
    <span>Pass</span>
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
