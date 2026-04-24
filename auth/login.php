<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if ($email && $pass) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($pass, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']     = $user['id'];
            $_SESSION['user_prenom'] = $user['prenom'];
            $_SESSION['user_nom']    = $user['nom'];
            $_SESSION['user_type']   = $user['type'];
            $_SESSION['user_ecole']  = $user['ecole'];

            $loc = $user['type'] === 'partenaire'
                ? baseUrl('/partenaire/dashboard.php')
                : baseUrl('/explore.php');
            header('Location: ' . $loc);
            exit;
        } else {
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
<link rel="stylesheet" href="<?= baseUrl() ?>/assets/css/style.css">
</head>
<body>
<div class="auth-page">
  <div class="auth-logo">
    <div class="brand">StudentLink <em>/ Explorer</em></div>
  </div>

  <div class="auth-headline">
    <div class="display" style="font-size:2.4rem;">Content de</div>
    <div class="display-italic" style="font-size:2.4rem;">te revoir.</div>
  </div>

  <?php if ($error): ?>
    <div class="form-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <div class="form-group">
      <label for="email">Email</label>
      <input type="email" id="email" name="email"
             value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
             placeholder="arthur@uca.fr" required autofocus>
    </div>
    <div class="form-group">
      <label for="password">Mot de passe</label>
      <input type="password" id="password" name="password" placeholder="••••••••" required>
    </div>
    <button type="submit" class="btn btn-primary btn-full" style="margin-top:8px;">
      → Se connecter
    </button>
  </form>

  <div class="auth-link">
    Pas encore de compte ? <a href="/auth/register.php">Créer un compte</a>
  </div>

  <div style="margin-top:32px; padding:16px; background:rgba(0,0,0,0.05); border-radius:6px; font-size:12px; color:var(--gris);">
    <strong>Comptes de démo :</strong><br>
    Étudiant : arthur@uca.fr / password<br>
    Partenaire : jean@lebecquipique.fr / password
  </div>
</div>
</body>
</html>
