<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/gamification.php';
requireStudent();
$user = currentUser();
$uid = $user['id'];

$eid = (int)($_GET['event_id'] ?? 0);
if (!$eid) { header('Location: ' . baseUrl('/wallet.php')); exit; }

// Check that user attended (checkin required)
$stmt = $pdo->prepare("SELECT i.id FROM inscriptions i WHERE i.user_id=? AND i.evenement_id=? AND i.statut='checkin'");
$stmt->execute([$uid, $eid]);
if (!$stmt->fetch()) {
    header('Location: ' . baseUrl('/wallet.php?error=no_checkin'));
    exit;
}

$stmt = $pdo->prepare("SELECT e.*, et.nom AS etab_nom FROM evenements e JOIN etablissements et ON et.id=e.etablissement_id WHERE e.id=?");
$stmt->execute([$eid]);
$event = $stmt->fetch();
if (!$event) { header('Location: ' . baseUrl('/wallet.php')); exit; }

$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $note = (int)($_POST['note'] ?? 0);
    $commentaire = substr(trim($_POST['commentaire'] ?? ''), 0, 1000);

    if ($note < 1 || $note > 5) {
        $error = 'Note invalide.';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO avis (user_id, evenement_id, note, commentaire) VALUES (?,?,?,?)
                                   ON DUPLICATE KEY UPDATE note=VALUES(note), commentaire=VALUES(commentaire)");
            $stmt->execute([$uid, $eid, $note, $commentaire]);
            checkBadges($pdo, $uid);
            $success = true;
        } catch (PDOException $e) {
            $error = 'Erreur lors de l\'enregistrement.';
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM avis WHERE user_id=? AND evenement_id=?");
$stmt->execute([$uid, $eid]);
$existing = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StudentLink — Laisser un avis</title>
<?= themeBootScript() ?>
<link rel="stylesheet" href="<?= baseUrl() ?>/assets/css/style.css">
<style>
  .rate-page { max-width: 520px; margin: 0 auto; padding: 32px 24px 100px; }
  .stars { display: flex; gap: 8px; justify-content: center; margin: 24px 0; }
  .star {
    font-size: 42px; cursor: pointer; color: var(--gris-clair);
    transition: transform .12s, color .12s;
    user-select: none;
  }
  .star:hover, .star.filled { color: #F5B400; transform: scale(1.1); }
  .star.filled { color: #F5B400; }
  .comment-box {
    width: 100%; border: 2px solid var(--noir); background: var(--blanc);
    padding: 14px; font-family: 'DM Sans', sans-serif; font-size: 14px;
    min-height: 120px; resize: vertical; box-sizing: border-box;
  }
</style>
</head>
<body>
<div class="rate-page">
  <a href="<?= baseUrl('/wallet.php') ?>" style="color:var(--gris);font-size:13px;text-decoration:none;">← Retour au wallet</a>

  <div style="margin-top:24px;margin-bottom:8px;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--gris);">
    <?= htmlspecialchars($event['etab_nom']) ?>
  </div>
  <h1 style="font-family:'Playfair Display',serif;font-weight:900;font-size:2rem;line-height:1.1;margin-bottom:8px;">
    <?= htmlspecialchars($event['titre']) ?>
  </h1>
  <div style="font-size:13px;color:var(--gris);margin-bottom:32px;">
    <?= date('j F · H\hi', strtotime($event['date_heure'])) ?>
  </div>

  <?php if ($success): ?>
    <div style="background:var(--lime);border:2px solid var(--noir);box-shadow:4px 4px 0 var(--noir);padding:20px;text-align:center;font-weight:700;margin-bottom:24px;">
      Merci pour ton avis !
    </div>
  <?php endif; ?>

  <?php if ($error): ?>
    <div style="background:var(--rouge-clair);border:2px solid var(--rouge);padding:14px;color:var(--rouge);font-weight:600;margin-bottom:24px;">
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <form method="POST">
    <div style="font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;text-align:center;">Ta note</div>
    <div class="stars" id="stars">
      <?php for ($i = 1; $i <= 5; $i++): ?>
        <span class="star <?= ($existing && $existing['note'] >= $i) ? 'filled' : '' ?>" data-value="<?= $i ?>">★</span>
      <?php endfor; ?>
    </div>
    <input type="hidden" name="note" id="note-input" value="<?= $existing['note'] ?? 0 ?>">

    <div style="font-size:13px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;margin-top:24px;">
      Ton commentaire (optionnel)
    </div>
    <textarea class="comment-box" name="commentaire" placeholder="Raconte-nous ta soirée..."><?= htmlspecialchars($existing['commentaire'] ?? '') ?></textarea>

    <button type="submit" class="btn btn-primary btn-full" style="margin-top:20px;font-size:15px;padding:16px;">
      <?= $existing ? 'Modifier mon avis' : 'Envoyer mon avis' ?>
    </button>
  </form>
</div>

<script src="<?= baseUrl() ?>/assets/js/app.js"></script>
<script>
  const stars = document.querySelectorAll('.star');
  const input = document.getElementById('note-input');
  stars.forEach(s => {
    s.addEventListener('click', () => {
      const v = parseInt(s.dataset.value);
      input.value = v;
      stars.forEach(st => st.classList.toggle('filled', parseInt(st.dataset.value) <= v));
    });
  });
</script>
</body>
</html>
