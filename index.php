<?php
/**
 * La vitrine d'Outsee.
 *
 * Deux publics, deux sorties :
 *   - les étudiants téléchargent l'application (le site ne leur sert plus
 *     d'interface) ;
 *   - les établissements ouvrent leur espace pro, où se gèrent compte,
 *     événements et paiements.
 *
 * Direction visuelle (2026-11-17, références d'Arthur) : claire, grand cadre
 * blanc, titre central, anneaux et pastilles flottantes, cartes en verre.
 * Styles : assets/css/vitrine.css.
 *
 * Les formules et leurs quotas sont lus dans le code (crmOffres(),
 * grilleCapacites()) : la vitrine affiche ce qui est réellement facturé.
 * Les notifications et étiquettes de l'accroche sont des exemples
 * d'interface, pas des chiffres d'usage.
 */
require_once __DIR__ . '/includes/site.php';
require_once __DIR__ . '/includes/crm.php';
require_once __DIR__ . '/includes/capacites.php';
require_once __DIR__ . '/includes/sponsoring.php';

// Un établissement ou un administrateur connecté va droit à son espace.
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . accueilSelonType($_SESSION['user_type'] ?? null));
    exit;
}

$offres = crmOffres();
$grille = grilleCapacites();

/** Les formules présentées aux établissements, dans l'ordre de la grille. */
$formules = [
    'essentiel' => ['pour' => 'Bars, restaurants, afterworks', 'vedette' => false],
    'premium'   => ['pour' => 'Discothèques, groupes, plusieurs lieux', 'vedette' => true],
    'club'      => ['pour' => 'Clubs et complexes sportifs', 'vedette' => false],
    'bde'       => ['pour' => 'BDE et associations étudiantes', 'vedette' => false],
];

/**
 * Ce que comprend une formule, en phrases courtes tirées de la grille.
 *
 * @param array<string, int|null> $cap une ligne de grilleCapacites()
 * @return list<string>
 */
function avantagesFormule(string $code, array $cap): array
{
    $l = [(int) $cap['evenements_par_mois'] . ' événements publiés par mois'];
    if ($code === 'club') {
        $l[] = 'Créneaux de terrain illimités';
    }
    if (($cap['flash_par_mois'] ?? 0) > 0) {
        $l[] = $cap['flash_par_mois'] . ' offre flash par mois';
    }
    if (($cap['mises_en_avant_offertes'] ?? 0) > 0) {
        $l[] = $cap['mises_en_avant_offertes'] . ' mise en avant offerte par mois';
    }
    $l[] = 'Scan des pass et liste des inscrits';
    $l[] = 'Statistiques de fréquentation';

    return $l;
}

/**
 * Un motif de QR code pour l'illustration du pass : trois repères et des
 * modules déterministes. Ce n'est PAS un code lisible — aucun pass réel ne
 * figure sur la vitrine.
 */
function qrIllustration(): string
{
    $n = 25;
    $repere = static fn (int $x, int $y): bool =>
        ($x < 7 && $y < 7) || ($x >= $n - 7 && $y < 7) || ($x < 7 && $y >= $n - 7);
    $plein = static function (int $x, int $y) use ($n): bool {
        foreach ([[0, 0], [$n - 7, 0], [0, $n - 7]] as [$ox, $oy]) {
            if ($x >= $ox && $x < $ox + 7 && $y >= $oy && $y < $oy + 7) {
                $dx = $x - $ox;
                $dy = $y - $oy;
                $bord = $dx === 0 || $dx === 6 || $dy === 0 || $dy === 6;
                $coeur = $dx >= 2 && $dx <= 4 && $dy >= 2 && $dy <= 4;

                return $bord || $coeur;
            }
        }

        return ((($x * 7 + $y * 13 + $x * $y) % 5) < 2) && (($x + $y) % 3 !== 0 || $x % 2 === 0);
    };

    $rects = '';
    for ($y = 0; $y < $n; $y++) {
        for ($x = 0; $x < $n; $x++) {
            // Les marges autour des repères restent vides.
            $marge = ($x < 8 && $y < 8) || ($x >= $n - 8 && $y < 8) || ($x < 8 && $y >= $n - 8);
            if (($repere($x, $y) || !$marge) && $plein($x, $y)) {
                $rects .= '<rect x="' . $x . '" y="' . $y . '" width="1" height="1"/>';
            }
        }
    }

    return '<svg viewBox="0 0 ' . $n . ' ' . $n . '" fill="#111116" shape-rendering="crispEdges" focusable="false">' . $rects . '</svg>';
}

$ecran = static fn (int $n): string => htmlspecialchars(asset('/assets/img/site/telephone-' . $n . '.jpg'), ENT_QUOTES);
$url   = static fn (string $chemin): string => htmlspecialchars(baseUrl($chemin), ENT_QUOTES);

/** Une pastille flottante de l'accroche : icône sur carré coloré. */
$bulle = static fn (string $ico, string $couleur, string $position, string $delai = '0s'): string =>
    '<span class="lp-bulle" style="' . $position . ';animation-delay:' . $delai . '" aria-hidden="true"><i style="background:' . $couleur . '">' . iconeSite($ico) . '</i></span>';

siteDebut('Outsee — Le réseau social des sorties étudiantes', [
    'classe'      => 'vitrine',
    'styles'      => ['/assets/css/vitrine.css'],
    'description' => "Vois à quelles soirées vont tes potes, invite-les et sortez ensemble : bars, boîtes, sport et culture de ta ville, avec des réductions étudiantes. Établissements : remplissez votre salle avec Outsee.",
]);
?>
<a class="sr-only" href="#contenu">Aller au contenu</a>

<div class="lp-cadre">
  <header class="lp-nav">
    <nav class="lp-nav__barre" aria-label="Navigation principale">
      <?= marqueSite('', baseUrl('/')) ?>
      <div class="lp-nav__liens">
        <a href="#potes">Tes potes</a>
        <a href="#appli">L'application</a>
        <a href="#comment">Comment ça marche</a>
        <a href="#etablissements">Établissements</a>
        <a href="#tarifs">Tarifs</a>
      </div>
      <div class="lp-nav__actions">
        <a class="pilule pilule--grise pilule--petite" href="<?= $url('/auth/login.php') ?>">Espace pro</a>
        <a class="pilule pilule--noire pilule--petite" href="#telecharger">Télécharger</a>
      </div>
    </nav>
  </header>

  <main id="contenu">
    <!-- Accroche -->
    <section class="lp-hero">
      <div class="lp-anneaux" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
      <?= $bulle('verre', '#FF8A3D', 'left:13%;top:16%') ?>
      <?= $bulle('musique', '#8B5CF6', 'left:6%;top:44%', '-2s') ?>
      <?= $bulle('ballon', '#3B82F6', 'left:12%;top:72%', '-4s') ?>
      <?= $bulle('palette', '#F0508A', 'left:24%;top:58%', '-1s') ?>
      <?= $bulle('qr', '#111116', 'right:13%;top:16%', '-3s') ?>
      <?= $bulle('lieu', '#1D55F0', 'right:6%;top:44%', '-5s') ?>
      <?= $bulle('etoile', '#FFB020', 'right:12%;top:72%', '-2.5s') ?>
      <?= $bulle('billet', '#7B4DF2', 'right:24%;top:58%', '-6s') ?>

      <div class="lp-interieur">
        <span class="lp-note"><b>Gratuit</b>Le réseau social des sorties étudiantes</span>
        <h1 class="lp-titre">Vois où sortent tes potes <span>et rejoins-les ce soir.</span></h1>
        <p class="lp-sous">Suis tes amis, découvre à quelles soirées ils vont et invite-les en un geste. Bars, boîtes, sport et culture de ta ville, avec des réductions réservées aux étudiants.</p>
        <div class="lp-actions">
          <a class="pilule pilule--noire" href="#telecharger">Télécharger l'application</a>
          <a class="pilule pilule--claire" href="<?= $url('/auth/register.php') ?>">Je suis un établissement</a>
        </div>

        <div class="lp-notifs" aria-label="Exemples de notifications de l'application">
          <div class="lp-notif">
            <span class="lp-notif__avatar" style="background:#1D55F0">H</span>
            <div class="lp-notif__corps"><strong>Hugo</strong> t'invite à <strong>Warehouse : nuit techno</strong><small>Il y a 2 min · Le Bunker · Ce soir 23h</small></div>
            <span class="lp-notif__bouton">Accepter</span>
          </div>
          <div class="lp-notif">
            <span class="lp-pile" aria-hidden="true"><i style="background:#F0508A">L</i><i style="background:#FF8A3D">C</i><i style="background:#8B5CF6">M</i></span>
            <div class="lp-notif__corps"><strong>Léa et 3 potes</strong> vont à <strong>Mojito Night</strong><small>Le Comptoir de Jaude · 21h</small></div>
          </div>
          <div class="lp-notif">
            <span class="lp-notif__avatar" style="background:#E02D24"><?= iconeSite('ballon', 'icon-sm') ?></span>
            <div class="lp-notif__corps"><strong>Camille</strong> cherche un 4e pour le padel<small>Club des Volcans · 19h30</small></div>
          </div>
        </div>
      </div>
    </section>

    <div class="lp-bande">
      <p>Tout ce qui se passe en ville, dans une seule application</p>
      <ul>
        <li><?= iconeSite('verre') ?> Bars</li>
        <li><?= iconeSite('musique') ?> Boîtes</li>
        <li><?= iconeSite('cible') ?> Restos</li>
        <li><?= iconeSite('horloge') ?> Afterworks</li>
        <li><?= iconeSite('ballon') ?> Padel et five</li>
        <li><?= iconeSite('palette') ?> Expos</li>
        <li><?= iconeSite('billet') ?> Concerts</li>
      </ul>
    </div>
  </main>
</div>

<div class="lp-cadre">
  <!-- Le réseau social -->
  <section class="lp-section" id="potes">
    <div class="lp-interieur lp-social">
      <div>
        <span class="lp-surtitre">Le réseau de tes sorties</span>
        <h2 class="lp-h2" style="text-align:left;">Sortir, c'est mieux <span>avec tes potes.</span></h2>
        <p class="lp-sous" style="margin-left:0;text-align:left;">Outsee n'est pas qu'un agenda : c'est l'endroit où tu vois ce que font tes amis ce soir, et où vous vous organisez.</p>
        <ul class="lp-social__liste">
          <li><span><?= iconeSite('personnes') ?></span><div><strong>Suis tes potes</strong>Ajoute tes amis, ou les étudiants de ton école qui aiment les mêmes choses. Ton compte peut rester privé.</div></li>
          <li><span><?= iconeSite('oeil') ?></span><div><strong>Vois où ils vont</strong>Chaque soirée affiche les potes qui y sont inscrits, et le filtre « Pour moi » rassemble celles où ils vont.</div></li>
          <li><span><?= iconeSite('megaphone') ?></span><div><strong>Invite-les en un geste</strong>Envoie une invitation à une soirée, une partie de sport ou une sortie culture : ils acceptent ou refusent depuis leurs notifications.</div></li>
        </ul>
      </div>

      <div class="lp-social__scene" aria-hidden="true">
        <div class="lp-verre lp-social__evenement">
          <div class="lp-social__ligne">
            <span class="lp-surtitre" style="font-size:11px;">Bar · Ce soir 21h</span>
            <span class="lp-social__badge">-30 %</span>
          </div>
          <strong class="lp-social__titre">Mojito Night</strong>
          <span class="lp-social__lieu">Le Comptoir de Jaude</span>
          <div class="lp-social__potes">
            <span class="lp-pile"><i style="background:#F0508A">L</i><i style="background:#1D55F0">H</i><i style="background:#FF8A3D">C</i><i style="background:#8B5CF6">+3</i></span>
            <span><b>Léa, Hugo</b> et 4 potes y vont</span>
          </div>
          <div class="lp-social__ligne" style="margin-top:16px;">
            <span class="pilule pilule--claire pilule--petite"><?= iconeSite('megaphone', 'icon-sm') ?> Inviter</span>
            <span class="pilule pilule--bleue pilule--petite">Rejoindre</span>
          </div>
        </div>

        <div class="lp-verre lp-social__invitation">
          <small>Inviter des potes</small>
          <?php foreach ([['Camille', 'UCA · L3', '#E02D24', true], ['Mehdi', 'INP · M1', '#3B82F6', true], ['Inès', 'SIGMA · L2', '#7B4DF2', false]] as [$p, $e, $c, $invite]): ?>
            <div class="lp-social__ami">
              <i style="background:<?= $c ?>"><?= mb_substr($p, 0, 1) ?></i>
              <div><b><?= $p ?></b><span><?= $e ?></span></div>
              <em class="<?= $invite ? 'fait' : '' ?>"><?= $invite ? iconeSite('check', 'icon-sm') . ' Invité' : 'Inviter' ?></em>
            </div>
          <?php endforeach; ?>
        </div>

        <div class="lp-verre lp-social__suivi">
          <i style="background:#FF8A3D">M</i><span><b>Manon</b> te suit</span>
        </div>
      </div>
    </div>
  </section>

  <!-- L'application -->
  <section class="lp-section" id="appli">
    <div class="lp-interieur">
      <div class="lp-entete">
        <span class="lp-surtitre">L'application</span>
        <h2 class="lp-h2">Tout ce qui se passe ce soir, <span>dans ta poche.</span></h2>
        <p>Rejoins une soirée en un geste, présente ton QR code à l'entrée, retrouve tes potes là où ça bouge.</p>
      </div>

      <div class="lp-bento">
        <article class="lp-tuile lp-tuile--large">
          <h3>Les bons plans du moment, filtrés pour toi.</h3>
          <p>Ce qui se passe maintenant et bientôt, selon tes goûts, ton école et les lieux que tu suis.</p>
          <img class="lp-tuile__ecran" src="<?= $ecran(1) ?>" width="469" height="872" loading="lazy" alt="L'accueil de l'application : les bons plans du moment.">
          <span class="lp-etiquette" style="left:28px;bottom:96px;"><i style="background:var(--encre);"><?= iconeSite('check') ?></i>En cours · 14 places restantes</span>
          <span class="lp-etiquette" style="left:56px;bottom:40px;"><i style="background:var(--bleu);"><?= iconeSite('eclair') ?></i>-30 % ce soir</span>
        </article>

        <article class="lp-tuile lp-tuile--petite lp-tuile--bleue">
          <h3>Des réductions réservées aux étudiants</h3>
          <p>Négociées avec les bars et les boîtes de ta ville.</p>
          <div class="lp-tickets" aria-hidden="true">
            <div class="lp-ticket"><div><strong>Happy hour</strong><span>Bar · ce soir</span></div><b>-50 %</b></div>
            <div class="lp-ticket"><div><strong>Soirée techno</strong><span>Boîte · samedi</span></div><b>-30 %</b></div>
            <div class="lp-ticket"><div><strong>Afterwork</strong><span>Resto · jeudi</span></div><b>Gratuit</b></div>
          </div>
          <p class="lp-tuile__pied">Réductions exemples : elles varient selon les soirées.</p>
        </article>

        <article class="lp-tuile lp-tuile--moyenne lp-tuile--sport">
          <h3>Ne fais plus de sport seul.</h3>
          <p>Padel, five, tennis : complète une partie ou réserve un terrain, le prix se partage.</p>
          <img class="lp-tuile__ecran" src="<?= $ecran(5) ?>" width="469" height="872" loading="lazy" alt="La rubrique Sport : les parties à compléter.">
        </article>

        <article class="lp-tuile lp-tuile--moyenne lp-tuile--culture">
          <h3>La culture en ville.</h3>
          <p>Expos, concerts, patrimoine : ce que ta ville met en avant, souvent gratuit.</p>
          <img class="lp-tuile__ecran" src="<?= $ecran(7) ?>" width="469" height="872" loading="lazy" alt="La rubrique Culture.">
        </article>

        <article class="lp-tuile lp-tuile--petite">
          <h3>Vois où ça bouge.</h3>
          <p>La carte des soirées en direct.</p>
          <img class="lp-tuile__ecran" src="<?= $ecran(4) ?>" width="469" height="872" loading="lazy" alt="La carte des soirées.">
        </article>

        <article class="lp-tuile lp-tuile--petite">
          <h3>Trouve tes futurs potes.</h3>
          <p>Les étudiants de ton école qui aiment les mêmes choses.</p>
          <img class="lp-tuile__ecran" src="<?= $ecran(6) ?>" width="469" height="872" loading="lazy" alt="L'annuaire des étudiants.">
        </article>

        <article class="lp-tuile lp-tuile--petite">
          <h3>Ton pass, en poche.</h3>
          <p>Un QR code par soirée, scanné à l'entrée : la réduction s'applique sur place.</p>
          <div class="lp-pass" aria-hidden="true">
            <div class="lp-pass__tete"><span>Pass étudiant</span><b>Actif</b></div>
            <div class="lp-pass__qr"><?= qrIllustration() ?></div>
            <div class="lp-pass__pied"><strong>Warehouse : nuit techno</strong><span>Ce soir · 23h · -30 %</span></div>
          </div>
        </article>
      </div>
    </div>
  </section>

  <!-- Comment ça marche -->
  <section class="lp-section" id="comment">
    <div class="lp-interieur">
      <div class="lp-entete">
        <span class="lp-surtitre">Comment ça marche</span>
        <h2 class="lp-h2">Trois gestes, <span>et tu sors.</span></h2>
      </div>
      <div class="lp-etapes">
        <div class="lp-etape"><b>1</b><h3>Suis tes potes</h3><p>Ajoute tes amis et découvre les soirées, les parties et les sorties où ils vont.</p></div>
        <div class="lp-etape"><b>2</b><h3>Rejoins ou invite</h3><p>Un appui pour réserver ta place, un autre pour inviter ceux qui ne sont pas encore inscrits.</p></div>
        <div class="lp-etape"><b>3</b><h3>Présente ton pass</h3><p>À l'entrée, ton QR code est scanné et ta réduction s'applique.</p></div>
      </div>
    </div>
  </section>
</div>

<div class="lp-cadre">
  <!-- Établissements -->
  <section class="lp-section" id="etablissements">
    <div class="lp-interieur">
      <div class="lp-scene">
        <div>
          <span class="lp-surtitre" style="color:var(--encre);">Pour les établissements</span>
          <h2 class="lp-scene__titre" style="margin-top:18px;">Remplissez votre salle<sup>+</sup></h2>
          <hr>
          <p class="lp-scene__texte">Publiez vos soirées, suivez les inscriptions en direct et scannez les pass à l'entrée. Vous savez qui vient, d'où, et à quelle heure.</p>
          <hr>
          <ul class="lp-scene__points">
            <li><?= iconeSite('check') ?> Une soirée publiée en deux minutes</li>
            <li><?= iconeSite('check') ?> Scan des pass depuis le téléphone ou le navigateur</li>
            <li><?= iconeSite('check') ?> Inscriptions, présence et écoles, mesurées</li>
          </ul>
          <div class="lp-scene__actions">
            <a class="pilule pilule--noire" href="<?= $url('/auth/register.php') ?>">Créer mon espace pro</a>
            <a class="lien" href="#tarifs">Voir les tarifs</a>
          </div>
        </div>
        <div class="lp-scene__visuel" aria-hidden="true">
          <div class="lp-scene__panneau"><img src="<?= $ecran(8) ?>" width="469" height="906" loading="lazy" alt=""></div>
          <div class="lp-verre" style="right:0;top:36px;width:190px;"><small>Ce soir</small><strong>12</strong><span>étudiants en route</span></div>
          <div class="lp-verre" style="left:0;top:210px;display:flex;align-items:center;gap:10px;padding:12px 16px;">
            <span style="width:26px;height:26px;border-radius:8px;background:var(--bleu);color:#fff;display:grid;place-items:center;"><?= iconeSite('check', 'icon-sm') ?></span>
            <span style="margin:0;font-size:15px;color:var(--encre);font-weight:600;">Entrée validée pour Camille T.</span>
          </div>
          <div class="lp-verre" style="right:-8px;bottom:40px;width:210px;"><small>Profil de salle</small><span style="font-size:15px;color:var(--encre);font-weight:600;margin-top:8px;">UCA · SIGMA · INP</span></div>
        </div>
      </div>
    </div>
  </section>

  <!-- Tarifs -->
  <section class="lp-section" id="tarifs">
    <div class="lp-interieur">
      <div class="lp-entete">
        <span class="lp-surtitre">Tarifs établissements</span>
        <h2 class="lp-h2">Une formule par mois, <span>sans engagement de durée.</span></h2>
        <p>Prix hors taxes. Les étudiants, eux, ne paient jamais l'application.</p>
      </div>
      <div class="lp-tarifs">
        <?php foreach ($formules as $code => $f):
            $offre = $offres[$code];
        ?>
        <article class="lp-tarif<?= $f['vedette'] ? ' lp-tarif--vedette' : '' ?>">
          <?php if ($f['vedette']): ?><span class="lp-tarif__badge">Le plus complet</span><?php endif; ?>
          <h3><?= htmlspecialchars($offre['libelle']) ?></h3>
          <p class="lp-tarif__pour"><?= htmlspecialchars($f['pour']) ?></p>
          <p class="lp-tarif__prix"><?= htmlspecialchars(eurosSite((float) $offre['tarif'])) ?><small>HT / mois</small></p>
          <ul>
            <?php foreach (avantagesFormule($code, $grille[$code]) as $ligne): ?>
              <li><?= iconeSite('check') ?><span><?= htmlspecialchars($ligne) ?></span></li>
            <?php endforeach; ?>
          </ul>
          <a class="pilule <?= $f['vedette'] ? 'pilule--bleue' : 'pilule--claire' ?>" href="<?= $url('/auth/register.php?formule=' . $code) ?>">Choisir <?= htmlspecialchars($offre['libelle']) ?></a>
        </article>
        <?php endforeach; ?>
      </div>
      <p class="lp-note-tarifs">
        Événement au-delà du quota : <?= htmlspecialchars(eurosSite(PRIX_EVENEMENT_SUPPLEMENTAIRE)) ?> HT ·
        Mises en avant à partir de <?= htmlspecialchars(eurosSite(tarifSponsoring('boost24'))) ?> HT ·
        Offre Fondateur : <?= htmlspecialchars(eurosSite((float) $offres['fondateur']['tarif'])) ?> HT par mois après trois mois offerts, dans la limite des places.
      </p>
    </div>
  </section>

  <!-- Dernier appel -->
  <section class="lp-section" id="telecharger">
    <div class="lp-interieur">
      <div class="lp-final lp-sur-bleu">
        <h2 class="lp-h2">Ce soir, <span>tu sors où ?</span></h2>
        <p>Télécharge Outsee et connecte-toi avec ton compte étudiant.</p>
        <div class="lp-actions"><?= boutonsStores() ?></div>
      </div>
    </div>
  </section>

  <?= piedSite() ?>
</div>
<?php siteFin(); ?>
