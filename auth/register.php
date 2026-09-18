<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/interets.php';
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . baseUrl('/index.php'));
    exit;
}

$error   = '';
$success = '';

$ecoles = ['UCA', 'SIGMA Clermont', 'INP Ingénieurs', 'IFSI', 'Autre'];
$promos = ['L1','L2','L3','M1','M2','BUT1','BUT2','BUT3'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
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
    $naissance  = trim($_POST['date_naissance'] ?? '');
    $cgu        = !empty($_POST['cgu']);

    // Les centres d'intérêt ne concernent que les comptes étudiants, et ne
    // passent que par le catalogue : ce qui arrive du formulaire n'est jamais
    // écrit tel quel en base.
    $interets = $type === 'etudiant' ? filtrerInterets($_POST['interests'] ?? []) : [];

    /*
     * L'âge se calcule à partir de la date, il ne se déclare pas : une case
     * « je certifie être majeur » ne vaut rien, ni pour la revue de l'App
     * Store, ni devant un établissement qui sert de l'alcool.
     */
    $age = ageEnAnnees($naissance);

    if (!$prenom || !$nom || !$email || !$pass) {
        $error = 'Merci de remplir tous les champs obligatoires.';
    } elseif (strlen($pass) < 6) {
        $error = 'Le mot de passe doit faire au moins 6 caractères.';
    } elseif ($age === null) {
        $error = 'Merci d’indiquer une date de naissance valide.';
    } elseif ($age < 18) {
        $error = 'StudentLink est réservée aux personnes majeures : '
               . 'l’inscription n’est pas possible avant 18 ans.';
    } elseif ($age > 120) {
        $error = 'Cette date de naissance ne semble pas correcte.';
    } elseif (!$cgu) {
        $error = 'Merci d’accepter les conditions générales pour continuer.';
    } else {
        try {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare(
                "INSERT INTO users (nom, prenom, email, password, ecole, promo,
                                    date_naissance, cgu_acceptees_le, type, interests)
                 VALUES (?,?,?,?,?,?,?,NOW(),?,?)"
            );
            $stmt->execute([$nom, $prenom, $email, $hash, $ecole ?: null, $promo ?: null,
                            $naissance, $type, interetsVersTexte($interets)]);
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
// Une erreur de formulaire ne doit pas effacer les étiquettes déjà choisies.
$interetsChoisis = filtrerInterets($_POST['interests'] ?? []);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StudentLink — Inscription</title>
<?= themeBootScript() ?>
<link rel="stylesheet" href="<?= asset('/assets/css/style.css') ?>">
</head>
<body>
<div class="auth-page" style="padding-top:24px;">
  <div class="auth-logo">
    <div class="brand">StudentLink <em>/ Inscription</em></div>
  </div>

  <h1 class="auth-headline titre-page">
    <div class="display" style="font-size:var(--fs-8);">Rejoins</div>
    <div class="display-italic" style="font-size:var(--fs-8);">la communauté.</div>
  </h1>

  <div class="type-toggle" style="margin-bottom:24px;">
    <button type="button" class="type-toggle-btn <?= $selectedType === 'etudiant' ? 'active' : '' ?>" data-type="etudiant">
      Étudiant·e
    </button>
    <button type="button" class="type-toggle-btn <?= $selectedType === 'partenaire' ? 'active' : '' ?>" data-type="partenaire">
      Partenaire
    </button>
  </div>

  <?php if ($error): ?>
    <div class="form-error" role="alert" aria-live="assertive"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="type" id="user-type-input" value="<?= htmlspecialchars($selectedType) ?>">

    <div class="form-row">
      <div class="form-group">
        <label for="inscription-prenom">Prénom</label>
        <input id="inscription-prenom" type="text" name="prenom" autocomplete="given-name" value="<?= htmlspecialchars($_POST['prenom'] ?? '') ?>" placeholder="Arthur" required>
      </div>
      <div class="form-group">
        <label for="inscription-nom">Nom</label>
        <input id="inscription-nom" type="text" name="nom" autocomplete="family-name" value="<?= htmlspecialchars($_POST['nom'] ?? '') ?>" placeholder="Martin" required>
      </div>
    </div>

    <div class="form-group">
      <label for="inscription-email">Email</label>
      <input id="inscription-email" type="email" name="email" autocomplete="username" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" placeholder="arthur@uca.fr" required>
    </div>

    <div class="form-group">
      <label for="inscription-password">Mot de passe</label>
      <input type="password" id="inscription-password" name="password" autocomplete="new-password" placeholder="••••••••" required>
    </div>

    <div class="form-group">
      <label for="inscription-naissance">Date de naissance</label>
      <?php
        // Le champ natif propose d'emblée une borne cohérente ; le vrai
        // contrôle reste celui du serveur, juste au-dessus.
        $borneMajeur = (new DateTimeImmutable('today'))->modify('-18 years')->format('Y-m-d');
        $bornePlancher = (new DateTimeImmutable('today'))->modify('-120 years')->format('Y-m-d');
      ?>
      <input type="date" id="inscription-naissance" name="date_naissance"
             autocomplete="bday"
             min="<?= $bornePlancher ?>" max="<?= $borneMajeur ?>"
             value="<?= htmlspecialchars($_POST['date_naissance'] ?? '') ?>"
             aria-describedby="aide-naissance" required>
      <p class="aide-champ" id="aide-naissance">
        StudentLink donne accès à des soirées en bar et en discothèque :
        l’inscription est réservée aux personnes majeures.
      </p>
    </div>

    <!-- Student fields -->
    <div id="student-fields" <?= $selectedType === 'partenaire' ? 'class="hidden"' : '' ?>>
      <div class="form-row">
        <div class="form-group">
          <label for="inscription-ecole">École</label>
          <select id="inscription-ecole" name="ecole">
            <option value="">— Choisir —</option>
            <?php foreach ($ecoles as $e): ?>
              <option value="<?= $e ?>" <?= ($_POST['ecole'] ?? '') === $e ? 'selected' : '' ?>><?= $e ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="inscription-promo">Promo</label>
          <select id="inscription-promo" name="promo">
            <option value="">—</option>
            <?php foreach ($promos as $p): ?>
              <option value="<?= $p ?>" <?= ($_POST['promo'] ?? '') === $p ? 'selected' : '' ?>><?= $p ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label id="label-interets">Centres d'intérêt</label>
        <p class="aide-champ" style="margin-bottom:10px;">
          Ils servent à te proposer des étudiants qui aiment les mêmes choses que toi.
          Tu pourras les changer quand tu veux depuis ton profil.
        </p>
        <?= selecteurInteretsHtml($interetsChoisis) ?>
      </div>
    </div>

    <!-- Partner fields -->
    <div id="partner-fields" <?= $selectedType !== 'partenaire' ? 'class="hidden"' : '' ?>>
      <div class="form-group">
        <label for="inscription-etablissement-nom">Nom de l'établissement</label>
        <input id="inscription-etablissement-nom" type="text" name="etablissement_nom" value="<?= htmlspecialchars($_POST['etablissement_nom'] ?? '') ?>" placeholder="Le Bec qui Pique">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="inscription-etablissement-type">Type</label>
          <select id="inscription-etablissement-type" name="etablissement_type">
            <option value="bar">Bar</option>
            <option value="boite">Boîte</option>
            <option value="resto">Restaurant</option>
            <option value="afterwork">Afterwork</option>
          </select>
        </div>
        <div class="form-group">
          <label for="inscription-ville">Ville</label>
          <input id="inscription-ville" type="text" name="ville" value="<?= htmlspecialchars($_POST['ville'] ?? 'Clermont-Ferrand') ?>">
        </div>
      </div>
    </div>

    <label class="case" style="margin-top:10px; align-items:flex-start; line-height:var(--lh-normal);">
      <input type="checkbox" name="cgu" value="1" style="margin-top:2px;"
             <?= !empty($_POST['cgu']) ? 'checked' : '' ?> required>
      <span>
        J’accepte les
        <a href="<?= baseUrl('/cgu.php') ?>" target="_blank" rel="noopener">conditions générales</a>
        et la
        <a href="<?= baseUrl('/confidentialite.php') ?>" target="_blank" rel="noopener">politique de confidentialité</a>.
      </span>
    </label>

    <button type="submit" class="btn btn-primary btn-full" style="margin-top:14px;">
      → Créer mon compte
    </button>
  </form>

  <div class="auth-link">
    Déjà un compte ? <a href="<?= baseUrl('/auth/login.php') ?>">Se connecter</a>
  </div>
</div>

<script src="<?= asset('/assets/js/app.js') ?>"></script>
</body>
</html>
