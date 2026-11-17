<?php
/**
 * Connexion à l'espace établissement.
 *
 * Le site ne sert plus d'interface aux étudiants : un compte étudiant qui
 * saisit ses identifiants n'ouvre pas de session web, il est renvoyé vers
 * l'application (application.php). Le contrôle se fait APRÈS la vérification
 * du mot de passe : annoncer « c'est un compte étudiant » à qui n'a pas le
 * bon mot de passe dirait quelles adresses existent.
 *
 * Les comptes administrateurs passent toujours par ici (back-office).
 */
require_once __DIR__ . '/../includes/site.php';
require_once __DIR__ . '/../includes/db.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . accueilSelonType($_SESSION['user_type'] ?? null));
    exit;
}

$erreur = '';

// Message posé par expirerSessionInactive() : une déconnexion silencieuse se
// lit comme un bug.
$expiree = !empty($_SESSION['session_expiree']);
unset($_SESSION['session_expiree']);

$ip = ipClient();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();

    $email = trim((string) ($_POST['email'] ?? ''));
    $pass  = (string) ($_POST['password'] ?? '');

    if (isRateLimited($pdo, $ip, 5, 900, $email)) {
        $erreur = 'Trop de tentatives. Réessaie dans quinze minutes.';
    } elseif ($email === '' || $pass === '') {
        $erreur = 'Merci de renseigner ton e-mail et ton mot de passe.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Hachage témoin quand le compte n'existe pas : même durée de réponse
        // dans les deux cas (voir api/v1/login.php).
        $hachage = $user ? (string) $user['password'] : '$2y$12$0000000000000000000000000000000000000000000000000000';
        $valide  = password_verify($pass, $hachage) && $user;

        if (!$valide || ($user['type'] ?? '') === 'supprime') {
            recordLoginAttempt($pdo, $ip, $email);
            $erreur = 'E-mail ou mot de passe incorrect.';
        } else {
            clearLoginAttempts($pdo, $ip, $email);

            if ($user['type'] === 'etudiant') {
                // Aucun accès web pour un compte étudiant : l'application.
                header('Location: ' . baseUrl('/application.php?compte=etudiant'));
                exit;
            }

            session_regenerate_id(true);
            $_SESSION['user_id']     = (int) $user['id'];
            $_SESSION['user_prenom'] = $user['prenom'];
            $_SESSION['user_nom']    = $user['nom'];
            $_SESSION['user_type']   = $user['type'];
            $_SESSION['user_ecole']  = $user['ecole'];

            header('Location: ' . accueilSelonType($user['type']));
            exit;
        }
    }
}

siteDebut('Connexion — Outsee pro', ['description' => "Connectez-vous à l'espace établissement Outsee : événements, scan des pass, statistiques et formule."]);
?>
<div class="auth">
  <div class="auth__formulaire">
    <div class="auth__haut">
      <?= marqueSite('pro', baseUrl('/')) ?>
      <a class="bouton bouton--texte" href="<?= htmlspecialchars(baseUrl('/'), ENT_QUOTES) ?>"><?= iconeSite('fleche-g', 'icon-sm') ?> Retour au site</a>
    </div>

    <main class="auth__corps" id="contenu">
      <p class="surtitre" style="color:var(--bleu);">Espace établissement</p>
      <h1 class="auth__titre" style="margin-top:10px;">Content de vous <em>revoir.</em></h1>
      <p class="auth__sous">Gérez vos événements, scannez les pass et suivez votre mois.</p>

      <form class="auth__form" method="post" novalidate>
        <?= csrfField() ?>

        <?php if ($expiree && $erreur === ''): ?>
          <div class="message message--alerte" role="status"><?= iconeSite('horloge') ?><span>Session expirée après une période d'inactivité. Reconnectez-vous pour continuer.</span></div>
        <?php endif; ?>
        <?php if ($erreur !== ''): ?>
          <div class="message message--erreur" role="alert"><?= iconeSite('alerte') ?><span><?= htmlspecialchars($erreur) ?></span></div>
        <?php endif; ?>

        <div class="champ">
          <label for="email">E-mail</label>
          <input type="email" id="email" name="email" autocomplete="username" required autofocus
                 value="<?= htmlspecialchars((string) ($_POST['email'] ?? '')) ?>" placeholder="contact@votre-bar.fr">
        </div>
        <div class="champ">
          <div class="champ__ligne">
            <label for="password">Mot de passe</label>
            <a href="<?= htmlspecialchars(baseUrl('/auth/forgot.php'), ENT_QUOTES) ?>">Mot de passe oublié ?</a>
          </div>
          <input type="password" id="password" name="password" autocomplete="current-password" required>
        </div>

        <button type="submit" class="bouton bouton--plein bouton--grand bouton--bloc">Se connecter</button>
      </form>

      <p class="auth__bas">Pas encore de compte ? <a href="<?= htmlspecialchars(baseUrl('/auth/register.php'), ENT_QUOTES) ?>">Créer mon espace pro</a></p>
      <p class="auth__bas" style="margin-top:8px;">Vous êtes étudiant ? <a href="<?= htmlspecialchars(baseUrl('/application.php'), ENT_QUOTES) ?>">Tout se passe dans l'application</a></p>
    </main>
  </div>

  <aside class="auth__visuel" aria-hidden="true">
    <p class="surtitre" style="color:var(--bleu-clair);margin-bottom:16px;">Outsee pro</p>
    <h2>Les étudiants de la ville, <em>chez vous ce soir.</em></h2>
    <ul class="auth__points">
      <li><?= iconeSite('check') ?> Publiez une soirée en deux minutes</li>
      <li><?= iconeSite('check') ?> Suivez les inscriptions en direct</li>
      <li><?= iconeSite('check') ?> Scannez les pass à l'entrée</li>
    </ul>
  </aside>
</div>
<?php siteFin(); ?>
