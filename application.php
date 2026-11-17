<?php
/**
 * « Outsee, c'est dans l'application. »
 *
 * Page d'arrivée de tout étudiant qui passe par le site : connexion avec un
 * compte étudiant, ancien lien vers une page étudiante, inscription
 * commencée sur le web. Le site ne propose plus d'interface étudiante ; la
 * session web d'un étudiant éventuellement encore ouverte est fermée ici.
 */
require_once __DIR__ . '/includes/site.php';

if (($_SESSION['user_type'] ?? null) === 'etudiant') {
    $_SESSION = [];
    session_regenerate_id(true);
}

$depuisConnexion = ($_GET['compte'] ?? '') === 'etudiant';

siteDebut("Outsee, c'est dans l'application", [
    'classe'      => 'vitrine',
    'styles'      => ['/assets/css/vitrine.css'],
    'description' => "Les soirées, le sport et la culture étudiante : tout se passe dans l'application Outsee, sur iPhone et Android.",
]);
?>
<div class="lp-cadre">
  <header class="lp-nav">
    <nav class="lp-nav__barre" aria-label="Navigation">
      <?= marqueSite('', baseUrl('/')) ?>
      <div class="lp-nav__actions">
        <a class="pilule pilule--grise pilule--petite" href="<?= htmlspecialchars(baseUrl('/auth/login.php'), ENT_QUOTES) ?>">Espace pro</a>
      </div>
    </nav>
  </header>

  <main class="lp-interieur lp-appli" id="contenu">
    <div style="padding-bottom:72px;">
      <?php if ($depuisConnexion): ?>
        <span class="lp-note"><b>Compte étudiant</b>Connecte-toi dans l'application avec les mêmes identifiants</span>
      <?php else: ?>
        <span class="lp-note"><b>Gratuit</b>Sur iPhone et Android</span>
      <?php endif; ?>
      <h1 class="lp-titre">Outsee, c'est <span>dans l'appli.</span></h1>
      <p class="lp-sous">Les soirées, le sport et la culture de ta ville, ton pass et tes potes : tout se passe sur ton téléphone. Télécharge l'application et connecte-toi avec ton compte.</p>
      <div class="lp-actions"><?= boutonsStores() ?></div>
      <p class="lp-sous" style="font-size:15px;">Tu gères un bar, un club ou un BDE ? <a href="<?= htmlspecialchars(baseUrl('/auth/login.php'), ENT_QUOTES) ?>" style="color:var(--bleu);font-weight:600;">Ouvre l'espace pro</a>.</p>
    </div>
    <div class="lp-appli__visuel">
      <img src="<?= htmlspecialchars(asset('/assets/img/site/telephone-1.jpg'), ENT_QUOTES) ?>" width="469" height="872" alt="L'accueil de l'application Outsee : les bons plans du moment.">
    </div>
  </main>

  <?= piedSite() ?>
</div>
<?php siteFin(); ?>
