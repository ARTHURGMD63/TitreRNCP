<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/crm.php';
require_once __DIR__ . '/../includes/uploads.php';
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

const VENUE_PHOTOS_MAX = 12;

$errors  = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM etablissement_photos WHERE etablissement_id=?");
        $stmt->execute([$etab['id']]);
        $count = (int)$stmt->fetchColumn();

        if ($count >= VENUE_PHOTOS_MAX) {
            $errors[] = 'Tu as atteint la limite de ' . VENUE_PHOTOS_MAX . ' photos. Supprimes-en une pour en ajouter une nouvelle.';
        } else {
            $result = storeUploadedImage($_FILES['photo'] ?? [], venuePhotoDir());
            if (!$result['ok']) {
                $errors[] = $result['error'];
            } else {
                $legende = trim($_POST['legende'] ?? '');
                if ($legende === '') {
                    $legende = null;
                }
                $stmt = $pdo->prepare("SELECT COALESCE(MAX(position), -1) + 1 FROM etablissement_photos WHERE etablissement_id=?");
                $stmt->execute([$etab['id']]);
                $position = (int)$stmt->fetchColumn();

                $stmt = $pdo->prepare(
                    "INSERT INTO etablissement_photos (etablissement_id, fichier, legende, position) VALUES (?,?,?,?)"
                );
                $stmt->execute([$etab['id'], $result['filename'], $legende, $position]);

                header('Location: ' . baseUrl('/partenaire/photos.php?added=1'));
                exit;
            }
        }
    }

    if ($action === 'delete') {
        $photoId = (int)($_POST['photo_id'] ?? 0);
        // On filtre sur etablissement_id : un partenaire ne peut pas supprimer
        // la photo d'un autre établissement en devinant un id.
        $stmt = $pdo->prepare("SELECT fichier FROM etablissement_photos WHERE id=? AND etablissement_id=?");
        $stmt->execute([$photoId, $etab['id']]);
        $fichier = $stmt->fetchColumn();

        if ($fichier === false) {
            $errors[] = 'Photo introuvable.';
        } else {
            $stmt = $pdo->prepare("DELETE FROM etablissement_photos WHERE id=? AND etablissement_id=?");
            $stmt->execute([$photoId, $etab['id']]);
            deleteStoredImage((string)$fichier, venuePhotoDir());
            header('Location: ' . baseUrl('/partenaire/photos.php?deleted=1'));
            exit;
        }
    }

    if ($action === 'cover') {
        $photoId = (int)($_POST['photo_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT id FROM etablissement_photos WHERE id=? AND etablissement_id=?");
        $stmt->execute([$photoId, $etab['id']]);

        if ($stmt->fetchColumn() === false) {
            $errors[] = 'Photo introuvable.';
        } else {
            // La couverture passe en position 0, les autres sont décalées.
            $pdo->prepare("UPDATE etablissement_photos SET position = position + 1 WHERE etablissement_id=?")
                ->execute([$etab['id']]);
            $pdo->prepare("UPDATE etablissement_photos SET position = 0 WHERE id=? AND etablissement_id=?")
                ->execute([$photoId, $etab['id']]);
            header('Location: ' . baseUrl('/partenaire/photos.php?cover=1'));
            exit;
        }
    }
}

if (isset($_GET['added']))   $success = 'Photo ajoutée.';
if (isset($_GET['deleted'])) $success = 'Photo supprimée.';
if (isset($_GET['cover']))   $success = 'Photo de couverture mise à jour.';

$stmt = $pdo->prepare("SELECT * FROM etablissement_photos WHERE etablissement_id=? ORDER BY position ASC, id ASC");
$stmt->execute([$etab['id']]);
$photos = $stmt->fetchAll();
?>
<?php ob_start(); ?>
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
  .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
  .form-group label { font-size: var(--fs-1); font-weight: var(--fw-display); text-transform: uppercase; letter-spacing: var(--ls-wide); }
  .form-group input {
    border: 1px solid var(--gris-clair);
    padding: 10px 14px;
    font-family: var(--font-sans);
    font-size: var(--fs-4);
    background: var(--blanc);
    outline: none;
    width: 100%;
    box-sizing: border-box;
  }
  .form-group input:focus { border-color: var(--rouge); }
  .form-hint { font-size: var(--fs-2); color: var(--gris); }
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

  .photo-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 20px;
    margin-top: 24px;
  }
  .photo-item {
    background: var(--blanc);
    border-radius: var(--radius);
    border: 1px solid var(--gris-clair);
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    display: flex;
    flex-direction: column;
  }
  .photo-thumb {
    width: 100%;
    aspect-ratio: 4 / 3;
    object-fit: cover;
    display: block;
    background: var(--gris-clair);
  }
  .photo-meta { padding: 12px 14px; display: flex; flex-direction: column; gap: 10px; flex: 1; }
  .photo-legende { font-size: var(--fs-3); font-weight: var(--fw-semibold); line-height: var(--lh-snug); }
  .photo-actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: auto; }
  .photo-actions button {
    font-family: var(--font-sans);
    font-size: var(--fs-1);
    font-weight: var(--fw-display);
    text-transform: uppercase;
    letter-spacing: var(--ls-wide);
    padding: 8px 12px;
    min-height: 34px;
    border: 1px solid var(--noir);
    background: var(--blanc);
    color: var(--noir);
    cursor: pointer;
  }
  .photo-actions button.danger { border-color: var(--rouge); color: var(--rouge); }
  .cover-flag {
    display: inline-block;
    font-size: var(--fs-1);
    font-weight: var(--fw-display);
    text-transform: uppercase;
    letter-spacing: var(--ls-wide);
    background: var(--noir);
    color: var(--blanc);
    border-radius: var(--radius-pill);
    padding: 4px 11px;
    align-self: flex-start;
  }
  .empty-state {
    background: var(--blanc);
    border: 1px dashed var(--gris-clair);
    padding: 40px 24px;
    text-align: center;
    color: var(--gris);
    margin-top: 24px;
  }
</style>
<?php pageDebut('StudentLink — Photos de l\'établissement', ['tete' => ob_get_clean()]); ?>
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
      <a href="<?= baseUrl('/partenaire/create_event.php') ?>" class="sidebar-link">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
        Créer un event
      </a>
      <a href="<?= baseUrl('/partenaire/photos.php') ?>" class="sidebar-link active">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
        Photos
      </a>
      <a href="<?= baseUrl('/partenaire/abonnement.php') ?>" class="sidebar-link">
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
    <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:32px;">
      <div>
        <div class="label text-gris" style="margin-bottom:6px;">Vitrine de l'établissement</div>
        <h1 class="titre-page" style="font-family:var(--font-display);font-size:var(--fs-9);font-weight:var(--fw-black);line-height:var(--lh-tight);">
          Photos du lieu
        </h1>
      </div>
    </div>

    <?= csrfFlash() ?>

    <?php if ($success): ?>
      <div class="form-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <ul class="form-errors">
        <?php foreach ($errors as $e): ?>
          <li><?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <div class="form-card">
      <div class="form-section-title">Ajouter une photo</div>
      <form method="POST" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="upload">
        <div class="form-group">
          <label for="photo">Fichier</label>
          <div class="champ-fichier">
            <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" required>
            <label for="photo" class="btn btn-outline">
              <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
              Choisir une photo
            </label>
            <span class="nom-fichier vide">Aucune image choisie</span>
          </div>
          <div class="form-hint">JPG, PNG ou WebP — 2 Mo maximum, 200 px minimum par côté.</div>
        </div>
        <div class="form-group">
          <label for="legende">Légende (optionnelle)</label>
          <input type="text" id="legende" name="legende" maxlength="160" placeholder="La terrasse un vendredi soir">
        </div>
        <button type="submit" class="btn btn-primary">+ Ajouter la photo</button>
      </form>
    </div>

    <div class="form-section-title" style="max-width:640px;">
      Galerie (<?= count($photos) ?>/<?= VENUE_PHOTOS_MAX ?>)
    </div>

    <?php if (empty($photos)): ?>
      <div class="empty-state">
        Aucune photo pour le moment. Ajoute des vues de la salle, de la terrasse ou d'une soirée :
        les étudiants choisissent d'abord à l'ambiance.
      </div>
    <?php else: ?>
      <div class="photo-grid">
        <?php foreach ($photos as $i => $photo): ?>
          <div class="photo-item">
            <img class="photo-thumb"
                 src="<?= venuePhotoUrl($photo['fichier']) ?>"
                 alt="<?= htmlspecialchars($photo['legende'] ?? ('Photo de ' . $etab['nom'])) ?>"
                 loading="lazy">
            <div class="photo-meta">
              <?php if ($i === 0): ?>
                <span class="cover-flag">Couverture</span>
              <?php endif; ?>
              <?php if (!empty($photo['legende'])): ?>
                <div class="photo-legende"><?= htmlspecialchars($photo['legende']) ?></div>
              <?php endif; ?>
              <div class="photo-actions">
                <?php if ($i !== 0): ?>
                  <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="cover">
                    <input type="hidden" name="photo_id" value="<?= (int)$photo['id'] ?>">
                    <button type="submit"><svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg> Couverture</button>
                  </form>
                <?php endif; ?>
                <form method="POST" onsubmit="return confirm('Supprimer cette photo ?');">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="photo_id" value="<?= (int)$photo['id'] ?>">
                  <button type="submit" class="danger">Supprimer</button>
                </form>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </main>
</div>

<script src="<?= asset('/assets/js/app.js') ?>"></script>
</body>
</html>
