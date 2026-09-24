<?php
/**
 * Page d'accueil publique — compte à rebours avant le lancement.
 *
 * Remplace l'ancienne vitrine à deux publics (étudiants / établissements) :
 * tant que includes/auth_check.php est en mode « bientôt disponible »
 * (verifierGateBientotDisponible()), c'est la SEULE page joignable du site —
 * tout le reste (explore.php, auth/register.php, le tableau de bord
 * partenaire…) redirige ici. Rien à faire de spécial pour ça : le blocage
 * vit au même endroit pour toutes les pages, cette page-ci n'a qu'à exister.
 *
 * Le compteur de la section « Pass Fondateur » lit la vraie table
 * liste_attente plutôt que d'afficher un chiffre inventé : un compte à
 * rebours marketing qui ment sur ses propres chiffres n'inspire pas
 * confiance à des étudiants qui, eux, vont vérifier.
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/page.php';
require_once __DIR__ . '/includes/db.php';

// Déjà connecté (un administrateur, en pratique — verifierGateBientotDisponible()
// laisse tout le reste hors mode « bientôt disponible ») : la page d'attente
// ne lui apprend rien, il attend son écran.
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . accueilSelonType($_SESSION['user_type'] ?? null));
    exit;
}

// Date de lancement : réglable sans toucher au code (variable d'environnement
// APP_LAUNCH_DATE), pour ne pas avoir à redéployer juste pour la décaler.
$lancementIso = reglage('APP_LAUNCH_DATE', 'lancement_date', '2026-11-05T19:00:00+01:00');
$lancementTs  = strtotime($lancementIso) ?: strtotime('2026-11-05T19:00:00+01:00');

// Compteur honnête : le vrai nombre d'inscrits, jamais un chiffre inventé.
// La page d'attente doit s'afficher même si la base est injoignable — c'est
// désormais la porte d'entrée entière du site.
$inscrits = 0;
try {
    $inscrits = (int) $pdo->query('SELECT COUNT(*) FROM liste_attente')->fetchColumn();
} catch (Throwable $e) {
    $inscrits = 0;
}
$objectifFondateurs = 500;
$placesRestantes    = max(0, $objectifFondateurs - $inscrits);

/** Une carte de fonctionnalité : icône, étiquette, titre, texte. */
function baCarte(string $icone, string $etiquette, string $titre, string $texte): string
{
    return '<div class="ba-carte">'
         . '<div class="ba-carte__icone">' . icon($icone) . '</div>'
         . '<span class="ba-carte__etiquette">' . htmlspecialchars($etiquette) . '</span>'
         . '<h3 class="ba-carte__titre">' . htmlspecialchars($titre) . '</h3>'
         . '<p class="ba-carte__texte">' . htmlspecialchars($texte) . '</p>'
         . '</div>';
}

/** Une carte « secrète » : contenu révélé au lancement, pas avant. */
function baCarteSecrete(string $icone, string $titre): string
{
    return '<div class="ba-carte ba-carte--secrete">'
         . '<div class="ba-carte__icone">' . icon($icone) . '</div>'
         . '<span class="ba-carte__etiquette">Secret · révélé le jour J</span>'
         . '<h3 class="ba-carte__titre">' . htmlspecialchars($titre) . '</h3>'
         . '<p class="ba-carte__texte">Une fonctionnalité qu\'aucune app étudiante ne propose encore à Clermont.</p>'
         . '<span class="ba-carte__verrou">' . icon('etoile') . ' Débloqué au lancement</span>'
         . '</div>';
}

/** Une étape numérotée (« comment ça marche »). */
function baEtape(string $numero, string $titre, string $texte): string
{
    return '<li class="ba-etape">'
         . '<span class="ba-etape__rang">' . htmlspecialchars($numero) . '</span>'
         . '<div><h3 class="ba-etape__titre">' . htmlspecialchars($titre) . '</h3>'
         . '<p class="ba-etape__texte">' . htmlspecialchars($texte) . '</p></div>'
         . '</li>';
}

ob_start();
?>
<style>
  /* ═══════════════════════════════════════════════════════════════════
     Page d'attente — préfixe .ba- (« bientôt »), pour ne rien mélanger à
     la grammaire de classes de l'application. Les jetons de couleur, de
     typographie et d'espacement viennent tous de style.css : cette page
     doit ressembler au produit qu'elle annonce, pas à une plaquette à part.
     ═══════════════════════════════════════════════════════════════════ */

  .ba-conteneur { max-width: 1120px; margin: 0 auto; padding: 0 22px; }

  .ba-entete {
    position: sticky; top: 0; z-index: 30;
    background: color-mix(in srgb, var(--bg) 86%, transparent);
    -webkit-backdrop-filter: blur(12px); backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--gris-clair);
  }
  .ba-entete__rangee { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 14px 0; }
  .ba-entete__nav { display: flex; align-items: center; gap: 26px; }
  .ba-entete__lien {
    font-size: var(--fs-4); font-weight: var(--fw-semibold); color: var(--gris-fonce); text-decoration: none;
  }
  .ba-entete__lien:hover { color: var(--noir); }
  @media (max-width: 760px) { .ba-entete__nav { display: none; } }

  .ba-hero { padding: 56px 0 40px; overflow-x: clip; }
  .ba-hero__grille { display: grid; gap: 48px; align-items: center; grid-template-columns: 1.1fr 0.9fr; }
  .ba-hero__grille > * { min-width: 0; }
  @media (max-width: 900px) { .ba-hero__grille { grid-template-columns: 1fr; } }

  .ba-badge {
    display: inline-flex; align-items: center; gap: 8px;
    font-family: var(--font-mono); font-size: var(--fs-2); font-weight: var(--fw-medium);
    letter-spacing: var(--ls-label); text-transform: uppercase; color: var(--gris-fonce);
    background: var(--surface-2); border-radius: var(--radius-pill); padding: 7px 14px 7px 10px;
  }
  .ba-badge__point { width: 8px; height: 8px; border-radius: 50%; background: var(--rouge); flex-shrink: 0; }
  @media (prefers-reduced-motion: no-preference) { .ba-badge__point { animation: ba-pulse 1.8s ease-in-out infinite; } }
  @keyframes ba-pulse { 0%, 100% { opacity: 1; } 50% { opacity: .35; } }

  h1.ba-hero__titre {
    font-family: var(--font-display); font-weight: var(--fw-black);
    line-height: var(--lh-display); letter-spacing: var(--ls-display);
    font-size: clamp(2.25rem, 5vw + 1rem, 4rem);
    margin: 18px 0 0;
  }
  .ba-hero__titre .accent { color: var(--rouge); }
  .ba-hero__texte { font-size: var(--fs-5); line-height: var(--lh-relaxed); color: var(--gris-fonce); max-width: 52ch; margin: 18px 0 0; }

  .ba-countdown { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; max-width: 420px; margin: 28px 0 0; }
  .ba-countdown__surtitre {
    display: block; font-family: var(--font-mono); font-size: var(--fs-1); font-weight: var(--fw-medium);
    letter-spacing: var(--ls-label); text-transform: uppercase; color: var(--gris); margin-bottom: 10px;
  }
  .ba-compte { background: var(--blanc); border: 1px solid var(--gris-clair); border-radius: var(--radius-md); padding: 12px 6px; text-align: center; }
  .ba-compte dd {
    margin: 0; font-family: var(--font-display); font-weight: var(--fw-black); letter-spacing: var(--ls-display);
    font-size: clamp(1.5rem, 3vw, 2rem); line-height: var(--lh-display); font-variant-numeric: tabular-nums;
  }
  .ba-compte dt { font-family: var(--font-mono); font-size: 0.625rem; font-weight: var(--fw-medium); letter-spacing: var(--ls-label); text-transform: uppercase; color: var(--gris); margin-top: 4px; }

  .ba-form { display: flex; gap: 8px; max-width: 460px; margin: 26px 0 0; }
  .ba-form input[type="email"] {
    flex: 1; min-width: 0; font-size: var(--fs-5); padding: 0 16px; height: 52px;
    border: 1px solid var(--gris-clair); border-radius: var(--radius-sm); background: var(--blanc); color: var(--noir);
  }
  .ba-form input[type="email"]:focus-visible { outline: 2px solid var(--rouge); outline-offset: 2px; }
  .ba-form .btn { height: 52px; white-space: nowrap; }
  @media (max-width: 480px) { .ba-form { flex-direction: column; } .ba-form .btn { width: 100%; } }
  .ba-form__retour { font-size: var(--fs-3); margin: 10px 0 0; min-height: 1.4em; }
  .ba-form__retour.est-ok { color: var(--succes); }
  .ba-form__retour.est-erreur { color: var(--danger); }

  .ba-mention { font-size: var(--fs-2); color: var(--gris); margin: 14px 0 0; line-height: var(--lh-normal); }
  .ba-mention a { color: var(--sur-rouge-clair); font-weight: var(--fw-semibold); text-decoration: none; }
  .ba-mention a:hover { text-decoration: underline; }

  /* ── Aperçu du pass, à droite du hero : mockup en CSS, pas une photo
       de banque d'images — ça reste la charte de l'application. ── */
  .ba-apercu { position: relative; min-height: 320px; }
  .ba-apercu__fond {
    position: absolute; inset: 6% 4%; border-radius: var(--radius-xl);
    background: linear-gradient(155deg, var(--rouge) 0%, #FFC23D 100%);
    box-shadow: var(--halo-lave);
  }
  .ba-pass {
    position: relative; margin: 14% 10%; background: var(--noir); color: var(--sur-lave);
    border-radius: var(--radius-xl); padding: 22px; box-shadow: 0 20px 44px rgba(0,0,0,.28);
  }
  [data-theme="dark"] .ba-pass { background: var(--blanc); color: var(--noir); }
  .ba-pass__tampon {
    display: inline-flex; align-items: center; justify-content: center;
    width: 56px; height: 56px; border-radius: var(--radius-md); background: rgba(245,241,232,.12); margin-bottom: 14px;
  }
  [data-theme="dark"] .ba-pass__tampon { background: var(--surface-2); }
  .ba-pass__tampon svg { width: 30px; height: 30px; }
  .ba-pass__etiquette { font-family: var(--font-mono); font-size: var(--fs-1); font-weight: var(--fw-medium); letter-spacing: var(--ls-label); text-transform: uppercase; color: var(--rouge); }
  .ba-pass__titre { font-family: var(--font-display); font-weight: var(--fw-black); font-size: var(--fs-6); margin: 4px 0 2px; }
  .ba-pass__sous { font-size: var(--fs-3); opacity: .7; margin: 0; }

  .ba-chip {
    position: absolute; display: flex; align-items: center; gap: 8px;
    background: var(--blanc); border-radius: var(--radius-pill); padding: 9px 14px;
    box-shadow: 0 10px 24px rgba(0,0,0,.16); font-size: var(--fs-3); font-weight: var(--fw-semibold); color: var(--noir);
  }
  .ba-chip--remise { top: 2%; left: 0; background: var(--rouge); color: var(--sur-lave); font-family: var(--font-display); font-weight: var(--fw-black); }
  .ba-chip--social { bottom: 14%; right: -2%; }
  .ba-chip--xp { top: 44%; right: -4%; background: var(--lime); color: var(--sur-lave); font-family: var(--font-mono); letter-spacing: var(--ls-label); }
  @media (max-width: 900px) { .ba-chip--social, .ba-chip--xp { display: none; } }

  .ba-section { padding: 54px 0; border-top: 1px solid var(--gris-clair); }
  .ba-section__entete { max-width: 620px; margin: 0 auto 32px; text-align: center; }
  .ba-surtitre {
    display: block; font-family: var(--font-mono); font-size: var(--fs-2); font-weight: var(--fw-medium);
    letter-spacing: var(--ls-label); text-transform: uppercase; color: var(--sur-rouge-clair); margin-bottom: 10px;
  }
  h2.ba-titre { font-family: var(--font-display); font-weight: var(--fw-black); font-size: var(--fs-8); line-height: var(--lh-tight); letter-spacing: var(--ls-display); margin: 0; }

  .ba-etapes { list-style: none; margin: 0; padding: 0; display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px; }
  .ba-etape { display: flex; gap: 14px; align-items: flex-start; }
  .ba-etape__rang {
    flex-shrink: 0; width: 34px; height: 34px; border-radius: 50%; background: var(--rouge); color: var(--sur-lave);
    display: flex; align-items: center; justify-content: center; font-family: var(--font-display); font-weight: var(--fw-black); font-size: var(--fs-4);
  }
  .ba-etape__titre { font-size: var(--fs-5); font-weight: var(--fw-bold); margin: 4px 0 6px; }
  .ba-etape__texte { margin: 0; font-size: var(--fs-4); line-height: var(--lh-relaxed); color: var(--gris-fonce); }

  .ba-grille { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; }
  .ba-carte { background: var(--blanc); border: 1px solid var(--gris-clair); border-radius: var(--radius); padding: 24px; }
  .ba-carte__icone {
    display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 50%;
    background: var(--rouge-clair, var(--surface-2)); color: var(--sur-rouge-clair); margin-bottom: 12px;
  }
  .ba-carte__etiquette { display: block; font-family: var(--font-mono); font-size: var(--fs-1); font-weight: var(--fw-medium); letter-spacing: var(--ls-label); text-transform: uppercase; color: var(--gris); margin-bottom: 6px; }
  .ba-carte__titre { font-family: var(--font-display); font-weight: var(--fw-black); font-size: var(--fs-5); line-height: var(--lh-tight); letter-spacing: var(--ls-display); margin: 0 0 8px; }
  .ba-carte__texte { margin: 0; font-size: var(--fs-4); line-height: var(--lh-relaxed); color: var(--gris-fonce); }
  /* Toujours basalte, quel que soit le thème choisi : --noir est le jeton
     « encre », il s'inverse en craie en thème sombre (voir style.css). Même
     convention que .btn-outline-blanc — un aplat volontairement fixe. */
  .ba-carte--secrete { position: relative; background: #111013; color: #F5F1E8; border-color: #111013; }
  .ba-carte--secrete .ba-carte__icone { background: rgba(245,241,232,.14); color: #F5F1E8; }
  .ba-carte--secrete .ba-carte__etiquette { color: rgba(245,241,232,.55); }
  .ba-carte--secrete .ba-carte__texte { color: rgba(245,241,232,.72); }
  .ba-carte__verrou { display: inline-flex; align-items: center; gap: 6px; margin-top: 14px; font-family: var(--font-mono); font-size: var(--fs-2); letter-spacing: var(--ls-label); text-transform: uppercase; color: var(--rouge); }
  .ba-carte__verrou svg { width: 15px; height: 15px; }

  .ba-etablissements { text-align: center; }
  .ba-etablissements .ba-titre { margin-bottom: 14px; }
  .ba-etablissements__texte { max-width: 60ch; margin: 0 auto 26px; font-size: var(--fs-5); line-height: var(--lh-relaxed); color: var(--gris-fonce); }
  .ba-etablissements__actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 12px; }

  /* Toujours basalte, comme .ba-carte--secrete ci-dessus : var(--noir)
     s'inverserait en craie en thème sombre. */
  .ba-fondateur { background: #111013; color: #F5F1E8; border-radius: var(--radius-xl); padding: 44px 28px; text-align: center; }
  .ba-fondateur .ba-surtitre { color: var(--rouge); }
  .ba-fondateur h2.ba-titre { color: #F5F1E8; }
  .ba-fondateur__texte { max-width: 52ch; margin: 14px auto 26px; font-size: var(--fs-5); line-height: var(--lh-relaxed); color: rgba(245,241,232,.75); }
  .ba-fondateur .ba-form { margin: 0 auto; }
  .ba-fondateur .ba-form input[type="email"] { background: rgba(245,241,232,.08); border-color: rgba(245,241,232,.2); color: var(--sur-lave); }
  .ba-fondateur .ba-form input[type="email"]::placeholder { color: rgba(245,241,232,.45); }
  .ba-barre { max-width: 420px; margin: 22px auto 8px; }
  .ba-barre__piste { height: 8px; border-radius: var(--radius-pill); background: rgba(245,241,232,.14); overflow: hidden; }
  .ba-barre__remplie { height: 100%; background: var(--rouge); border-radius: var(--radius-pill); }
  .ba-barre__legende { font-family: var(--font-mono); font-size: var(--fs-2); letter-spacing: var(--ls-label); text-transform: uppercase; color: rgba(245,241,232,.6); margin-top: 8px; }

  .ba-pied { border-top: 1px solid var(--gris-clair); padding: 40px 0 28px; }
  .ba-pied__grille { display: grid; gap: 28px; grid-template-columns: 1.4fr 1fr 1fr 1fr; margin-bottom: 26px; }
  @media (max-width: 700px) { .ba-pied__grille { grid-template-columns: 1fr 1fr; } }
  .ba-pied__texte { font-size: var(--fs-3); color: var(--gris); margin: 12px 0 0; max-width: 32ch; }
  .ba-pied__titre { font-family: var(--font-mono); font-size: var(--fs-1); font-weight: var(--fw-medium); letter-spacing: var(--ls-label); text-transform: uppercase; color: var(--gris); margin: 0 0 10px; }
  .ba-pied__liste { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; }
  .ba-pied__liste a { color: var(--gris-fonce); text-decoration: none; font-size: var(--fs-4); }
  .ba-pied__liste a:hover { color: var(--noir); text-decoration: underline; }
  .ba-pied__bas { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 10px; font-size: var(--fs-3); color: var(--gris); border-top: 1px solid var(--gris-clair); padding-top: 18px; }
  .ba-pied__bas a { color: var(--gris); }
</style>
<?php
$styles = ob_get_clean();

pageDebut('Linkee — Bientôt à Clermont-Ferrand', [
    'pwa'         => true,
    'description' => 'Une nouvelle app arrive pour les étudiants de Clermont-Ferrand : soirées, réductions exclusives dans les bars et boîtes de la ville, squads sportives. Réserve ta place avant l\'ouverture.',
    'scripts'     => ['/assets/js/app.js'],
    'tete'        => $styles,
]);
?>
<header class="ba-entete">
  <div class="ba-conteneur ba-entete__rangee">
    <?= marqueLinkee('', baseUrl('/')) ?>
    <nav class="ba-entete__nav" aria-label="Sections de la page">
      <a class="ba-entete__lien" href="#fonctionnalites">Fonctionnalités</a>
      <a class="ba-entete__lien" href="#etudiants">Étudiants</a>
      <a class="ba-entete__lien" href="#etablissements">Établissements &amp; assos</a>
    </nav>
    <a class="btn btn-primary" href="#inscription">Accès anticipé</a>
  </div>
</header>

<main>
  <section class="ba-hero">
    <div class="ba-conteneur ba-hero__grille">
      <div>
        <span class="ba-badge"><span class="ba-badge__point" aria-hidden="true"></span>Bientôt à Clermont-Ferrand</span>
        <h1 class="ba-hero__titre">Clermont va sortir <span class="accent">autrement.</span></h1>
        <p class="ba-hero__texte">
          Une nouvelle app arrive pour les étudiants&nbsp;: soirées, réductions exclusives dans les bars
          et boîtes de la ville, squads sportives. Les premiers inscrits entrent avant tout le monde.
        </p>

        <div class="ba-countdown" data-lancement="<?= htmlspecialchars(date(DATE_ATOM, $lancementTs), ENT_QUOTES) ?>">
          <span class="ba-countdown__surtitre" style="grid-column: 1 / -1;">Lancement dans</span>
          <dl class="ba-compte"><dd data-compte="jours">00</dd><dt>Jours</dt></dl>
          <dl class="ba-compte"><dd data-compte="heures">00</dd><dt>Heures</dt></dl>
          <dl class="ba-compte"><dd data-compte="minutes">00</dd><dt>Min</dt></dl>
          <dl class="ba-compte"><dd data-compte="secondes">00</dd><dt>Sec</dt></dl>
        </div>

        <form class="ba-form" id="ba-form-hero" novalidate>
          <label class="sr-only" for="ba-email-hero">Ton e-mail étudiant</label>
          <input type="email" id="ba-email-hero" name="email" placeholder="ton.email@etu.uca.fr" autocomplete="email" required>
          <button class="btn btn-primary" type="submit">Réserver ma place</button>
        </form>
        <p class="ba-form__retour" data-retour-pour="ba-form-hero" role="status" aria-live="polite"></p>

        <p class="ba-mention">
          Gratuit pour les étudiants · Tu gères un lieu ou une asso&nbsp;?
          <a href="#etablissements">Devenir partenaire →</a>
        </p>
      </div>

      <div class="ba-apercu" aria-hidden="true">
        <div class="ba-apercu__fond"></div>
        <span class="ba-chip ba-chip--remise">-40&nbsp;% ce soir</span>
        <div class="ba-pass">
          <div class="ba-pass__tampon"><?= icon('appareil') ?></div>
          <span class="ba-pass__etiquette">Pass actif</span>
          <p class="ba-pass__titre">Afterwork Mix</p>
          <p class="ba-pass__sous">Montre-le à l'entrée</p>
        </div>
        <span class="ba-chip ba-chip--social"><?= icon('personnes') ?> Léa, Hugo +3 y vont</span>
        <span class="ba-chip ba-chip--xp"><?= icon('trophee') ?> +50 XP</span>
      </div>
    </div>
  </section>

  <section class="ba-section" id="fonctionnalites">
    <div class="ba-conteneur">
      <div class="ba-section__entete">
        <span class="ba-surtitre">Comment ça marche</span>
        <h2 class="ba-titre">De ton canapé au comptoir en trois étapes.</h2>
      </div>
      <ol class="ba-etapes">
        <?= baEtape('01', 'Explore', "Les events du moment, les offres flash du soir et les sorties où vont tes amis.") ?>
        <?= baEtape('02', 'Inscris-toi', "Un tap pour réserver ta place. Ton pass QR apparaît aussitôt dans l'app.") ?>
        <?= baEtape('03', 'Scanne et profite', "Montre ton pass à l'entrée : la réduction s'applique et tu gagnes de l'XP.") ?>
      </ol>
    </div>
  </section>

  <section class="ba-section" id="etudiants">
    <div class="ba-conteneur">
      <div class="ba-section__entete">
        <span class="ba-surtitre">Tout ce qu'il y a dans l'app</span>
        <h2 class="ba-titre">Six raisons de l'ouvrir. Deux qu'on garde secrètes.</h2>
      </div>
      <div class="ba-grille">
        <?= baCarte('epingle', 'Explorer', 'Events près de toi', 'Filtre par bars, boîtes, restos ou sport. Carte et liste, triées par date et distance.') ?>
        <?= baCarte('flamme', 'Offres flash', 'Deals à durée limitée', 'Réductions valables quelques heures, avec compte à rebours et places limitées.') ?>
        <?= baCarte('appareil', 'Pass QR', 'Ton entrée en un scan', "Chaque inscription génère un pass. Tu le montres à l'accueil, la réduction s'applique.") ?>
        <?= baCarte('personnes', 'Squads', 'Sport en groupe', 'Running, vélo, muscu, escalade. Rejoins une squad à ton niveau ou crée la tienne.') ?>
        <?= baCarte('personne', 'Amis', 'Vois où ils sortent', "Suis les étudiants de ton école, découvre leurs sorties et invite-les à un event.") ?>
        <?= baCarte('trophee', 'XP & badges', 'Chaque sortie compte', "Check-in, avis, squads : gagne de l'XP, débloque des badges et monte de niveau.") ?>
        <?= baCarteSecrete('lune', 'Mode soirée surprise') ?>
        <?= baCarteSecrete('drapeau', 'Le grand jeu inter-écoles') ?>
      </div>
    </div>
  </section>

  <section class="ba-section ba-etablissements" id="etablissements">
    <div class="ba-conteneur">
      <span class="ba-surtitre">Pour les établissements &amp; associations</span>
      <h2 class="ba-titre">Touchez les étudiants là où ils décident de sortir.</h2>
      <p class="ba-etablissements__texte">
        Bars, boîtes, restaurants, BDE et associations étudiantes&nbsp;: Linkee pro vous donne les outils
        pour publier vos événements, scanner les pass à l'entrée et mesurer ce qu'ils vous rapportent —
        dès le jour du lancement.
      </p>
      <div class="ba-etablissements__actions">
        <a class="btn btn-primary" href="mailto:contact@linkee.fr?subject=Devenir%20partenaire%20Linkee">Devenir partenaire</a>
        <a class="btn btn-outline" href="mailto:contact@linkee.fr?subject=Demande%20de%20d%C3%A9mo%20Linkee">Demander une démo</a>
      </div>
    </div>
  </section>

  <section class="ba-section" id="inscription">
    <div class="ba-conteneur">
      <div class="ba-fondateur">
        <span class="ba-surtitre">Pass Fondateur · places limitées</span>
        <h2 class="ba-titre">Sois là avant tout le monde.</h2>
        <p class="ba-fondateur__texte">
          Inscris-toi avec ton e-mail étudiant. On t'envoie ton accès le jour du lancement, avant
          l'ouverture au public.
        </p>

        <form class="ba-form" id="ba-form-fondateur" novalidate>
          <label class="sr-only" for="ba-email-fondateur">Ton e-mail étudiant</label>
          <input type="email" id="ba-email-fondateur" name="email" placeholder="ton.email@etu.uca.fr" autocomplete="email" required>
          <button class="btn btn-primary" type="submit">Je réserve mon pass</button>
        </form>
        <p class="ba-form__retour" data-retour-pour="ba-form-fondateur" role="status" aria-live="polite"></p>

        <div class="ba-barre">
          <div class="ba-barre__piste"><div class="ba-barre__remplie" data-barre-fondateur style="width: <?= (int) round(min(100, $inscrits / $objectifFondateurs * 100)) ?>%;"></div></div>
          <p class="ba-barre__legende" data-legende-fondateur>Pass Fondateur réservés · <span data-inscrits><?= (int) min($inscrits, $objectifFondateurs) ?></span> / <?= (int) $objectifFondateurs ?></p>
        </div>
      </div>
    </div>
  </section>
</main>

<footer class="ba-pied">
  <div class="ba-conteneur">
    <div class="ba-pied__grille">
      <div>
        <?= marqueLinkee() ?>
        <p class="ba-pied__texte">La plateforme sociale et événementielle des étudiants de Clermont-Ferrand.</p>
      </div>
      <div>
        <p class="ba-pied__titre">Étudiants</p>
        <ul class="ba-pied__liste">
          <li><a href="#fonctionnalites">Fonctionnalités</a></li>
          <li><a href="#inscription">Réserver ma place</a></li>
        </ul>
      </div>
      <div>
        <p class="ba-pied__titre">Partenaires</p>
        <ul class="ba-pied__liste">
          <li><a href="#etablissements">Établissements</a></li>
          <li><a href="mailto:contact@linkee.fr">Associations &amp; BDE</a></li>
        </ul>
      </div>
      <div>
        <p class="ba-pied__titre">Contact</p>
        <ul class="ba-pied__liste">
          <li><a href="mailto:contact@linkee.fr">contact@linkee.fr</a></li>
        </ul>
      </div>
    </div>
    <div class="ba-pied__bas">
      <span>© <?= date('Y') ?> Linkee · Clermont-Ferrand</span>
      <span>
        <a href="<?= baseUrl('/mentions-legales.php') ?>">Mentions légales</a>
        &nbsp;·&nbsp;
        <a href="<?= baseUrl('/confidentialite.php') ?>">Confidentialité</a>
      </span>
    </div>
  </div>
</footer>

<script>
(function () {
  // ── Compte à rebours ────────────────────────────────────────────────────
  var bloc = document.querySelector('[data-lancement]');
  if (bloc) {
    var cible = new Date(bloc.getAttribute('data-lancement')).getTime();
    var cases = {
      jours: bloc.querySelector('[data-compte="jours"]'),
      heures: bloc.querySelector('[data-compte="heures"]'),
      minutes: bloc.querySelector('[data-compte="minutes"]'),
      secondes: bloc.querySelector('[data-compte="secondes"]'),
    };
    var deuxChiffres = function (n) { return String(Math.max(0, n)).padStart(2, '0'); };
    var mettreAJour = function () {
      var reste = cible - Date.now();
      if (reste < 0) reste = 0;
      var s = Math.floor(reste / 1000);
      cases.jours.textContent = deuxChiffres(Math.floor(s / 86400));
      cases.heures.textContent = deuxChiffres(Math.floor((s % 86400) / 3600));
      cases.minutes.textContent = deuxChiffres(Math.floor((s % 3600) / 60));
      cases.secondes.textContent = deuxChiffres(s % 60);
    };
    mettreAJour();
    setInterval(mettreAJour, 1000);
  }

  // ── Formulaires de la liste d'attente (hero + Pass Fondateur) ──────────
  var formulaires = document.querySelectorAll('#ba-form-hero, #ba-form-fondateur');
  Array.prototype.forEach.call(formulaires, function (form) {
    var retour = document.querySelector('[data-retour-pour="' + form.id + '"]');
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var email = form.querySelector('input[type="email"]').value.trim();
      var bouton = form.querySelector('button[type="submit"]');
      var texteInitial = bouton.textContent;

      retour.textContent = '';
      retour.className = 'ba-form__retour';
      bouton.disabled = true;
      bouton.textContent = '…';

      fetch(BASE + '/api/liste_attente.php', {
        method: 'POST',
        headers: enTetesJson(),
        body: JSON.stringify({ email: email }),
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          bouton.disabled = false;
          bouton.textContent = texteInitial;
          if (data && data.success) {
            form.reset();
            retour.textContent = 'C\'est noté — rendez-vous au lancement !';
            retour.className = 'ba-form__retour est-ok';
            if (typeof data.count === 'number') {
              var objectif = 500;
              var affiche = Math.min(data.count, objectif);
              document.querySelectorAll('[data-inscrits]').forEach(function (el) { el.textContent = affiche; });
              document.querySelectorAll('[data-barre-fondateur]').forEach(function (el) {
                el.style.width = Math.round(Math.min(100, (data.count / objectif) * 100)) + '%';
              });
            }
          } else {
            retour.textContent = (data && data.message) || 'Une erreur est survenue, réessaie.';
            retour.className = 'ba-form__retour est-erreur';
          }
        })
        .catch(function () {
          bouton.disabled = false;
          bouton.textContent = texteInitial;
          retour.textContent = 'Erreur réseau, réessaie.';
          retour.className = 'ba-form__retour est-erreur';
        });
    });
  });
})();
</script>
</body>
</html>
