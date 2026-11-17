<?php
/**
 * Tableau de bord de l'établissement.
 *
 * Mêmes données que api/v1/partenaire_dashboard.php (l'écran d'accueil
 * partenaire de l'application) : le prochain événement ou celui en cours,
 * inscrits et entrées, inscriptions des six dernières heures, écoles, et le
 * mois de l'établissement (quotas, achats).
 *
 * L'ancienne version affichait des valeurs inventées — tendance tirée au
 * hasard, conseil écrit en dur, répartition d'écoles de secours, âge moyen
 * deviné d'après la promo. Elles ont disparu : ce qui n'est pas mesuré ne
 * s'affiche pas.
 */
require_once __DIR__ . '/../includes/site.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/crm.php';
require_once __DIR__ . '/../includes/capacites.php';
require_once __DIR__ . '/../includes/evenements_temps.php';

$etab = ouvrirEspacePro($pdo);
$eid0 = (int) $etab['id'];

$capacites = capacitesEtablissement($pdo, $eid0);
$usage     = usagePartenaireMois($pdo, $eid0, $capacites);
$client    = abonnementEtablissement($pdo, $eid0);

// Le prochain événement, ou celui en cours tant qu'il n'est pas terminé.
$stmt = $pdo->prepare(
    'SELECT * FROM evenements WHERE etablissement_id = ? AND '
    . sqlFinEffectiveEvenement('date_heure', 'date_fin')
    . ' >= ? ORDER BY date_heure ASC LIMIT 1'
);
$stmt->execute([$eid0, date('Y-m-d H:i:s')]);
$event = $stmt->fetch() ?: null;

$nbInscrits = $nbCheckin = 0;
$ecoles = $histo = $recentes = [];

if ($event) {
    $eid = (int) $event['id'];

    $stmt = $pdo->prepare("SELECT statut, COUNT(*) FROM inscriptions WHERE evenement_id = ? AND statut IN ('inscrit','checkin') GROUP BY statut");
    $stmt->execute([$eid]);
    foreach ($stmt->fetchAll(PDO::FETCH_KEY_PAIR) as $statut => $nb) {
        if ($statut === 'inscrit') {
            $nbInscrits = (int) $nb;
        } else {
            $nbCheckin = (int) $nb;
        }
    }

    $stmt = $pdo->prepare("SELECT u.ecole, COUNT(*) AS nb FROM inscriptions i JOIN users u ON u.id = i.user_id
                            WHERE i.evenement_id = ? AND i.statut != 'annule' AND u.ecole IS NOT NULL AND u.ecole != ''
                            GROUP BY u.ecole ORDER BY nb DESC LIMIT 6");
    $stmt->execute([$eid]);
    $lignes = $stmt->fetchAll();
    $totalEcoles = array_sum(array_column($lignes, 'nb')) ?: 1;
    foreach ($lignes as $l) {
        $ecoles[] = ['nom' => (string) $l['ecole'], 'pct' => (int) round((int) $l['nb'] / $totalEcoles * 100)];
    }

    // Inscriptions par heure, sur les six dernières heures (une requête).
    $stmt = $pdo->prepare("SELECT FLOOR(TIMESTAMPDIFF(MINUTE, created_at, ?) / 60) AS h, COUNT(*)
                             FROM inscriptions WHERE evenement_id = ? AND created_at > ? AND created_at <= ?
                            GROUP BY h");
    $maintenant = date('Y-m-d H:i:s');
    $stmt->execute([$maintenant, $eid, date('Y-m-d H:i:s', time() - 6 * 3600), $maintenant]);
    $parHeure = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    for ($h = 5; $h >= 0; $h--) {
        $histo[] = ['libelle' => date('H\h', time() - $h * 3600), 'valeur' => (int) ($parHeure[$h] ?? 0)];
    }

    $stmt = $pdo->prepare("SELECT u.prenom, u.nom, u.ecole, u.promo, i.created_at, i.statut
                             FROM inscriptions i JOIN users u ON u.id = i.user_id
                            WHERE i.evenement_id = ? AND i.statut != 'annule'
                            ORDER BY i.created_at DESC LIMIT 6");
    $stmt->execute([$eid]);
    $recentes = $stmt->fetchAll();
}

$total     = $nbInscrits + $nbCheckin;
$places    = $event ? (int) $event['quota'] : 0;
$presence  = $total > 0 ? (int) round($nbCheckin / $total * 100) : null;
$remplissage = $places > 0 ? min(100, (int) round($total / $places * 100)) : 0;
$enCours   = $event && strtotime((string) $event['date_heure']) <= time();
$maxHisto  = max(1, ...array_column($histo ?: [['valeur' => 0]], 'valeur'));
$prenom    = (string) ($_SESSION['user_prenom'] ?? '');

$ev = $usage['evenements'];

proDebut('Tableau de bord', 'tableau', $etab, ['scripts' => SCRIPTS_SCAN_PRO]);
?>
<?= messageFlashPro() ?>

<header class="pro-entete">
  <div>
    <p class="surtitre"><?= $prenom !== '' ? 'Bonjour ' . htmlspecialchars($prenom) : 'Tableau de bord' ?></p>
    <?php if ($event): ?>
      <h1 class="titre-pro"><?= $total ?> étudiant<?= $total > 1 ? 's' : '' ?>, <em><?= $enCours ? 'sur place ou en route.' : 'déjà inscrit' . ($total > 1 ? 's.' : '.') ?></em></h1>
      <p class="pro-entete__sous"><?= htmlspecialchars((string) $event['titre']) ?> · <?= htmlspecialchars(dateFr($event['date_heure'], 'D j M')) ?> à <?= date('H\hi', strtotime((string) $event['date_heure'])) ?></p>
    <?php else: ?>
      <h1 class="titre-pro">Aucune soirée <em>à venir.</em></h1>
      <p class="pro-entete__sous">Publiez un événement : il apparaîtra dans le fil des étudiants de <?= htmlspecialchars((string) ($etab['ville'] ?? 'votre ville')) ?>.</p>
    <?php endif; ?>
  </div>
  <div class="pro-entete__actions">
    <?php if ($event): ?>
      <button type="button" class="bouton bouton--encre" data-scanner="<?= (int) $event['id'] ?>" data-titre="<?= htmlspecialchars((string) $event['titre'], ENT_QUOTES) ?>"><?= iconeSite('qr') ?> Scanner un pass</button>
    <?php endif; ?>
    <a class="bouton bouton--plein" href="<?= htmlspecialchars(baseUrl('/partenaire/create_event.php'), ENT_QUOTES) ?>"><?= iconeSite('plus') ?> Créer un événement</a>
  </div>
</header>

<div class="grille-tdb">
  <div class="pile">
    <?php if ($event): ?>
      <div class="tuiles">
        <div class="tuile tuile--accent">
          <div class="tuile__libelle">Inscrits</div>
          <div class="tuile__valeur"><?= $total ?><?php if ($places > 0): ?> <small>/ <?= $places ?></small><?php endif; ?></div>
          <div class="tuile__note"><?= $places > 0 ? $remplissage . ' % des places' : 'Places illimitées' ?></div>
        </div>
        <div class="tuile">
          <div class="tuile__libelle">Entrées scannées</div>
          <div class="tuile__valeur"><?= $nbCheckin ?></div>
          <div class="tuile__note"><?= $presence !== null ? $presence . ' % de présence' : 'Aucun inscrit pour l\'instant' ?></div>
        </div>
        <div class="tuile">
          <div class="tuile__libelle">Places restantes</div>
          <div class="tuile__valeur"><?= $places > 0 ? max(0, $places - $total) : '—' ?></div>
          <div class="tuile__note"><?= $places > 0 && $total >= $places ? 'Complet' : 'Ouvert aux inscriptions' ?></div>
        </div>
      </div>

      <section class="carte" aria-labelledby="t-histo">
        <div class="carte__tete">
          <h2 class="carte__titre" id="t-histo">Inscriptions des six dernières heures</h2>
          <span class="carte__sous"><?= array_sum(array_column($histo, 'valeur')) ?> au total</span>
        </div>
        <div class="histo" role="img" aria-label="<?= htmlspecialchars(implode(', ', array_map(static fn ($h) => $h['libelle'] . ' : ' . $h['valeur'], $histo)), ENT_QUOTES) ?>">
          <?php foreach ($histo as $h): ?>
            <div class="histo__col">
              <span class="histo__val"><?= $h['valeur'] ?: '' ?></span>
              <div class="histo__barre<?= $h['valeur'] === 0 ? ' histo__barre--vide' : '' ?>" style="height:<?= max(4, (int) round($h['valeur'] / $maxHisto * 100)) ?>%"></div>
              <span class="histo__lib"><?= htmlspecialchars($h['libelle']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <div class="grille-2">
        <section class="carte" aria-labelledby="t-ecoles">
          <div class="carte__tete"><h2 class="carte__titre" id="t-ecoles">Qui vient ?</h2></div>
          <?php if ($ecoles): ?>
            <div class="barres">
              <?php foreach ($ecoles as $e): ?>
                <div class="barres__ligne">
                  <span><?= htmlspecialchars($e['nom']) ?></span><b><?= $e['pct'] ?> %</b>
                  <div class="jauge"><span style="width:<?= $e['pct'] ?>%"></span></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="carte__sous">Les écoles des inscrits apparaîtront ici.</p>
          <?php endif; ?>
        </section>

        <section class="carte" aria-labelledby="t-recentes">
          <div class="carte__tete"><h2 class="carte__titre" id="t-recentes">Dernières inscriptions</h2></div>
          <?php if ($recentes): ?>
            <div class="liste">
              <?php foreach ($recentes as $r): ?>
                <div class="liste__ligne">
                  <span class="avatar"><?= htmlspecialchars(initialesSite((string) $r['prenom'], (string) $r['nom'])) ?></span>
                  <div class="liste__corps">
                    <div class="liste__titre"><?= htmlspecialchars($r['prenom'] . ' ' . mb_substr((string) $r['nom'], 0, 1) . '.') ?></div>
                    <div class="liste__sous"><?= htmlspecialchars(trim(($r['ecole'] ?? '') . ' ' . ($r['promo'] ?? '')) ?: 'Étudiant') ?></div>
                  </div>
                  <?php if ($r['statut'] === 'checkin'): ?>
                    <span class="pastille pastille--succes">Entré</span>
                  <?php else: ?>
                    <span class="liste__fin"><?= date('H\hi', strtotime((string) $r['created_at'])) ?></span>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p class="carte__sous">Aucune inscription pour l'instant.</p>
          <?php endif; ?>
        </section>
      </div>
    <?php else: ?>
      <section class="carte vide">
        <div class="vide__icone"><?= iconeSite('calendrier', 'icon-lg') ?></div>
        <h3>Votre prochaine soirée commence ici</h3>
        <p>Un titre, une date, une réduction pour les étudiants : deux minutes suffisent.</p>
        <a class="bouton bouton--plein" href="<?= htmlspecialchars(baseUrl('/partenaire/create_event.php'), ENT_QUOTES) ?>"><?= iconeSite('plus') ?> Créer un événement</a>
      </section>
    <?php endif; ?>
  </div>

  <aside class="pile">
    <section class="carte carte--accent" aria-labelledby="t-mois">
      <p class="surtitre">Mon mois · formule <?= htmlspecialchars(crmLibelleOffre((string) $capacites['offre'])) ?></p>
      <h2 class="carte__titre" id="t-mois" style="margin-top:10px;font-size:22px;"><?= (int) $ev['publies'] ?> événement<?= $ev['publies'] > 1 ? 's' : '' ?> publié<?= $ev['publies'] > 1 ? 's' : '' ?> sur <?= (int) $ev['quota'] ?></h2>
      <div class="jauge" style="margin-top:14px;"><span style="width:<?= $ev['quota'] > 0 ? min(100, (int) round($ev['publies'] / $ev['quota'] * 100)) : 0 ?>%"></span></div>
      <p class="carte__sous" style="margin-top:10px;">
        <?php if ($ev['restants'] > 0): ?>
          Encore <?= (int) $ev['restants'] ?> compris dans votre formule ce mois-ci.
        <?php else: ?>
          Quota atteint : chaque événement en plus coûte <?= htmlspecialchars(eurosSite((float) $ev['prix_supplement'])) ?> HT.
        <?php endif; ?>
      </p>
      <?php if (!empty($capacites['en_periode_offerte']) && !empty($client['essai_jusqu_au'])): ?>
        <p class="carte__sous" style="margin-top:6px;">Période offerte jusqu'au <?= htmlspecialchars(dateFr($client['essai_jusqu_au'], 'j M Y')) ?>.</p>
      <?php endif; ?>
    </section>

    <section class="carte" aria-labelledby="t-achats">
      <div class="carte__tete"><h2 class="carte__titre" id="t-achats">Ce mois-ci</h2></div>
      <div class="liste">
        <div class="liste__ligne">
          <div class="liste__corps"><div class="liste__titre">Offres flash</div><div class="liste__sous">Comprises dans la formule</div></div>
          <span class="liste__fin"><?= $usage['flash']['quota'] === null ? $usage['flash']['utilises'] . ' (illimité)' : $usage['flash']['utilises'] . ' / ' . $usage['flash']['quota'] ?></span>
        </div>
        <div class="liste__ligne">
          <div class="liste__corps"><div class="liste__titre">Mises en avant offertes</div><div class="liste__sous">Restantes ce mois-ci</div></div>
          <span class="liste__fin"><?= (int) $usage['mises_en_avant']['offertes_restantes'] ?> / <?= (int) $usage['mises_en_avant']['offertes_droit'] ?></span>
        </div>
        <div class="liste__ligne">
          <div class="liste__corps"><div class="liste__titre">Achats en plus</div><div class="liste__sous">Événements supplémentaires et mises en avant</div></div>
          <strong><?= htmlspecialchars(eurosSite((float) $usage['achats_total'])) ?></strong>
        </div>
      </div>
      <a class="bouton bouton--clair bouton--bloc" style="margin-top:18px;" href="<?= htmlspecialchars(baseUrl('/partenaire/abonnement.php'), ENT_QUOTES) ?>">Voir ma formule et mes achats</a>
    </section>
  </aside>
</div>

<?= $event ? modaleScanPro() : '' ?>
<?php proFin(); ?>
