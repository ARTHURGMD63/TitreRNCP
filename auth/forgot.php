<?php
// La session est demarree par auth_check.php, qui pose d'abord les
// drapeaux du cookie : la demarrer ici la ferait naitre sans eux.
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$sent  = false;
$error = '';
$devLink = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
    } else {
        $token = createPasswordResetToken($pdo, $email);
        // On envoie toujours un message de succès (même si email inconnu) → anti-enumération
        $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $isLocal = str_contains($host, 'localhost') || str_contains($host, '127.0.0.1');
        $base    = $isLocal ? 'http://' . $host . '/TitreRNCP' : 'https://' . $host;

        if ($token) {
            sendResetEmail($email, $token, $base);
        }
        $sent = true;
        // Mode dev : récupère le lien stocké en session
        $devLink = $_SESSION['dev_reset_link'] ?? null;
        unset($_SESSION['dev_reset_link']);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StudentLink — Mot de passe oublié</title>
<?= themeBootScript() ?>
<link rel="stylesheet" href="<?= asset('/assets/css/style.css') ?>">
</head>
<body>
<div class="auth-page">
  <div class="auth-logo">
    <div class="brand">StudentLink <em>/ Sécurité</em></div>
  </div>

  <h1 class="auth-headline titre-page">
    <div class="display" style="font-size:var(--fs-8);">Mot de passe</div>
    <div class="display-italic" style="font-size:var(--fs-8);">oublié ?</div>
  </h1>

  <?php if ($sent): ?>
    <div style="background:var(--lime);color:var(--sur-media-encre);border:1px solid var(--gris-clair);box-shadow:var(--shadow);padding:20px;margin-bottom:24px;font-weight:var(--fw-bold);">
      Si cet email est associé à un compte, tu recevras un lien de réinitialisation dans quelques minutes.
    </div>
    <?php if ($devLink): ?>
      <div style="background:var(--bleu-clair);border:2px solid var(--bleu);padding:16px;margin-bottom:24px;font-size:var(--fs-3);">
        <strong class="with-icon"><?= icon('outil', 'icon-sm') ?>Mode dev — lien de réinitialisation :</strong><br>
        <a href="<?= htmlspecialchars($devLink) ?>" style="word-break:break-all;"><?= htmlspecialchars($devLink) ?></a>
      </div>
    <?php endif; ?>
  <?php else: ?>
    <?php if ($error): ?>
      <div class="form-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <p style="font-size:var(--fs-4);color:var(--gris);margin-bottom:24px;">
      Saisis ton adresse email. Si elle est associée à un compte, tu recevras un lien valable <strong>1 heure</strong>.
    </p>

    <form method="POST">
      <?= csrfField() ?>
      <div class="form-group">
        <label for="email">Adresse email</label>
        <input type="email" id="email" name="email" autocomplete="username"
               placeholder="arthur@uca.fr" required autofocus>
      </div>
      <button type="submit" class="btn btn-primary btn-full" style="margin-top:8px;">
        → Envoyer le lien
      </button>
    </form>
  <?php endif; ?>

  <div class="auth-link">
    <a href="<?= baseUrl('/auth/login.php') ?>" style="font-size:var(--fs-3);">← Retour à la connexion</a>
  </div>
</div>
</body>
</html>
