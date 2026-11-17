<?php
/**
 * Modifier un événement de l'établissement. Règles et formulaire :
 * includes/evenement_pro.php. Un événement d'un autre établissement n'est
 * jamais chargé (la requête filtre sur etablissement_id).
 */
require_once __DIR__ . '/../includes/site.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/evenement_pro.php';

$etab      = ouvrirEspacePro($pdo);
$capacites = capacitesEtablissement($pdo, (int) $etab['id']);

$stmt = $pdo->prepare('SELECT * FROM evenements WHERE id = ? AND etablissement_id = ?');
$stmt->execute([(int) ($_GET['id'] ?? 0), (int) $etab['id']]);
$event = $stmt->fetch();
if (!$event || ($event['type'] ?? '') === 'sport') {
    flashPro('Cet événement n\'existe plus ou se modifie depuis l\'application.', 'alerte');
    header('Location: ' . baseUrl('/partenaire/evenements.php'));
    exit;
}

$erreurs = [];
$valeurs = traiterRetourPaiementEvenement($pdo, $etab) ?? $event;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    ['erreurs' => $erreurs, 'valeurs' => $valeurs] = traiterFormulaireEvenement($pdo, $etab, $capacites, $event);
}

$stmt = $pdo->prepare("SELECT COUNT(*) FROM inscriptions WHERE evenement_id = ? AND statut != 'annule'");
$stmt->execute([(int) $event['id']]);
$inscrits = (int) $stmt->fetchColumn();

proDebut('Modifier un événement', 'evenements', $etab);
?>
<?= messageFlashPro() ?>
<header class="pro-entete">
  <div>
    <a class="retour" href="<?= htmlspecialchars(baseUrl('/partenaire/evenements.php'), ENT_QUOTES) ?>"><?= iconeSite('fleche-g', 'icon-sm') ?> Mes événements</a>
    <h1 class="titre-pro"><?= htmlspecialchars((string) $event['titre']) ?></h1>
    <p class="pro-entete__sous"><?= htmlspecialchars(dateFr($event['date_heure'], 'D j M')) ?> à <?= date('H\hi', strtotime((string) $event['date_heure'])) ?> · <?= $inscrits ?> inscrit<?= $inscrits > 1 ? 's' : '' ?></p>
  </div>
</header>

<?php if (!empty($event['is_sponsorise'])): ?>
  <?= sponsoringChronoHtml($event) ?>
<?php endif; ?>
<?php if ($inscrits > 0): ?>
  <div class="message message--alerte" style="margin-bottom:20px;"><?= iconeSite('info') ?><span><?= $inscrits ?> étudiant<?= $inscrits > 1 ? 's sont inscrits' : ' est inscrit' ?> : prévenez-les si vous changez la date ou l'avantage.</span></div>
<?php endif; ?>
<?php if ($erreurs): ?>
  <div class="message message--erreur" role="alert" style="margin-bottom:20px;"><?= iconeSite('alerte') ?>
    <div><?php foreach ($erreurs as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
  </div>
<?php endif; ?>

<?= formulaireEvenementHtml($pdo, $etab, $capacites, $valeurs, $event) ?>
<?php proFin(); ?>
