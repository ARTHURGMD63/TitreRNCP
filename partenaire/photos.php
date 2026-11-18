<?php
/**
 * Les photos de l'établissement, affichées sur sa fiche dans l'application.
 *
 * Douze au plus ; la première est la couverture. Chaque action filtre sur
 * etablissement_id : deviner l'identifiant d'une photo ne permet ni de la
 * supprimer ni de la mettre en couverture chez un autre. Les fichiers sont
 * contrôlés et renommés par storeUploadedImage() (includes/uploads.php).
 */
require_once __DIR__ . '/../includes/site.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/uploads.php';

$etab = ouvrirEspacePro($pdo);
$etabId = (int) $etab['id'];

const PHOTOS_ETABLISSEMENT_MAX = 12;

$erreur = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $action  = (string) ($_POST['action'] ?? '');
    $photoId = (int) ($_POST['photo_id'] ?? 0);

    if ($action === 'ajouter') {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM etablissement_photos WHERE etablissement_id = ?');
        $stmt->execute([$etabId]);
        if ((int) $stmt->fetchColumn() >= PHOTOS_ETABLISSEMENT_MAX) {
            $erreur = 'Vous avez atteint ' . PHOTOS_ETABLISSEMENT_MAX . ' photos : supprimez-en une pour en ajouter une autre.';
        } else {
            $resultat = storeUploadedImage($_FILES['photo'] ?? [], venuePhotoDir());
            if (!$resultat['ok']) {
                $erreur = (string) $resultat['error'];
            } else {
                $legende = trim((string) ($_POST['legende'] ?? ''));
                $stmt = $pdo->prepare('SELECT COALESCE(MAX(position), -1) + 1 FROM etablissement_photos WHERE etablissement_id = ?');
                $stmt->execute([$etabId]);
                $pdo->prepare('INSERT INTO etablissement_photos (etablissement_id, fichier, legende, position) VALUES (?,?,?,?)')
                    ->execute([$etabId, $resultat['filename'], $legende !== '' ? mb_substr($legende, 0, 160) : null, (int) $stmt->fetchColumn()]);
                flashPro('Photo ajoutée.');
                header('Location: ' . baseUrl('/partenaire/photos.php'));
                exit;
            }
        }
    } elseif ($action === 'supprimer' || $action === 'couverture') {
        $stmt = $pdo->prepare('SELECT fichier FROM etablissement_photos WHERE id = ? AND etablissement_id = ?');
        $stmt->execute([$photoId, $etabId]);
        $fichier = $stmt->fetchColumn();

        if ($fichier === false) {
            $erreur = 'Cette photo n\'existe plus.';
        } else {
            if ($action === 'supprimer') {
                $pdo->prepare('DELETE FROM etablissement_photos WHERE id = ? AND etablissement_id = ?')->execute([$photoId, $etabId]);
                deleteStoredImage((string) $fichier, venuePhotoDir());
                flashPro('Photo supprimée.');
            } else {
                $pdo->prepare('UPDATE etablissement_photos SET position = position + 1 WHERE etablissement_id = ?')->execute([$etabId]);
                $pdo->prepare('UPDATE etablissement_photos SET position = 0 WHERE id = ? AND etablissement_id = ?')->execute([$photoId, $etabId]);
                flashPro('Photo de couverture mise à jour.');
            }
            header('Location: ' . baseUrl('/partenaire/photos.php'));
            exit;
        }
    }
}

$stmt = $pdo->prepare('SELECT * FROM etablissement_photos WHERE etablissement_id = ? ORDER BY position ASC, id ASC');
$stmt->execute([$etabId]);
$photos = $stmt->fetchAll();
$plein  = count($photos) >= PHOTOS_ETABLISSEMENT_MAX;

proDebut('Photos', 'photos', $etab);
?>
<?= messageFlashPro() ?>
<header class="pro-entete">
  <div>
    <p class="surtitre">Photos de l'établissement</p>
    <h1 class="titre-pro">Donnez envie <em>de venir.</em></h1>
    <p class="pro-entete__sous">Elles s'affichent sur votre fiche dans l'application. La première sert de couverture.</p>
  </div>
</header>

<?php if ($erreur !== ''): ?>
  <div class="message message--erreur" role="alert" style="margin-bottom:20px;"><?= iconeSite('alerte') ?><span><?= htmlspecialchars($erreur) ?></span></div>
<?php endif; ?>

<div class="grille-tdb">
  <section class="carte" aria-labelledby="t-galerie">
    <div class="carte__tete">
      <h2 class="carte__titre" id="t-galerie">Vos photos</h2>
      <span class="carte__sous"><?= count($photos) ?> / <?= PHOTOS_ETABLISSEMENT_MAX ?></span>
    </div>
    <?php if (!$photos): ?>
      <div class="vide">
        <div class="vide__icone"><?= iconeSite('photo', 'icon-lg') ?></div>
        <h3>Aucune photo pour l'instant</h3>
        <p>Une salle pleine, une terrasse, un cocktail maison : les fiches avec photos attirent davantage d'inscriptions.</p>
      </div>
    <?php else: ?>
      <div class="photos">
        <?php foreach ($photos as $i => $photo): ?>
          <figure class="photo" style="margin:0;">
            <img src="<?= htmlspecialchars(venuePhotoUrl((string) $photo['fichier']), ENT_QUOTES) ?>" alt="<?= htmlspecialchars((string) ($photo['legende'] ?: 'Photo de ' . $etab['nom']), ENT_QUOTES) ?>" loading="lazy">
            <?php if ($i === 0): ?><span class="pastille">Couverture</span><?php endif; ?>
            <div class="photo__actions">
              <?php if ($i !== 0): ?>
                <form method="post">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="couverture">
                  <input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>">
                  <button type="submit" class="bouton bouton--clair bouton--petit">En couverture</button>
                </form>
              <?php else: ?><span></span><?php endif; ?>
              <form method="post" data-confirmer="Supprimer cette photo ?">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="supprimer">
                <input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>">
                <button type="submit" class="bouton-icone bouton-icone--danger"><?= iconeSite('poubelle', '', 'Supprimer') ?></button>
              </form>
            </div>
          </figure>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <aside class="pile">
    <section class="carte" aria-labelledby="t-ajout">
      <h2 class="carte__titre" id="t-ajout" style="margin-bottom:16px;">Ajouter une photo</h2>
      <?php if ($plein): ?>
        <p class="carte__sous">Vous avez atteint la limite de <?= PHOTOS_ETABLISSEMENT_MAX ?> photos. Supprimez-en une pour en ajouter une autre.</p>
      <?php else: ?>
        <form method="post" enctype="multipart/form-data" class="formulaire">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="ajouter">
          <div class="champ">
            <label for="photo">Image</label>
            <input type="file" id="photo" name="photo" accept="image/jpeg,image/png,image/webp" required class="saisie" style="padding:10px;">
            <span class="champ__aide">JPEG, PNG ou WebP, 2 Mo au plus.</span>
          </div>
          <div class="champ">
            <label for="legende">Légende <span class="champ__aide">(facultatif)</span></label>
            <input type="text" id="legende" name="legende" maxlength="160" placeholder="La terrasse un vendredi soir">
          </div>
          <button type="submit" class="bouton bouton--plein bouton--bloc"><?= iconeSite('telecharger') ?> Ajouter</button>
        </form>
      <?php endif; ?>
    </section>
  </aside>
</div>
<?php proFin(); ?>
