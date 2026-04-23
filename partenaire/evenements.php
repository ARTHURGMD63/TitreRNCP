<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
requirePartner();
$user = currentUser();
$uid = $user['id'];

$stmt = $pdo->prepare("SELECT * FROM etablissements WHERE user_id=? LIMIT 1");
$stmt->execute([$uid]);
$etab = $stmt->fetch();

if (!$etab) {
    echo "<p>Aucun établissement trouvé. <a href='/TitreRNCP/auth/logout.php'>Déconnexion</a></p>";
    exit;
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event'])) {
    $eid = (int)$_POST['delete_event'];
    $stmt = $pdo->prepare("DELETE FROM evenements WHERE id=? AND etablissement_id=?");
    $stmt->execute([$eid, $etab['id']]);
    header('Location: /TitreRNCP/partenaire/evenements.php?deleted=1');
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
<link rel="stylesheet" href="/TitreRNCP/assets/css/style.css">
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
      <a href="/TitreRNCP/partenaire/dashboard.php" class="sidebar-link">
        <span class="icon">📊</span> Dashboard
      </a>
      <a href="/TitreRNCP/partenaire/evenements.php" class="sidebar-link active">
        <span class="icon">🎉</span> Événements
      </a>
      <a href="/TitreRNCP/partenaire/create_event.php" class="sidebar-link">
        <span class="icon">➕</span> Créer un event
      </a>
    </nav>
    <div class="sidebar-venue" style="margin-top:48px;padding-top:20px;border-top:1px solid rgba(255,255,255,0.1);">
      <div class="sidebar-venue-name"><?= htmlspecialchars(strtoupper($etab['nom'])) ?></div>
      <div class="sidebar-venue-city"><?= htmlspecialchars($etab['ville']) ?></div>
      <a href="/TitreRNCP/auth/logout.php" style="display:block;margin-top:12px;font-size:12px;color:rgba(255,255,255,0.4);text-decoration:none;">
        → Déconnexion
      </a>
    </div>
  </aside>

  <main class="partner-main">
    <div style="display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:32px;">
      <div>
        <div class="label text-gris" style="margin-bottom:6px;">Gestion des événements</div>
        <div style="font-family:'Playfair Display',serif;font-size:2.4rem;font-weight:900;line-height:1.1;">
          Mes <?= count($evenements) ?> événement<?= count($evenements)>1?'s':'' ?>
        </div>
      </div>
      <a href="/TitreRNCP/partenaire/create_event.php" class="btn btn-primary">
        + Créer un événement
      </a>
    </div>

    <?php if (isset($_GET['deleted'])): ?>
      <div class="form-success">Événement supprimé.</div>
    <?php endif; ?>
    <?php if (isset($_GET['created'])): ?>
      <div class="form-success">Événement créé avec succès !</div>
    <?php endif; ?>

    <div style="background:var(--blanc);border-radius:var(--radius);overflow:hidden;">
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
              Aucun événement. <a href="/TitreRNCP/partenaire/create_event.php" style="color:var(--bleu);font-weight:600;">Créez-en un !</a>
            </td>
          </tr>
          <?php endif; ?>
          <?php foreach ($evenements as $e):
            $isPast = strtotime($e['date_heure']) < time();
            $isFull = $e['nb_inscrits'] >= $e['quota'];
          ?>
          <tr>
            <td>
              <div style="font-weight:600;"><?= htmlspecialchars($e['titre']) ?></div>
              <?php if ($e['is_flash']): ?><span class="badge badge-flash" style="margin-top:4px;display:inline-block;">Flash</span><?php endif; ?>
              <?php if ($e['is_gratuit']): ?><span class="badge badge-bar" style="margin-top:4px;display:inline-block;">Gratuit</span><?php endif; ?>
            </td>
            <td><span class="badge badge-<?= $e['type'] ?>"><?= $typeLabels[$e['type']] ?></span></td>
            <td style="white-space:nowrap;color:var(--gris-fonce);"><?= date('D j M · H\hi', strtotime($e['date_heure'])) ?></td>
            <td>
              <div style="font-weight:700;"><?= $e['nb_inscrits'] ?>/<?= $e['quota'] ?></div>
              <div style="height:3px;background:var(--gris-clair);border-radius:2px;margin-top:4px;width:60px;overflow:hidden;">
                <div style="height:100%;background:var(--rouge);width:<?= $e['quota']>0?round($e['nb_inscrits']/$e['quota']*100):0 ?>%;border-radius:2px;"></div>
              </div>
            </td>
            <td style="font-weight:700;"><?= $e['nb_checkin'] ?></td>
            <td><?= $e['reduction'] > 0 ? '-'.$e['reduction'].'%' : ($e['is_gratuit'] ? 'Gratuit' : '—') ?></td>
            <td>
              <?php if ($isPast): ?>
                <span style="color:var(--gris);font-size:12px;font-weight:600;">Passé</span>
              <?php elseif ($isFull): ?>
                <span style="color:var(--rouge);font-size:12px;font-weight:600;">Complet</span>
              <?php else: ?>
                <span style="color:#2e7d32;font-size:12px;font-weight:600;">Actif</span>
              <?php endif; ?>
            </td>
            <td>
              <form method="POST" onsubmit="return confirm('Supprimer cet événement ?')">
                <input type="hidden" name="delete_event" value="<?= $e['id'] ?>">
                <button type="submit" style="background:none;border:none;color:var(--rouge);cursor:pointer;font-size:16px;" title="Supprimer">🗑</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>
</body>
</html>
