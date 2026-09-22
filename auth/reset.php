<?php
// La session est demarree par auth_check.php, qui pose d'abord les
// drapeaux du cookie : la demarrer ici la ferait naitre sans eux.
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$success = false;
$error   = '';

// Valide le token dès l'arrivée sur la page
$email = $token ? validateResetToken($pdo, $token) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $pass1 = $_POST['password'] ?? '';
    $pass2 = $_POST['password_confirm'] ?? '';

    // Le plancher suit le compte, pas le formulaire : un fondateur qui passe
    // par « mot de passe oublié » reste tenu à la règle des comptes fondateurs.
    $stmtType = $pdo->prepare("SELECT type FROM users WHERE email = ?");
    $stmtType->execute([$email]);
    $minimum = longueurMinimaleMotDePasse($stmtType->fetchColumn() ?: null);

    if (!$email) {
        $error = 'Lien invalide ou expiré.';
    } elseif (strlen($pass1) < $minimum) {
        $error = "Le mot de passe doit faire au moins $minimum caractères.";
    } elseif ($pass1 !== $pass2) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        if (consumeResetToken($pdo, $token, $pass1)) {
            $success = true;
        } else {
            $error = 'Lien invalide ou déjà utilisé.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StudentLink — Nouveau mot de passe</title>
<?= themeBootScript() ?>
<?= metaCsrf() ?>
<link rel="stylesheet" href="<?= asset('/assets/css/style.css') ?>">
</head>
<body>
<div class="auth-page">
  <div class="auth-logo">
    <div class="brand">StudentLink <em>/ Sécurité</em></div>
  </div>

  <h1 class="auth-headline titre-page">
    <div class="display" style="font-size:var(--fs-8);">Nouveau</div>
    <div class="display-italic" style="font-size:var(--fs-8);">mot de passe</div>
  </h1>

  <?php if ($success): ?>
    <div style="background:var(--lime);color:var(--sur-media-encre);border:1px solid var(--gris-clair);box-shadow:var(--shadow);padding:20px;margin-bottom:24px;font-weight:var(--fw-bold);text-align:center;">
      <span class="with-icon"><?= icon('valide', 'icon-sm') ?>Mot de passe mis à jour !</span>
    </div>
    <a href="<?= baseUrl('/auth/login.php') ?>" class="btn btn-primary btn-full">
      → Se connecter
    </a>

  <?php elseif (!$email && !$_POST): ?>
    <div class="form-error">
      Lien invalide ou expiré. <a href="<?= baseUrl('/auth/forgot.php') ?>">Faire une nouvelle demande</a>
    </div>

  <?php else: ?>
    <?php if ($error): ?>
      <div class="form-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <p style="font-size:var(--fs-4);color:var(--gris);margin-bottom:24px;">
      Choisis un nouveau mot de passe (8 caractères minimum).
    </p>

    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

      <div class="form-group">
        <label for="password">Nouveau mot de passe</label>
        <input type="password" id="password" name="password" autocomplete="new-password"
               placeholder="••••••••" minlength="8" required autofocus>
      </div>
      <div class="form-group">
        <label for="password_confirm">Confirmer</label>
        <input type="password" id="password_confirm" name="password_confirm"
               placeholder="••••••••" minlength="8" required>
      </div>
      <button type="submit" class="btn btn-primary btn-full" style="margin-top:8px;">
        → Enregistrer
      </button>
    </form>
  <?php endif; ?>

  <div class="auth-link">
    <a href="<?= baseUrl('/auth/login.php') ?>" style="font-size:var(--fs-3);">← Connexion</a>
  </div>
</div>
</body>
</html>
