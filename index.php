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
 * Deux mécaniques tiennent sur de vraies données plutôt que sur un chiffre
 * inventé : le compteur « Pass Fondateur » (table liste_attente) et le
 * parrainage — « chaque pote inscrit avec ton lien te fait gagner 25
 * places » est un calcul réel sur les filleuls, pas un texte décoratif. Voir
 * includes/liste_attente.php.
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/page.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/liste_attente.php';

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
$lancementJJMM = date('d.m', $lancementTs);

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

// Lien de parrainage porté dans l'URL (?r=LK...) : simplement transmis au
// formulaire, revalidé côté serveur dans api/liste_attente.php — un code
// invalide ou inventé s'y ignore silencieusement.
$parrainUrl = preg_replace('/[^A-Za-z0-9]/', '', (string) ($_GET['r'] ?? ''));

/** Une carte de fonctionnalité : étiquette de couleur, titre, texte. */
function baCarte(string $couleur, string $etiquette, string $titre, string $texte, bool $vedette = false): string
{
    $style = $vedette
        ? 'background:var(--rouge);color:var(--sur-lave);'
        : 'background:var(--blanc);color:var(--noir);';

    return '<div class="ba-carte ba-reveal" style="' . $style . '">'
         . '<span class="ba-carte__etiquette" style="' . ($vedette ? 'color:var(--sur-lave);opacity:.8;' : 'color:' . htmlspecialchars($couleur) . ';') . '">' . htmlspecialchars($etiquette) . '</span>'
         . '<h3 class="ba-carte__titre">' . htmlspecialchars($titre) . '</h3>'
         . '<p class="ba-carte__texte"' . ($vedette ? ' style="color:var(--sur-lave);opacity:.85;"' : '') . '>' . htmlspecialchars($texte) . '</p>'
         . '</div>';
}

/** Une carte « secrète » : contenu révélé au lancement, pas avant. */
function baCarteSecrete(string $titre): string
{
    return '<div class="ba-carte ba-carte--secrete ba-reveal">'
         . '<span class="ba-carte__etiquette" style="color:var(--rouge);">Secret · révélé le jour J</span>'
         . '<h3 class="ba-carte__titre ba-flou">' . htmlspecialchars($titre) . '</h3>'
         . '<p class="ba-carte__texte ba-flou">Une fonctionnalité qu\'aucune app étudiante ne propose encore à Clermont.</p>'
         . '<span class="ba-carte__verrou">' . icon('etoile') . ' Débloqué au lancement</span>'
         . '</div>';
}

/** Une étape numérotée, fond clair (section établissements). */
function baEtapeClaire(string $numero, string $titre, string $texte): string
{
    return '<div class="ba-etape-claire ba-reveal">'
         . '<span class="ba-etape-claire__num">' . htmlspecialchars($numero) . '</span>'
         . '<span class="ba-etape-claire__titre">' . htmlspecialchars($titre) . '</span>'
         . '<span class="ba-etape-claire__texte">' . htmlspecialchars($texte) . '</span>'
         . '</div>';
}

/** Une étape numérotée du Pass Fondateur, sur fond lave. */
function baEtapeLave(string $numero, string $titre, string $texte): string
{
    return '<div class="ba-etape-lave">'
         . '<span class="ba-etape-lave__num">' . htmlspecialchars($numero) . '</span>'
         . '<div><span class="ba-etape-lave__titre">' . htmlspecialchars($titre) . '</span>'
         . '<span class="ba-etape-lave__texte">' . htmlspecialchars($texte) . '</span></div>'
         . '</div>';
}

/**
 * Un motif façon QR, purement décoratif — comme le pass affiché à l'écran
 * avant le scan, pas un vrai code. Généré une fois, côté serveur : même
 * algorithme (générateur congruentiel linéaire, graine fixe) que la version
 * dessinée en JavaScript dans la maquette d'origine, mais il n'y a ici ni
 * React ni nouveau rendu à chaque frame — un <svg> statique suffit.
 */
function baFauxQr(): string
{
    $n = 25;
    $seed = 11;
    $rnd = function () use (&$seed) {
        $seed = ($seed * 9301 + 49297) % 233280;
        return $seed / 233280;
    };
    $motifs = [[0, 0], [$n - 7, 0], [0, $n - 7]];
    $dansMotif = function (int $x, int $y) use ($motifs): bool {
        foreach ($motifs as [$fx, $fy]) {
            if ($x >= $fx - 1 && $x < $fx + 8 && $y >= $fy - 1 && $y < $fy + 8) {
                return true;
            }
        }
        return false;
    };

    $rects = '';
    for ($y = 0; $y < $n; $y++) {
        for ($x = 0; $x < $n; $x++) {
            if (!$dansMotif($x, $y) && $rnd() > 0.52) {
                $rects .= '<rect x="' . $x . '" y="' . $y . '" width="1" height="1" rx=".2"/>';
            }
        }
    }
    foreach ($motifs as $i => [$fx, $fy]) {
        $rects .= '<rect x="' . ($fx + .5) . '" y="' . ($fy + .5) . '" width="6" height="6" rx="1.6" fill="none" stroke="currentColor" stroke-width="1"/>';
        $rects .= '<rect x="' . ($fx + 2) . '" y="' . ($fy + 2) . '" width="3" height="3" rx=".8"/>';
    }

    return '<svg viewBox="0 0 25 25" width="100%" height="100%" fill="currentColor" aria-hidden="true">' . $rects . '</svg>';
}

/** Une puce de la bande logos / teasing, dupliquée pour une boucle continue. */
function baLogosMarquee(): string
{
    $logos = [
        ['Le Bec qui Pique', "font-family:var(--font-display);font-weight:var(--fw-black);font-size:24px;letter-spacing:-.03em;"],
        ['La Comédie', "font-family:var(--font-sans);font-weight:700;font-size:26px;font-style:italic;"],
        ['CHAT NOIR', "font-family:var(--font-mono);font-weight:600;font-size:20px;letter-spacing:.2em;"],
        ['club volcan', "font-family:var(--font-display);font-weight:400;font-size:22px;letter-spacing:.08em;"],
        ['Brasserie Jaude', "font-family:var(--font-sans);font-weight:700;font-size:24px;text-transform:uppercase;letter-spacing:.14em;"],
        ['Le Zinc.', "font-family:var(--font-display);font-weight:600;font-size:24px;"],
        ['studio_63', "font-family:var(--font-mono);font-weight:400;font-size:22px;"],
        ['Boui-Boui', "font-family:var(--font-sans);font-weight:500;font-size:26px;letter-spacing:-.02em;"],
        ['BDE UCA', "font-family:var(--font-display);font-weight:800;font-size:20px;letter-spacing:.1em;"],
    ];
    $item = '';
    foreach ($logos as [$nom, $style]) {
        $item .= '<span class="ba-marquee__item" style="' . $style . '">' . htmlspecialchars($nom) . '</span><span class="ba-marquee__point"></span>';
    }
    return '<div class="ba-marquee__groupe">' . $item . '</div><div class="ba-marquee__groupe" aria-hidden="true">' . $item . '</div>';
}

ob_start();
?>
<script>
  // Cette page reste toujours sombre, quel que soit le thème choisi côté
  // étudiant (stocké dans localStorage) : c'est l'écran de lancement de la
  // marque, pas un écran de l'application. Même mécanisme que les pages
  // pro (data-theme-fixe), qui restent en craie quoi qu'il arrive — ici,
  // en basalte. Sans ce garde, --noir (l'« encre ») s'inverserait en craie
  // pour un visiteur qui a basculé en clair, et le texte disparaîtrait sur
  // fond clair… littéralement.
  document.documentElement.setAttribute('data-theme', 'dark');
  document.documentElement.setAttribute('data-theme-fixe', 'dark');
</script>
<style>
  /* ═══════════════════════════════════════════════════════════════════
     Page d'attente — préfixe .ba- (« bientôt »). Les couleurs viennent des
     jetons de style.css (--rouge, --bleu, --lime…) : cette page est figée
     en thème sombre (script ci-dessus), donc --noir, --bg, --blanc y
     valent toujours craie / basalte / surface sombre.
     ═══════════════════════════════════════════════════════════════════ */

  .ba-conteneur { max-width: 1240px; margin: 0 auto; padding: 0 clamp(20px, 4vw, 48px); }

  .ba-entete {
    position: sticky; top: 0; z-index: 30;
    background: color-mix(in srgb, var(--bg) 92%, transparent);
    -webkit-backdrop-filter: blur(12px); backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--gris-clair);
  }
  .ba-entete__rangee { display: flex; align-items: center; justify-content: space-between; gap: 20px; padding: 16px clamp(20px, 4vw, 48px); }
  .ba-entete__nav { display: flex; align-items: center; gap: 20px; }
  .ba-entete__lien { font-size: 15px; font-weight: var(--fw-medium); color: var(--gris-fonce); text-decoration: none; white-space: nowrap; }
  .ba-entete__lien:hover { color: var(--noir); }
  @media (max-width: 760px) { .ba-entete__nav { display: none; } }

  .ba-pilule {
    display: inline-flex; align-items: center; gap: 8px; align-self: flex-start;
    height: 34px; padding: 0 14px; border-radius: var(--radius-pill); border: 1px solid var(--gris-clair);
    font-family: var(--font-mono); font-size: 12px; letter-spacing: .06em; color: var(--gris-fonce);
  }
  .ba-pilule__point { width: 8px; height: 8px; border-radius: 50%; background: var(--rouge); flex-shrink: 0; }
  @media (prefers-reduced-motion: no-preference) { .ba-pilule__point { animation: ba-pulse 1.7s ease-in-out infinite; } }
  @keyframes ba-pulse { 0%, 100% { box-shadow: 0 0 0 0 rgba(255,84,36,.6); } 70% { box-shadow: 0 0 0 9px rgba(255,84,36,0); } }

  /* ── Hero ── */
  .ba-hero {
    max-width: 1240px; margin: 0 auto; padding: clamp(32px, 8vw, 88px) clamp(20px, 4vw, 48px) clamp(48px, 8vw, 72px);
    display: grid; gap: clamp(40px, 6vw, 56px); align-items: center;
    grid-template-columns: repeat(auto-fit, minmax(min(100%, 440px), 1fr));
    overflow-x: clip;
  }
  .ba-hero__gauche { display: flex; flex-direction: column; gap: 26px; min-width: 0; }
  h1.ba-titre-hero {
    margin: 0; font-family: var(--font-display); font-weight: var(--fw-black);
    font-size: clamp(2.4rem, 6vw, 4.6rem); line-height: .98; letter-spacing: -.045em;
  }
  .ba-titre-hero .accent { color: var(--rouge); }
  .ba-texte-hero { margin: 0; max-width: 30em; font-size: clamp(17px, 1.6vw, 20px); line-height: 1.55; color: var(--gris-fonce); }

  .ba-countdown-bloc { display: flex; flex-direction: column; gap: 10px; max-width: 540px; }
  .ba-countdown-bloc__label { font-family: var(--font-mono); font-size: 12px; letter-spacing: .06em; color: var(--gris); }
  .ba-countdown { display: flex; gap: 8px; }
  .ba-compte {
    flex: 1; min-width: 0; background: var(--blanc); border: 1px solid var(--gris-clair); border-radius: var(--radius-md);
    padding: 12px 6px; display: flex; flex-direction: column; align-items: center; gap: 2px;
  }
  .ba-compte dd { margin: 0; font-family: var(--font-display); font-weight: var(--fw-black); font-size: clamp(26px, 3vw, 36px); font-variant-numeric: tabular-nums; }
  .ba-compte dt { font-family: var(--font-mono); font-size: 11px; color: var(--gris); letter-spacing: .06em; }

  .ba-form { display: flex; gap: 8px; flex-wrap: wrap; background: var(--blanc); border: 1px solid var(--gris-clair); border-radius: var(--radius-pill); padding: 6px; max-width: 540px; }
  .ba-form input[type="email"] {
    flex: 1; min-width: 180px; height: 52px; padding: 0 20px; border: none; outline: none; background: transparent;
    color: var(--noir); font-size: 16px;
  }
  .ba-form button[type="submit"] {
    flex: 1 0 auto; height: 52px; padding: 0 26px; border: none; border-radius: var(--radius-pill);
    background: var(--rouge); color: var(--sur-lave); font-weight: var(--fw-bold); font-size: 16px; cursor: pointer;
    white-space: nowrap; transition: transform .2s, filter .2s;
  }
  .ba-form button[type="submit"]:hover { filter: brightness(1.08); transform: translateY(-1px); }
  .ba-form button[type="submit"]:disabled { opacity: .6; cursor: default; }

  /* ── État « inscrit·e » : carte avec le lien de parrainage ── */
  .ba-rejoint {
    display: none; flex-direction: column; gap: 14px; background: var(--blanc); border: 1px solid var(--lime);
    border-radius: 28px; padding: 20px 22px; max-width: 540px;
  }
  .est-inscrit .ba-rejoint { display: flex; }
  .est-inscrit .ba-form { display: none; }
  .ba-rejoint__ligne1 { display: flex; align-items: center; gap: 14px; }
  .ba-rejoint__coche {
    width: 40px; height: 40px; border-radius: 50%; background: var(--lime); color: var(--sur-lave);
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
  }
  .ba-rejoint__coche svg { width: 20px; height: 20px; }
  .ba-rejoint__texte { display: flex; flex-direction: column; gap: 2px; }
  .ba-rejoint__titre { font-weight: var(--fw-bold); font-size: 16px; }
  .ba-rejoint__sous { font-size: 14px; color: var(--gris-fonce); }
  .ba-rejoint__lien {
    display: flex; gap: 8px; align-items: center; background: var(--sur-lave); border-radius: var(--radius-pill);
    padding: 5px 5px 5px 18px;
  }
  .ba-rejoint__lien span { flex: 1; min-width: 0; font-family: var(--font-mono); font-size: 13px; color: #CFC9D3; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .ba-rejoint__copier {
    height: 40px; padding: 0 18px; border: none; border-radius: var(--radius-pill); background: var(--lime);
    color: var(--sur-lave); font-weight: var(--fw-bold); font-size: 14px; cursor: pointer; white-space: nowrap;
  }

  /* ── Confirmation simple (section Pass Fondateur) ── */
  .ba-confirmation {
    display: none; background: var(--sur-lave); color: var(--noir); border-radius: 24px; padding: 18px 22px;
    max-width: 520px; flex-direction: column; gap: 4px;
  }
  .est-inscrit .ba-confirmation { display: flex; }
  .est-inscrit #ba-form-fondateur { display: none; }
  .ba-confirmation__titre { font-weight: var(--fw-bold); font-size: 16px; }
  .ba-confirmation__sous { font-size: 14px; opacity: .75; }

  .ba-mention { font-size: 14px; color: var(--gris); }
  .ba-mention a { color: var(--rouge); font-weight: var(--fw-semibold); text-decoration: none; }
  .ba-mention a:hover { text-decoration: underline; }
  .ba-form__retour { font-size: 13px; margin: 0; min-height: 1.2em; }
  .ba-form__retour.est-erreur { color: var(--danger); }

  /* ── Photo du hero + puces flottantes ── */
  .ba-photo-cadre {
    position: relative; min-height: clamp(400px, 100vw, 560px); display: flex; justify-content: center; align-items: center;
  }
  .ba-photo {
    position: relative; width: min(100%, 460px); height: clamp(380px, 96vw, 520px); border-radius: 40px; overflow: hidden;
    background: var(--blanc); transform: rotate(-2deg); transition: transform .5s cubic-bezier(.2,.8,.2,1);
  }
  .ba-photo:hover { transform: rotate(0deg) scale(1.015); }
  .ba-photo img { width: 100%; height: 100%; object-fit: cover; display: block; }

  .ba-flottant {
    position: absolute; pointer-events: none; box-shadow: 0 12px 32px rgba(0,0,0,.4);
  }
  .ba-flottant--remise {
    top: 20px; left: 0; height: 44px; padding: 0 18px; border-radius: var(--radius-pill); background: var(--rouge);
    color: var(--sur-lave); font-family: var(--font-display); font-weight: var(--fw-black); font-size: 20px;
    display: flex; align-items: center; transform: rotate(-8deg);
  }
  /* Toujours craie, comme le pastille QR plus haut : une pilule claire
     posée sur la photo, l'inverse volontaire du reste de la page. */
  .ba-flottant--social {
    top: 96px; right: 0; background: #F5F1E8; color: var(--sur-lave); border-radius: 22px; padding: 12px 16px;
    display: flex; align-items: center; gap: 10px; transform: rotate(5deg);
  }
  .ba-flottant--social span { font-weight: var(--fw-semibold); font-size: 14px; white-space: nowrap; }
  .ba-avatars { display: flex; }
  .ba-avatars i { width: 30px; height: 30px; border-radius: 50%; border: 2px solid #F5F1E8; margin-left: -10px; display: block; }
  .ba-avatars i:first-child { margin-left: 0; }
  .ba-flottant--pass {
    bottom: 24px; left: 0; width: min(230px, 72%); background: var(--sur-lave); border: 1px solid var(--gris-clair);
    border-radius: 26px; padding: 14px; display: flex; gap: 12px; align-items: center; transform: rotate(-4deg);
    box-shadow: 0 16px 40px rgba(0,0,0,.5);
  }
  /* Toujours craie, même dans cette page figée en sombre : c'est un
     pastille imprimée à l'intérieur de la carte, l'inverse volontaire du
     reste — comme .btn-outline-blanc le fait dans l'autre sens (toujours
     basalte). var(--bg) ne convient pas : ici il vaudrait basalte, la même
     couleur que le fond de la carte. */
  .ba-flottant--pass__qr { width: 66px; height: 66px; border-radius: 14px; background: #F5F1E8; color: #111013; padding: 7px; flex-shrink: 0; }
  .ba-flottant--pass__texte { display: flex; flex-direction: column; gap: 2px; color: var(--noir); }
  .ba-flottant--pass__texte b { font-family: var(--font-mono); font-size: 10px; color: var(--rouge); font-weight: 600; letter-spacing: .04em; }
  .ba-flottant--pass__texte strong { font-weight: var(--fw-bold); font-size: 14px; line-height: 1.2; }
  .ba-flottant--pass__texte small { font-size: 12px; color: var(--gris); }
  .ba-flottant--xp {
    bottom: 118px; right: 0; height: 44px; padding: 0 16px; border-radius: var(--radius-pill); background: var(--lime);
    color: var(--sur-lave); font-family: var(--font-mono); font-weight: var(--fw-semibold); font-size: 15px;
    display: flex; align-items: center; gap: 6px; transform: rotate(7deg);
  }
  @media (max-width: 760px) { .ba-flottant--social, .ba-flottant--xp { display: none; } }

  /* ── Bande logos partenaires (marquee) ── */
  .ba-bande-logos {
    border-top: 1px solid var(--gris-clair); border-bottom: 1px solid var(--gris-clair);
    padding: 34px 0; display: flex; flex-direction: column; gap: 22px; overflow: hidden;
  }
  .ba-bande-logos__label { text-align: center; font-family: var(--font-mono); font-size: 12px; letter-spacing: .08em; color: var(--gris); }
  .ba-marquee { position: relative; overflow: hidden; -webkit-mask-image: linear-gradient(90deg, transparent, #000 12%, #000 88%, transparent); mask-image: linear-gradient(90deg, transparent, #000 12%, #000 88%, transparent); }
  .ba-marquee__piste { display: flex; width: max-content; }
  @media (prefers-reduced-motion: no-preference) { .ba-marquee__piste { animation: ba-marquee 36s linear infinite; } }
  @keyframes ba-marquee { from { transform: translateX(0); } to { transform: translateX(-50%); } }
  .ba-marquee__groupe { display: flex; align-items: center; gap: 40px; padding-right: 40px; }
  .ba-marquee__item { color: var(--gris-fonce); white-space: nowrap; flex: none; transition: color .2s; }
  .ba-marquee__item:hover { color: var(--rouge); }
  .ba-marquee__point { width: 8px; height: 8px; border-radius: 50%; background: var(--gris-clair); flex: none; }

  /* ── Sections communes ── */
  .ba-section { max-width: 1240px; margin: 0 auto; padding: clamp(56px, 9vw, 96px) clamp(20px, 4vw, 48px); display: flex; flex-direction: column; gap: 40px; }
  .ba-section__entete { display: flex; flex-direction: column; gap: 12px; }
  .ba-surtitre { font-family: var(--font-mono); font-size: 13px; letter-spacing: .06em; color: var(--rouge); }
  h2.ba-titre { margin: 0; font-family: var(--font-display); font-weight: var(--fw-black); font-size: clamp(1.9rem, 4.4vw, 3.2rem); line-height: 1.03; letter-spacing: -.03em; max-width: 20ch; }

  /* Grilles qui deviennent un défilement horizontal sous 760px, comme la maquette. */
  .ba-grille-etapes, .ba-grille-cartes, .ba-grille-partenaires {
    display: grid; gap: 20px;
  }
  .ba-grille-etapes { grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
  .ba-grille-cartes { grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; }
  .ba-grille-partenaires { grid-template-columns: repeat(3, 1fr); gap: 16px; }
  @media (max-width: 760px) {
    .ba-grille-etapes, .ba-grille-cartes, .ba-grille-partenaires {
      grid-auto-flow: column; grid-auto-columns: 82%; grid-template-columns: none;
      overflow-x: auto; scroll-snap-type: x mandatory; scroll-padding: 0 20px;
      margin: 0 -20px; padding: 4px 20px 12px; scrollbar-width: none;
    }
    .ba-grille-etapes > *, .ba-grille-cartes > *, .ba-grille-partenaires > * { scroll-snap-align: start; }
  }

  .ba-etape-num { display: flex; flex-direction: column; gap: 16px; background: var(--blanc); border-radius: 28px; padding: 32px; transition: transform .35s cubic-bezier(.2,.8,.2,1); }
  .ba-etape-num:hover { transform: translateY(-6px); }
  .ba-etape-num.est-vedette { background: var(--rouge); color: var(--sur-lave); }
  .ba-etape-num__num { font-family: var(--font-display); font-weight: var(--fw-black); font-size: 48px; line-height: 1; color: var(--rouge); }
  .ba-etape-num.est-vedette .ba-etape-num__num { color: var(--sur-lave); }
  .ba-etape-num__titre { font-family: var(--font-display); font-weight: var(--fw-semibold); font-size: 22px; }
  .ba-etape-num__texte { font-size: 16px; line-height: 1.55; color: var(--gris-fonce); }
  .ba-etape-num.est-vedette .ba-etape-num__texte { color: var(--sur-lave); opacity: .9; }

  .ba-carte { border-radius: 28px; padding: 28px; display: flex; flex-direction: column; gap: 14px; min-height: 220px; transition: transform .35s cubic-bezier(.2,.8,.2,1), box-shadow .35s; }
  .ba-carte:hover { transform: translateY(-6px); box-shadow: 0 18px 40px rgba(0,0,0,.3); }
  .ba-carte__etiquette { font-family: var(--font-mono); font-size: 12px; letter-spacing: .06em; }
  .ba-carte__titre { margin: 0; font-family: var(--font-display); font-weight: var(--fw-semibold); font-size: 22px; line-height: 1.15; }
  .ba-carte__texte { margin: 0; font-size: 15px; line-height: 1.55; color: var(--gris-fonce); }
  .ba-carte--secrete { border: 1.5px dashed var(--rouge); position: relative; overflow: hidden; }
  .ba-carte--secrete:hover { box-shadow: 0 18px 40px rgba(255,84,36,.22); }
  .ba-flou { filter: blur(6px); user-select: none; }
  .ba-carte__verrou { margin-top: auto; align-self: flex-start; display: inline-flex; align-items: center; gap: 8px; height: 34px; padding: 0 14px; border-radius: var(--radius-pill); background: var(--rouge); color: var(--sur-lave); font-weight: var(--fw-bold); font-size: 13px; }
  .ba-carte__verrou svg { width: 15px; height: 15px; }

  /* ── Bande teasing ── */
  .ba-bande-teasing { overflow: hidden; padding: 26px 0; margin: clamp(24px,5vw,56px) 0; }
  .ba-bande-teasing__ruban {
    background: var(--rouge); color: var(--sur-lave); transform: rotate(-2deg); margin: 0 -40px;
    padding: 20px 0; overflow: hidden; box-shadow: 0 20px 60px rgba(255,84,36,.22);
  }
  .ba-bande-teasing__groupe { display: flex; align-items: center; gap: 32px; padding-right: 32px; }
  .ba-bande-teasing__item { font-family: var(--font-display); font-weight: var(--fw-black); font-size: clamp(24px, 3.6vw, 42px); letter-spacing: -.03em; white-space: nowrap; flex: none; }
  .ba-bande-teasing__item--fin { font-weight: var(--fw-regular); }
  .ba-bande-teasing__item--date { font-family: var(--font-mono); font-weight: 600; font-size: clamp(20px, 2.8vw, 32px); }
  .ba-bande-teasing__rond { width: 16px; height: 16px; border-radius: 50%; border: 4px solid var(--sur-lave); flex: none; }

  /* ── Étudiants : photo + cartes ── */
  .ba-etudiants-grille { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 480px), 1fr)); gap: 20px; }
  .ba-etudiants-photo { border-radius: 32px; overflow: hidden; background: var(--blanc); display: flex; flex-direction: column; }
  .ba-etudiants-photo__cadre { height: 280px; position: relative; }
  .ba-etudiants-photo__cadre img { width: 100%; height: 100%; object-fit: cover; display: block; }
  .ba-etudiants-photo__badge { position: absolute; top: 18px; left: 18px; height: 34px; padding: 0 14px; border-radius: var(--radius-pill); background: var(--rouge); color: var(--sur-lave); font-weight: var(--fw-bold); font-size: 14px; display: flex; align-items: center; }
  .ba-etudiants-photo__corps { padding: 26px; display: flex; flex-direction: column; gap: 8px; }
  .ba-etudiants-photo__corps h3 { margin: 0; font-family: var(--font-display); font-weight: var(--fw-semibold); font-size: 23px; }
  .ba-etudiants-photo__corps p { margin: 0; font-size: 15px; line-height: 1.55; color: var(--gris-fonce); }

  .ba-etudiants-droite { display: grid; grid-template-rows: 1fr 1fr; gap: 20px; }
  .ba-carte-squads { background: var(--bleu); color: var(--sur-lave); border-radius: 32px; padding: 26px; display: flex; flex-direction: column; justify-content: space-between; gap: 14px; transition: transform .35s; }
  .ba-carte-squads:hover { transform: translateY(-6px); }
  .ba-carte-squads__entete { display: flex; justify-content: space-between; align-items: flex-start; gap: 14px; }
  .ba-carte-squads__entete h3 { margin: 0; font-family: var(--font-display); font-weight: var(--fw-semibold); font-size: 22px; }
  .ba-carte-squads__entete span { font-family: var(--font-mono); font-size: 12px; font-weight: 600; }
  .ba-carte-squads p { margin: 0; font-size: 15px; line-height: 1.55; font-weight: var(--fw-medium); }

  .ba-carte-xp { background: var(--blanc); border-radius: 32px; padding: 26px; display: flex; flex-direction: column; gap: 14px; transition: transform .35s; }
  .ba-carte-xp:hover { transform: translateY(-6px); }
  .ba-carte-xp__entete { display: flex; justify-content: space-between; align-items: center; gap: 14px; }
  .ba-carte-xp__entete h3 { margin: 0; font-family: var(--font-display); font-weight: var(--fw-semibold); font-size: 22px; }
  .ba-carte-xp__badge { width: 44px; height: 44px; border-radius: 50%; background: var(--lime); color: var(--sur-lave); font-family: var(--font-display); font-weight: var(--fw-black); display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
  .ba-carte-xp p { margin: 0; font-size: 15px; line-height: 1.55; color: var(--gris-fonce); }
  .ba-carte-xp__jauge { height: 8px; border-radius: 4px; background: var(--gris-clair); overflow: hidden; }
  .ba-carte-xp__jauge i { display: block; width: 68%; height: 100%; background: var(--lime); }

  /* ── Établissements : bascule en clair, comme la maquette ── */
  .ba-clair { background: #F5F1E8; color: #111013; }
  .ba-clair .ba-surtitre { color: #C73A0E; }
  .ba-clair .ba-etablissements__texte { color: #4A4550; font-size: 17px; line-height: 1.55; max-width: 42ch; }
  .ba-etablissements__entete { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 400px), 1fr)); gap: 28px; align-items: end; }
  .ba-etape-claire { background: #FFFFFF; border-radius: 26px; padding: 26px; display: flex; flex-direction: column; gap: 10px; transition: transform .35s; }
  .ba-etape-claire:hover { transform: translateY(-6px); }
  .ba-etape-claire__num { font-family: var(--font-mono); font-size: 12px; color: #6B6570; }
  .ba-etape-claire__titre { font-family: var(--font-display); font-weight: var(--fw-semibold); font-size: 19px; }
  .ba-etape-claire__texte { font-size: 15px; line-height: 1.55; color: #4A4550; }

  .ba-vitrine-pro { background: var(--sur-lave); color: var(--noir); border-radius: 32px; padding: clamp(22px, 3vw, 38px); display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 380px), 1fr)); gap: 32px; align-items: center; }
  .ba-stats-mock { background: #1C1A1F; border-radius: 22px; padding: 22px; display: flex; flex-direction: column; gap: 16px; }
  .ba-stats-mock__entete { display: flex; justify-content: space-between; align-items: baseline; }
  .ba-stats-mock__entete span:first-child { font-family: var(--font-mono); font-size: 11px; color: var(--gris); }
  .ba-stats-mock__hausse { font-size: 13px; font-weight: var(--fw-bold); color: var(--rouge); }
  .ba-stats-mock__chiffres { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
  .ba-stats-mock__chiffres div { display: flex; flex-direction: column; }
  .ba-stats-mock__chiffres strong { font-family: var(--font-display); font-weight: var(--fw-black); font-size: 28px; }
  .ba-stats-mock__chiffres small { font-size: 12px; color: var(--gris); }
  .ba-stats-mock__barres { display: flex; align-items: flex-end; gap: 8px; height: 90px; }
  .ba-stats-mock__barres i { flex: 1; border-radius: 6px; background: var(--gris-clair); display: block; }

  .ba-vitrine-pro__droite { display: flex; flex-direction: column; gap: 18px; }
  .ba-vitrine-pro__droite h3 { margin: 0; font-family: var(--font-display); font-weight: var(--fw-black); font-size: clamp(24px, 3vw, 34px); line-height: 1.1; }
  .ba-vitrine-pro__droite p { margin: 0; font-size: 16px; line-height: 1.55; color: var(--gris-fonce); }
  .ba-vitrine-pro__actions { display: flex; gap: 12px; flex-wrap: wrap; }

  .ba-btn { height: 52px; padding: 0 24px; border-radius: var(--radius-pill); font-weight: var(--fw-bold); display: inline-flex; align-items: center; text-decoration: none; transition: transform .2s; }
  .ba-btn:hover { transform: translateY(-2px); }
  .ba-btn--pleine { background: var(--rouge); color: var(--sur-lave); }
  /* Le seul bouton « contour » de la page vit sur la carte sombre
     .ba-vitrine-pro, elle-même posée sur la section claire : toujours
     craie, jamais lié au fond de la section qui l'entoure. */
  .ba-btn--contour { border: 1.5px solid var(--noir); color: var(--noir); }

  /* ── Pass Fondateur ── */
  .ba-fondateur {
    background: var(--rouge); color: var(--sur-lave); border-radius: 40px; padding: clamp(32px, 6vw, 72px);
    display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 400px), 1fr)); gap: 44px;
    position: relative; overflow: hidden;
  }
  .ba-fondateur__anneaux { position: absolute; right: -60px; top: -60px; display: flex; opacity: .9; pointer-events: none; }
  .ba-fondateur__anneaux i { width: 200px; height: 200px; border-radius: 50%; display: block; }
  .ba-fondateur__anneaux i:first-child { border: 30px solid var(--sur-lave); }
  .ba-fondateur__anneaux i:last-child { border: 30px solid var(--noir); margin-left: -72px; }
  .ba-fondateur__gauche { display: flex; flex-direction: column; gap: 20px; position: relative; min-width: 0; }
  .ba-fondateur__gauche .ba-surtitre { color: var(--sur-lave); font-weight: 600; }
  h2.ba-titre-fondateur { margin: 0; font-family: var(--font-display); font-weight: var(--fw-black); font-size: clamp(2rem, 5vw, 3.6rem); line-height: 1; letter-spacing: -.04em; }
  .ba-fondateur__texte { margin: 0; font-size: 18px; line-height: 1.5; font-weight: var(--fw-medium); max-width: 30ch; }
  .ba-progression { display: flex; flex-direction: column; gap: 8px; }
  .ba-progression__legende { display: flex; justify-content: space-between; font-weight: var(--fw-bold); font-size: 14px; }
  .ba-progression__legende span:last-child { font-family: var(--font-mono); }
  .ba-progression__piste { height: 12px; border-radius: 6px; background: rgba(17,16,19,.18); overflow: hidden; }
  .ba-progression__remplie { height: 100%; border-radius: 6px; background: var(--sur-lave); transition: width .6s; }
  .ba-fondateur .ba-form { background: var(--sur-lave); }
  .ba-fondateur .ba-form input[type="email"] { color: var(--noir); }
  .ba-fondateur .ba-form input[type="email"]::placeholder { color: rgba(245,241,232,.5); }
  .ba-fondateur .ba-form button[type="submit"] { background: var(--noir); color: var(--sur-lave); }
  .ba-fondateur .ba-confirmation { background: var(--sur-lave); color: var(--noir); }

  .ba-fondateur__droite { display: flex; flex-direction: column; gap: 12px; justify-content: center; position: relative; }
  .ba-etape-lave { background: rgba(17,16,19,.08); border: 1.5px solid var(--sur-lave); border-radius: 22px; padding: 18px 20px; display: flex; gap: 14px; align-items: flex-start; }
  .ba-etape-lave > div { display: flex; flex-direction: column; gap: 4px; }
  .ba-etape-lave__num { font-family: var(--font-display); font-weight: var(--fw-black); font-size: 22px; line-height: 1; }
  .ba-etape-lave__titre { font-weight: var(--fw-bold); font-size: 16px; }
  .ba-etape-lave__texte { font-size: 14px; line-height: 1.45; font-weight: var(--fw-medium); }

  /* ── Barre mobile flottante ── */
  .ba-barre-mobile {
    display: none; position: fixed; left: 12px; right: 12px; bottom: 12px; z-index: 35; height: 56px;
    border-radius: var(--radius-pill); background: var(--rouge); color: var(--sur-lave); align-items: center;
    justify-content: space-between; padding: 0 8px 0 22px; font-weight: var(--fw-bold); font-size: 16px;
    text-decoration: none; box-shadow: 0 12px 32px rgba(0,0,0,.5);
  }
  .ba-barre-mobile__jours { height: 42px; padding: 0 14px; border-radius: var(--radius-pill); background: var(--sur-lave); color: var(--noir); font-family: var(--font-mono); font-size: 13px; display: flex; align-items: center; }
  @media (max-width: 760px) { .ba-barre-mobile { display: flex; } }
  .est-inscrit .ba-barre-mobile { display: none; }

  /* ── Pied de page ── */
  .ba-pied { border-top: 1px solid var(--gris-clair); }
  .ba-pied__grille { max-width: 1240px; margin: 0 auto; padding: 48px clamp(20px, 4vw, 48px) 0; display: grid; grid-template-columns: 1.6fr 1fr 1fr 1fr; gap: 28px; }
  @media (max-width: 700px) { .ba-pied__grille { grid-template-columns: 1fr 1fr; } }
  .ba-pied__texte { font-size: 14px; color: var(--gris); margin: 10px 0 0; max-width: 30ch; }
  .ba-pied__titre { font-family: var(--font-mono); font-size: 11px; letter-spacing: .06em; color: var(--gris); margin: 0 0 10px; }
  .ba-pied__liste { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 9px; }
  .ba-pied__liste a { color: var(--gris-fonce); text-decoration: none; font-size: 14px; }
  .ba-pied__liste a:hover { color: var(--noir); text-decoration: underline; }
  .ba-pied__bas { max-width: 1240px; margin: 0 auto; padding: 20px clamp(20px, 4vw, 48px) 32px; display: flex; flex-wrap: wrap; justify-content: space-between; gap: 10px; font-size: 13px; color: var(--gris); border-top: 1px solid var(--gris-clair); margin-top: 20px; padding-top: 18px; }
  .ba-pied__bas a { color: var(--gris); }

  /* Révélé au défilement. */
  .ba-reveal { opacity: 0; translate: 0 28px; transition: opacity .6s cubic-bezier(.2,.8,.2,1), translate .6s cubic-bezier(.2,.8,.2,1); }
  .ba-reveal.ba-visible { opacity: 1; translate: 0 0; }
  @media (prefers-reduced-motion: reduce) { .ba-reveal { opacity: 1; translate: 0 0; transition: none; } }
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
<nav class="ba-entete">
  <div class="ba-entete__rangee">
    <?= marqueLinkee('', baseUrl('/')) ?>
    <div class="ba-entete__nav">
      <a class="ba-entete__lien" href="#fonctionnement">Fonctionnalités</a>
      <a class="ba-entete__lien" href="#etudiants">Étudiants</a>
      <a class="ba-entete__lien" href="#partenaires">Établissements &amp; assos</a>
    </div>
    <a class="ba-btn ba-btn--pleine" href="#inscription">Accès anticipé</a>
  </div>
</nav>

<main data-parrain="<?= htmlspecialchars($parrainUrl, ENT_QUOTES) ?>">
  <header class="ba-hero">
    <div class="ba-hero__gauche">
      <span class="ba-pilule"><span class="ba-pilule__point" aria-hidden="true"></span>Bientôt à Clermont-Ferrand</span>
      <h1 class="ba-titre-hero">Clermont va sortir <span class="accent">autrement.</span></h1>
      <p class="ba-texte-hero">
        Une nouvelle app arrive pour les étudiants&nbsp;: soirées, réductions exclusives dans les bars
        et boîtes de la ville, squads sportives. Les premiers inscrits entrent avant tout le monde.
      </p>

      <div class="ba-countdown-bloc" data-lancement="<?= htmlspecialchars(date(DATE_ATOM, $lancementTs), ENT_QUOTES) ?>">
        <span class="ba-countdown-bloc__label">Lancement dans</span>
        <div class="ba-countdown">
          <dl class="ba-compte"><dd data-compte="jours">00</dd><dt>Jours</dt></dl>
          <dl class="ba-compte"><dd data-compte="heures">00</dd><dt>Heures</dt></dl>
          <dl class="ba-compte"><dd data-compte="minutes">00</dd><dt>Min</dt></dl>
          <dl class="ba-compte"><dd data-compte="secondes">00</dd><dt>Sec</dt></dl>
        </div>
      </div>

      <div data-groupe-inscription>
        <form class="ba-form" id="ba-form-hero" novalidate>
          <label class="sr-only" for="ba-email-hero">Ton e-mail étudiant</label>
          <input type="email" id="ba-email-hero" name="email" placeholder="ton.email@etu.uca.fr" autocomplete="email" required>
          <button type="submit">Réserver ma place</button>
        </form>
        <div class="ba-rejoint" data-carte-rejoint>
          <div class="ba-rejoint__ligne1">
            <span class="ba-rejoint__coche"><?= icon('check') ?></span>
            <span class="ba-rejoint__texte">
              <span class="ba-rejoint__titre" data-position-texte>Tu es sur la liste.</span>
              <span class="ba-rejoint__sous">Chaque pote inscrit avec ton lien te fait gagner 25 places.</span>
            </span>
          </div>
          <div class="ba-rejoint__lien">
            <span data-lien-partage></span>
            <button type="button" class="ba-rejoint__copier" data-bouton-copier>Copier</button>
          </div>
        </div>
        <p class="ba-form__retour" data-retour-pour="ba-form-hero" role="status" aria-live="polite"></p>
      </div>

      <p class="ba-mention">Gratuit pour les étudiants · Tu gères un lieu ou une asso&nbsp;? <a href="#partenaires">Devenir partenaire →</a></p>
    </div>

    <div class="ba-photo-cadre" aria-hidden="true">
      <div class="ba-photo">
        <img src="https://images.unsplash.com/photo-1671116810348-8148423dec80?fm=jpg&amp;q=70&amp;w=1200&amp;auto=format&amp;fit=crop"
             alt="" loading="eager" title="Photo par Himanshu Choudhary sur Unsplash">
      </div>
      <span class="ba-flottant ba-flottant--remise">-40&nbsp;% ce soir</span>
      <span class="ba-flottant ba-flottant--social">
        <span class="ba-avatars"><i style="background:var(--bleu);"></i><i style="background:var(--orange);"></i><i style="background:var(--rouge);"></i></span>
        <span>Léa, Hugo +3 y vont</span>
      </span>
      <div class="ba-flottant ba-flottant--pass">
        <div class="ba-flottant--pass__qr"><?= baFauxQr() ?></div>
        <div class="ba-flottant--pass__texte"><b>PASS ACTIF</b><strong>Afterwork Mix</strong><small>Montre-le à l'entrée</small></div>
      </div>
      <span class="ba-flottant ba-flottant--xp"><?= icon('trophee') ?> +50 XP</span>
    </div>
  </header>

  <section class="ba-bande-logos">
    <span class="ba-bande-logos__label">Ils nous font déjà confiance</span>
    <div class="ba-marquee"><div class="ba-marquee__piste"><?= baLogosMarquee() ?></div></div>
  </section>

  <section class="ba-section" id="fonctionnement">
    <div class="ba-section__entete">
      <span class="ba-surtitre">Comment ça marche</span>
      <h2 class="ba-titre">De ton canapé au comptoir en trois étapes.</h2>
    </div>
    <div class="ba-grille-etapes">
      <div class="ba-etape-num ba-reveal"><span class="ba-etape-num__num">01</span><span class="ba-etape-num__titre">Explore</span><span class="ba-etape-num__texte">Les events du moment, les offres flash du soir et les sorties où vont tes amis.</span></div>
      <div class="ba-etape-num ba-reveal"><span class="ba-etape-num__num">02</span><span class="ba-etape-num__titre">Inscris-toi</span><span class="ba-etape-num__texte">Un tap pour réserver ta place. Ton pass QR apparaît aussitôt dans l'app.</span></div>
      <div class="ba-etape-num est-vedette ba-reveal"><span class="ba-etape-num__num">03</span><span class="ba-etape-num__titre">Scanne et profite</span><span class="ba-etape-num__texte">Montre ton pass à l'entrée : la réduction s'applique et tu gagnes de l'XP.</span></div>
    </div>
  </section>

  <section class="ba-section" style="padding-top:0;">
    <div class="ba-section__entete">
      <span class="ba-surtitre">Tout ce qu'il y a dans l'app</span>
      <h2 class="ba-titre">Six raisons de l'ouvrir. Deux qu'on garde secrètes.</h2>
    </div>
    <div class="ba-grille-cartes">
      <?= baCarte('var(--rouge)', 'Explorer', 'Events près de toi', 'Filtre par bars, boîtes, restos ou sport. Carte et liste, triées par date et distance.') ?>
      <?= baCarte('var(--orange)', 'Offres flash', 'Deals à durée limitée', 'Réductions valables quelques heures, avec compte à rebours et places limitées.') ?>
      <?= baCarte('', 'Pass QR', 'Ton entrée en un scan', "Chaque inscription génère un pass. Tu le montres à l'accueil, la réduction s'applique.", true) ?>
      <?= baCarte('var(--bleu)', 'Squads', 'Sport en groupe', 'Running, vélo, muscu, escalade. Rejoins une squad à ton niveau ou crée la tienne.') ?>
      <?= baCarte('var(--lime)', 'Amis', 'Vois où ils sortent', "Suis les étudiants de ton école, découvre leurs sorties et invite-les à un event.") ?>
      <?= baCarte('var(--lime)', 'XP & badges', 'Chaque sortie compte', "Check-in, avis, squads : gagne de l'XP, débloque des badges et monte de niveau.") ?>
      <?= baCarteSecrete('Mode soirée surprise') ?>
      <?= baCarteSecrete('Le grand jeu inter-écoles') ?>
    </div>
  </section>

  <div class="ba-bande-teasing">
    <div class="ba-bande-teasing__ruban">
      <div class="ba-marquee"><div class="ba-marquee__piste">
        <?php for ($i = 0; $i < 2; $i++): ?>
        <div class="ba-bande-teasing__groupe">
          <span class="ba-bande-teasing__item">BIENTÔT</span><span class="ba-bande-teasing__rond"></span>
          <span class="ba-bande-teasing__item ba-bande-teasing__item--fin">Clermont-Ferrand</span><span class="ba-bande-teasing__rond"></span>
          <span class="ba-bande-teasing__item ba-bande-teasing__item--date"><?= htmlspecialchars($lancementJJMM) ?></span><span class="ba-bande-teasing__rond"></span>
          <span class="ba-bande-teasing__item">BIENTÔT</span><span class="ba-bande-teasing__rond"></span>
          <span class="ba-bande-teasing__item ba-bande-teasing__item--fin">Clermont-Ferrand</span><span class="ba-bande-teasing__rond"></span>
          <span class="ba-bande-teasing__item ba-bande-teasing__item--date"><?= htmlspecialchars($lancementJJMM) ?></span><span class="ba-bande-teasing__rond"></span>
        </div>
        <?php endfor; ?>
      </div></div>
    </div>
  </div>

  <section class="ba-section" id="etudiants" style="padding-top:0;">
    <div class="ba-conteneur" style="padding:0;display:flex;justify-content:space-between;align-items:flex-end;gap:24px;flex-wrap:wrap;">
      <div class="ba-section__entete" style="gap:12px;">
        <span class="ba-surtitre">Pour les étudiants</span>
        <h2 class="ba-titre">Ta vie étudiante, en plus simple et moins cher.</h2>
      </div>
      <span style="font-size:15px;color:var(--gris);">Gratuit · iOS et Android</span>
    </div>
    <div class="ba-etudiants-grille">
      <div class="ba-etudiants-photo ba-reveal">
        <div class="ba-etudiants-photo__cadre">
          <img src="https://images.unsplash.com/photo-1573159625584-56169888c23d?fm=jpg&amp;q=70&amp;w=1000&amp;auto=format&amp;fit=crop"
               alt="" loading="lazy" title="Photo par Josh Olalde sur Unsplash">
          <span class="ba-etudiants-photo__badge">Happy hour -30&nbsp;%</span>
        </div>
        <div class="ba-etudiants-photo__corps">
          <h3>Des réductions exclusives</h3>
          <p>Happy hours, offres flash avec compte à rebours, entrées gratuites : des deals négociés pour les étudiants, dans les lieux que tu aimes déjà.</p>
        </div>
      </div>
      <div class="ba-etudiants-droite">
        <div class="ba-carte-squads ba-reveal">
          <div class="ba-carte-squads__entete"><h3>Squads sportives</h3><span>RUNNING · VÉLO · MUSCU</span></div>
          <p>Rejoins un groupe à ton niveau près de chez toi, ou crée le tien. Ne cours plus seul·e.</p>
        </div>
        <div class="ba-carte-xp ba-reveal">
          <div class="ba-carte-xp__entete"><h3>XP, badges, amis</h3><span class="ba-carte-xp__badge">7</span></div>
          <p>Suis tes amis, vois où ils sortent, grimpe au classement de ton école à chaque check-in.</p>
          <div class="ba-carte-xp__jauge"><i></i></div>
        </div>
      </div>
    </div>
  </section>

  <section class="ba-clair" id="partenaires">
    <div class="ba-section">
      <div class="ba-etablissements__entete">
        <div class="ba-section__entete" style="gap:12px;">
          <span class="ba-surtitre">Pour les établissements &amp; associations</span>
          <h2 class="ba-titre">Touchez les étudiants là où ils décident de sortir.</h2>
        </div>
        <p class="ba-etablissements__texte">Bars, boîtes, restaurants, BDE et associations étudiantes&nbsp;: Linkee pro vous donne les outils pour publier, accueillir et mesurer.</p>
      </div>

      <div class="ba-grille-partenaires">
        <?= baEtapeClaire('01', 'Créez vos événements', 'Quota de places, réduction, offre flash, entrée gratuite. Publié en deux minutes.') ?>
        <?= baEtapeClaire('02', 'Scannez à l\'entrée', "Depuis un téléphone ou un poste d'accueil. Pass validé ou refusé en une seconde.") ?>
        <?= baEtapeClaire('03', 'Suivez la fréquentation', 'Inscrits, check-in, heures de pointe, écoles représentées, avis des étudiants.') ?>
      </div>

      <div class="ba-vitrine-pro ba-reveal">
        <div class="ba-stats-mock">
          <div class="ba-stats-mock__entete"><span>CE SOIR · AFTERWORK MIX</span><span class="ba-stats-mock__hausse">+340&nbsp;%</span></div>
          <div class="ba-stats-mock__chiffres">
            <div><strong>86</strong><small>inscrits</small></div>
            <div><strong>41</strong><small>check-in</small></div>
            <div><strong>4,6</strong><small>note</small></div>
          </div>
          <div class="ba-stats-mock__barres">
            <i style="height:22%;"></i><i style="height:38%;"></i><i style="height:32%;"></i>
            <i style="height:62%;background:var(--noir);"></i><i style="height:100%;background:var(--rouge);"></i><i style="height:86%;background:var(--noir);"></i>
          </div>
        </div>
        <div class="ba-vitrine-pro__droite">
          <h3>Vous êtes une asso ou un BDE&nbsp;?</h3>
          <p>Publiez vos soirées, afterworks et tournois comme un établissement, et vendez vos places aux étudiants de toutes les écoles de Clermont.</p>
          <div class="ba-vitrine-pro__actions">
            <a class="ba-btn ba-btn--pleine" href="mailto:contact@linkee.fr?subject=Devenir%20partenaire%20Linkee">Devenir partenaire</a>
            <a class="ba-btn ba-btn--contour" href="mailto:contact@linkee.fr?subject=Demande%20de%20d%C3%A9mo%20Linkee">Demander une démo</a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <section class="ba-section" id="inscription">
    <div class="ba-fondateur ba-reveal">
      <div class="ba-fondateur__anneaux" aria-hidden="true"><i></i><i></i></div>
      <div class="ba-fondateur__gauche">
        <span class="ba-surtitre">Pass Fondateur · places limitées</span>
        <h2 class="ba-titre-fondateur">Sois là avant tout le monde.</h2>
        <p class="ba-fondateur__texte">Inscris-toi avec ton e-mail étudiant. On t'envoie ton accès le jour du lancement, avant l'ouverture au public.</p>

        <div class="ba-progression">
          <div class="ba-progression__legende"><span>Pass Fondateur réservés</span><span data-legende-fondateur><?= (int) min($inscrits, $objectifFondateurs) ?> / <?= (int) $objectifFondateurs ?></span></div>
          <div class="ba-progression__piste"><div class="ba-progression__remplie" data-barre-fondateur style="width: <?= (int) round(min(100, $inscrits / $objectifFondateurs * 100)) ?>%;"></div></div>
        </div>

        <div data-groupe-inscription>
          <form class="ba-form" id="ba-form-fondateur" novalidate>
            <label class="sr-only" for="ba-email-fondateur">Ton e-mail étudiant</label>
            <input type="email" id="ba-email-fondateur" name="email" placeholder="ton.email@etu.uca.fr" autocomplete="email" required>
            <button type="submit">Je réserve mon pass</button>
          </form>
          <div class="ba-confirmation">
            <span class="ba-confirmation__titre">Ton Pass Fondateur est réservé.</span>
            <span class="ba-confirmation__sous">Rendez-vous le jour du lancement dans ta boîte mail.</span>
          </div>
          <p class="ba-form__retour" data-retour-pour="ba-form-fondateur" role="status" aria-live="polite"></p>
        </div>
      </div>
      <div class="ba-fondateur__droite">
        <?= baEtapeLave('01', "Accès avant l'ouverture", 'Tu découvres les premiers events et offres avant tout le monde.') ?>
        <?= baEtapeLave('02', 'Badge Fondateur', "Un badge exclusif sur ton profil, qu'on ne pourra plus obtenir après le lancement.") ?>
        <?= baEtapeLave('03', 'Ta première sortie offerte', 'Un verre ou une entrée offerte chez un partenaire au lancement.') ?>
      </div>
    </div>
  </section>
</main>

<a href="#inscription" class="ba-barre-mobile" data-barre-mobile>
  <span>Réserver mon Pass Fondateur</span>
  <span class="ba-barre-mobile__jours">J-<span data-compte="jours-mobile">00</span></span>
</a>

<footer class="ba-pied">
  <div class="ba-pied__grille">
    <div>
      <?= marqueLinkee() ?>
      <p class="ba-pied__texte">La plateforme sociale et événementielle des étudiants de Clermont-Ferrand.</p>
    </div>
    <div>
      <p class="ba-pied__titre">Étudiants</p>
      <ul class="ba-pied__liste"><li><a href="#fonctionnement">Fonctionnalités</a></li><li><a href="#inscription">Réserver ma place</a></li></ul>
    </div>
    <div>
      <p class="ba-pied__titre">Partenaires</p>
      <ul class="ba-pied__liste"><li><a href="#partenaires">Établissements</a></li><li><a href="mailto:contact@linkee.fr">Associations &amp; BDE</a></li></ul>
    </div>
    <div>
      <p class="ba-pied__titre">Contact</p>
      <ul class="ba-pied__liste"><li><a href="mailto:contact@linkee.fr">contact@linkee.fr</a></li></ul>
    </div>
  </div>
  <div class="ba-pied__bas">
    <span>© <?= date('Y') ?> Linkee · Clermont-Ferrand</span>
    <span><a href="<?= baseUrl('/mentions-legales.php') ?>">Mentions légales</a> · <a href="<?= baseUrl('/confidentialite.php') ?>">Confidentialité</a></span>
  </div>
</footer>

<script>
(function () {
  var racine = document.querySelector('main[data-parrain]');
  var parrain = racine ? racine.getAttribute('data-parrain') : '';

  // ── Compte à rebours (bloc principal + chip de la barre mobile) ────────
  var bloc = document.querySelector('[data-lancement]');
  if (bloc) {
    var cible = new Date(bloc.getAttribute('data-lancement')).getTime();
    var cases = {
      jours: document.querySelectorAll('[data-compte="jours"], [data-compte="jours-mobile"]'),
      heures: bloc.querySelector('[data-compte="heures"]'),
      minutes: bloc.querySelector('[data-compte="minutes"]'),
      secondes: bloc.querySelector('[data-compte="secondes"]'),
    };
    var deuxChiffres = function (n) { return String(Math.max(0, n)).padStart(2, '0'); };
    var mettreAJour = function () {
      var reste = Math.max(0, cible - Date.now());
      var s = Math.floor(reste / 1000);
      var j = deuxChiffres(Math.floor(s / 86400));
      cases.jours.forEach(function (el) { el.textContent = j; });
      cases.heures.textContent = deuxChiffres(Math.floor((s % 86400) / 3600));
      cases.minutes.textContent = deuxChiffres(Math.floor((s % 3600) / 60));
      cases.secondes.textContent = deuxChiffres(s % 60);
    };
    mettreAJour();
    setInterval(mettreAJour, 1000);
  }

  // ── État « inscrit·e » : applique le résultat à toute la page ──────────
  function appliquerEtat(donnees) {
    document.body.classList.add('est-inscrit');

    var objectif = 500;
    var affiche = Math.min(donnees.count, objectif);
    document.querySelectorAll('[data-legende-fondateur]').forEach(function (el) {
      el.textContent = affiche + ' / ' + objectif;
    });
    document.querySelectorAll('[data-barre-fondateur]').forEach(function (el) {
      el.style.width = Math.round(Math.min(100, (donnees.count / objectif) * 100)) + '%';
    });

    var lien = location.origin + location.pathname + '?r=' + donnees.code;
    document.querySelectorAll('[data-lien-partage]').forEach(function (el) { el.textContent = lien.replace(/^https?:\/\//, ''); });
    document.querySelectorAll('[data-position-texte]').forEach(function (el) {
      el.textContent = 'Tu es n°' + donnees.position_affichee + ' sur la liste.';
    });
    document.querySelectorAll('[data-bouton-copier]').forEach(function (bouton) {
      bouton.onclick = function () {
        try { navigator.clipboard.writeText(lien); } catch (e) {}
        bouton.textContent = 'Copié !';
        setTimeout(function () { bouton.textContent = 'Copier'; }, 2000);
      };
    });

    try { localStorage.setItem('linkee_liste_attente_code', donnees.code); } catch (e) {}
  }

  // Visiteur qui revient : son code est en mémoire, on retrouve son rang
  // (mis à jour, si des filleuls ont rejoint depuis) sans le réinscrire.
  try {
    var codeStocke = localStorage.getItem('linkee_liste_attente_code');
    if (codeStocke) {
      fetch(BASE + '/api/liste_attente_statut.php?code=' + encodeURIComponent(codeStocke))
        .then(function (r) { return r.json(); })
        .then(function (data) { if (data && data.success) appliquerEtat(data); })
        .catch(function () {});
    }
  } catch (e) {}

  // ── Formulaires (hero + Pass Fondateur) ─────────────────────────────────
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
        body: JSON.stringify({ email: email, parrain: parrain }),
      })
        .then(function (r) { return r.json(); })
        .then(function (data) {
          bouton.disabled = false;
          bouton.textContent = texteInitial;
          if (data && data.success) {
            appliquerEtat(data);
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

  // ── Micro-animations : chips flottantes et révélation au défilement ────
  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
    document.querySelectorAll('.ba-flottant').forEach(function (el, i) {
      el.animate(
        [{ translate: '0 0' }, { translate: '0 -10px' }, { translate: '0 0' }],
        { duration: 3200 + i * 500, delay: i * 300, iterations: Infinity, easing: 'ease-in-out' }
      );
    });

    var els = Array.prototype.slice.call(document.querySelectorAll('.ba-reveal'));
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entree) {
        if (!entree.isIntersecting) return;
        entree.target.classList.add('ba-visible');
        io.unobserve(entree.target);
      });
    }, { threshold: 0.12 });
    els.forEach(function (el) {
      if (el.getBoundingClientRect().top > window.innerHeight) io.observe(el);
      else el.classList.add('ba-visible');
    });
  } else {
    document.querySelectorAll('.ba-reveal').forEach(function (el) { el.classList.add('ba-visible'); });
  }
})();
</script>
</body>
</html>
