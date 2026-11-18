<?php
/**
 * Création d'un espace établissement.
 *
 * Le site n'inscrit plus d'étudiants : leur compte se crée dans
 * l'application (api/v1/inscription.php), seul endroit qui leur sert
 * d'interface. Ce formulaire reprend les règles de l'API pour les
 * partenaires — mêmes contrôles, même vérification initiale selon le type
 * (verificationInitiale()) — pour qu'un compte créé ici et un compte créé
 * dans l'app soient identiques.
 *
 * ?formule=essentiel (lien d'un tarif de la vitrine) est retenu en session
 * et présélectionne la formule à l'étape suivante. Il ne décide de rien :
 * la formule réellement souscrite est revérifiée sur la page Abonnement.
 */
require_once __DIR__ . '/../includes/site.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/comptes.php';
require_once __DIR__ . '/../includes/crm.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: ' . accueilSelonType($_SESSION['user_type'] ?? null));
    exit;
}

$formuleVoulue = (string) ($_GET['formule'] ?? '');
if (isset(crmOffres()[$formuleVoulue]) && $formuleVoulue !== 'aucune') {
    $_SESSION['formule_souhaitee'] = $formuleVoulue;
}

$types  = typesEtablissement();
$erreur = '';
$v      = static fn (string $cle, string $defaut = ''): string => trim((string) ($_POST[$cle] ?? $defaut));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();

    // Même garde qu'au login, seuil plus large : la page répond différemment
    // selon qu'un e-mail existe déjà.
    $ip = ipClient();

    $prenom    = $v('prenom');
    $nom       = $v('nom');
    $email     = $v('email');
    $pass      = (string) ($_POST['password'] ?? '');
    $naissance = $v('date_naissance');
    $etabNom   = $v('etablissement_nom');
    $etabType  = typeEtablissementValide($_POST['etablissement_type'] ?? null) ? (string) $_POST['etablissement_type'] : '';
    // Seule ville ouverte pour l'instant : plus de champ, enregistrée d'office.
    $ville     = 'Clermont-Ferrand';
    $cgu       = !empty($_POST['cgu']);
    $age       = ageEnAnnees($naissance);
    $min       = longueurMinimaleMotDePasse('partenaire');

    if (limiteAtteinte($pdo, 'inscription', $ip, 20)) {
        $erreur = 'Trop de tentatives. Réessayez dans quinze minutes.';
    } elseif ($prenom === '' || $nom === '' || $email === '' || $pass === '' || $etabNom === '' || $etabType === '') {
        $erreur = 'Merci de remplir tous les champs.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erreur = "Cette adresse e-mail n'est pas valide.";
    } elseif (strlen($pass) < $min) {
        $erreur = "Le mot de passe doit faire au moins $min caractères.";
    } elseif ($age === null || $age > 120) {
        $erreur = 'Merci d’indiquer une date de naissance valide.';
    } elseif ($age < 18) {
        $erreur = 'La personne qui gère le compte doit être majeure.';
    } elseif (!$cgu) {
        $erreur = 'Merci d’accepter les conditions générales pour continuer.';
    } else {
        try {
            $pdo->beginTransaction();
            $pdo->prepare(
                "INSERT INTO users (nom, prenom, email, password, date_naissance, cgu_acceptees_le, type)
                 VALUES (?,?,?,?,?,NOW(),'partenaire')"
            )->execute([$nom, $prenom, $email, password_hash($pass, PASSWORD_DEFAULT), $naissance]);
            $userId = (int) $pdo->lastInsertId();

            $pdo->prepare('INSERT INTO etablissements (user_id, nom, type, ville, verification) VALUES (?,?,?,?,?)')
                ->execute([$userId, $etabNom, $etabType, $ville, verificationInitiale($etabType)]);
            $pdo->commit();
            enregistrerTentative($pdo, 'inscription', $ip);
            emailBienvenueEtablissement($email, $prenom, $etabNom, $etabType);

            session_regenerate_id(true);
            $_SESSION['user_id']     = $userId;
            $_SESSION['user_prenom'] = $prenom;
            $_SESSION['user_nom']    = $nom;
            $_SESSION['user_type']   = 'partenaire';
            $_SESSION['user_ecole']  = '';

            // Première étape d'un nouvel établissement : choisir sa formule.
            header('Location: ' . baseUrl('/partenaire/abonnement.php?bienvenue=1'));
            exit;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (($e->errorInfo[1] ?? null) === 1062) {
                enregistrerTentative($pdo, 'inscription', $ip);
                $erreur = 'Un compte existe déjà avec cette adresse. Connectez-vous.';
            } else {
                logErreur('Inscription établissement impossible', $e);
                $erreur = 'Le compte n’a pas pu être créé. Réessayez dans un instant.';
            }
        }
    }
}

$borneMajeur   = (new DateTimeImmutable('today'))->modify('-18 years')->format('Y-m-d');
$bornePlancher = (new DateTimeImmutable('today'))->modify('-120 years')->format('Y-m-d');

siteDebut('Créer mon espace pro — Outsee', ['description' => "Créez l'espace Outsee de votre bar, boîte, restaurant, club sportif ou BDE."]);
?>
<div class="auth">
  <div class="auth__formulaire">
    <div class="auth__haut">
      <?= marqueSite('pro', baseUrl('/')) ?>
      <a class="bouton bouton--texte" href="<?= htmlspecialchars(baseUrl('/'), ENT_QUOTES) ?>"><?= iconeSite('fleche-g', 'icon-sm') ?> Retour au site</a>
    </div>

    <main class="auth__corps" id="contenu" style="max-width:520px;">
      <p class="surtitre" style="color:var(--bleu);">Espace établissement</p>
      <h1 class="auth__titre" style="margin-top:10px;">Créez votre <em>espace pro.</em></h1>
      <p class="auth__sous">Vous choisirez votre formule juste après. Vous êtes étudiant ? <a href="<?= htmlspecialchars(baseUrl('/application.php'), ENT_QUOTES) ?>" style="color:var(--bleu);font-weight:600;">Inscris-toi dans l'application</a>.</p>

      <form class="auth__form" method="post" novalidate>
        <?= csrfField() ?>
        <?php if ($erreur !== ''): ?>
          <div class="message message--erreur" role="alert"><?= iconeSite('alerte') ?><span><?= htmlspecialchars($erreur) ?></span></div>
        <?php endif; ?>

        <div class="champ">
          <label for="etablissement_nom">Nom de l'établissement</label>
          <input type="text" id="etablissement_nom" name="etablissement_nom" required maxlength="120" value="<?= htmlspecialchars($v('etablissement_nom')) ?>" placeholder="Le Comptoir de Jaude">
        </div>
        <div class="champ">
          <label for="etablissement_type">Type</label>
          <select id="etablissement_type" name="etablissement_type" required>
            <option value="">Choisir</option>
            <?php foreach ($types as $code => $t): ?>
              <option value="<?= htmlspecialchars($code) ?>"<?= $v('etablissement_type') === $code ? ' selected' : '' ?>><?= htmlspecialchars($t['libelle']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="formulaire__ligne">
          <div class="champ">
            <label for="prenom">Prénom</label>
            <input type="text" id="prenom" name="prenom" autocomplete="given-name" required value="<?= htmlspecialchars($v('prenom')) ?>">
          </div>
          <div class="champ">
            <label for="nom">Nom</label>
            <input type="text" id="nom" name="nom" autocomplete="family-name" required value="<?= htmlspecialchars($v('nom')) ?>">
          </div>
        </div>
        <div class="champ">
          <label for="email">E-mail professionnel</label>
          <input type="email" id="email" name="email" autocomplete="email" required value="<?= htmlspecialchars($v('email')) ?>" placeholder="contact@votre-bar.fr">
        </div>
        <div class="formulaire__ligne">
          <div class="champ">
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password" autocomplete="new-password" required minlength="<?= longueurMinimaleMotDePasse('partenaire') ?>" aria-describedby="aide-mdp">
            <span class="champ__aide" id="aide-mdp"><?= longueurMinimaleMotDePasse('partenaire') ?> caractères minimum.</span>
          </div>
          <div class="champ">
            <label for="date_naissance">Date de naissance</label>
            <input type="date" id="date_naissance" name="date_naissance" autocomplete="bday" required min="<?= $bornePlancher ?>" max="<?= $borneMajeur ?>" value="<?= htmlspecialchars($v('date_naissance')) ?>" aria-describedby="aide-naissance">
            <span class="champ__aide" id="aide-naissance">Le gérant du compte doit être majeur.</span>
          </div>
        </div>

        <label class="case">
          <input type="checkbox" name="cgu" value="1" required<?= !empty($_POST['cgu']) ? ' checked' : '' ?>>
          <span>J'accepte les <a href="<?= htmlspecialchars(baseUrl('/cgu.php'), ENT_QUOTES) ?>" target="_blank" rel="noopener">conditions générales d'utilisation</a>, les <a href="<?= htmlspecialchars(baseUrl('/cgv.php'), ENT_QUOTES) ?>" target="_blank" rel="noopener">conditions générales de vente</a> et la <a href="<?= htmlspecialchars(baseUrl('/confidentialite.php'), ENT_QUOTES) ?>" target="_blank" rel="noopener">politique de confidentialité</a>.</span>
        </label>

        <button type="submit" class="bouton bouton--plein bouton--grand bouton--bloc">Créer mon espace <?= iconeSite('fleche-d', 'icon-sm') ?></button>
      </form>

      <p class="auth__bas">Déjà un compte ? <a href="<?= htmlspecialchars(baseUrl('/auth/login.php'), ENT_QUOTES) ?>">Se connecter</a></p>
    </main>
  </div>

  <aside class="auth__visuel" aria-hidden="true">
    <p class="surtitre" style="color:var(--bleu-clair);margin-bottom:16px;">En trois étapes</p>
    <h2>Votre première soirée publiée <em>ce soir.</em></h2>
    <ul class="auth__points">
      <li><?= iconeSite('check') ?> Créez votre espace</li>
      <li><?= iconeSite('check') ?> Choisissez votre formule</li>
      <li><?= iconeSite('check') ?> Publiez, les étudiants s'inscrivent</li>
    </ul>
  </aside>
</div>
<?php siteFin(); ?>
