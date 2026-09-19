<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/gamification.php';
require_once __DIR__ . '/includes/agregats.php';
require_once __DIR__ . '/includes/temps_reel.php';
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
    csrfVerify();
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

            // La note moyenne de l'etablissement vient d'un cache partage de
            // cinq minutes. Sans cet oubli explicite, l'etudiant qui vient de
            // noter verrait l'ancienne etoile et conclurait que son avis
            // s'est perdu.
            oublierNotesEtablissements();
            fluxToucher($pdo, canalEvenement($eid));

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
<link rel="stylesheet" href="<?= asset('/assets/css/style.css') ?>">
<style>
  .rate-page { max-width: 520px; margin: 0 auto; padding: 32px 24px 100px; }
  .stars { display: flex; gap: 8px; justify-content: center; margin: 24px 0; }
  .star {
    font-size: var(--fs-10); cursor: pointer; color: var(--gris-clair);
    transition: transform .12s, color .12s;
    user-select: none;
  }
  .star svg { fill: none; transition: fill .12s ease; }
  .star:hover, .star.filled { color: var(--lime-deep); transform: scale(1.1); }
  .star:hover svg, .star.filled svg { fill: currentColor; }
  .star.filled { color: var(--lime-deep); }
  .comment-box {
    width: 100%; border: 1px solid var(--gris-clair); background: var(--blanc);
    padding: 14px; font-family: var(--font-sans); font-size: var(--fs-4);
    min-height: 120px; resize: vertical; box-sizing: border-box;
  }
</style>
</head>
<body>
<div class="rate-page">
  <a href="<?= baseUrl('/wallet.php') ?>" style="color:var(--gris);font-size:var(--fs-3);text-decoration:none;">← Retour au wallet</a>

  <div style="margin-top:24px;margin-bottom:8px;font-size:var(--fs-1);font-weight:var(--fw-bold);text-transform:uppercase;letter-spacing:var(--ls-wide);color:var(--gris);">
    <?= htmlspecialchars($event['etab_nom']) ?>
  </div>
  <h1 style="font-family:var(--font-display);font-weight:var(--fw-black);font-size:var(--fs-8);line-height:var(--lh-tight);margin-bottom:8px;">
    <?= htmlspecialchars($event['titre']) ?>
  </h1>
  <div style="font-size:var(--fs-3);color:var(--gris);margin-bottom:32px;">
    <?= dateFr($event['date_heure'], 'j F · H\hi') ?>
  </div>

  <?php if ($success): ?>
    <div style="background:var(--lime);color:var(--sur-media-encre);border:1px solid var(--gris-clair);box-shadow:var(--shadow);padding:20px;text-align:center;font-weight:var(--fw-bold);margin-bottom:24px;">
      Merci pour ton avis !
    </div>
  <?php endif; ?>

  <?= csrfFlash() ?>
  <?php if ($error): ?>
    <div style="background:var(--rouge-clair);border:2px solid var(--rouge);padding:14px;color:var(--sur-rouge-clair);font-weight:var(--fw-semibold);margin-bottom:24px;">
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <form method="POST">
    <?= csrfField() ?>
    <div id="stars-label" style="font-size:var(--fs-3);font-weight:var(--fw-bold);text-transform:uppercase;letter-spacing:var(--ls-wide);text-align:center;">Ta note</div>
    <div class="stars" id="stars" role="radiogroup" aria-labelledby="stars-label" aria-required="true">
      <?php for ($i = 1; $i <= 5; $i++): ?>
        <span class="star <?= ($existing && $existing['note'] >= $i) ? 'filled' : '' ?>"
              role="radio"
              aria-label="<?= $i ?> étoile<?= $i > 1 ? 's' : '' ?>"
              aria-checked="<?= ($existing && $existing['note'] == $i) ? 'true' : 'false' ?>"
              tabindex="<?= ($existing && $existing['note'] == $i) || (!$existing && $i == 1) ? '0' : '-1' ?>"
              data-value="<?= $i ?>"><?= icon('etoile') ?></span>
      <?php endfor; ?>
    </div>
    <input type="hidden" name="note" id="note-input" value="<?= $existing['note'] ?? 0 ?>">

    <label for="commentaire" style="font-size:var(--fs-3);font-weight:var(--fw-bold);text-transform:uppercase;letter-spacing:var(--ls-wide);display:block;margin-bottom:8px;margin-top:24px;">
      Ton commentaire <span style="font-weight:var(--fw-regular);text-transform:none;">(optionnel)</span>
    </label>
    <textarea id="commentaire" class="comment-box" name="commentaire" placeholder="Raconte-nous ta soirée..." aria-describedby="commentaire-hint"><?= htmlspecialchars($existing['commentaire'] ?? '') ?></textarea>
    <span id="commentaire-hint" class="sr-only">Maximum 1000 caractères</span>

    <button type="submit" class="btn btn-primary btn-full" style="margin-top:20px;font-size:var(--fs-5);padding:16px;">
      <?= $existing ? 'Modifier mon avis' : 'Envoyer mon avis' ?>
    </button>
  </form>
</div>

<script src="<?= asset('/assets/js/app.js') ?>"></script>
<script>
  const stars = document.querySelectorAll('.star');
  const input = document.getElementById('note-input');

  function setRating(v) {
    input.value = v;
    stars.forEach(st => {
      const sv = parseInt(st.dataset.value);
      st.classList.toggle('filled', sv <= v);
      st.setAttribute('aria-checked', sv === v ? 'true' : 'false');
      st.setAttribute('tabindex', sv === v ? '0' : '-1');
    });
  }

  stars.forEach(s => {
    // Clic souris
    s.addEventListener('click', () => setRating(parseInt(s.dataset.value)));
    // Navigation clavier (flèches + espace/entrée)
    s.addEventListener('keydown', e => {
      const v = parseInt(s.dataset.value);
      if (e.key === 'ArrowRight' || e.key === 'ArrowUp') {
        e.preventDefault();
        const next = Math.min(5, v + 1);
        setRating(next);
        document.querySelector(`.star[data-value="${next}"]`).focus();
      } else if (e.key === 'ArrowLeft' || e.key === 'ArrowDown') {
        e.preventDefault();
        const prev = Math.max(1, v - 1);
        setRating(prev);
        document.querySelector(`.star[data-value="${prev}"]`).focus();
      } else if (e.key === ' ' || e.key === 'Enter') {
        e.preventDefault();
        setRating(v);
      }
    });
  });
</script>
<nav class="bottom-nav nav-ordinateur" aria-label="Navigation principale">
  <a href="<?= baseUrl('/explore.php') ?>" class="nav-item">
    <span class="nav-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></span>
    <span>Explore</span>
  </a>
  <a href="<?= baseUrl('/squads.php') ?>" class="nav-item">
    <span class="nav-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg></span>
    <span>Squads</span>
  </a>
  <a href="<?= baseUrl('/wallet.php') ?>" class="nav-item">
    <span class="nav-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg></span>
    <span>Wallet</span>
  </a>
  <a href="<?= baseUrl('/profil.php') ?>" class="nav-item">
    <span class="nav-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></span>
    <span>Moi</span>
  </a>
</nav>

</body>
</html>
