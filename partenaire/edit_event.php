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
    $date_heure   = trim($_POST['date_heure'] ?? '');
    $quota        = (int)($_POST['quota'] ?? 100);
    $reduction    = (int)($_POST['reduction'] ?? 0);
    $prix_normal  = (float)($_POST['prix_normal'] ?? 0);
    $is_flash     = isset($_POST['is_flash']) ? 1 : 0;
    $flash_expiry = $is_flash ? trim($_POST['flash_expiry'] ?? '') : null;
    $is_gratuit   = isset($_POST['is_gratuit']) ? 1 : 0;
    $lieu         = trim($_POST['lieu'] ?? '');

    if (!$titre)      $errors[] = 'Le titre est obligatoire.';
    if (!in_array($type, ['bar','boite','resto','afterwork'])) $errors[] = 'Type invalide.';
    if (!$date_heure) $errors[] = 'La date est obligatoire.';
    if ($quota < 1)   $errors[] = 'Le quota doit être au moins 1.';
    if ($is_flash && !$flash_expiry) $errors[] = 'Date d\'expiration flash requise.';

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            UPDATE evenements SET
                titre=?, description=?, type=?, date_heure=?,
                quota=?, reduction=?, prix_normal=?,
                is_flash=?, flash_expiry=?, is_gratuit=?, lieu=?
            WHERE id=? AND etablissement_id=?
        ");
        $stmt->execute([
            $titre, $description, $type, $date_heure,
            $quota, $reduction, $prix_normal,
            $is_flash, $flash_expiry ?: null, $is_gratuit, $lieu,
            $eid, $etab['id']
        ]);
        header('Location: ' . baseUrl('/partenaire/evenements.php?updated=1'));
        exit;
    }

    // Re-populate $event from POST on error
    $event = array_merge($event, [
        'titre' => $titre, 'description' => $description, 'type' => $type,
        'date_heure' => $date_heure, 'quota' => $quota, 'reduction' => $reduction,
        'prix_normal' => $prix_normal, 'is_flash' => $is_flash,
        'flash_expiry' => $flash_expiry, 'is_gratuit' => $is_gratuit, 'lieu' => $lieu,
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
  .stats-bar {
    background: var(--gris-clair);
    border-radius: var(--radius);
    padding: 16px 20px;
    margin-bottom: 24px;
    display: flex;
    gap: 32px;
  }
  .stats-bar-item { text-align: center; }
  .stats-bar-value { font-size: 1.5rem; font-weight: 900; }
  .stats-bar-label { font-size: 11px; color: var(--gris); text-transform: uppercase; letter-spacing: .05em; font-weight: 700; }
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
        <span class="icon">📊</span> Dashboard
      </a>
      <a href="<?= baseUrl('/partenaire/evenements.php') ?>" class="sidebar-link active">
        <span class="icon">🎉</span> Événements
      </a>
      <a href="<?= baseUrl('/partenaire/create_event.php') ?>" class="sidebar-link">
        <span class="icon">➕</span> Créer un event
      </a>
    </nav>
    <div class="sidebar-venue" style="margin-top:48px;padding-top:20px;border-top:1px solid rgba(255,255,255,0.1);">
      <div class="sidebar-venue-name"><?= htmlspecialchars(strtoupper($etab['nom'])) ?></div>
      <div class="sidebar-venue-city"><?= htmlspecialchars($etab['ville']) ?></div>
      <a href="<?= baseUrl('/auth/logout.php') ?>" style="display:block;margin-top:12px;font-size:12px;color:rgba(255,255,255,0.4);text-decoration:none;">
        → Déconnexion
      </a>
    </div>
  </aside>

  <main class="partner-main">
    <div style="margin-bottom:32px;">
      <div class="label text-gris" style="margin-bottom:6px;">
        <a href="<?= baseUrl('/partenaire/evenements.php') ?>" style="color:var(--gris);text-decoration:none;">← Retour aux événements</a>
      </div>
      <div style="font-family:'Playfair Display',serif;font-size:2.2rem;font-weight:900;line-height:1.1;">
        Modifier l'événement
      </div>
      <div style="color:var(--gris);font-size:14px;margin-top:6px;"><?= htmlspecialchars($event['titre']) ?></div>
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
        <div class="stats-bar-value"><?= date('D j M', strtotime($event['date_heure'])) ?></div>
        <div class="stats-bar-label">Date</div>
      </div>
    </div>

    <div class="form-card">
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
          <span style="font-size:11px;color:var(--gris);">La réduction sera affichée sur le pass étudiant</span>
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

        <div style="margin-top:32px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
          <button type="submit" class="btn btn-primary" style="font-size:15px;padding:14px 32px;">
            Enregistrer les modifications
          </button>
          <a href="<?= baseUrl('/partenaire/evenements.php') ?>"
             style="font-size:13px;color:var(--gris);text-decoration:none;">Annuler</a>
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
</script>
</body>
</html>
