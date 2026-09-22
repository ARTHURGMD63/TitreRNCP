<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/crm.php';
require_once __DIR__ . '/../includes/sponsoring.php';
requirePartner();
$user = currentUser();
$uid = $user['id'];

$stmt = $pdo->prepare("SELECT * FROM etablissements WHERE user_id=? LIMIT 1");
$stmt->execute([$uid]);
$etab = $stmt->fetch();

exigerAbonnement($pdo, $etab);

if (!$etab) {
    echo "<p>Aucun établissement trouvé. <a href='" . baseUrl('/auth/logout.php') . "'>Déconnexion</a></p>";
    exit;
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event'])) {
    csrfVerify();
    $eid = (int)$_POST['delete_event'];
    $stmt = $pdo->prepare("DELETE FROM evenements WHERE id=? AND etablissement_id=?");
    $stmt->execute([$eid, $etab['id']]);
    header('Location: ' . baseUrl('/partenaire/evenements.php?deleted=1'));
    exit;
}

// Fetch events
$stmt = $pdo->prepare("
    SELECT e.*,
           (SELECT COUNT(*) FROM inscriptions i WHERE i.evenement_id=e.id AND i.statut != 'annule') AS nb_inscrits,
           (SELECT COUNT(*) FROM inscriptions i WHERE i.evenement_id=e.id AND i.statut='checkin') AS nb_checkin
    FROM evenements e
    WHERE e.etablissement_id=?
    ORDER BY e.date_heure DESC
");
$stmt->execute([$etab['id']]);
$evenements = $stmt->fetchAll();

$typeLabels = ['bar'=>'Bar','boite'=>'Boîte','resto'=>'Resto','afterwork'=>'Afterwork'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StudentLink — Mes événements</title>
<?= themeBootScript() ?>
<?= metaCsrf() ?>
<link rel="stylesheet" href="<?= asset('/assets/css/style.css') ?>">
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
    <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:32px;">
      <div>
        <div class="label text-gris" style="margin-bottom:6px;">Gestion des événements</div>
        <h1 class="titre-page" style="font-family:var(--font-display);font-size:var(--fs-9);font-weight:var(--fw-black);line-height:var(--lh-tight);">
          Mes <?= count($evenements) ?> événement<?= count($evenements)>1?'s':'' ?>
        </h1>
      </div>
      <a href="<?= baseUrl('/partenaire/create_event.php') ?>" class="btn btn-primary">
        + Créer un événement
      </a>
    </div>

    <?= csrfFlash() ?>
    <?php if (isset($_GET['deleted'])): ?>
      <div class="form-success">Événement supprimé.</div>
    <?php endif; ?>
    <?php if (isset($_GET['created'])): ?>
      <div class="form-success">Événement créé avec succès !</div>
    <?php endif; ?>
    <?php if (isset($_GET['updated'])): ?>
      <div class="form-success">Événement mis à jour avec succès !</div>
    <?php endif; ?>

    <div style="background:var(--blanc);border-radius:var(--radius);overflow:hidden;">
      <div style="overflow-x:auto;-webkit-overflow-scrolling:touch;">
      <table class="events-table">
        <thead>
          <tr>
            <th>Titre</th>
            <th>Type</th>
            <th>Date</th>
            <th>Inscrits</th>
            <th>Check-in</th>
            <th>Réduction</th>
            <th>Statut</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($evenements)): ?>
          <tr>
            <td colspan="8" style="text-align:center;padding:40px;color:var(--gris);">
              Aucun événement. <a href="<?= baseUrl('/partenaire/create_event.php') ?>" style="color:var(--bleu);font-weight:var(--fw-semibold);">Créez-en un !</a>
            </td>
          </tr>
          <?php endif; ?>
          <?php foreach ($evenements as $e):
            $isPast = strtotime($e['date_heure']) < time();
            $isFull = $e['nb_inscrits'] >= $e['quota'];
          ?>
          <tr>
            <td data-label="Titre">
              <div style="font-weight:var(--fw-semibold);"><?= htmlspecialchars($e['titre']) ?></div>
              <?php if ($e['is_flash']): ?><span class="badge badge-flash" style="margin-top:4px;display:inline-block;">Flash</span><?php endif; ?>
              <?php if ($e['is_gratuit']): ?><span class="badge badge-gratuit" style="margin-top:4px;display:inline-block;">Gratuit</span><?php endif; ?>
              <?php if (!empty($e['is_sponsorise'])): ?>
                <span class="badge badge-sponsorise" style="margin-top:4px;display:inline-block;"
                      title="<?= htmlspecialchars(libelleSponsoring($e['sponsor_formule'])) ?><?= $e['sponsor_jusqu_au'] ? " · jusqu'au " . dateFr($e['sponsor_jusqu_au'], 'j M') : '' ?>">
                  <?= sponsoringActif($e) ? 'Sponsorisé' : 'Sponsoring terminé' ?>
                </span>
              <?php endif; ?>
            </td>
            <td data-label="Type"><span class="badge badge-<?= $e['type'] ?>"><?= $typeLabels[$e['type']] ?></span></td>
            <td data-label="Date" style="white-space:nowrap;color:var(--gris-fonce);"><?= dateFr($e['date_heure'], 'D j M · H\hi') ?></td>
            <td data-label="Inscrits">
              <div style="font-weight:var(--fw-bold);"><?= $e['nb_inscrits'] ?>/<?= $e['quota'] ?></div>
              <div style="height:3px;background:var(--gris-clair);border-radius:2px;margin-top:4px;width:60px;overflow:hidden;">
                <div style="height:100%;background:var(--rouge);width:<?= $e['quota']>0?round($e['nb_inscrits']/$e['quota']*100):0 ?>%;border-radius:2px;"></div>
              </div>
            </td>
            <td data-label="Check-in" style="font-weight:var(--fw-bold);"><?= $e['nb_checkin'] ?></td>
            <td data-label="Réduction"><?= $e['reduction'] > 0 ? '-'.$e['reduction'].'%' : ($e['is_gratuit'] ? 'Gratuit' : '—') ?></td>
            <td data-label="Statut">
              <?php if ($isPast): ?>
                <span style="color:var(--gris);font-size:var(--fs-2);font-weight:var(--fw-semibold);">Passé</span>
              <?php elseif ($isFull): ?>
                <span style="color:var(--sur-rouge-clair);font-size:var(--fs-2);font-weight:var(--fw-semibold);">Complet</span>
              <?php else: ?>
                <span style="color:var(--succes);font-size:var(--fs-2);font-weight:var(--fw-semibold);">Actif</span>
              <?php endif; ?>
            </td>
            <td data-label="Actions" style="white-space:nowrap;">
              <a href="<?= baseUrl('/partenaire/edit_event.php?id=' . $e['id']) ?>"
                 class="btn-icon"
                 title="Modifier" aria-label="Modifier"><svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></a>
              <form method="POST" onsubmit="return confirm('Supprimer cet événement ?')" style="display:inline;">
                <?= csrfField() ?>
                <input type="hidden" name="delete_event" value="<?= $e['id'] ?>">
                <button type="submit" class="btn-icon danger" title="Supprimer"><svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
  </main>
</div>
</body>
</html>
