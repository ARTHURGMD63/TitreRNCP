<?php
// La session est demarree par auth_check.php, qui pose d'abord les
// drapeaux du cookie : la demarrer ici la ferait naitre sans eux.
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page.php';
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
        // Le lien du message doit être absolu : il est suivi depuis un client
        // de messagerie, qui n'a aucun contexte de page. Schéma réellement
        // servi, hôte demandé, puis le préfixe d'installation calculé par
        // prefixeApplication() — et non plus deviné d'après le nom d'hôte, ce
        // qui produisait un lien sans « /TitreRNCP » dès qu'on ouvrait le site
        // à autre chose que localhost.
        $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $enHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $base    = ($enHttps ? 'https://' : 'http://') . $host . baseUrl('');

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
<?php pageDebut('Linkee — Mot de passe oublié'); ?>
<div class="auth-page">
  <div class="auth-logo">
    <a class="bouton-retour" href="<?= baseUrl('/auth/login.php') ?>" aria-label="Retour à la connexion"><?= icon('fleche-g') ?></a>
  </div>

  <h1 class="auth-headline titre-page">
    <div class="display" style="font-size:var(--fs-8);">Mot de passe</div>
    <div class="display-italic" style="font-size:var(--fs-8);">oublié ?</div>
  </h1>

  <?php if ($sent): ?>
    <div class="encart-ok" role="status">
      Si cet email est associé à un compte, tu recevras un lien de réinitialisation dans quelques minutes.
    </div>
    <?php if ($devLink): ?>
      <div class="encart-info">
        <strong class="with-icon"><?= icon('outil', 'icon-sm') ?>Mode dev — lien de réinitialisation :</strong><br>
        <a href="<?= htmlspecialchars($devLink) ?>" style="word-break:break-all;"><?= htmlspecialchars($devLink) ?></a>
      </div>
    <?php endif; ?>
  <?php else: ?>
    <?php if ($error): ?>
      <div class="form-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <p style="font-size:var(--fs-5);color:var(--gris-fonce);line-height:var(--lh-normal);margin-bottom:24px;">
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
        Envoyer le lien
      </button>
    </form>
  <?php endif; ?>

  <div class="auth-link">
    <a href="<?= baseUrl('/auth/login.php') ?>" style="font-weight:var(--fw-medium);color:var(--gris);">Retour à la connexion</a>
  </div>
</div>
</body>
</html>
