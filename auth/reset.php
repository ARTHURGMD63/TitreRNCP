<?php
if (session_status() === PHP_SESSION_NONE) session_start();
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

    if (!$email) {
        $error = 'Lien invalide ou expiré.';
    } elseif (strlen($pass1) < 8) {
        $error = 'Le mot de passe doit faire au moins 8 caractères.';
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
<link rel="stylesheet" href="<?= baseUrl() ?>/assets/css/style.css">
</head>
<body>
<div class="auth-page">
  <div class="auth-logo">
    <div class="brand">StudentLink <em>/ Sécurité</em></div>
  </div>

  <div class="auth-headline">
    <div class="display" style="font-size:2rem;">Nouveau</div>
    <div class="display-italic" style="font-size:2rem;">mot de passe</div>
  </div>

  <?php if ($success): ?>
    <div style="background:var(--lime);border:2px solid var(--noir);box-shadow:4px 4px 0 var(--noir);padding:20px;margin-bottom:24px;font-weight:700;text-align:center;">
      ✅ Mot de passe mis à jour !
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

    <p style="font-size:14px;color:var(--gris);margin-bottom:24px;">
      Choisis un nouveau mot de passe (8 caractères minimum).
    </p>

    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

      <div class="form-group">
        <label for="password">Nouveau mot de passe</label>
        <input type="password" id="password" name="password"
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
    <a href="<?= baseUrl('/auth/login.php') ?>" style="font-size:13px;">← Connexion</a>
  </div>
</div>
</body>
</html>
