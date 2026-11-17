<?php
// La session est demarree par auth_check.php, qui pose d'abord les
// drapeaux du cookie : la demarrer ici la ferait naitre sans eux.
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/site.php';
require_once __DIR__ . '/../includes/db.php';

$sent  = false;
$error = '';
$devLink = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $email = trim($_POST['email'] ?? '');
    $ip    = ipClient();

    // Sans cette garde, connaître l'adresse d'une victime suffisait à la
    // bombarder de mails de réinitialisation à volonté.
    if (isRateLimited($pdo, $ip, 5, 900)) {
        $error = 'Trop de tentatives. Réessaie dans quinze minutes.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse email invalide.';
    } else {
        recordLoginAttempt($pdo, $ip, $email);
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
<?php siteDebut('Mot de passe oublié — Outsee'); ?>
<div class="auth">
  <div class="auth__formulaire">
    <div class="auth__haut">
      <?= marqueSite('', baseUrl('/')) ?>
      <a class="bouton bouton--texte" href="<?= htmlspecialchars(baseUrl('/auth/login.php'), ENT_QUOTES) ?>"><?= iconeSite('fleche-g', 'icon-sm') ?> Connexion</a>
    </div>
    <main class="auth__corps" id="contenu">
      <h1 class="auth__titre">Mot de passe <em>oublié ?</em></h1>
      <p class="auth__sous">Indiquez l'adresse de votre compte : vous recevrez un lien pour en choisir un nouveau, valable une heure.</p>

      <div class="auth__form">
        <?php if ($sent): ?>
          <div class="message message--succes" role="status"><?= iconeSite('check') ?><span>Si un compte existe avec cette adresse, un e-mail vient de partir. Pensez à regarder dans les indésirables.</span></div>
          <?php if ($devLink): ?>
            <div class="message message--alerte"><?= iconeSite('info') ?><span>Mode développement : <a href="<?= htmlspecialchars($devLink, ENT_QUOTES) ?>">ouvrir le lien de réinitialisation</a></span></div>
          <?php endif; ?>
          <a class="bouton bouton--clair bouton--bloc" href="<?= htmlspecialchars(baseUrl('/auth/login.php'), ENT_QUOTES) ?>">Retour à la connexion</a>
        <?php else: ?>
          <form method="post" class="auth__form" style="margin-top:0;" novalidate>
            <?= csrfField() ?>
            <?php if ($error): ?>
              <div class="message message--erreur" role="alert"><?= iconeSite('alerte') ?><span><?= htmlspecialchars($error) ?></span></div>
            <?php endif; ?>
            <div class="champ">
              <label for="email">E-mail</label>
              <input type="email" id="email" name="email" autocomplete="email" required autofocus value="<?= htmlspecialchars((string) ($_POST['email'] ?? '')) ?>">
            </div>
            <button type="submit" class="bouton bouton--plein bouton--grand bouton--bloc">Recevoir le lien</button>
          </form>
        <?php endif; ?>
      </div>
      <p class="auth__bas">Étudiant ? Le mot de passe se change aussi depuis l'application.</p>
    </main>
  </div>
  <aside class="auth__visuel" aria-hidden="true">
    <h2>On vous remet <em>sur la piste.</em></h2>
  </aside>
</div>
<?php siteFin(); ?>
