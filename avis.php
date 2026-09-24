<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/page.php';
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
<?php ob_start(); ?>
<style>
  .rate-page { max-width: 520px; margin: 0 auto; padding: 32px 24px 100px; }
  /* Maquette 09 : la note dans une surface, étoiles moutarde. */
  .carte-note {
    background: var(--blanc); border: 1px solid var(--gris-clair); border-radius: var(--radius);
    padding: 22px 16px 10px;
  }
  .stars { display: flex; gap: 8px; justify-content: center; margin: 14px 0; }
  .star {
    font-size: var(--fs-10); cursor: pointer; color: var(--line-2);
    transition: transform .12s, color .12s;
    user-select: none;
  }
  .star svg { fill: none; transition: fill .12s ease; }
  .star:hover, .star.filled { color: var(--orange); transform: scale(1.1); }
  .star:hover svg, .star.filled svg { fill: currentColor; }
  .star.filled { color: var(--orange); }
  .comment-box {
    width: 100%; border: 1px solid var(--gris-clair); background: var(--blanc); color: var(--noir);
    border-radius: var(--radius-md);
    padding: 16px; font-family: var(--font-sans); font-size: var(--fs-5);
    min-height: 130px; resize: vertical; box-sizing: border-box;
  }
  .comment-box:focus { outline: none; border-color: var(--rouge); }
  .etiquette-avis {
    font-family: var(--font-mono); font-size: var(--fs-1); font-weight: var(--fw-medium);
    text-transform: uppercase; letter-spacing: var(--ls-label); color: var(--gris);
  }
</style>
<?php pageDebut('Linkee — Laisser un avis', ['tete' => ob_get_clean()]); ?>
<div class="rate-page">
  <a href="<?= baseUrl('/wallet.php') ?>" style="color:var(--gris);font-size:var(--fs-4);text-decoration:none;">← Retour au pass</a>

  <div class="etiquette-avis" style="margin-top:24px;margin-bottom:8px;color:var(--sur-rouge-clair);">
    <?= htmlspecialchars($event['etab_nom']) ?>
  </div>
  <h1 style="font-family:var(--font-display);font-weight:var(--fw-black);font-size:var(--fs-8);line-height:var(--lh-tight);letter-spacing:var(--ls-display);margin-bottom:8px;">
    <?= htmlspecialchars($event['titre']) ?>
  </h1>
  <div style="font-family:var(--font-mono);font-size:var(--fs-3);color:var(--gris);margin-bottom:28px;">
    <?= dateFr($event['date_heure'], 'j F · H\hi') ?>
  </div>

  <?php if ($success): ?>
    <div class="encart-ok" role="status">
      Merci pour ton avis !
    </div>
  <?php endif; ?>

  <?= csrfFlash() ?>
  <?php if ($error): ?>
    <div class="form-error" role="alert">
      <?= htmlspecialchars($error) ?>
    </div>
  <?php endif; ?>

  <form method="POST">
    <?= csrfField() ?>
    <div class="carte-note">
    <div id="stars-label" class="etiquette-avis" style="text-align:center;">Ta note</div>
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
    </div>
    <input type="hidden" name="note" id="note-input" value="<?= $existing['note'] ?? 0 ?>">

    <label for="commentaire" class="etiquette-avis" style="display:block;margin-bottom:8px;margin-top:24px;">
      Ton commentaire <span style="font-weight:var(--fw-regular);text-transform:none;">(optionnel)</span>
    </label>
    <textarea id="commentaire" class="comment-box" name="commentaire" placeholder="Raconte-nous ta soirée..." aria-describedby="commentaire-hint"><?= htmlspecialchars($existing['commentaire'] ?? '') ?></textarea>
    <span id="commentaire-hint" class="sr-only">Maximum 1000 caractères</span>

    <button type="submit" class="btn btn-primary btn-full" style="margin-top:24px;">
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
  <span class="nav-marque" aria-hidden="true"><?= marqueLinkee() ?></span>
  <a href="<?= baseUrl('/explore.php') ?>" class="nav-item">
    <span class="nav-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></span>
    <span>Explorer</span>
  </a>
  <a href="<?= baseUrl('/squads.php') ?>" class="nav-item">
    <span class="nav-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg></span>
    <span>Squads</span>
  </a>
  <a href="<?= baseUrl('/wallet.php') ?>" class="nav-item">
    <span class="nav-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg></span>
    <span>Pass</span>
  </a>
  <a href="<?= baseUrl('/profil.php') ?>" class="nav-item">
    <span class="nav-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></span>
    <span>Moi</span>
  </a>
</nav>

</body>
</html>
