<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!empty($_SESSION['user_id'])) {
    header('Location: /index.php');
    exit;
}

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
$error   = '';
$success = '';

$ecoles = ['UCA', 'SIGMA Clermont', 'INP Ingénieurs', 'IFSI', 'Autre'];
$promos = ['L1','L2','L3','M1','M2','BUT1','BUT2','BUT3'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type    = in_array($_POST['type'] ?? '', ['etudiant','partenaire']) ? $_POST['type'] : 'etudiant';
    $prenom  = trim($_POST['prenom'] ?? '');
    $nom     = trim($_POST['nom'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $pass    = $_POST['password'] ?? '';
    $ecole   = trim($_POST['ecole'] ?? '');
    $promo   = trim($_POST['promo'] ?? '');
    $etablNom  = trim($_POST['etablissement_nom'] ?? '');
    $etablType = in_array($_POST['etablissement_type'] ?? '', ['bar','boite','resto','afterwork'])
        ? $_POST['etablissement_type'] : 'bar';
    $etablVille = trim($_POST['ville'] ?? 'Clermont-Ferrand');

    if (!$prenom || !$nom || !$email || !$pass) {
        $error = 'Merci de remplir tous les champs obligatoires.';
    } elseif (strlen($pass) < 6) {
        $error = 'Le mot de passe doit faire au moins 6 caractères.';
    } else {
        try {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                "INSERT INTO users (nom, prenom, email, password, ecole, promo, type) VALUES (?,?,?,?,?,?,?)"
            );
            $stmt->execute([$nom, $prenom, $email, $hash, $ecole ?: null, $promo ?: null, $type]);
            $userId = $pdo->lastInsertId();

            if ($type === 'partenaire' && $etablNom) {
                $stmt2 = $pdo->prepare(
                    "INSERT INTO etablissements (user_id, nom, type, ville) VALUES (?,?,?,?)"
                );
                $stmt2->execute([$userId, $etablNom, $etablType, $etablVille]);
            }

            session_regenerate_id(true);
            $_SESSION['user_id']     = $userId;
            $_SESSION['user_prenom'] = $prenom;
            $_SESSION['user_nom']    = $nom;
            $_SESSION['user_type']   = $type;
            $_SESSION['user_ecole']  = $ecole;

            $loc = $type === 'partenaire'
                ? baseUrl('/partenaire/dashboard.php')
                : baseUrl('/onboarding.php');
            header('Location: ' . $loc);
            exit;
        } catch (PDOException $e) {
            $error = str_contains($e->getMessage(), 'Duplicate')
                ? 'Cet email est déjà utilisé.'
                : 'Erreur lors de la création du compte.';
        }
    }
}
$selectedType = $_POST['type'] ?? 'etudiant';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StudentLink — Inscription</title>
<?= themeBootScript() ?>
<link rel="stylesheet" href="<?= baseUrl() ?>/assets/css/style.css">
</head>
<body>
<div class="auth-page" style="padding-top:24px;">
  <div class="auth-logo">
    <div class="brand">StudentLink <em>/ Inscription</em></div>
  </div>

  <div class="auth-headline">
    <div class="display" style="font-size:2rem;">Rejoins</div>
    <div class="display-italic" style="font-size:2rem;">la communauté.</div>
  </div>

  <div class="type-toggle" style="margin-bottom:24px;">
    <button type="button" class="type-toggle-btn <?= $selectedType === 'etudiant' ? 'active' : '' ?>" data-type="etudiant">
      Étudiant·e
    </button>
    <button type="button" class="type-toggle-btn <?= $selectedType === 'partenaire' ? 'active' : '' ?>" data-type="partenaire">
      Partenaire
    </button>
  </div>

  <?php if ($error): ?>
    <div class="form-error"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <input type="hidden" name="type" id="user-type-input" value="<?= htmlspecialchars($selectedType) ?>">

    <div class="form-row">
      <div class="form-group">
        <label>Prénom</label>
        <input type="text" name="prenom" value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>" placeholder="Arthur" required>
      </div>
      <div class="form-group">
        <label>Nom</label>
        <input type="text" name="nom" value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>" placeholder="Martin" required>
      </div>
    </div>

    <div class="form-group">
      <label>Email</label>
      <input type="email" name="email" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="arthur@uca.fr" required>
    </div>

    <div class="form-group">
      <label>Mot de passe</label>
      <input type="password" name="password" placeholder="••••••••" required>
    </div>

    <!-- Student fields -->
    <div id="student-fields" <?= $selectedType === 'partenaire' ? 'class="hidden"' : '' ?>>
      <div class="form-row">
        <div class="form-group">
          <label>École</label>
          <select name="ecole">
            <option value="">— Choisir —</option>
            <?php foreach ($ecoles as $e): ?>
              <option value="<?= $e ?>" <?= ($_POST['ecole'] ?? '') === $e ? 'selected' : '' ?>><?= $e ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Promo</label>
          <select name="promo">
            <option value="">—</option>
            <?php foreach ($promos as $p): ?>
              <option value="<?= $p ?>" <?= ($_POST['promo'] ?? '') === $p ? 'selected' : '' ?>><?= $p ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
    </div>

    <!-- Partner fields -->
    <div id="partner-fields" <?= $selectedType !== 'partenaire' ? 'class="hidden"' : '' ?>>
      <div class="form-group">
        <label>Nom de l'établissement</label>
        <input type="text" name="etablissement_nom" value="<?= htmlspecialchars($_POST['etablissement_nom'] ?? '') ?>" placeholder="Le Bec qui Pique">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Type</label>
          <select name="etablissement_type">
            <option value="bar">Bar</option>
            <option value="boite">Boîte</option>
            <option value="resto">Restaurant</option>
            <option value="afterwork">Afterwork</option>
          </select>
        </div>
        <div class="form-group">
          <label>Ville</label>
          <input type="text" name="ville" value="<?= htmlspecialchars($_POST['ville'] ?? 'Clermont-Ferrand') ?>">
        </div>
      </div>
    </div>

    <button type="submit" class="btn btn-primary btn-full" style="margin-top:8px;">
      → Créer mon compte
    </button>
  </form>

  <div class="auth-link">
    Déjà un compte ? <a href="/auth/login.php">Se connecter</a>
  </div>
</div>

<script src="<?= baseUrl() ?>/assets/js/app.js"></script>
</body>
</html>
