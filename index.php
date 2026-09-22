<?php
/**
 * Page d'accueil publique — la vitrine de StudentLink.
 *
 * Un visiteur qui tapait le nom de domaine tombait jusqu'ici sur un
 * formulaire de connexion : rien n'expliquait ce qu'est l'application, et un
 * gérant de bar n'avait aucune raison d'aller plus loin. Cette page présente
 * le produit à ses deux publics avant toute demande de compte.
 *
 * Les deux publics ne partagent presque rien — ni le vocabulaire, ni les
 * arguments, ni le prix. Plutôt qu'une page moyenne qui ne parle à personne,
 * un sélecteur segmenté en tête (le même contrôle que le hub de
 * l'application) bascule entre deux pages complètes. Le choix vit dans l'URL
 * (?pour=…) : sans JavaScript le lien recharge simplement la page, et un lien
 * partagé arrive sur le bon public.
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/crm.php';
require_once __DIR__ . '/includes/sponsoring.php';

// Déjà connecté : la vitrine ne lui apprendrait rien, il attend son écran.
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . accueilSelonType($_SESSION['user_type'] ?? null));
    exit;
}

$pour = ($_GET['pour'] ?? '') === 'etablissements' ? 'etablissements' : 'etudiants';

/*
 * Les tarifs viennent de la grille du back-office, jamais d'une valeur
 * recopiée : une page publique qui annonce 79 € pendant que la facturation en
 * applique 89 est un litige, pas une coquille. Seuls les arguments de vente
 * sont écrits ici — la grille, elle, reste dans crm.php.
 */
$offres = crmOffres();
$formules = [
    'fondateur' => [
        'accroche' => 'Les 15 premiers établissements de Clermont-Ferrand.',
        'points'   => [
            '3 mois offerts, puis 39 € gelés',
            'Événements illimités',
            'Ligne directe avec les fondateurs',
        ],
        'vedette'  => true,
    ],
    'essentiel' => [
        'accroche' => 'Bars, restaurants, afterworks.',
        'points'   => [
            'Événements illimités',
            'Scan des pass et statistiques',
            'Fiche établissement et photos',
        ],
        'vedette'  => false,
    ],
    'premium' => [
        'accroche' => 'Discothèques, groupes, multi-établissements.',
        'points'   => [
            'Tout Essentiel, sur plusieurs adresses',
            'Mises en avant incluses dans le fil',
            'Accompagnement sur vos temps forts',
        ],
        'vedette'  => false,
    ],
];

// « À partir de » : le premier prix de la grille de mise en avant.
$sponsoDepart = (int) min(array_column(formulesSponsoring(), 'tarif'));

$titres = [
    'etudiants'      => 'StudentLink — La vie étudiante à prix réduit',
    'etablissements' => 'StudentLink — Remplissez vos soirées creuses',
];
$description = $pour === 'etablissements'
    ? 'StudentLink amène les étudiants de Clermont-Ferrand dans votre établissement : '
      . 'publiez une soirée, scannez les pass à l\'entrée, mesurez ce qu\'elle vous rapporte.'
    : 'Les soirées, les bons plans et les gens de ta ville au même endroit. Réserve ton '
      . 'pass, montre-le à l\'entrée, paie moins cher. Gratuit pour les étudiants.';

/** Une carte d'argument : icône, titre, texte. */
function lpCarte(string $icone, string $titre, string $texte): string
{
    return '<article class="lp-carte">'
         . '<span class="lp-carte__icone">' . icon($icone) . '</span>'
         . '<h3 class="lp-carte__titre">' . htmlspecialchars($titre) . '</h3>'
         . '<p class="lp-carte__texte">' . htmlspecialchars($texte) . '</p>'
         . '</article>';
}

/** Une étape du « comment ça marche », numérotée. */
function lpEtape(int $rang, string $titre, string $texte): string
{
    return '<li class="lp-etape">'
         . '<span class="lp-etape__rang">' . $rang . '</span>'
         . '<div><h3 class="lp-etape__titre">' . htmlspecialchars($titre) . '</h3>'
         . '<p class="lp-etape__texte">' . htmlspecialchars($texte) . '</p></div>'
         . '</li>';
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?= htmlspecialchars($titres[$pour]) ?></title>
<meta name="description" content="<?= htmlspecialchars($description) ?>">
<?= themeBootScript() ?>
<link rel="stylesheet" href="<?= asset('/assets/css/style.css') ?>">
<link rel="icon" type="image/png" href="<?= baseUrl('/Logo.png') ?>">
<link rel="manifest" href="<?= baseUrl('/manifest.json') ?>">
<style>
  /* ═══════════════════════════════════════════════════════════════════
     Accueil public.
     Tout est préfixé .lp- : l'application a sa propre grammaire de
     classes, et rien d'écrit ici ne doit lui échapper. Les composants qui
     existent déjà (.btn, .card, .hub-toggle, .event-card, .salle-panel…)
     sont repris tels quels — la vitrine doit ressembler au produit, pas à
     une plaquette dessinée à côté.
     ═══════════════════════════════════════════════════════════════════ */

  .lp-conteneur { max-width: 1120px; margin: 0 auto; padding: 0 22px; }

  /* ── En-tête collant : marque, choix du public, accès au compte ── */
  .lp-entete {
    position: sticky; top: 0; z-index: 30;
    background: var(--bg);
    border-bottom: 1px solid var(--gris-clair);
  }
  /* Le flou n'est posé que là où il existe : sans ce garde, le repli est un
     aplat semi-transparent qui laisse lire la page au travers. */
  @supports (backdrop-filter: blur(8px)) and (background: color-mix(in srgb, red 50%, transparent)) {
    .lp-entete {
      background: color-mix(in srgb, var(--bg) 84%, transparent);
      -webkit-backdrop-filter: blur(12px);
      backdrop-filter: blur(12px);
    }
  }
  .lp-entete__rangee {
    display: flex; align-items: center; justify-content: space-between;
    gap: 16px; padding: 14px 0 12px;
  }
  .lp-entete__actions { display: flex; align-items: center; gap: 10px; }
  .lp-entete__connexion {
    font-size: var(--fs-4); font-weight: var(--fw-semibold);
    color: var(--gris-fonce); text-decoration: none; white-space: nowrap;
  }
  .lp-entete__connexion:hover { color: var(--noir); }

  /* Le sélecteur de public : même contrôle segmenté que le hub. */
  .lp-bascule { max-width: 420px; margin: 0 auto 14px; }

  /* ── Rythme vertical ── */
  .lp-section { padding: 56px 0; }
  .lp-section + .lp-section { border-top: 1px solid var(--gris-clair); }
  .lp-section__entete { max-width: 640px; margin-bottom: 32px; }
  .lp-surtitre {
    display: block; font-size: var(--fs-1); font-weight: var(--fw-bold);
    letter-spacing: var(--ls-label); text-transform: uppercase;
    color: var(--rouge); margin-bottom: 12px;
  }
  .lp-chapo {
    font-size: var(--fs-5); line-height: var(--lh-relaxed);
    color: var(--gris-fonce); margin: 16px 0 0; max-width: 56ch;
  }

  /* ── Hero : le discours à gauche, un morceau de l'application à droite ── */
  .lp-hero { padding: 40px 0 56px; }
  .lp-hero__grille { display: grid; gap: 40px; align-items: center; }
  /* h1.titre-page porte `font: inherit` dans la feuille de l'application :
     sans un selecteur aussi specifique, le titre du hero retombait a 16px. */
  h1.lp-hero__titre { font-size: var(--fs-9); }
  .lp-hero__actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 28px; }
  .lp-mention { font-size: var(--fs-2); color: var(--gris); margin: 14px 0 0; line-height: var(--lh-normal); }

  /* Trois chiffres, dans l'esprit des pilules de l'onboarding. */
  .lp-chiffres { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin: 34px 0 0; }
  .lp-chiffre {
    background: var(--blanc); border: 1px solid var(--gris-clair);
    border-radius: var(--radius); box-shadow: var(--shadow-xs); padding: 16px;
  }
  .lp-chiffre dd {
    margin: 0; font-family: var(--font-display); font-weight: var(--fw-black);
    font-size: var(--fs-8); line-height: var(--lh-display); font-variant-numeric: tabular-nums;
  }
  .lp-chiffre dt {
    font-size: var(--fs-1); font-weight: var(--fw-bold); letter-spacing: var(--ls-wide);
    text-transform: uppercase; color: var(--gris); line-height: var(--lh-snug); margin-top: 6px;
  }

  /* ── Aperçu de l'application ── */
  .lp-apercu-cadre {
    background: var(--surface-2); border: 1px solid var(--line-2);
    border-radius: var(--radius-xl); padding: 18px; box-shadow: var(--shadow);
  }
  .lp-apercu-legende {
    font-size: var(--fs-1); font-weight: var(--fw-bold); letter-spacing: var(--ls-label);
    text-transform: uppercase; color: var(--gris); margin: 0 0 12px;
  }
  .lp-apercu { display: flex; flex-direction: column; gap: 14px; }
  .lp-apercu > *, .lp-apercu .card, .lp-apercu .event-card { margin-bottom: 0; }

  /* ── Grille d'arguments ── */
  .lp-grille { display: grid; grid-template-columns: repeat(auto-fit, minmax(258px, 1fr)); gap: 16px; }
  .lp-carte {
    background: var(--blanc); border: 1px solid var(--gris-clair);
    border-radius: var(--radius); box-shadow: var(--shadow-xs); padding: 22px;
  }
  .lp-carte__icone {
    display: inline-flex; align-items: center; justify-content: center;
    width: 40px; height: 40px; border-radius: var(--radius-sm);
    background: var(--rouge-clair); color: var(--sur-rouge-clair); margin-bottom: 14px;
  }
  .lp-carte__titre {
    font-family: var(--font-display); font-weight: var(--fw-black);
    font-size: var(--fs-6); line-height: var(--lh-tight); margin: 0 0 8px;
  }
  .lp-carte__texte { margin: 0; font-size: var(--fs-4); line-height: var(--lh-relaxed); color: var(--gris-fonce); }

  /* ── Comment ça marche ── */
  .lp-etapes {
    list-style: none; margin: 0; padding: 0;
    display: grid; grid-template-columns: repeat(auto-fit, minmax(258px, 1fr)); gap: 24px;
  }
  .lp-etape { display: flex; gap: 14px; align-items: flex-start; }
  .lp-etape__rang {
    flex-shrink: 0; width: 34px; height: 34px; border-radius: 50%;
    background: var(--noir); color: var(--blanc);
    display: flex; align-items: center; justify-content: center;
    font-family: var(--font-display); font-weight: var(--fw-black); font-size: var(--fs-4);
  }
  .lp-etape__titre { font-size: var(--fs-5); font-weight: var(--fw-bold); margin: 4px 0 6px; }
  .lp-etape__texte { margin: 0; font-size: var(--fs-4); line-height: var(--lh-relaxed); color: var(--gris-fonce); }

  /* ── Tarifs ── */
  .lp-tarifs {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(258px, 1fr));
    gap: 16px; align-items: stretch;
  }
  .lp-tarif {
    background: var(--blanc); border: 1px solid var(--gris-clair);
    border-radius: var(--radius); box-shadow: var(--shadow-xs); padding: 24px;
    display: flex; flex-direction: column; gap: 14px;
  }
  /* Une seule formule mise en avant : deux cartes « vedette » ne
     recommandent plus rien. */
  .lp-tarif.est-vedette { border-color: var(--noir); box-shadow: var(--shadow); }
  .lp-tarif__entete { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
  .lp-tarif__nom { font-family: var(--font-display); font-weight: var(--fw-black); font-size: var(--fs-6); }
  .lp-tarif__prix {
    font-family: var(--font-display); font-weight: var(--fw-black);
    font-size: var(--fs-8); line-height: var(--lh-display); text-align: right; white-space: nowrap;
  }
  .lp-tarif__prix small {
    display: block; font-family: var(--font-sans); font-weight: var(--fw-semibold);
    font-size: var(--fs-2); color: var(--gris);
  }
  .lp-tarif__accroche { margin: 0; font-size: var(--fs-3); color: var(--gris-fonce); }
  .lp-tarif__points { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 9px; }
  .lp-tarif__points li { display: flex; gap: 9px; font-size: var(--fs-4); line-height: var(--lh-snug); }
  .lp-tarif__points svg { flex-shrink: 0; color: var(--succes); margin-top: 1px; }
  /* Les boutons s'alignent d'une carte à l'autre, quelle que soit la
     longueur de la liste au-dessus. */
  .lp-tarif .btn { margin-top: auto; }

  /* ── Bandeau d'appel final ── */
  .lp-bandeau {
    background: linear-gradient(160deg, #26221E, #161310); color: #F3EEE3;
    border-radius: var(--radius-xl); padding: 40px 28px; text-align: center;
  }
  .lp-bandeau h2 {
    font-family: var(--font-display); font-weight: var(--fw-black);
    font-size: var(--fs-8); line-height: var(--lh-display); margin: 0 0 12px;
  }
  .lp-bandeau p { margin: 0 auto 24px; max-width: 48ch; color: rgba(243,238,227,.78); line-height: var(--lh-relaxed); }
  /* En thème sombre, le bandeau a presque la couleur du papier : sans ce
     filet, il cesse d'être un bloc et redevient du fond. */
  [data-theme="dark"] .lp-bandeau { border: 1px solid var(--line-2); }
  .lp-bandeau__actions { display: flex; flex-wrap: wrap; gap: 12px; justify-content: center; }

  /* ── Pied de page ── */
  .lp-pied { border-top: 1px solid var(--gris-clair); padding: 34px 0 44px; }
  .lp-pied__rangee { display: flex; flex-wrap: wrap; gap: 18px; align-items: center; justify-content: space-between; }
  .lp-pied__liens { display: flex; flex-wrap: wrap; gap: 18px; }
  .lp-pied a { color: var(--gris-fonce); font-size: var(--fs-3); text-decoration: none; }
  .lp-pied a:hover { color: var(--noir); }
  .lp-pied__mention { font-size: var(--fs-2); color: var(--gris); margin: 16px 0 0; line-height: var(--lh-normal); }

  /* ── Grand écran ── */
  @media (min-width: 860px) {
    .lp-entete__rangee { padding: 16px 0; }
    .lp-hero { padding: 56px 0 72px; }
    .lp-hero__grille { grid-template-columns: 1.05fr .95fr; gap: 56px; }
    h1.lp-hero__titre { font-size: var(--fs-10); }
    .lp-section { padding: 76px 0; }
    .lp-bandeau { padding: 56px 48px; }
  }

  /* Sous 420 px, trois chiffres côte à côte ne se lisent plus. */
  @media (max-width: 420px) {
    .lp-chiffres { grid-template-columns: 1fr; }
    .lp-entete__connexion { display: none; }
  }
</style>
</head>
<body>
<a href="#contenu" class="skip-nav">Aller au contenu principal</a>

<header class="lp-entete">
  <div class="lp-conteneur">
    <div class="lp-entete__rangee">
      <a href="<?= baseUrl('/') ?>" class="logo" style="text-decoration:none;">
        <svg class="logo-icon" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <rect x="15" y="20" width="50" height="30" rx="15" stroke="var(--noir)" stroke-width="10"/>
          <rect x="35" y="50" width="50" height="30" rx="15" class="accent" stroke-width="10"/>
          <circle cx="50" cy="50" r="6" fill="var(--noir)"/>
        </svg>
        StudentLink
      </a>
      <div class="lp-entete__actions">
        <a href="<?= baseUrl('/auth/login.php') ?>" class="lp-entete__connexion">Se connecter</a>
        <a class="btn btn-primary" data-inscription
           href="<?= baseUrl('/auth/register.php') . ($pour === 'etablissements' ? '?type=partenaire' : '') ?>">
          Créer un compte
        </a>
      </div>
    </div>

    <!-- Le choix du public, en tête de page comme dans l'application. -->
    <nav class="hub-toggle lp-bascule" data-bascule aria-label="Choisir votre profil">
      <a href="?pour=etudiants" data-pour="etudiants"
         class="<?= $pour === 'etudiants' ? 'active' : '' ?>"
         <?= $pour === 'etudiants' ? 'aria-current="page"' : '' ?>>Étudiants</a>
      <a href="?pour=etablissements" data-pour="etablissements"
         class="<?= $pour === 'etablissements' ? 'active' : '' ?>"
         <?= $pour === 'etablissements' ? 'aria-current="page"' : '' ?>>Établissements</a>
    </nav>
  </div>
</header>

<main id="contenu">

<!-- ══════════════════════ PUBLIC : ÉTUDIANTS ══════════════════════ -->
<div data-public="etudiants"<?= $pour === 'etudiants' ? '' : ' hidden' ?>>

  <section class="lp-hero">
    <div class="lp-conteneur lp-hero__grille">
      <div>
        <span class="lp-surtitre">Pour les étudiants</span>
        <h1 class="titre-page lp-hero__titre">
          <span class="display" style="font-size:inherit;">La vie étudiante</span><br>
          <span class="display-italic" style="font-size:inherit;color:var(--rouge);">à prix réduit.</span>
        </h1>
        <p class="lp-chapo">
          Les soirées, les bons plans et les gens de ta ville, au même endroit.
          Tu réserves ta place en deux clics, tu montres ton pass à l'entrée,
          tu paies moins cher. Et tu y vas rarement seul.
        </p>
        <div class="lp-hero__actions">
          <a class="btn btn-rouge" href="<?= baseUrl('/auth/register.php') ?>">Créer mon compte — c'est gratuit</a>
          <a class="btn btn-outline" href="<?= baseUrl('/auth/login.php') ?>">J'ai déjà un compte</a>
        </div>
        <p class="lp-mention">
          Gratuit, sans engagement, sans carte bancaire. Réservé aux personnes
          majeures : StudentLink donne accès à des soirées en bar et en discothèque.
        </p>

        <dl class="lp-chiffres">
          <div class="lp-chiffre"><dd style="color:var(--rouge);">−50 %</dd><dt>sur tes sorties</dt></div>
          <div class="lp-chiffre"><dd>0 €</dd><dt>pour les étudiants</dt></div>
          <div class="lp-chiffre"><dd style="color:var(--sur-bleu-clair);">2 min</dd><dt>pour réserver</dt></div>
        </dl>
      </div>

      <figure style="margin:0;">
        <figcaption class="sr-only">Aperçu du fil d'événements et du pass numérique de l'application.</figcaption>
        <div class="lp-apercu-cadre" aria-hidden="true">
          <p class="lp-apercu-legende">Aperçu · Hub Explore</p>
          <div class="lp-apercu">
            <div class="event-card event-card-flash">
              <div class="event-entete">
                <div class="event-entete__meta">
                  <div class="label" style="opacity:.8;">CE SOIR · 22H00</div>
                  <div class="flash-badge">FLASH</div>
                </div>
              </div>
              <div class="event-title">Jeudi Étudiant</div>
              <div class="event-lieu" style="color:inherit;opacity:.85;">Le Bec qui Pique — Clermont-Ferrand</div>
              <div class="event-reduction">−50 %</div>
              <div class="progress-bar"><div class="progress-bar-fill" style="width:72%;"></div></div>
              <div class="event-places">36 places sur 50</div>
            </div>
            <div class="card card-noir" style="padding:18px;">
              <div class="label" style="opacity:.7;">Ton pass</div>
              <div style="display:flex;align-items:center;gap:10px;margin-top:8px;">
                <?= icon('valide', 'icon-lg') ?>
                <span style="font-family:var(--font-display);font-weight:var(--fw-black);font-size:var(--fs-7);">Validé à l'entrée</span>
              </div>
              <div style="font-size:var(--fs-2);opacity:.75;margin-top:6px;">+40 XP · badge « Habitué » débloqué</div>
            </div>
          </div>
        </div>
      </figure>
    </div>
  </section>

  <section class="lp-section">
    <div class="lp-conteneur">
      <div class="lp-section__entete">
        <span class="lp-surtitre">Ce que tu y trouves</span>
        <h2 class="t-title">Tout ce qui se passe ce soir, dans une seule appli.</h2>
      </div>
      <div class="lp-grille">
        <?= lpCarte('flamme', 'Les bons plans du moment',
            'Le fil des soirées de ta ville, réductions comprises. Les offres flash ont un compte à rebours : quand c\'est parti, c\'est parti.') ?>
        <?= lpCarte('billet', 'Ton pass dans la poche',
            'Tu t\'inscris, ton pass QR arrive dans ton wallet. À l\'entrée on le scanne — pas de liste papier, pas de discussion.') ?>
        <?= lpCarte('personnes', 'Des squads pour bouger',
            'Running, vélo, muscu : rejoins un groupe qui sort déjà, ou crée le tien et invite qui tu veux.') ?>
        <?= lpCarte('personne', 'Les gens avant la soirée',
            'L\'annuaire te propose des étudiants qui aiment les mêmes choses que toi, et te dit ce que vous avez en commun.') ?>
        <?= lpCarte('etoile', 'Des avis qui valent quelque chose',
            'Seuls ceux qui ont vraiment scanné leur pass peuvent noter. Tu sais à quoi t\'attendre avant de traverser la ville.') ?>
        <?= lpCarte('trophee', 'XP, niveaux et badges',
            'Chaque sortie compte. Tu montes de niveau, tu débloques des badges, et ton profil raconte tes soirées.') ?>
      </div>
    </div>
  </section>

  <section class="lp-section">
    <div class="lp-conteneur">
      <div class="lp-section__entete">
        <span class="lp-surtitre">Comment ça marche</span>
        <h2 class="t-title">Trois étapes, et tu es dedans.</h2>
      </div>
      <ol class="lp-etapes">
        <?= lpEtape(1, 'Tu crées ton compte',
            'Ton école, ta promo, tes centres d\'intérêt. Deux minutes, et le fil s\'adapte à toi.') ?>
        <?= lpEtape(2, 'Tu réserves ta place',
            'Une soirée te plaît, tu t\'inscris : ton pass QR est généré aussitôt, réduction comprise.') ?>
        <?= lpEtape(3, 'Tu scannes à l\'entrée',
            'L\'établissement scanne ton pass, tu paies le tarif réduit. Ensuite tu notes la soirée et tu gagnes de l\'XP.') ?>
      </ol>
    </div>
  </section>

  <section class="lp-section">
    <div class="lp-conteneur">
      <div class="lp-bandeau">
        <h2>Prêt à sortir pour moins cher ?</h2>
        <p>StudentLink est gratuit pour les étudiants, sans engagement et sans carte bancaire.</p>
        <div class="lp-bandeau__actions">
          <a class="btn btn-rouge" href="<?= baseUrl('/auth/register.php') ?>">Créer mon compte</a>
          <a class="btn btn-outline-blanc" href="?pour=etablissements" data-pour="etablissements">Je représente un établissement</a>
        </div>
      </div>
    </div>
  </section>

</div>

<!-- ═══════════════════ PUBLIC : ÉTABLISSEMENTS ═══════════════════ -->
<div data-public="etablissements"<?= $pour === 'etablissements' ? '' : ' hidden' ?>>

  <section class="lp-hero">
    <div class="lp-conteneur lp-hero__grille">
      <div>
        <span class="lp-surtitre">Pour les établissements</span>
        <h1 class="titre-page lp-hero__titre">
          <span class="display" style="font-size:inherit;">Remplissez vos</span><br>
          <span class="display-italic" style="font-size:inherit;color:var(--rouge);">soirées creuses.</span>
        </h1>
        <p class="lp-chapo">
          Bars, restaurants, discothèques, afterworks : publiez une offre, elle part
          dans le fil des étudiants de votre ville. Vous scannez les pass à l'entrée,
          et vous savez enfin combien de personnes l'application vous a réellement amenées.
        </p>
        <div class="lp-hero__actions">
          <a class="btn btn-primary" href="<?= baseUrl('/auth/register.php?type=partenaire') ?>">Devenir partenaire</a>
          <a class="btn btn-outline" href="#tarifs">Voir les tarifs</a>
        </div>
        <p class="lp-mention">
          Une soirée test complète — publiée et scannée — avant la moindre facturation.
          Aucune commission sur vos entrées ni sur vos consommations.
        </p>

        <dl class="lp-chiffres">
          <div class="lp-chiffre"><dd><?= (int) $offres['fondateur']['tarif'] ?> €</dd><dt>par mois, tarif fondateur</dt></div>
          <div class="lp-chiffre"><dd style="color:var(--sur-bleu-clair);">3 mois</dd><dt>offerts aux 15 premiers</dt></div>
          <div class="lp-chiffre"><dd>0 %</dd><dt>de commission</dt></div>
        </dl>
      </div>

      <figure style="margin:0;">
        <figcaption class="sr-only">Aperçu du tableau de bord partenaire : inscrits, présents et écoles d'origine.</figcaption>
        <div class="lp-apercu-cadre" aria-hidden="true">
          <p class="lp-apercu-legende">Aperçu · Tableau de bord</p>
          <div class="lp-apercu">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
              <div class="card card-neutre" style="padding:16px;margin:0;">
                <div class="label">Inscrits</div>
                <div style="font-family:var(--font-display);font-weight:var(--fw-black);font-size:var(--fs-8);line-height:var(--lh-display);">48</div>
                <div style="font-size:var(--fs-2);color:var(--gris);margin-top:4px;">soirée de jeudi</div>
              </div>
              <div class="card card-neutre" style="padding:16px;margin:0;">
                <div class="label">Présents</div>
                <div style="font-family:var(--font-display);font-weight:var(--fw-black);font-size:var(--fs-8);line-height:var(--lh-display);">31</div>
                <div style="font-size:var(--fs-2);color:var(--gris);margin-top:4px;">65 % de conversion</div>
              </div>
            </div>
            <div class="salle-panel">
              <div class="sp-label">D'où ils viennent</div>
              <div class="sp-title">Trois écoles, <em>un jeudi.</em></div>
              <div class="school-bar">
                <div class="school-row">
                  <div class="school-name">UCA</div>
                  <div class="school-track"><div class="school-fill" style="width:58%;background:var(--lime);"></div></div>
                  <div class="school-pct">58%</div>
                </div>
                <div class="school-row">
                  <div class="school-name">SIGMA</div>
                  <div class="school-track"><div class="school-fill" style="width:27%;background:var(--lime);"></div></div>
                  <div class="school-pct">27%</div>
                </div>
                <div class="school-row">
                  <div class="school-name">IFSI</div>
                  <div class="school-track"><div class="school-fill" style="width:15%;background:var(--lime);"></div></div>
                  <div class="school-pct">15%</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </figure>
    </div>
  </section>

  <section class="lp-section">
    <div class="lp-conteneur">
      <div class="lp-section__entete">
        <span class="lp-surtitre">Votre espace partenaire</span>
        <h2 class="t-title">Un back-office qui tient sur le téléphone du bar.</h2>
      </div>
      <div class="lp-grille">
        <?= lpCarte('calendrier', 'Publiez en deux minutes',
            'Date, capacité, réduction, photo. L\'offre part dans le fil des étudiants, et reste modifiable jusqu\'à la dernière minute.') ?>
        <?= lpCarte('valide', 'Scannez les pass à l\'entrée',
            'La caméra du téléphone suffit. Un pass déjà utilisé ou périmé est refusé net : personne ne discute avec le videur.') ?>
        <?= lpCarte('loupe', 'Vos chiffres, sans tableur',
            'Inscriptions par jour, arrivées par heure, écoles d\'origine, taux de présence. De quoi décider de la prochaine soirée.') ?>
        <?= lpCarte('appareil', 'Votre vitrine',
            'Photos, informations pratiques, abonnés : les étudiants suivent votre établissement et sont prévenus de vos offres.') ?>
        <?= lpCarte('flamme', 'Une mise en avant quand il le faut',
            'Une soirée à remplir vite ? Passez en tête du fil à partir de ' . $sponsoDepart . ' €, sans changer de formule.') ?>
        <?= lpCarte('etoile', 'Les avis de ceux qui sont venus',
            'Seul un pass réellement scanné donne droit à un avis. Votre note reflète vos clients, pas les passants.') ?>
      </div>
    </div>
  </section>

  <section class="lp-section" id="tarifs">
    <div class="lp-conteneur">
      <div class="lp-section__entete">
        <span class="lp-surtitre">Tarifs</span>
        <h2 class="t-title">Un abonnement mensuel, pas une commission.</h2>
        <p class="lp-chapo">
          Vous savez ce que vous payez avant de publier. Les prix sont hors taxes,
          et l'abonnement s'interrompt quand vous le décidez.
        </p>
      </div>

      <div class="lp-tarifs">
        <?php foreach ($formules as $code => $f): ?>
          <article class="lp-tarif<?= $f['vedette'] ? ' est-vedette' : '' ?>">
            <div class="lp-tarif__entete">
              <div>
                <div class="lp-tarif__nom"><?= htmlspecialchars($offres[$code]['libelle']) ?></div>
                <?php if ($f['vedette']): ?>
                  <span class="badge badge-flash" style="margin-top:6px;">15 places</span>
                <?php endif; ?>
              </div>
              <div class="lp-tarif__prix">
                <?= (int) $offres[$code]['tarif'] ?> €
                <small>HT / mois</small>
              </div>
            </div>
            <p class="lp-tarif__accroche"><?= htmlspecialchars($f['accroche']) ?></p>
            <ul class="lp-tarif__points">
              <?php foreach ($f['points'] as $point): ?>
                <li><?= icon('check', 'icon-sm') ?><span><?= htmlspecialchars($point) ?></span></li>
              <?php endforeach; ?>
            </ul>
            <a class="btn <?= $f['vedette'] ? 'btn-primary' : 'btn-outline' ?> btn-full"
               href="<?= baseUrl('/auth/register.php?type=partenaire') ?>">
              Choisir <?= htmlspecialchars($offres[$code]['libelle']) ?>
            </a>
          </article>
        <?php endforeach; ?>
      </div>

      <div class="card" style="margin:20px 0 0;padding:20px;display:flex;gap:14px;align-items:flex-start;">
        <span style="color:var(--sur-bleu-clair);flex-shrink:0;"><?= icon('personnes', 'icon-lg') ?></span>
        <p style="margin:0;font-size:var(--fs-4);line-height:var(--lh-relaxed);color:var(--gris-fonce);">
          <strong style="color:var(--noir);">Vous êtes un BDE ou une association étudiante ?</strong><br>
          L'accès est gratuit, définitivement. Créez votre compte partenaire et
          signalez-le : nous ouvrons l'accès sans formule payante.
        </p>
      </div>
    </div>
  </section>

  <section class="lp-section">
    <div class="lp-conteneur">
      <div class="lp-section__entete">
        <span class="lp-surtitre">Comment ça marche</span>
        <h2 class="t-title">De l'inscription à la première soirée.</h2>
      </div>
      <ol class="lp-etapes">
        <?= lpEtape(1, 'Vous créez votre établissement',
            'Nom, type, ville, photos. Votre fiche est en ligne, et les étudiants peuvent déjà vous suivre.') ?>
        <?= lpEtape(2, 'Vous publiez votre soirée test',
            'Complète, publiée et scannée — et facturée seulement après. C\'est le meilleur argument que nous ayons.') ?>
        <?= lpEtape(3, 'Vous mesurez, puis vous recommencez',
            'Le tableau de bord dit qui est venu, d\'où et à quelle heure. La soirée suivante s\'écrit avec ces chiffres.') ?>
      </ol>
    </div>
  </section>

  <section class="lp-section">
    <div class="lp-conteneur">
      <div class="lp-bandeau">
        <h2>Votre prochaine soirée peut se remplir toute seule.</h2>
        <p>Créez votre espace partenaire, publiez une soirée test, et jugez sur les chiffres.</p>
        <div class="lp-bandeau__actions">
          <a class="btn btn-rouge" href="<?= baseUrl('/auth/register.php?type=partenaire') ?>">Devenir partenaire</a>
          <a class="btn btn-outline-blanc" href="?pour=etudiants" data-pour="etudiants">Je suis étudiant</a>
        </div>
      </div>
    </div>
  </section>

</div>

</main>

<footer class="lp-pied">
  <div class="lp-conteneur">
    <div class="lp-pied__rangee">
      <div class="logo" style="font-size:var(--fs-5);">
        <svg class="logo-icon" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" style="width:26px;height:26px;">
          <rect x="15" y="20" width="50" height="30" rx="15" stroke="var(--noir)" stroke-width="10"/>
          <rect x="35" y="50" width="50" height="30" rx="15" class="accent" stroke-width="10"/>
          <circle cx="50" cy="50" r="6" fill="var(--noir)"/>
        </svg>
        StudentLink
      </div>
      <nav class="lp-pied__liens" aria-label="Liens de bas de page">
        <a href="?pour=etudiants" data-pour="etudiants">Étudiants</a>
        <a href="?pour=etablissements" data-pour="etablissements">Établissements</a>
        <a href="<?= baseUrl('/auth/login.php') ?>">Se connecter</a>
        <a href="<?= baseUrl('/cgu.php') ?>">CGU</a>
        <a href="<?= baseUrl('/confidentialite.php') ?>">Confidentialité</a>
        <a href="<?= baseUrl('/mentions-legales.php') ?>">Mentions légales</a>
      </nav>
    </div>
    <p class="lp-pied__mention">
      StudentLink — plateforme événementielle et sociale pour les étudiants de
      Clermont-Ferrand. Accès réservé aux personnes majeures. © <?= date('Y') ?>
    </p>
  </div>
</footer>

<script>
/*
 * Bascule entre les deux publics sans recharger la page.
 *
 * Les liens restent de vrais liens : sans JavaScript, ?pour=… recharge la
 * page sur le bon public et un lien copié fonctionne. Le script n'évite que
 * l'aller-retour réseau — et tient l'historique à jour, pour que « précédent »
 * revienne au public précédent plutôt que de quitter le site.
 */
(function () {
  var blocs   = document.querySelectorAll('[data-public]');
  var onglets = document.querySelectorAll('[data-bascule] a[data-pour]');
  if (!blocs.length || !onglets.length) return;

  var INSCRIPTION = <?= json_encode(baseUrl('/auth/register.php')) ?>;
  var TITRES = <?= json_encode($titres, JSON_UNESCAPED_UNICODE) ?>;

  function afficher(pour, remonter) {
    blocs.forEach(function (bloc) {
      bloc.hidden = bloc.dataset.public !== pour;
    });
    onglets.forEach(function (onglet) {
      var actif = onglet.dataset.pour === pour;
      onglet.classList.toggle('active', actif);
      if (actif) { onglet.setAttribute('aria-current', 'page'); }
      else { onglet.removeAttribute('aria-current'); }
    });

    // Le bouton d'inscription de l'en-tête suit le public affiché : un gérant
    // de bar ne doit pas atterrir sur l'onglet étudiant du formulaire.
    var inscription = document.querySelector('[data-inscription]');
    if (inscription) {
      inscription.href = INSCRIPTION + (pour === 'etablissements' ? '?type=partenaire' : '');
    }
    if (TITRES[pour]) { document.title = TITRES[pour]; }

    if (remonter) {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }
  }

  // Tout lien porteur de data-pour bascule : les onglets, mais aussi les
  // renvois croisés des bandeaux et du pied de page.
  document.addEventListener('click', function (e) {
    var lien = e.target.closest ? e.target.closest('a[data-pour]') : null;
    if (!lien || e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;
    e.preventDefault();
    var pour = lien.dataset.pour;
    history.pushState({ pour: pour }, '', '?pour=' + pour);
    afficher(pour, true);
  });

  window.addEventListener('popstate', function (e) {
    var depuisUrl = new URLSearchParams(location.search).get('pour');
    var pour = (e.state && e.state.pour)
      || (depuisUrl === 'etablissements' ? 'etablissements' : 'etudiants');
    afficher(pour, false);
  });

  // L'état initial vient du serveur ; on le pose dans l'historique pour que
  // le premier « précédent » après une bascule retrouve bien ce public-là.
  history.replaceState({ pour: <?= json_encode($pour) ?> }, '');
})();
</script>
</body>
</html>
