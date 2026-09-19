<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/crm.php';
require_once __DIR__ . '/../includes/sponsoring.php';
require_once __DIR__ . '/../includes/capacites.php';
require_once __DIR__ . '/../includes/musique.php';
require_once __DIR__ . '/../includes/temps_reel.php';
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

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $titre       = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $type        = $_POST['type'] ?? '';
    $musique     = trim($_POST['style_musique'] ?? '');
    $date_heure  = trim($_POST['date_heure'] ?? '');
    $quota       = (int)($_POST['quota'] ?? 100);
    $reduction   = (int)($_POST['reduction'] ?? 0);
    $prix_normal = (float)($_POST['prix_normal'] ?? 0);
    $is_flash    = isset($_POST['is_flash']) ? 1 : 0;
    $flash_expiry = $is_flash ? trim($_POST['flash_expiry'] ?? '') : null;
    $is_gratuit  = isset($_POST['is_gratuit']) ? 1 : 0;
    $lieu        = trim($_POST['lieu'] ?? '');

    // Sponsoring : la formule choisie fixe le tarif et la duree. Le tarif
    // n'est jamais lu depuis le formulaire — un champ prix envoye par le
    // client se modifie a la console, et la facturation avec.
    $is_sponsorise  = isset($_POST['is_sponsorise']) ? 1 : 0;
    $sponsorFormule = $is_sponsorise ? ($_POST['sponsor_formule'] ?? '') : null;

    if (!$titre)      $errors[] = 'Le titre est obligatoire.';
    if (!in_array($type, ['bar','boite','resto','afterwork'])) $errors[] = 'Type invalide.';
    // Le style est facultatif — un resto n'en annonce pas toujours — mais
    // s'il est renseigné, il vient du catalogue et de nulle part ailleurs.
    if ($musique !== '' && !styleMusiqueValide($musique)) $errors[] = 'Style de musique invalide.';
    if (!$date_heure) $errors[] = 'La date est obligatoire.';
    if ($quota < 1)   $errors[] = 'Le quota doit être au moins 1.';
    if ($is_flash && !$flash_expiry) $errors[] = 'Date d\'expiration flash requise.';
    if ($is_sponsorise && !formuleSponsoringValide($sponsorFormule)) {
        $errors[] = 'Choisis une formule de sponsoring.';
    }
    // Le quota se revérifie ici et pas seulement à l'affichage : la case est
    // dans le formulaire, elle se recoche à la console.
    if ($is_flash && !flashDisponible($pdo, (int) $etab['id'], $capacites, null)) {
        $errors[] = 'Ton quota d\'offres flash du mois est atteint ('
                  . (int) $capacites['flash_par_mois'] . ' par mois). Le Premium les rend illimitées.';
    }

    if (empty($errors)) {
        /*
         * Le Premium a droit à une mise en avant par mois (SL-03). Elle
         * s'écrit comme une ligne de sponsoring à 0 €, et non comme une
         * absence de sponsoring : sans trace, impossible de savoir combien
         * de mises en avant offertes ont été consommées.
         */
        $sponsorTarif   = $is_sponsorise ? tarifSponsoring($sponsorFormule) : 0;
        $offerteUtilisee = false;
        if ($is_sponsorise && misesEnAvantOffertesRestantes($pdo, (int) $etab['id'], $capacites) > 0) {
            $sponsorTarif    = 0;
            $offerteUtilisee = true;
        }
        $sponsorFin   = $is_sponsorise ? finSponsoring($sponsorFormule, $date_heure) : null;

        $stmt = $pdo->prepare("
            INSERT INTO evenements
                (etablissement_id, titre, description, type, style_musique, date_heure, quota, reduction, prix_normal, is_flash, flash_expiry, is_gratuit, lieu,
                 is_sponsorise, sponsor_formule, sponsor_tarif, sponsor_jusqu_au)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute([
            $etab['id'], $titre, $description, $type, $musique ?: null, $date_heure,
            $quota, $reduction, $prix_normal, $is_flash,
            $flash_expiry ?: null, $is_gratuit, $lieu,
            $is_sponsorise, $sponsorFormule, $sponsorTarif, $sponsorFin
        ]);
        // Le canal global ne bouge que pour ca : une nouvelle soiree publiee.
        // Assez rare pour qu'un onglet reste des heures sur son 304, assez
        // important pour meriter d'apparaitre sans rechargement quand ca arrive.
        fluxToucher($pdo, CANAL_GLOBAL);

        header('Location: ' . baseUrl('/partenaire/evenements.php?created=1'));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StudentLink — Créer un événement</title>
<?= themeBootScript() ?>
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
  /* L'encart tarifaire : le prix doit etre lisible avant de cocher, pas
     decouvert sur la facture. */
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
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
        Dashboard
      </a>
      <a href="<?= baseUrl('/partenaire/evenements.php') ?>" class="sidebar-link">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Événements
      </a>
      <a href="<?= baseUrl('/partenaire/create_event.php') ?>" class="sidebar-link active">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
        Créer un event
      </a>
      <a href="<?= baseUrl('/partenaire/photos.php') ?>" class="sidebar-link">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
        Photos
      </a>
      <a href="<?= baseUrl('/partenaire/abonnement.php') ?>" class="sidebar-link<?= basename($_SERVER['SCRIPT_NAME']) === 'abonnement.php' ? ' active' : '' ?>">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
        Abonnement
      </a>
    </nav>
    <div style="margin-top:auto;padding:20px;border-top:1px solid rgba(255,255,255,0.1);">
      <div style="font-weight:var(--fw-bold);font-size:var(--fs-3);color:#fff;"><?= htmlspecialchars(mb_strtoupper($etab['nom'])) ?></div>
      <div style="font-size:var(--fs-1);color:rgba(255,255,255,0.4);margin-top:2px;"><?= htmlspecialchars($etab['ville']) ?></div>
      <a href="<?= baseUrl('/auth/logout.php') ?>" class="lien-action" style="margin-top:12px;font-size:var(--fs-2);color:rgba(255,255,255,0.4);text-decoration:none;">→ Déconnexion</a>
    </div>
  </aside>

  <main class="partner-main">
    <div style="margin-bottom:32px;">
      <div class="label text-gris" style="margin-bottom:6px;">
        <a href="<?= baseUrl('/partenaire/evenements.php') ?>" style="color:var(--gris);text-decoration:none;">← Retour aux événements</a>
      </div>
      <h1 class="titre-page" style="font-family:var(--font-display);font-size:var(--fs-8);font-weight:var(--fw-black);line-height:var(--lh-tight);">
        Créer un événement
      </h1>
    </div>

    <div class="form-card">
      <?= csrfFlash() ?>
      <?php if (!empty($errors)): ?>
      <ul class="form-errors">
        <?php foreach ($errors as $e): ?>
          <li><?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>

      <form method="POST">
        <?= csrfField() ?>
        <div class="form-section-title">Informations générales</div>

        <div class="form-group">
          <label for="create-titre">Titre de l'événement *</label>
          <input id="create-titre" type="text" name="titre" value="<?= htmlspecialchars($_POST['titre'] ?? '') ?>" placeholder="ex: Soirée Étudiants — DJ Groove" required>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="create-type">Type *</label>
            <select id="create-type" name="type" required>
              <option value="">— Choisir —</option>
              <option value="bar"       <?= ($_POST['type']??'')==='bar'       ?'selected':'' ?>>Bar</option>
              <option value="boite"     <?= ($_POST['type']??'')==='boite'     ?'selected':'' ?>>Boîte de nuit</option>
              <option value="resto"     <?= ($_POST['type']??'')==='resto'     ?'selected':'' ?>>Restaurant</option>
              <option value="afterwork" <?= ($_POST['type']??'')==='afterwork' ?'selected':'' ?>>Afterwork</option>
            </select>
          </div>
          <div class="form-group">
            <label for="create-musique">Style de musique</label>
            <select id="create-musique" name="style_musique">
              <option value="">— Non précisé —</option>
              <?php foreach (stylesMusique() as $code => $libelle): ?>
                <option value="<?= $code ?>" <?= ($_POST['style_musique'] ?? '') === $code ? 'selected' : '' ?>><?= htmlspecialchars($libelle) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label for="create-date_heure">Date & heure *</label>
            <input id="create-date_heure" type="datetime-local" name="date_heure" value="<?= htmlspecialchars($_POST['date_heure'] ?? '') ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label for="create-description">Description</label>
          <textarea id="create-description" name="description" placeholder="Décris l'ambiance, les animations, le dress code..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
          <label for="create-lieu">Lieu / Salle</label>
          <input id="create-lieu" type="text" name="lieu" value="<?= htmlspecialchars($_POST['lieu'] ?? $etab['adresse'] ?? '') ?>" placeholder="<?= htmlspecialchars($etab['adresse'] ?? $etab['nom']) ?>">
        </div>

        <div class="form-section-title">Capacité & tarifs</div>

        <div class="form-row">
          <div class="form-group">
            <label for="create-quota">Quota (places max) *</label>
            <input id="create-quota" type="number" name="quota" value="<?= (int)($_POST['quota'] ?? 100) ?>" min="1" max="10000" required>
          </div>
          <div class="form-group">
            <label for="create-prix_normal">Prix normal (€)</label>
            <input id="create-prix_normal" type="number" name="prix_normal" value="<?= (float)($_POST['prix_normal'] ?? 0) ?>" min="0" step="0.50" placeholder="0">
          </div>
        </div>

        <div class="form-group">
          <label for="create-reduction">Réduction étudiants (%)</label>
          <input id="create-reduction" type="number" name="reduction" value="<?= (int)($_POST['reduction'] ?? 0) ?>" min="0" max="100" placeholder="ex: 20">
          <span style="font-size:var(--fs-1);color:var(--gris);">La réduction sera affichée sur le pass étudiant</span>
        </div>

        <label class="toggle-row">
          <input type="checkbox" name="is_gratuit" id="cb-gratuit" <?= isset($_POST['is_gratuit'])?'checked':'' ?>>
          <div>
            <div class="toggle-label">Entrée gratuite</div>
            <div class="toggle-desc">Le pass sera 100% offert pour les étudiants</div>
          </div>
        </label>

        <div class="form-section-title">Options avancées</div>

        <label class="toggle-row" id="flash-toggle-row">
          <input type="checkbox" name="is_flash" id="cb-flash" <?= isset($_POST['is_flash'])?'checked':'' ?>>
          <div>
            <div class="toggle-label">Event Flash</div>
            <div class="toggle-desc">Offre limitée dans le temps — s'affiche en tête du fil</div>
          </div>
        </label>

        <div class="flash-extra" id="flash-extra">
          <div class="form-group">
            <label for="create-flash_expiry">Expiration de l'offre flash</label>
            <input id="create-flash_expiry" type="datetime-local" name="flash_expiry" value="<?= htmlspecialchars($_POST['flash_expiry'] ?? '') ?>">
          </div>
        </div>

        <?php $formules = formulesSponsoring(); $formuleChoisie = $_POST['sponsor_formule'] ?? ''; ?>
        <label class="toggle-row" id="sponsor-toggle-row">
          <input type="checkbox" name="is_sponsorise" id="cb-sponsor" <?= isset($_POST['is_sponsorise'])?'checked':'' ?>>
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
            Tarif facturé une fois à la publication. La mise en avant s'arrête à la fin
            de la formule, ou à la date de l'événement si elle arrive avant.
          </div>
        </div>

        <div style="margin-top:32px;display:flex;gap:12px;align-items:center;">
          <button type="submit" class="btn btn-primary" style="font-size:var(--fs-5);padding:14px 32px;">
            Publier l'événement
          </button>
          <a href="<?= baseUrl('/partenaire/evenements.php') ?>" style="font-size:var(--fs-3);color:var(--gris);text-decoration:none;">Annuler</a>
        </div>
      </form>
    </div>
  </main>
</div>

<script>
  const cbFlash = document.getElementById('cb-flash');
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
    // Une formule doit etre choisie des que la case est cochee : sans cela le
    // formulaire repart en erreur pour un champ que personne n'a vu.
    const choix = sponsorExtra.querySelectorAll('input[name=sponsor_formule]');
    choix.forEach(r => { r.required = cbSponsor.checked; });
    if (cbSponsor.checked && ![...choix].some(r => r.checked) && choix[0]) choix[0].checked = true;
  }
  cbSponsor.addEventListener('change', toggleSponsor);
  toggleSponsor();
</script>
</body>
</html>
