<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
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

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titre       = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $type        = $_POST['type'] ?? '';
    $date_heure  = trim($_POST['date_heure'] ?? '');
    $quota       = (int)($_POST['quota'] ?? 100);
    $reduction   = (int)($_POST['reduction'] ?? 0);
    $prix_normal = (float)($_POST['prix_normal'] ?? 0);
    $is_flash    = isset($_POST['is_flash']) ? 1 : 0;
    $flash_expiry = $is_flash ? trim($_POST['flash_expiry'] ?? '') : null;
    $is_gratuit  = isset($_POST['is_gratuit']) ? 1 : 0;
    $lieu        = trim($_POST['lieu'] ?? '');

    if (!$titre)      $errors[] = 'Le titre est obligatoire.';
    if (!in_array($type, ['bar','boite','resto','afterwork'])) $errors[] = 'Type invalide.';
    if (!$date_heure) $errors[] = 'La date est obligatoire.';
    if ($quota < 1)   $errors[] = 'Le quota doit être au moins 1.';
    if ($is_flash && !$flash_expiry) $errors[] = 'Date d\'expiration flash requise.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO evenements
                (etablissement_id, titre, description, type, date_heure, quota, reduction, prix_normal, is_flash, flash_expiry, is_gratuit, lieu)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute([
            $etab['id'], $titre, $description, $type, $date_heure,
            $quota, $reduction, $prix_normal, $is_flash,
            $flash_expiry ?: null, $is_gratuit, $lieu
        ]);
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
<link rel="stylesheet" href="<?= baseUrl() ?>/assets/css/style.css">
<style>
  .form-card {
    background: var(--blanc);
    border: 2px solid var(--noir);
    box-shadow: 6px 6px 0 var(--noir);
    padding: 32px;
    max-width: 640px;
  }
  .form-section-title {
    font-family: 'Playfair Display', serif;
    font-weight: 900;
    font-size: 1.1rem;
    margin: 28px 0 14px;
    padding-bottom: 8px;
    border-bottom: 2px solid var(--noir);
  }
  .form-section-title:first-child { margin-top: 0; }
  .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
  .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
  .form-group label { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .05em; }
  .form-group input,
  .form-group select,
  .form-group textarea {
    border: 2px solid var(--noir);
    padding: 10px 14px;
    font-family: 'DM Sans', sans-serif;
    font-size: 14px;
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
    gap: 12px;
    padding: 12px 16px;
    border: 2px solid var(--noir);
    margin-bottom: 12px;
    cursor: pointer;
  }
  .toggle-row input[type=checkbox] { width: 18px; height: 18px; cursor: pointer; }
  .toggle-row .toggle-label { font-weight: 700; font-size: 14px; }
  .toggle-row .toggle-desc { font-size: 12px; color: var(--gris); }
  .flash-extra { display: none; margin-top: 12px; }
  .form-errors {
    background: #fff5f5;
    border: 2px solid var(--rouge);
    padding: 14px 18px;
    margin-bottom: 24px;
    font-size: 14px;
  }
  .form-errors li { margin: 4px 0; color: var(--rouge); font-weight: 600; }
</style>
</head>
<body>
<div class="partner-shell">

  <aside class="partner-sidebar">
    <div class="sidebar-brand">
      <div style="font-family:'DM Sans',sans-serif;font-weight:700;font-size:15px;color:#fff;">
        StudentLink <em style="font-style:italic;color:#E5331A;">/ Partenaires</em>
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
    </nav>
    <div style="margin-top:auto;padding:20px;border-top:1px solid rgba(255,255,255,0.1);">
      <div style="font-weight:700;font-size:13px;color:#fff;"><?= htmlspecialchars(strtoupper($etab['nom'])) ?></div>
      <div style="font-size:11px;color:rgba(255,255,255,0.4);margin-top:2px;"><?= htmlspecialchars($etab['ville']) ?></div>
      <a href="<?= baseUrl('/auth/logout.php') ?>" style="display:block;margin-top:12px;font-size:12px;color:rgba(255,255,255,0.4);text-decoration:none;">→ Déconnexion</a>
    </div>
  </aside>

  <main class="partner-main">
    <div style="margin-bottom:32px;">
      <div class="label text-gris" style="margin-bottom:6px;">
        <a href="<?= baseUrl('/partenaire/evenements.php') ?>" style="color:var(--gris);text-decoration:none;">← Retour aux événements</a>
      </div>
      <div style="font-family:'Playfair Display',serif;font-size:2.2rem;font-weight:900;line-height:1.1;">
        Créer un événement
      </div>
    </div>

    <div class="form-card">
      <?php if (!empty($errors)): ?>
      <ul class="form-errors">
        <?php foreach ($errors as $e): ?>
          <li><?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>

      <form method="POST">
        <div class="form-section-title">Informations générales</div>

        <div class="form-group">
          <label>Titre de l'événement *</label>
          <input type="text" name="titre" value="<?= htmlspecialchars($_POST['titre'] ?? '') ?>" placeholder="ex: Soirée Étudiants — DJ Groove" required>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Type *</label>
            <select name="type" required>
              <option value="">— Choisir —</option>
              <option value="bar"       <?= ($_POST['type']??'')==='bar'       ?'selected':'' ?>>Bar</option>
              <option value="boite"     <?= ($_POST['type']??'')==='boite'     ?'selected':'' ?>>Boîte de nuit</option>
              <option value="resto"     <?= ($_POST['type']??'')==='resto'     ?'selected':'' ?>>Restaurant</option>
              <option value="afterwork" <?= ($_POST['type']??'')==='afterwork' ?'selected':'' ?>>Afterwork</option>
            </select>
          </div>
          <div class="form-group">
            <label>Date & heure *</label>
            <input type="datetime-local" name="date_heure" value="<?= htmlspecialchars($_POST['date_heure'] ?? '') ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label>Description</label>
          <textarea name="description" placeholder="Décris l'ambiance, les animations, le dress code..."><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
        </div>

        <div class="form-group">
          <label>Lieu / Salle</label>
          <input type="text" name="lieu" value="<?= htmlspecialchars($_POST['lieu'] ?? $etab['adresse'] ?? '') ?>" placeholder="<?= htmlspecialchars($etab['adresse'] ?? $etab['nom']) ?>">
        </div>

        <div class="form-section-title">Capacité & tarifs</div>

        <div class="form-row">
          <div class="form-group">
            <label>Quota (places max) *</label>
            <input type="number" name="quota" value="<?= (int)($_POST['quota'] ?? 100) ?>" min="1" max="10000" required>
          </div>
          <div class="form-group">
            <label>Prix normal (€)</label>
            <input type="number" name="prix_normal" value="<?= (float)($_POST['prix_normal'] ?? 0) ?>" min="0" step="0.50" placeholder="0">
          </div>
        </div>

        <div class="form-group">
          <label>Réduction étudiants (%)</label>
          <input type="number" name="reduction" value="<?= (int)($_POST['reduction'] ?? 0) ?>" min="0" max="100" placeholder="ex: 20">
          <span style="font-size:11px;color:var(--gris);">La réduction sera affichée sur le pass étudiant</span>
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
            <label>Expiration de l'offre flash</label>
            <input type="datetime-local" name="flash_expiry" value="<?= htmlspecialchars($_POST['flash_expiry'] ?? '') ?>">
          </div>
        </div>

        <div style="margin-top:32px;display:flex;gap:12px;align-items:center;">
          <button type="submit" class="btn btn-primary" style="font-size:15px;padding:14px 32px;">
            Publier l'événement
          </button>
          <a href="<?= baseUrl('/partenaire/evenements.php') ?>" style="font-size:13px;color:var(--gris);text-decoration:none;">Annuler</a>
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
</script>
</body>
</html>
