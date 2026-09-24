<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/page.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/uploads.php';
require_once __DIR__ . '/includes/icons.php';
require_once __DIR__ . '/includes/gamification.php';
require_once __DIR__ . '/includes/interets.php';
require_once __DIR__ . '/includes/agregats.php';
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

$initials = mb_strtoupper(mb_substr($u['prenom'], 0, 1) . mb_substr($u['nom'], 0, 1));

$stmt = $pdo->prepare("SELECT COUNT(*) FROM follows_users WHERE follower_id=? AND statut='accepted'");
$stmt->execute([$uid]);
$nbFollowing = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM follows_users WHERE followed_id=? AND statut='accepted'");
$stmt->execute([$uid]);
$nbFollowers = $stmt->fetchColumn();

// Demandes d'abonnement, invitations et activité des amis ne sont plus
// chargées ici : elles sont derrière la cloche du hub (includes/notifications.php).
// Le profil sert à régler son compte, pas à découvrir ce qui vient d'arriver.

// filtrerInterets() plutôt que interetsDepuisTexte() seul : la page affiche
// la même liste deux fois (les pastilles en haut, le sélecteur plus bas), et
// le sélecteur, lui, range toujours selon le catalogue. Sans cette remise en
// ordre, les deux listes sortaient dans un ordre différent sur un même écran.
$userInterests = filtrerInterets(interetsDepuisTexte($u['interests'] ?? null));

$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profil'])) {
    csrfVerify();
    $ecole = trim($_POST['ecole'] ?? '');
    $promo = trim($_POST['promo'] ?? '');
    $interests = interetsVersTexte(filtrerInterets($_POST['interests'] ?? []));
    
    // Photo de profil : on ne remplace qu'en cas de succès, et on efface
    // l'ancien fichier pour ne pas laisser de dépôt orphelin sur le disque.
    $photoErr = '';
    if (!empty($_POST['supprimer_photo']) && !empty($u['photo'])) {
        deleteStoredImage($u['photo'], avatarDir());
        $pdo->prepare("UPDATE users SET photo=NULL WHERE id=?")->execute([$uid]);
        $u['photo'] = null;
    } elseif (isset($_FILES['photo']) && ($_FILES['photo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $res = storeUploadedImage($_FILES['photo'], avatarDir(), 400);
        if ($res['ok']) {
            $ancienne = $u['photo'] ?? null;
            $pdo->prepare("UPDATE users SET photo=? WHERE id=?")->execute([$res['filename'], $uid]);
            if ($ancienne) {
                deleteStoredImage($ancienne, avatarDir());
            }
            $u['photo'] = $res['filename'];
        } else {
            $photoErr = $res['error'];
        }
    }

    $pdo->prepare("UPDATE users SET ecole=?, promo=?, interests=? WHERE id=?")->execute([$ecole, $promo, $interests, $uid]);
    synchroniserInterets($pdo, (int) $uid, interetsDepuisTexte($interests));

    // La liste des ecoles du filtre est mise en cache : changer d'ecole doit
    // pouvoir en faire apparaitre une nouvelle dans le menu deroulant.
    oublierEcolesRepresentees();
    
    $_SESSION['user_ecole'] = $ecole;
    $u['ecole'] = $ecole;
    $u['promo'] = $promo;
    $u['interests'] = $interests;
    $userInterests = filtrerInterets(interetsDepuisTexte($interests));
    $success = $photoErr !== '' ? '' : 'Profil mis à jour.';
}
?>
<?php ob_start(); ?>
<style>
  .profile-card {
    background: var(--blanc);
    border-radius: var(--radius);
    border: 1px solid var(--gris-clair);
    box-shadow: var(--shadow);
    padding: 22px;
    margin-bottom: 20px;
  }
  .section-title {
    font-family: var(--font-display);
    font-weight: var(--fw-black);
    font-size: var(--fs-6);
    letter-spacing: var(--ls-display);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  /* Tuiles chiffrées de la maquette « Moi » : étiquette mono colorée par
     univers (sorties en lave, squads en dôme, économies en volt). */
  .tuile-moi {
    background: var(--blanc); border: 1px solid var(--gris-clair); border-radius: var(--radius-md);
    padding: 14px 12px;
  }
  .tuile-moi__label {
    font-family: var(--font-mono); font-size: var(--fs-1); font-weight: var(--fw-medium);
    text-transform: uppercase; letter-spacing: var(--ls-label); margin-bottom: 6px;
  }
  .tuile-moi__valeur {
    font-family: var(--font-display); font-weight: var(--fw-black); font-size: var(--fs-7);
    letter-spacing: var(--ls-display); color: var(--noir); line-height: var(--lh-display);
  }
  .compteur-moi__label {
    font-family: var(--font-mono); font-size: var(--fs-1); font-weight: var(--fw-medium);
    text-transform: uppercase; letter-spacing: var(--ls-label); color: var(--gris);
  }
  /* Carte XP et badges (maquette 13). */
  .carte-xp__entete { display: flex; align-items: center; gap: 16px; }
  .carte-xp__niveau {
    width: 56px; height: 56px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    background: var(--lime); color: var(--sur-lave);
    font-family: var(--font-display); font-weight: var(--fw-black); font-size: var(--fs-7);
    letter-spacing: var(--ls-display);
  }
  .carte-xp__jauge-bloc { flex: 1; min-width: 0; }
  .carte-xp__ligne { display: flex; justify-content: space-between; align-items: baseline; gap: 8px; margin-bottom: 8px; }
  .carte-xp__compte { font-family: var(--font-mono); font-size: var(--fs-2); color: var(--sur-lime-clair); white-space: nowrap; }
  .carte-xp__jauge { height: 8px; border-radius: var(--radius-pill); background: var(--gris-clair); overflow: hidden; }
  .carte-xp__jauge > span { display: block; height: 100%; border-radius: inherit; background: var(--lime); }
  .carte-xp__resume { font-size: var(--fs-3); color: var(--gris); margin: 16px 0 18px; }
  .carte-xp__badges {
    list-style: none; margin: 0; padding: 0;
    display: grid; grid-template-columns: repeat(auto-fill, minmax(76px, 1fr)); gap: 16px 10px;
  }
  .badge-rond { display: flex; flex-direction: column; align-items: center; gap: 8px; text-align: center; }
  .badge-rond__pastille {
    width: 52px; height: 52px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center; color: var(--sur-lave);
  }
  .badge-rond__pastille .icon { width: 22px; height: 22px; }
  .badge-rond__nom { font-size: var(--fs-2); line-height: var(--lh-snug); color: var(--gris-fonce); }
  .badge-rond.est-verrouille .badge-rond__pastille {
    border: 2px dashed var(--line-2); color: var(--gris);
    font-family: var(--font-display); font-weight: var(--fw-black); font-size: var(--fs-5);
  }
  .badge-rond.est-verrouille .badge-rond__nom { color: var(--gris); }

  /* Le sélecteur de thème montre chaque thème dans ses propres couleurs. */
  .choix-theme {
    padding: 14px; border-radius: var(--radius-pill); font-weight: var(--fw-bold); font-size: var(--fs-4);
    cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px;
  }
</style>
<?php pageDebut('Linkee — Moi', ['pwa' => true, 'tete' => ob_get_clean()]); ?>
<a href="#main-content" class="skip-nav">Aller au contenu principal</a>
<div class="app-shell">

  <!-- Header -->
  <div class="page-header" style="margin-bottom:24px;">
    <!-- Identity block -->
    <div style="display:flex;align-items:center;gap:16px;margin-bottom:16px;">
      <?= avatarHtml($u['photo'] ?? null, $u['prenom'], 72, 'var(--rouge)') ?>
      <div>
        <h1 class="titre-page">
          <div class="display" style="font-size:var(--fs-8);line-height:var(--lh-tight);"><?= htmlspecialchars($u['prenom']) ?></div>
          <div class="display-italic" style="font-size:var(--fs-8);line-height:var(--lh-tight);color:var(--noir);"><?= htmlspecialchars($u['nom']) ?></div>
        </h1>
        <div style="font-size:var(--fs-3);color:var(--gris);margin-top:6px;"><?= htmlspecialchars($u['ecole'] ?? '—') ?> · <?= htmlspecialchars($u['promo'] ?? '—') ?></div>
      </div>
    </div>

    <!-- Centres d'intérêt : ils n'existaient que dans le formulaire plus bas.
         Sur sa propre page, on doit pouvoir lire ce qu'on a déclaré sans
         ouvrir un formulaire d'édition. -->
    <div style="margin-bottom:14px;">
      <?php if ($userInterests): ?>
        <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;">
          <?php foreach ($userInterests as $interet): ?>
            <a href="<?= baseUrl('/explore.php?view=people&interest=' . urlencode($interet)) ?>"
               class="interest-pill" style="text-decoration:none;"
               title="Voir les étudiants qui aiment <?= htmlspecialchars($interet) ?>">#<?= htmlspecialchars($interet) ?></a>
          <?php endforeach; ?>
          <a href="#champ-interets"
             style="font-size:var(--fs-3);font-weight:var(--fw-bold);color:var(--sur-rouge-clair);text-decoration:none;margin-left:4px;">Modifier</a>
        </div>
      <?php else: ?>
        <a href="#champ-interets"
           style="display:inline-block;font-size:var(--fs-2);font-weight:var(--fw-bold);color:var(--sur-rouge-clair);text-decoration:none;">
          + Ajoute tes centres d'intérêt
        </a>
      <?php endif; ?>
    </div>

    <!-- Followers row — les deux compteurs ouvrent la liste correspondante :
         un nombre sur lequel on ne peut pas cliquer ne dit pas qui il compte,
         et l'annuaire d'Explore ne montre plus les comptes déjà suivis. -->
    <div style="display:flex;gap:28px;padding:14px 0;border-top:1px solid var(--gris-clair);margin-bottom:10px;">
      <a href="<?= baseUrl('/abonnements.php?type=abonnes') ?>" style="text-align:center;text-decoration:none;color:inherit;">
        <div class="tuile-moi__valeur"><?= $nbFollowers ?></div>
        <div class="compteur-moi__label">Abonnés</div>
      </a>
      <a href="<?= baseUrl('/abonnements.php?type=abonnements') ?>" style="text-align:center;text-decoration:none;color:inherit;">
        <div class="tuile-moi__valeur"><?= $nbFollowing ?></div>
        <div class="compteur-moi__label">Abonnements</div>
      </a>
    </div>
  </div>

  <main id="main-content" class="page-content" style="padding-top:0;">
    
    <!-- Stats row (Sticky style top of content) -->
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:-20px;margin-bottom:24px;position:relative;z-index:2;">
      <div class="tuile-moi">
        <div class="tuile-moi__label" style="color:var(--sur-rouge-clair);">Sorties</div>
        <div class="tuile-moi__valeur"><?= $nbInscriptions ?></div>
      </div>
      <div class="tuile-moi">
        <div class="tuile-moi__label" style="color:var(--sur-bleu-clair);">Squads</div>
        <div class="tuile-moi__valeur"><?= $nbSquads ?></div>
      </div>
      <div class="tuile-moi">
        <div class="tuile-moi__label" style="color:var(--sur-lime-clair);">Économies</div>
        <div class="tuile-moi__valeur"><?= number_format($ecoTotal, 0, ',', '') ?>€</div>
      </div>
    </div>

    <?php if ($success): ?>
    <div class="encart-ok" role="status">
      <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <!-- SECTION 1: MON PROFIL -->
    <div class="profile-card">
      <div class="section-title">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
        Mon Profil
      </div>
      <?= csrfFlash() ?>
      <form method="POST" enctype="multipart/form-data">
        <?= csrfField() ?>

        <?php if (!empty($photoErr)): ?>
          <div class="form-error"><?= htmlspecialchars($photoErr) ?></div>
        <?php endif; ?>

        <div style="display:flex;align-items:center;gap:16px;margin-bottom:22px;">
          <div id="apercu-avatar" style="flex-shrink:0;">
            <?= avatarHtml($u['photo'] ?? null, $u['prenom'], 72, 'var(--rouge)') ?>
          </div>
          <div style="flex:1;min-width:0;">
            <div class="t-overline" style="margin-bottom:9px;">Photo de profil</div>

            <div class="champ-fichier">
              <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp">
              <label for="photo" class="btn btn-outline">
                <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                <?= !empty($u['photo']) ? 'Changer la photo' : 'Choisir une photo' ?>
              </label>
              <span class="nom-fichier vide" id="nom-photo">Aucune image choisie</span>
            </div>

            <div style="font-size:var(--fs-2);color:var(--gris);margin-top:8px;">JPG, PNG ou WebP — 2 Mo maximum.</div>

            <?php if (!empty($u['photo'])): ?>
              <label class="case" style="margin-top:4px;">
                <input type="checkbox" name="supprimer_photo" value="1"> Retirer ma photo
              </label>
            <?php endif; ?>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
          <div class="form-group" style="margin-bottom:0;">
            <label for="champ-ecole">École</label>
            <select id="champ-ecole" name="ecole">
              <?php foreach (['UCA','SIGMA Clermont','INP Ingénieurs','IFSI','Autre'] as $e): ?>
                <option value="<?= $e ?>" <?= $u['ecole'] === $e ? 'selected' : '' ?>><?= $e ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group" style="margin-bottom:0;">
            <label for="champ-promo">Promo</label>
            <select id="champ-promo" name="promo">
              <?php foreach (['L1','L2','L3','M1','M2','BUT1','BUT2','BUT3'] as $p): ?>
                <option value="<?= $p ?>" <?= $u['promo'] === $p ? 'selected' : '' ?>><?= $p ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div style="margin-bottom:20px;" id="champ-interets">
          <label class="t-overline" style="display:block;margin-bottom:12px;" id="label-interets">Centres d'intérêt</label>
          <?= selecteurInteretsHtml($userInterests) ?>
        </div>
        
        <button type="submit" name="save_profil" class="btn btn-primary btn-full">Enregistrer les modifications</button>
      </form>
    </div>

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
    <?php
      // Maquette 13 : niveau, jauge d'XP en volt, badges en pastilles de
      // couleur ; les badges restant à débloquer sont en pointillés.
      $obtenus = array_values(array_filter(
          $allBadges,
          static fn(array $b): bool => in_array($b['code'], $unlockedCodes, true)
      ));
      $aDebloquer = array_values(array_filter(
          $allBadges,
          static fn(array $b): bool => !in_array($b['code'], $unlockedCodes, true)
      ));
    ?>
    <div class="profile-card carte-xp">
      <div class="carte-xp__entete">
        <span class="carte-xp__niveau" aria-hidden="true"><?= (int) $level ?></span>
        <div class="carte-xp__jauge-bloc">
          <div class="carte-xp__ligne">
            <span class="t-overline" style="color:var(--gris-fonce);">Niveau <?= (int) $level ?></span>
            <span class="carte-xp__compte"><?= number_format($xp, 0, ',', ' ') ?> / <?= number_format($xpNextLevel, 0, ',', ' ') ?> XP</span>
          </div>
          <div class="carte-xp__jauge" role="progressbar" aria-label="Progression vers le niveau <?= (int) $level + 1 ?>"
               aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= (int) round($xpProgress) ?>">
            <span style="width:<?= round($xpProgress, 1) ?>%;"></span>
          </div>
        </div>
      </div>

      <div class="carte-xp__resume">
        <?= (int) $gamStats['events'] ?> sorties · <?= (int) $gamStats['squads'] ?> squads ·
        <?= (int) $gamStats['follows'] ?> abonnements · <?= (int) $gamStats['avis'] ?> avis
      </div>

      <?php if ($allBadges): ?>
        <ul class="carte-xp__badges">
          <?php foreach ($obtenus as $b): ?>
            <li class="badge-rond">
              <span class="badge-rond__pastille" style="background:<?= htmlspecialchars($b['couleur'] ?? 'var(--lime)') ?>;">
                <?= icon($b['icon'] ?? 'trophee') ?>
              </span>
              <span class="badge-rond__nom"><?= htmlspecialchars($b['nom']) ?></span>
            </li>
          <?php endforeach; ?>
          <?php foreach ($aDebloquer as $b): ?>
            <li class="badge-rond est-verrouille" title="<?= htmlspecialchars($b['description'] ?? '') ?>">
              <span class="badge-rond__pastille" aria-hidden="true">?</span>
              <span class="badge-rond__nom"><?= htmlspecialchars($b['nom']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <a href="<?= baseUrl('/classement.php') ?>" class="btn btn-outline btn-full" style="margin-top:18px;">
        <?= icon('trophee', 'icon-sm') ?> Voir le classement
      </a>
    </div>

    <!-- SECTION APPARENCE -->
    <div class="profile-card">
      <div class="section-title">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:8px;"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
        Apparence
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
        <button type="button" onclick="window.setTheme('light')" id="theme-light-btn" class="choix-theme"
                style="background:#F5F1E8;color:#111013;border:1px solid #DCD5C7;">
          <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg> Clair
        </button>
        <button type="button" onclick="window.setTheme('dark')" id="theme-dark-btn" class="choix-theme"
                style="background:#111013;color:#F5F1E8;border:1px solid #36323B;">
          <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg> Sombre
        </button>
      </div>
    </div>

    <!-- SECTION LÉGAL -->
    <div class="profile-card" style="padding:0;overflow:hidden;">
      <a href="<?= baseUrl('/mentions-legales.php') ?>" style="display:flex;justify-content:space-between;align-items:center;padding:14px 20px;border-bottom: 1px solid var(--gris-clair);text-decoration:none;color:var(--noir);font-size:var(--fs-4);font-weight:var(--fw-semibold);">
        Mentions légales <span style="color:var(--gris);">→</span>
      </a>
      <a href="<?= baseUrl('/cgu.php') ?>" style="display:flex;justify-content:space-between;align-items:center;padding:14px 20px;border-bottom: 1px solid var(--gris-clair);text-decoration:none;color:var(--noir);font-size:var(--fs-4);font-weight:var(--fw-semibold);">
        CGU <span style="color:var(--gris);">→</span>
      </a>
      <a href="<?= baseUrl('/confidentialite.php') ?>" style="display:flex;justify-content:space-between;align-items:center;padding:14px 20px;text-decoration:none;color:var(--noir);font-size:var(--fs-4);font-weight:var(--fw-semibold);">
        Politique de confidentialité <span style="color:var(--gris);">→</span>
      </a>
    </div>

    <!-- SECTION 4: COMPTE -->
    <div class="profile-card" style="padding:0;overflow:hidden;">
      <div style="padding:16px 20px;border-bottom:1px solid var(--gris-clair);background:var(--blanc);display:flex;justify-content:space-between;align-items:center;">
        <span class="t-overline">Email</span>
        <span style="font-size:var(--fs-2);font-weight:var(--fw-semibold);"><?= htmlspecialchars($u['email']) ?></span>
      </div>
      <div style="padding:16px 20px;border-bottom:1px solid var(--gris-clair);background:var(--blanc);display:flex;justify-content:space-between;align-items:center;">
        <span class="t-overline">Depuis</span>
        <span style="font-size:var(--fs-2);font-weight:var(--fw-semibold);"><?= dateFr($u['created_at'], 'M Y') ?></span>
      </div>
      <div style="padding:16px 20px;background:var(--blanc);">
        <a href="<?= baseUrl('/auth/logout.php') ?>" style="display:flex;align-items:center;justify-content:center;gap:8px;text-align:center;color:var(--sur-rouge-clair);font-weight:var(--fw-bold);text-decoration:none;font-size:var(--fs-4);">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
          Se déconnecter
        </a>
      </div>
    </div>

  </div>

</div><!-- .app-shell -->

<!-- Bottom Nav -->
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
