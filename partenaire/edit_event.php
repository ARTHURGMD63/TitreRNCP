<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/crm.php';
require_once __DIR__ . '/../includes/sponsoring.php';
require_once __DIR__ . '/../includes/capacites.php';
require_once __DIR__ . '/../includes/musique.php';
requirePartner();
$user = currentUser();
$uid  = $user['id'];

$stmt = $pdo->prepare("SELECT * FROM etablissements WHERE user_id=? LIMIT 1");
$stmt->execute([$uid]);
$etab = $stmt->fetch();

if (!$etab) {
    header('Location: ' . baseUrl('/partenaire/dashboard.php'));
    exit;
}

exigerAbonnement($pdo, $etab);

// Ce que la formule ouvre. Sans cette lecture, un Essentiel à 79 € et un
// Premium à 149 € avaient exactement les mêmes droits : la grille tarifaire
// était une promesse commerciale sans effet dans le produit.
$capacites = capacitesEtablissement($pdo, (int) $etab['id']);

$eid = (int)($_GET['id'] ?? 0);
if (!$eid) {
    header('Location: ' . baseUrl('/partenaire/evenements.php'));
    exit;
}

// Load event — must belong to this partner
$stmt = $pdo->prepare("SELECT * FROM evenements WHERE id=? AND etablissement_id=?");
$stmt->execute([$eid, $etab['id']]);
$event = $stmt->fetch();

if (!$event) {
    header('Location: ' . baseUrl('/partenaire/evenements.php'));
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $titre        = trim($_POST['titre'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $type         = $_POST['type'] ?? '';
    $musique      = trim($_POST['style_musique'] ?? '');
    $date_heure   = trim($_POST['date_heure'] ?? '');
    $quota        = (int)($_POST['quota'] ?? 100);
    $reduction    = (int)($_POST['reduction'] ?? 0);
    $prix_normal  = (float)($_POST['prix_normal'] ?? 0);
    $is_flash     = isset($_POST['is_flash']) ? 1 : 0;
    $flash_expiry = $is_flash ? trim($_POST['flash_expiry'] ?? '') : null;
    $is_gratuit   = isset($_POST['is_gratuit']) ? 1 : 0;
    $lieu         = trim($_POST['lieu'] ?? '');

    $is_sponsorise  = isset($_POST['is_sponsorise']) ? 1 : 0;
    $sponsorFormule = $is_sponsorise ? ($_POST['sponsor_formule'] ?? '') : null;

    if (!$titre)      $errors[] = 'Le titre est obligatoire.';
    if (!in_array($type, ['bar','boite','resto','afterwork'])) $errors[] = 'Type invalide.';
    // Facultatif, mais jamais libre : le code vient du catalogue.
    if ($musique !== '' && !styleMusiqueValide($musique)) $errors[] = 'Style de musique invalide.';
    if (!$date_heure) $errors[] = 'La date est obligatoire.';
    if ($quota < 1)   $errors[] = 'Le quota doit être au moins 1.';
    if ($is_flash && !$flash_expiry) $errors[] = 'Date d\'expiration flash requise.';
    if ($is_sponsorise && !formuleSponsoringValide($sponsorFormule)) {
        $errors[] = 'Choisis une formule de sponsoring.';
    }
    // Le quota se revérifie ici et pas seulement à l'affichage : la case est
    // dans le formulaire, elle se recoche à la console. L'événement en cours
    // d'édition ne se compte pas lui-même.
    if ($is_flash && !flashDisponible($pdo, (int) $etab['id'], $capacites, $eid)) {
        $errors[] = "Ton quota d'offres flash du mois est atteint ("
                  . (int) $capacites['flash_par_mois'] . ' par mois). Le Premium les rend illimitées.';
    }

    if (empty($errors)) {
        // La fenêtre de mise en avant n'est recalculée que si la formule change
        // réellement. Sans cette garde, corriger une faute de frappe dans le titre
        // redémarrait le compteur : sept jours de mise en avant offerts à chaque
        // enregistrement.
        $formuleAvant = $event['sponsor_formule'] ?? null;
        $etaitSponso  = !empty($event['is_sponsorise']);

        if (!$is_sponsorise) {
            $sponsorTarif = 0;
            $sponsorFin   = null;
        } elseif ($etaitSponso && $formuleAvant === $sponsorFormule) {
            $sponsorTarif = (float) ($event['sponsor_tarif'] ?? 0);
            $sponsorFin   = $event['sponsor_jusqu_au'] ?? null;
        } else {
            // Nouvelle mise en avant, ou changement de formule : c'est ici que
            // la mise en avant offerte du Premium (SL-03, une par mois) peut
            // s'appliquer. Elle s'écrit à 0 € plutôt que de ne rien écrire,
            // sinon rien ne dit combien ont été consommées.
            $sponsorTarif = misesEnAvantOffertesRestantes($pdo, (int) $etab['id'], $capacites) > 0
                ? 0.0
                : tarifSponsoring($sponsorFormule);
            $sponsorFin   = finSponsoring($sponsorFormule, $date_heure);
        }

        $stmt = $pdo->prepare("
            UPDATE evenements SET
                titre=?, description=?, type=?, style_musique=?, date_heure=?,
                quota=?, reduction=?, prix_normal=?,
                is_flash=?, flash_expiry=?, is_gratuit=?, lieu=?,
                is_sponsorise=?, sponsor_formule=?, sponsor_tarif=?, sponsor_jusqu_au=?
            WHERE id=? AND etablissement_id=?
        ");
        $stmt->execute([
            $titre, $description, $type, $musique ?: null, $date_heure,
            $quota, $reduction, $prix_normal,
            $is_flash, $flash_expiry ?: null, $is_gratuit, $lieu,
            $is_sponsorise, $sponsorFormule, $sponsorTarif, $sponsorFin,
            $eid, $etab['id']
        ]);
        header('Location: ' . baseUrl('/partenaire/evenements.php?updated=1'));
        exit;
    }

    // Re-populate $event from POST on error
    $event = array_merge($event, [
        'titre' => $titre, 'description' => $description, 'type' => $type,
        'style_musique' => $musique,
        'date_heure' => $date_heure, 'quota' => $quota, 'reduction' => $reduction,
        'prix_normal' => $prix_normal, 'is_flash' => $is_flash,
        'flash_expiry' => $flash_expiry, 'is_gratuit' => $is_gratuit, 'lieu' => $lieu,
        'is_sponsorise' => $is_sponsorise, 'sponsor_formule' => $sponsorFormule,
    ]);
}

// Format datetime-local value
$dtLocal = date('Y-m-d\TH:i', strtotime($event['date_heure']));
$flashLocal = $event['flash_expiry'] ? date('Y-m-d\TH:i', strtotime($event['flash_expiry'])) : '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StudentLink — Modifier l'événement</title>
<?= themeBootScript() ?>
<?= metaCsrf() ?>
<link rel="stylesheet" href="<?= asset('/assets/css/style.css') ?>">
<style>
  .form-card {
    background: var(--blanc);
    border-radius: var(--radius);
    border: 1px solid var(--gris-clair);
    box-shadow: var(--shadow-lg);
    padding: 32px;
    max-width: 640px;
  }
  .form-section-title {
    font-family: var(--font-display);
    font-weight: var(--fw-black);
    font-size: var(--fs-6);
    margin: 28px 0 14px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--gris-clair);
  }
  .form-section-title:first-child { margin-top: 0; }
  .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
  .form-group label { font-size: var(--fs-1); font-weight: var(--fw-display); text-transform: uppercase; letter-spacing: var(--ls-wide); }
  .form-group input,
  .form-group select,
  .form-group textarea {
    border: 1px solid var(--gris-clair);
    padding: 10px 14px;
    font-family: var(--font-sans);
    font-size: var(--fs-4);
    background: var(--blanc);
    outline: none;
    width: 100%;
    box-sizing: border-box;
  }
  .form-group textarea { resize: vertical; min-height: 80px; }
  .form-group input:focus,
  .form-group select:focus,
  .form-group textarea:focus { border-color: var(--rouge); }
  .toggle-row {
    display: flex;
    align-items: center;
    gap: 13px;
    padding: 14px 16px;
    border: 1px solid var(--gris-clair);
    border-radius: var(--radius);
    background: var(--blanc);
    margin-bottom: 12px;
    cursor: pointer;
    transition: border-color .15s ease;
  }
  .toggle-row:hover { border-color: var(--gris); }
  /* La case native est dessinee par le systeme : on reprend celle du
     systeme de design (.case), identique sur toutes les machines. */
  .toggle-row input[type=checkbox] {
    appearance: none; -webkit-appearance: none;
    width: 20px; height: 20px; margin: 0; flex-shrink: 0;
    border: 1.5px solid var(--line-2); border-radius: 6px;
    background: var(--blanc); cursor: pointer;
    display: grid; place-content: center;
    transition: background .15s ease, border-color .15s ease;
  }
  .toggle-row input[type=checkbox]::before {
    content: ''; width: 11px; height: 11px; transform: scale(0);
    transition: transform .12s ease-in-out;
    box-shadow: inset 1em 1em var(--sur-media);
    clip-path: polygon(14% 44%, 0 65%, 50% 100%, 100% 16%, 80% 0%, 43% 62%);
  }
  .toggle-row input[type=checkbox]:checked {
    background: var(--rouge-deep); border-color: var(--rouge-deep);
  }
  .toggle-row input[type=checkbox]:checked::before { transform: scale(1); }
  .toggle-row input[type=checkbox]:focus-visible { outline: 2px solid var(--bleu); outline-offset: 2px; }
  .toggle-row .toggle-label { font-weight: var(--fw-bold); font-size: var(--fs-4); }
  .toggle-row .toggle-desc { font-size: var(--fs-2); color: var(--gris); }
  .flash-extra, .sponsor-extra { display: none; margin-top: 12px; }
  .sponsor-grille { display: flex; flex-direction: column; gap: 10px; margin-top: 12px; }
  .sponsor-choix {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 14px 16px; border: 1px solid var(--line-2); border-radius: var(--radius);
    background: var(--blanc); cursor: pointer; transition: border-color .15s ease;
  }
  .sponsor-choix:hover { border-color: var(--gris); }
  .sponsor-choix:has(input:checked) { border-color: var(--rouge-deep); box-shadow: 0 0 0 1px var(--rouge-deep); }
  .sponsor-choix input[type=radio] { margin-top: 3px; flex-shrink: 0; accent-color: var(--rouge-deep); }
  .sponsor-choix .sc-nom { font-weight: var(--fw-bold); font-size: var(--fs-4); }
  .sponsor-choix .sc-desc { font-size: var(--fs-2); color: var(--gris); margin-top: 2px; }
  .sponsor-choix .sc-tarif {
    margin-left: auto; font-family: var(--font-display); font-weight: var(--fw-black);
    font-size: var(--fs-6); white-space: nowrap;
  }
  .sponsor-total {
    margin-top: 14px; padding: 12px 16px; border-radius: var(--radius-sm);
    background: var(--alerte-clair); color: var(--alerte);
    font-size: var(--fs-3); font-weight: var(--fw-semibold);
  }
  .form-errors {
    background: var(--danger-clair);
    color: var(--danger);
    border: 1px solid var(--danger);
    border-radius: var(--radius-sm);
    padding: 14px 18px;
    margin-bottom: 24px;
    font-size: var(--fs-4);
  }
  .form-errors li { margin: 4px 0; color: var(--rouge); font-weight: var(--fw-semibold); }
  .stats-bar {
    background: var(--gris-clair);
    border-radius: var(--radius);
    padding: 16px 20px;
    margin-bottom: 24px;
    display: flex;
    gap: 32px;
  }
  .stats-bar-item { text-align: center; }
  .stats-bar-value { font-size: var(--fs-7); font-weight: var(--fw-black); }
  .stats-bar-label { font-size: var(--fs-1); color: var(--gris); text-transform: uppercase; letter-spacing: var(--ls-wide); font-weight: var(--fw-bold); }
</style>
</head>
<body>
<div class="partner-shell">

  <aside class="partner-sidebar">
    <div class="sidebar-brand">
      <div style="font-family:var(--font-sans);font-weight:var(--fw-bold);font-size:var(--fs-5);color:#fff;">
        StudentLink <em style="font-style:italic;color:var(--rouge);">/ Partenaires</em>
      </div>
    </div>
    <nav class="sidebar-nav">
      <a href="<?= baseUrl('/partenaire/dashboard.php') ?>" class="sidebar-link">
        <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg> Dashboard
      </a>
      <a href="<?= baseUrl('/partenaire/evenements.php') ?>" class="sidebar-link active">
        <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> Événements
      </a>
      <a href="<?= baseUrl('/partenaire/create_event.php') ?>" class="sidebar-link">
        <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg> Créer un event
      </a>
      <a href="<?= baseUrl('/partenaire/photos.php') ?>" class="sidebar-link">
        <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg> Photos
      </a>
      <a href="<?= baseUrl('/partenaire/abonnement.php') ?>" class="sidebar-link">
        <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg> Abonnement
      </a>
    </nav>
    <div class="sidebar-venue" style="margin-top:48px;padding-top:20px;border-top:1px solid rgba(255,255,255,0.1);">
      <div class="sidebar-venue-name"><?= htmlspecialchars(mb_strtoupper($etab['nom'])) ?></div>
      <div class="sidebar-venue-city"><?= htmlspecialchars($etab['ville']) ?></div>
      <a href="<?= baseUrl('/auth/logout.php') ?>" class="lien-action" style="margin-top:12px;font-size:var(--fs-2);color:rgba(255,255,255,0.4);text-decoration:none;">
        → Déconnexion
      </a>
    </div>
  </aside>

  <main class="partner-main">
    <div style="margin-bottom:32px;">
      <div class="label text-gris" style="margin-bottom:6px;">
        <a href="<?= baseUrl('/partenaire/evenements.php') ?>" style="color:var(--gris);text-decoration:none;">← Retour aux événements</a>
      </div>
      <h1 class="titre-page" style="font-family:var(--font-display);font-size:var(--fs-8);font-weight:var(--fw-black);line-height:var(--lh-tight);">
        Modifier l'événement
      </h1>
      <div style="color:var(--gris);font-size:var(--fs-4);margin-top:6px;"><?= htmlspecialchars($event['titre']) ?></div>
    </div>

    <?php
    // Stats for this event
    $stmtStats = $pdo->prepare("
        SELECT
            COUNT(*) as total,
            SUM(statut = 'inscrit') as nb_inscrits,
            SUM(statut = 'checkin') as nb_checkin
        FROM inscriptions WHERE evenement_id=?
    ");
    $stmtStats->execute([$eid]);
    $stats = $stmtStats->fetch();
    ?>
    <div class="stats-bar">
      <div class="stats-bar-item">
        <div class="stats-bar-value"><?= $stats['nb_inscrits'] ?>/<?= $event['quota'] ?></div>
        <div class="stats-bar-label">Inscrits</div>
      </div>
      <div class="stats-bar-item">
        <div class="stats-bar-value"><?= $stats['nb_checkin'] ?></div>
        <div class="stats-bar-label">Check-in</div>
      </div>
      <div class="stats-bar-item">
        <div class="stats-bar-value"><?= dateFr($event['date_heure'], 'D j M') ?></div>
        <div class="stats-bar-label">Date</div>
      </div>
    </div>

    <div class="form-card">
      <?= csrfFlash() ?>
      <?php if (!empty($errors)): ?>
      <ul class="form-errors" role="alert" aria-live="assertive">
        <?php foreach ($errors as $err): ?>
          <li><?= htmlspecialchars($err) ?></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>

      <form method="POST">
        <?= csrfField() ?>
        <div class="form-section-title">Informations générales</div>

        <div class="form-group">
          <label for="titre">Titre de l'événement *</label>
          <input type="text" id="titre" name="titre"
                 value="<?= htmlspecialchars($event['titre']) ?>"
                 placeholder="ex: Soirée Étudiants — DJ Groove" required>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="type">Type *</label>
            <select id="type" name="type" required>
              <option value="">— Choisir —</option>
              <option value="bar"       <?= $event['type']==='bar'       ?'selected':'' ?>>Bar</option>
              <option value="boite"     <?= $event['type']==='boite'     ?'selected':'' ?>>Boîte de nuit</option>
              <option value="resto"     <?= $event['type']==='resto'     ?'selected':'' ?>>Restaurant</option>
              <option value="afterwork" <?= $event['type']==='afterwork' ?'selected':'' ?>>Afterwork</option>
            </select>
          </div>
          <div class="form-group">
            <label for="musique">Style de musique</label>
            <select id="musique" name="style_musique">
              <option value="">— Non précisé —</option>
              <?php foreach (stylesMusique() as $code => $libelle): ?>
                <option value="<?= $code ?>" <?= ($event['style_musique'] ?? '') === $code ? 'selected' : '' ?>><?= htmlspecialchars($libelle) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="date_heure">Date & heure *</label>
            <input type="datetime-local" id="date_heure" name="date_heure"
                   value="<?= htmlspecialchars($dtLocal) ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label for="description">Description</label>
          <textarea id="description" name="description" placeholder="Décris l'ambiance, les animations, le dress code..."><?= htmlspecialchars($event['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
          <label for="lieu">Lieu / Salle</label>
          <input type="text" id="lieu" name="lieu"
                 value="<?= htmlspecialchars($event['lieu'] ?? $etab['adresse'] ?? '') ?>"
                 placeholder="<?= htmlspecialchars($etab['adresse'] ?? $etab['nom']) ?>">
        </div>

        <div class="form-section-title">Capacité & tarifs</div>

        <div class="form-row">
          <div class="form-group">
            <label for="quota">Quota (places max) *</label>
            <input type="number" id="quota" name="quota"
                   value="<?= (int)$event['quota'] ?>" min="1" max="10000" required>
          </div>
          <div class="form-group">
            <label for="prix_normal">Prix normal (€)</label>
            <input type="number" id="prix_normal" name="prix_normal"
                   value="<?= (float)$event['prix_normal'] ?>" min="0" step="0.50">
          </div>
        </div>

        <div class="form-group">
          <label for="reduction">Réduction étudiants (%)</label>
          <input type="number" id="reduction" name="reduction"
                 value="<?= (int)$event['reduction'] ?>" min="0" max="100">
          <span style="font-size:var(--fs-1);color:var(--gris);">La réduction sera affichée sur le pass étudiant</span>
        </div>

        <label class="toggle-row">
          <input type="checkbox" name="is_gratuit" id="cb-gratuit" <?= $event['is_gratuit'] ? 'checked' : '' ?>>
          <div>
            <div class="toggle-label">Entrée gratuite</div>
            <div class="toggle-desc">Le pass sera 100% offert pour les étudiants</div>
          </div>
        </label>

        <div class="form-section-title">Options avancées</div>

        <label class="toggle-row">
          <input type="checkbox" name="is_flash" id="cb-flash" <?= $event['is_flash'] ? 'checked' : '' ?>>
          <div>
            <div class="toggle-label">Event Flash</div>
            <div class="toggle-desc">Offre limitée dans le temps — s'affiche en tête du fil</div>
          </div>
        </label>

        <div class="flash-extra" id="flash-extra">
          <div class="form-group">
            <label for="flash_expiry">Expiration de l'offre flash</label>
            <input type="datetime-local" id="flash_expiry" name="flash_expiry"
                   value="<?= htmlspecialchars($flashLocal) ?>">
          </div>
        </div>

        <?php
          $formules       = formulesSponsoring();
          $formuleChoisie = $event['sponsor_formule'] ?? '';
          $sponsoEncours  = sponsoringActif($event);
        ?>
        <label class="toggle-row" id="sponsor-toggle-row">
          <input type="checkbox" name="is_sponsorise" id="cb-sponsor" <?= !empty($event['is_sponsorise']) ? 'checked' : '' ?>>
          <div>
            <div class="toggle-label">Post sponsorisé</div>
            <div class="toggle-desc">Remonte l'événement en tête du fil Explore. Le badge &laquo;&nbsp;Sponsorisé&nbsp;&raquo; reste visible pour les étudiants.</div>
          </div>
        </label>

        <div class="sponsor-extra" id="sponsor-extra">
          <div class="sponsor-grille" role="radiogroup" aria-label="Formule de sponsoring">
            <?php foreach ($formules as $code => $f): ?>
            <label class="sponsor-choix">
              <input type="radio" name="sponsor_formule" value="<?= $code ?>" <?= $formuleChoisie === $code ? 'checked' : '' ?>>
              <div>
                <div class="sc-nom"><?= htmlspecialchars($f['nom']) ?></div>
                <div class="sc-desc"><?= htmlspecialchars($f['resume']) ?></div>
              </div>
              <div class="sc-tarif"><?= number_format($f['tarif'], 0, ',', ' ') ?>&nbsp;€</div>
            </label>
            <?php endforeach; ?>
          </div>
          <div class="sponsor-total">
            <?php if ($sponsoEncours && !empty($event['sponsor_jusqu_au'])): ?>
              Mise en avant active jusqu'au <?= dateFr($event['sponsor_jusqu_au'], 'j M') ?>.
              Changer de formule refacture le nouveau tarif et redémarre la période.
            <?php else: ?>
              Tarif facturé une fois à l'enregistrement. La mise en avant s'arrête à la fin
              de la formule, ou à la date de l'événement si elle arrive avant.
            <?php endif; ?>
          </div>
        </div>

        <div style="margin-top:32px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
          <button type="submit" class="btn btn-primary" style="font-size:var(--fs-5);padding:14px 32px;">
            Enregistrer les modifications
          </button>
          <a href="<?= baseUrl('/partenaire/evenements.php') ?>"
             style="font-size:var(--fs-3);color:var(--gris);text-decoration:none;">Annuler</a>
        </div>
      </form>
    </div>
  </main>
</div>

<script>
  const cbFlash  = document.getElementById('cb-flash');
  const flashExtra = document.getElementById('flash-extra');
  function toggleFlash() {
    flashExtra.style.display = cbFlash.checked ? 'block' : 'none';
  }
  cbFlash.addEventListener('change', toggleFlash);
  toggleFlash();

  const cbSponsor = document.getElementById('cb-sponsor');
  const sponsorExtra = document.getElementById('sponsor-extra');
  function toggleSponsor() {
    sponsorExtra.style.display = cbSponsor.checked ? 'block' : 'none';
    const choix = sponsorExtra.querySelectorAll('input[name=sponsor_formule]');
    choix.forEach(r => { r.required = cbSponsor.checked; });
    if (cbSponsor.checked && ![...choix].some(r => r.checked) && choix[0]) choix[0].checked = true;
  }
  cbSponsor.addEventListener('change', toggleSponsor);
  toggleSponsor();
</script>
</body>
</html>
