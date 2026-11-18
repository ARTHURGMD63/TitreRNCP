<?php
/**
 * Publier un événement. Règles et formulaire : includes/evenement_pro.php.
 */
require_once __DIR__ . '/../includes/site.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/evenement_pro.php';

$etab      = ouvrirEspacePro($pdo);
$capacites = capacitesEtablissement($pdo, (int) $etab['id']);
$estClub   = ($etab['type'] ?? '') === 'club';

$erreurs = [];
$valeurs = ['type' => 'bar', 'quota' => 50];
// Paiement abandonné : le formulaire revient tel qu'il avait été rempli.
$valeurs = traiterRetourPaiementEvenement($pdo, $etab) ?? $valeurs;
if (!$estClub && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    ['erreurs' => $erreurs, 'valeurs' => $valeurs] = traiterFormulaireEvenement($pdo, $etab, $capacites, null);
}

proDebut('Créer un événement', 'creer', $etab);
?>
<?= messageFlashPro() ?>
<header class="pro-entete">
  <div>
    <a class="retour" href="<?= htmlspecialchars(baseUrl('/partenaire/evenements.php'), ENT_QUOTES) ?>"><?= iconeSite('fleche-g', 'icon-sm') ?> Mes événements</a>
    <h1 class="titre-pro">Nouvel <em>événement.</em></h1>
    <p class="pro-entete__sous">Il apparaît aussitôt dans le fil des étudiants de <?= htmlspecialchars((string) ($etab['ville'] ?? 'votre ville')) ?>.</p>
  </div>
</header>

<?php if ($erreurs): ?>
  <div class="message message--erreur" role="alert" style="margin-bottom:20px;"><?= iconeSite('alerte') ?>
    <div><?php foreach ($erreurs as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
  </div>
<?php endif; ?>

<?php if ($estClub): ?>
  <section class="carte vide">
    <div class="vide__icone"><?= iconeSite('telephone', 'icon-lg') ?></div>
    <h3>Les créneaux de sport se publient dans l'application</h3>
    <p>Terrains, niveaux et parties à compléter : la publication d'un club se fait depuis l'application Outsee, onglet Terrains.</p>
  </section>
<?php else: ?>
  <?= formulaireEvenementHtml($pdo, $etab, $capacites, $valeurs, null) ?>
<?php endif; ?>
<?php proFin(); ?>
