<?php
/**
 * Les événements de l'établissement : à venir (et en cours), puis passés.
 *
 * Par ligne : remplissage, entrées scannées, réduction, statut, et les
 * actions — scanner les pass, modifier, supprimer. Supprimer ne rend pas ce
 * qu'un événement a consommé : le registre des achats (achats_partenaires)
 * garde la trace, voir includes/capacites.php.
 */
require_once __DIR__ . '/../includes/site.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/sponsoring.php';
require_once __DIR__ . '/../includes/evenements_temps.php';
require_once __DIR__ . '/../includes/evenement_pro.php';
require_once __DIR__ . '/../includes/evenements_suppression.php';

$etab = ouvrirEspacePro($pdo);

// Retour de la page de paiement d'un supplément ou d'une mise en avant.
traiterRetourPaiementEvenement($pdo, $etab);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['supprimer'])) {
    csrfVerify();
    // Refusée s'il a des inscrits ou un paiement (PAY-10).
    $resultat = evenementSupprimer($pdo, (int) $_POST['supprimer'], (int) $etab['id']);
    if ($resultat['ok']) {
        fluxToucher($pdo, CANAL_GLOBAL);
    }
    flashPro($resultat['message'], $resultat['ok'] ? 'succes' : 'alerte');
    header('Location: ' . baseUrl('/partenaire/evenements.php'));
    exit;
}

// Messages des anciennes redirections (?created=1 depuis la création…).
foreach (['created' => 'Événement publié.', 'updated' => 'Événement mis à jour.', 'deleted' => 'Événement supprimé.'] as $cle => $texte) {
    if (isset($_GET[$cle])) {
        flashPro($texte);
    }
}

$vue = ($_GET['vue'] ?? '') === 'passes' ? 'passes' : 'avenir';
$fin = sqlFinEffectiveEvenement('e.date_heure', 'e.date_fin');

$stmt = $pdo->prepare(
    "SELECT e.*,
            (SELECT COUNT(*) FROM inscriptions i WHERE i.evenement_id = e.id AND i.statut != 'annule') AS nb_inscrits,
            (SELECT COUNT(*) FROM inscriptions i WHERE i.evenement_id = e.id AND i.statut = 'checkin') AS nb_checkin,
            ($fin >= ?) AS a_venir
       FROM evenements e
      WHERE e.etablissement_id = ?
      ORDER BY e.date_heure " . ($vue === 'passes' ? 'DESC' : 'ASC')
);
$stmt->execute([date('Y-m-d H:i:s'), (int) $etab['id']]);
$tous = $stmt->fetchAll();

$aVenir = array_values(array_filter($tous, static fn ($e) => (int) $e['a_venir'] === 1));
$passes = array_values(array_filter($tous, static fn ($e) => (int) $e['a_venir'] === 0));
$liste  = $vue === 'passes' ? $passes : $aVenir;

proDebut('Mes événements', 'evenements', $etab, ['scripts' => SCRIPTS_SCAN_PRO]);
?>
<?= messageFlashPro() ?>

<header class="pro-entete">
  <div>
    <p class="surtitre">Mes événements</p>
    <h1 class="titre-pro"><?= count($aVenir) ?> à venir, <em><?= count($passes) ?> passé<?= count($passes) > 1 ? 's' : '' ?>.</em></h1>
  </div>
  <div class="pro-entete__actions">
    <a class="bouton bouton--plein" href="<?= htmlspecialchars(baseUrl('/partenaire/create_event.php'), ENT_QUOTES) ?>"><?= iconeSite('plus') ?> Créer un événement</a>
  </div>
</header>

<nav class="onglets" aria-label="Filtrer les événements" style="margin-bottom:20px;">
  <a href="<?= htmlspecialchars(baseUrl('/partenaire/evenements.php'), ENT_QUOTES) ?>"<?= $vue === 'avenir' ? ' class="actif" aria-current="page"' : '' ?>>À venir (<?= count($aVenir) ?>)</a>
  <a href="<?= htmlspecialchars(baseUrl('/partenaire/evenements.php?vue=passes'), ENT_QUOTES) ?>"<?= $vue === 'passes' ? ' class="actif" aria-current="page"' : '' ?>>Passés (<?= count($passes) ?>)</a>
</nav>

<section class="carte">
  <?php if (!$liste): ?>
    <div class="vide">
      <div class="vide__icone"><?= iconeSite('calendrier', 'icon-lg') ?></div>
      <h3><?= $vue === 'passes' ? 'Aucun événement passé' : 'Aucun événement à venir' ?></h3>
      <p><?= $vue === 'passes' ? 'Vos soirées terminées apparaîtront ici avec leur bilan.' : 'Publiez votre prochaine soirée : les étudiants la verront dans leur fil.' ?></p>
      <?php if ($vue === 'avenir'): ?>
        <a class="bouton bouton--plein" href="<?= htmlspecialchars(baseUrl('/partenaire/create_event.php'), ENT_QUOTES) ?>"><?= iconeSite('plus') ?> Créer un événement</a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="tableau-conteneur">
      <table class="tableau">
        <thead>
          <tr>
            <th scope="col">Événement</th>
            <th scope="col">Date</th>
            <th scope="col">Inscrits</th>
            <th scope="col" class="num masquer-mobile">Entrées</th>
            <th scope="col" class="masquer-mobile">Avantage</th>
            <th scope="col">Statut</th>
            <th scope="col"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($liste as $e):
              $quota   = (int) $e['quota'];
              $inscr   = (int) $e['nb_inscrits'];
              $complet = $quota > 0 && $inscr >= $quota;
              $enCours = (int) $e['a_venir'] === 1 && strtotime((string) $e['date_heure']) <= time();
              $pct     = $quota > 0 ? min(100, (int) round($inscr / $quota * 100)) : 0;
          ?>
          <tr>
            <td>
              <div class="tableau__titre"><a href="<?= htmlspecialchars(baseUrl('/partenaire/edit_event.php?id=' . (int) $e['id']), ENT_QUOTES) ?>"><?= htmlspecialchars((string) $e['titre']) ?></a></div>
              <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:6px;">
                <?php if (!empty($e['is_flash'])): ?><span class="pastille pastille--alerte"><?= iconeSite('eclair', 'icon-sm') ?> Flash</span><?php endif; ?>
                <?php if (!empty($e['is_sponsorise'])): ?><span class="pastille<?= sponsoringActif($e) ? '' : ' pastille--neutre' ?>"><?= iconeSite('etoile', 'icon-sm') ?> <?= sponsoringActif($e) ? htmlspecialchars(libelleSponsoring($e['sponsor_formule'])) : 'Mise en avant terminée' ?></span><?php endif; ?>
              </div>
              <?= sponsoringChronoHtml($e, true) ?>
            </td>
            <td style="white-space:nowrap;"><?= htmlspecialchars(dateFr($e['date_heure'], 'D j M')) ?><br><span class="carte__sous"><?= date('H\hi', strtotime((string) $e['date_heure'])) ?></span></td>
            <td style="min-width:110px;">
              <strong><?= $inscr ?></strong><span class="carte__sous"><?= $quota > 0 ? ' / ' . $quota : '' ?></span>
              <?php if ($quota > 0): ?><div class="jauge<?= $complet ? ' jauge--danger' : '' ?>" style="margin-top:6px;"><span style="width:<?= $pct ?>%"></span></div><?php endif; ?>
            </td>
            <td class="num masquer-mobile"><?= (int) $e['nb_checkin'] ?></td>
            <td class="masquer-mobile"><?= !empty($e['is_gratuit']) ? 'Entrée gratuite' : ((int) $e['reduction'] > 0 ? '-' . (int) $e['reduction'] . ' %' : '—') ?></td>
            <td>
              <?php if ((int) $e['a_venir'] === 0): ?><span class="pastille pastille--neutre">Terminé</span>
              <?php elseif ($enCours): ?><span class="pastille pastille--succes pastille--direct">En cours</span>
              <?php elseif ($complet): ?><span class="pastille pastille--danger">Complet</span>
              <?php else: ?><span class="pastille">À venir</span><?php endif; ?>
            </td>
            <td>
              <div class="actions-ligne">
                <?php if ((int) $e['a_venir'] === 1): ?>
                  <button type="button" class="bouton-icone" data-scanner="<?= (int) $e['id'] ?>" data-titre="<?= htmlspecialchars((string) $e['titre'], ENT_QUOTES) ?>"><?= iconeSite('qr', '', 'Scanner les pass') ?></button>
                <?php endif; ?>
                <a class="bouton-icone" href="<?= htmlspecialchars(baseUrl('/partenaire/edit_event.php?id=' . (int) $e['id']), ENT_QUOTES) ?>"><?= iconeSite('crayon', '', 'Modifier') ?></a>
                <form method="post" data-confirmer="Supprimer « <?= htmlspecialchars((string) $e['titre'], ENT_QUOTES) ?> » ? Les inscrits ne la verront plus. Ce qui a été consommé ce mois-ci reste compté.">
                  <?= csrfField() ?>
                  <input type="hidden" name="supprimer" value="<?= (int) $e['id'] ?>">
                  <button type="submit" class="bouton-icone bouton-icone--danger"><?= iconeSite('poubelle', '', 'Supprimer') ?></button>
                </form>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>

<?= modaleScanPro() ?>
<?php proFin(); ?>
