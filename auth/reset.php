<?php
// La session est demarree par auth_check.php, qui pose d'abord les
// drapeaux du cookie : la demarrer ici la ferait naitre sans eux.
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/site.php';
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
<?php siteDebut('Nouveau mot de passe — Outsee'); ?>
<div class="auth">
  <div class="auth__formulaire">
    <div class="auth__haut">
      <?= marqueSite('', baseUrl('/')) ?>
    </div>
    <main class="auth__corps" id="contenu">
      <h1 class="auth__titre">Nouveau <em>mot de passe.</em></h1>
      <div class="auth__form">
        <?php if ($success): ?>
          <div class="message message--succes" role="status"><?= iconeSite('check') ?><span>Mot de passe modifié. Connectez-vous avec le nouveau : dans l'application si vous êtes étudiant, ici si vous gérez un établissement.</span></div>
          <a class="bouton bouton--plein bouton--bloc" href="<?= htmlspecialchars(baseUrl('/auth/login.php'), ENT_QUOTES) ?>">Se connecter</a>
        <?php elseif (!$email): ?>
          <div class="message message--erreur" role="alert"><?= iconeSite('alerte') ?><span>Ce lien est invalide ou a expiré. Demandez-en un nouveau.</span></div>
          <a class="bouton bouton--clair bouton--bloc" href="<?= htmlspecialchars(baseUrl('/auth/forgot.php'), ENT_QUOTES) ?>">Recevoir un nouveau lien</a>
        <?php else: ?>
          <form method="post" class="auth__form" style="margin-top:0;" novalidate>
            <?= csrfField() ?>
            <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES) ?>">
            <?php if ($error): ?>
              <div class="message message--erreur" role="alert"><?= iconeSite('alerte') ?><span><?= htmlspecialchars($error) ?></span></div>
            <?php endif; ?>
            <div class="champ">
              <label for="password">Nouveau mot de passe</label>
              <input type="password" id="password" name="password" autocomplete="new-password" required autofocus>
            </div>
            <div class="champ">
              <label for="password_confirm">Confirmation</label>
              <input type="password" id="password_confirm" name="password_confirm" autocomplete="new-password" required>
            </div>
            <button type="submit" class="bouton bouton--plein bouton--grand bouton--bloc">Enregistrer</button>
          </form>
        <?php endif; ?>
      </div>
    </main>
  </div>
  <aside class="auth__visuel" aria-hidden="true">
    <h2>Un nouveau départ, <em>en deux champs.</em></h2>
  </aside>
</div>
<?php siteFin(); ?>
