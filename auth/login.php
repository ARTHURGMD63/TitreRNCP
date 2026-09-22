<?php
// La session est demarree par auth_check.php, qui pose d'abord les
// drapeaux du cookie : la demarrer ici la ferait naitre sans eux.
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . accueilSelonType($_SESSION['user_type'] ?? null));
    exit;
}
$error = '';

// Message pose par expirerSessionInactive() : une deconnexion silencieuse
// se lit comme un bug, et on recommence a taper son mot de passe sans
// comprendre pourquoi.
$expiree = !empty($_SESSION['session_expiree']);
unset($_SESSION['session_expiree']);

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ip = trim(explode(',', $ip)[0]); // Prend la première IP si proxy

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();

    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    // Rate limiting
    if (isRateLimited($pdo, $ip)) {
        $error = 'Trop de tentatives. Réessaye dans 15 minutes.';
    } elseif ($email && $pass) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($pass, $user['password'])) {
            clearLoginAttempts($pdo, $ip);
            session_regenerate_id(true);
            $_SESSION['user_id']     = $user['id'];
            $_SESSION['user_prenom'] = $user['prenom'];
            $_SESSION['user_nom']    = $user['nom'];
            $_SESSION['user_type']   = $user['type'];
            $_SESSION['user_ecole']  = $user['ecole'];

            header('Location: ' . accueilSelonType($user['type']));
            exit;
        } else {
            recordLoginAttempt($pdo, $ip, $email);
            $error = 'Email ou mot de passe incorrect.';
        }
    } else {
        $error = 'Merci de remplir tous les champs.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StudentLink — Connexion</title>
<?= themeBootScript() ?>
<?= metaCsrf() ?>
<link rel="stylesheet" href="<?= asset('/assets/css/style.css') ?>">
</head>
<body>
<div class="auth-page">
  <div class="auth-logo">
    <div class="brand">StudentLink <em>/ Explorer</em></div>
  </div>

  <h1 class="auth-headline titre-page">
    <div class="display" style="font-size:var(--fs-9);">Content de</div>
    <div class="display-italic" style="font-size:var(--fs-9);">te revoir.</div>
  </h1>

  <?php if ($expiree && !$error): ?>
    <div class="form-error" role="status" aria-live="polite"
         style="background:var(--alerte-clair);color:var(--alerte);border-color:var(--alerte);">
      Session expirée après une période d'inactivité. Reconnecte-toi pour continuer.
    </div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="form-error" role="alert" aria-live="assertive"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <?= csrfField() ?>
    <div class="form-group">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" autocomplete="username"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
             placeholder="arthur@uca.fr" required autofocus>
    </div>
    <div class="form-group">
      <label for="password">Mot de passe</label>
      <input type="password" id="password" name="password" autocomplete="current-password" placeholder="••••••••" required>
    </div>
    <button type="submit" class="btn btn-primary btn-full" style="margin-top:8px;">
      → Se connecter
    </button>
  </form>

  <div class="auth-link" style="margin-top:12px;">
    <a href="<?= baseUrl('/auth/forgot.php') ?>" style="font-size:var(--fs-3);color:var(--gris);">Mot de passe oublié ?</a>
  </div>

  <div class="auth-link">
    Pas encore de compte ? <a href="<?= baseUrl('/auth/register.php') ?>">Créer un compte</a>
  </div>

  <div style="margin-top:32px; padding:16px; background:rgba(0,0,0,0.05); border-radius:var(--radius); font-size:var(--fs-2); color:var(--gris);">
    <strong>Comptes de démo :</strong><br>
    Étudiant : arthur@uca.fr / password<br>
    Partenaire : jean@lebecquipique.fr / password
  </div>
</div>
</body>
</html>
