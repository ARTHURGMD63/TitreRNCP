<?php
/**
 * Tableau de bord fondateurs.
 *
 * Reprend le relevé hebdomadaire de SL-07, dans le même ordre : les seuils
 * d'alerte d'abord parce qu'ils déclenchent une action et non une lecture,
 * la North Star ensuite, puis la marketplace et le business.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin_layout.php';
requireAdmin();

$kpi        = kpiHebdomadaires($pdo);
$alertes    = crmAlertes($pdo);
$pipeline   = crmPipeline($pdo);
$mrr        = crmMrr($pdo);
$payants    = crmClientsPayants($pdo);
$churn      = crmChurnMensuel($pdo);
$solde      = financeSolde($pdo);
$pointMort  = financePointMort($pdo);
$moisCourant = financeMois($pdo, date('Y-m'));
$mrrEngage    = crmMrrEngage($pdo);
$souscriptions = crmSouscriptionsRecentes($pdo);
$nouvelles7j   = crmNouvellesSouscriptions($pdo);

// Progression vers l'objectif, plafonnée à 100 % pour la jauge : une barre
// qui déborde de son conteneur ne dit rien de plus qu'une barre pleine.
$objectif     = max(1, $kpi['objectif_scannes']);
$pctNorthStar = min(100, $kpi['pass_scannes'] / $objectif * 100);

$relances = $pdo->query("
    SELECT i.id, i.prochaine_action, i.prochaine_action_le, c.id AS client_id, c.nom
      FROM crm_interactions i
      JOIN crm_clients c ON c.id = i.client_id
     WHERE i.fait = 0 AND i.prochaine_action_le IS NOT NULL
     ORDER BY i.prochaine_action_le ASC
     LIMIT 8
")->fetchAll();

adminHeader($pdo, 'accueil', 'Tableau de bord', 'Relevé du ' . dateFr(time(), 'D j M Y'));
?>

<?php if ($alertes): ?>
  <section class="admin-alertes" aria-label="Seuils d'alerte franchis">
    <?php foreach ($alertes as $a):
      $classes = ['danger' => ' est-danger', 'alerte' => ' est-alerte'];
    ?>
      <a href="<?= baseUrl('/admin/' . $a['lien']) ?>" class="admin-alerte<?= $classes[$a['niveau']] ?? '' ?>">
        <span class="admin-alerte-titre"><?= htmlspecialchars($a['titre']) ?></span>
        <span class="admin-alerte-action"><?= htmlspecialchars($a['action']) ?></span>
      </a>
    <?php endforeach; ?>
  </section>
<?php endif; ?>

<section class="admin-northstar" aria-labelledby="ns-label">
  <h2 class="admin-northstar-label" id="ns-label">North Star · pass scannés sur 7 jours</h2>
  <div style="display:flex;align-items:flex-end;gap:16px;flex-wrap:wrap;">
    <span class="admin-northstar-valeur"><?= $kpi['pass_scannes'] ?></span>
    <span style="padding-bottom:8px;font-size:var(--fs-3);opacity:.7;">objectif <?= $objectif ?> / semaine</span>
  </div>
  <div class="admin-northstar-jauge" role="img"
       aria-label="<?= $kpi['pass_scannes'] ?> pass scannés sur un objectif de <?= $objectif ?> par semaine, soit <?= round($pctNorthStar) ?> %">
    <span style="width:<?= round($pctNorthStar) ?>%;"></span>
  </div>
  <p style="font-size:var(--fs-2);opacity:.7;margin-top:8px;">
    C'est ce que la plaquette promet aux partenaires : des présences vérifiées, pas de l'audience.
  </p>
</section>

<h2 class="admin-titre">Marketplace</h2>
<div class="admin-tuiles">
  <?php
    adminTuile('Étudiants inscrits', (string) $kpi['etudiants'], '+' . $kpi['etudiants_7j'] . ' sur 7 jours');
    adminTuile('Réservations', (string) $kpi['reservations_7j'], 'sur 7 jours');
    adminTuile('Soirées publiées', (string) $kpi['events_7j'],
               $kpi['events_7j'] < 10 ? 'sur 7 jours · seuil 10' : 'sur 7 jours',
               $kpi['events_7j'] < 10 ? 'alerte' : 'neutre');
    adminTuile(
        'Taux de présence',
        $kpi['taux_presence'] === null ? '—' : number_format($kpi['taux_presence'], 1, ',', ' ') . ' %',
        $kpi['taux_presence'] === null ? 'aucune soirée passée' : 'cible 70 % · 30 derniers jours',
        $kpi['taux_presence'] !== null && $kpi['taux_presence'] < 60 ? 'danger' : 'neutre'
    );
    adminTuile('Invitations', (string) $kpi['invitations_7j'], 'envoyées sur 7 jours');
    adminTuile('Squads à venir', (string) $kpi['squads_actifs']);
  ?>
</div>

<h2 class="admin-titre">Business</h2>
<div class="admin-tuiles">
  <?php
    // Un établissement qui vient de souscrire ne bouge pas le MRR tant que son
    // essai court : sans le montant engagé à côté, le tableau de bord n'affiche
    // rien alors qu'un contrat vient d'être signé.
    adminTuile('MRR facturé', eur($mrr),
               $mrrEngage > $mrr
                   ? eur($mrrEngage) . ' engagés, essais compris'
                   : $payants . ' ' . pluriel($payants, 'client') . ' ' . pluriel($payants, 'payant'),
               'marque');
    adminTuile('Panier moyen', eur($pointMort['panier_moyen']), 'cible 75 €');
    adminTuile('Établissements', (string) $kpi['etabs'], $kpi['etabs_actifs'] . ' actifs sur 30 jours');
    adminTuile('Churn du mois',
               $churn === null ? '—' : number_format($churn, 1, ',', ' ') . ' %',
               $churn === null ? 'aucun client en début de mois' : 'seuil 5 %',
               $churn !== null && $churn > 5 ? 'danger' : 'neutre');
    adminTuile('Trésorerie', eur($solde), 'plancher ' . eur(FINANCE_PLANCHER),
               $solde < FINANCE_PLANCHER ? 'danger' : 'succes');
    adminTuile('Résultat du mois', eur($moisCourant['resultat']),
               eur($moisCourant['recettes']) . ' encaissés, ' . eur($moisCourant['depenses']) . ' dépensés',
               $moisCourant['resultat'] >= 0 ? 'succes' : 'alerte');
  ?>
</div>

<section class="admin-carte" style="margin-bottom:26px;">
  <div style="display:flex;align-items:baseline;justify-content:space-between;gap:12px;flex-wrap:wrap;">
    <h2 class="admin-titre" style="margin-bottom:0;">
      Souscriptions
      <?php if ($nouvelles7j > 0): ?>
        <span class="badge" style="background:var(--succes-clair);color:var(--succes);margin-left:6px;vertical-align:middle;">
          +<?= $nouvelles7j ?> cette semaine
        </span>
      <?php endif; ?>
    </h2>
    <a href="<?= baseUrl('/admin/clients.php?offre=essentiel') ?>" class="admin-lien-discret" style="padding-bottom:0;">Tous les abonnés →</a>
  </div>

  <?php if (empty($souscriptions)): ?>
    <p style="color:var(--gris);font-size:var(--fs-3);margin-top:12px;">
      Aucun établissement n'a encore choisi de formule. Chaque souscription prise depuis
      l'espace partenaire apparaît ici, avec sa première échéance.
    </p>
  <?php else: ?>
    <div style="margin-top:14px;">
      <?php foreach ($souscriptions as $sc):
        $offert    = $sc['essai_jusqu_au'] && strtotime($sc['essai_jusqu_au']) > time();
        $modifiee  = $sc['modifie_le'] > $sc['signe_le'];
        $nouvelle  = strtotime($sc['signe_le']) >= strtotime('-7 days');
      ?>
        <a href="<?= baseUrl('/admin/client.php?id=' . $sc['id']) ?>"
           style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;padding:12px 0;
                  border-bottom:1px solid var(--gris-clair);text-decoration:none;color:inherit;">
          <span style="flex:1 1 200px;min-width:0;">
            <span style="display:block;font-weight:var(--fw-bold);font-size:var(--fs-4);">
              <?= htmlspecialchars($sc['nom']) ?>
              <?php if ($nouvelle): ?>
                <span class="badge" style="background:var(--succes-clair);color:var(--succes);">Nouveau</span>
              <?php endif; ?>
            </span>
            <span style="display:block;font-size:var(--fs-1);color:var(--gris);">
              <?= htmlspecialchars(crmCategoriesClient()[$sc['categorie']] ?? $sc['categorie']) ?>
              · <?= htmlspecialchars($sc['ville']) ?>
              <?= $sc['contact_nom'] ? ' · ' . htmlspecialchars($sc['contact_nom']) : '' ?>
            </span>
          </span>

          <span style="flex:0 0 auto;"><?= adminPastille(crmLibelleOffre($sc['offre']), crmCouleurStatut($sc['statut'])) ?></span>

          <span style="flex:0 0 auto;text-align:right;min-width:130px;">
            <span style="display:block;font-weight:var(--fw-bold);white-space:nowrap;">
              <?= (float) $sc['mrr'] > 0 ? htmlspecialchars(eur((float) $sc['mrr'])) . ' / mois' : 'Gratuit' ?>
            </span>
            <span style="display:block;font-size:var(--fs-1);color:<?= $offert ? 'var(--alerte)' : 'var(--gris)' ?>;white-space:nowrap;">
              <?php if ($offert): ?>
                offert jusqu'au <?= dateFr($sc['essai_jusqu_au'], 'j M') ?>
              <?php else: ?>
                facturé depuis le <?= dateFr($sc['essai_jusqu_au'] ?: $sc['signe_le'], 'j M') ?>
              <?php endif; ?>
            </span>
          </span>

          <span style="flex:0 0 auto;font-size:var(--fs-1);color:var(--gris);white-space:nowrap;min-width:92px;text-align:right;">
            <?= dateFr($sc['signe_le'], 'j M Y') ?>
            <?php if ($modifiee): ?>
              <span style="display:block;">modifiée le <?= dateFr($sc['modifie_le'], 'j M') ?></span>
            <?php endif; ?>
          </span>
        </a>
      <?php endforeach; ?>
    </div>
    <p style="font-size:var(--fs-2);color:var(--gris);margin-top:12px;">
      La première échéance de chaque souscription est déjà au registre, en prévisionnel —
      <a href="<?= baseUrl('/admin/finances.php?sens=recette') ?>" style="color:var(--sur-rouge-clair);font-weight:var(--fw-bold);">voir les finances</a>.
    </p>
  <?php endif; ?>
</section>

<div class="admin-2col">

  <section class="admin-carte">
    <div style="display:flex;align-items:baseline;justify-content:space-between;gap:12px;flex-wrap:wrap;">
      <h2 class="admin-titre" style="margin-bottom:0;">Pipeline</h2>
      <a href="<?= baseUrl('/admin/clients.php') ?>" class="admin-lien-discret" style="padding-bottom:0;">Tous les clients →</a>
    </div>
    <div style="margin-top:14px;">
      <?php
        $totalPipeline = max(1, array_sum($pipeline));
        foreach (crmStatuts() as $code => $def):
          $n = $pipeline[$code];
      ?>
        <a href="<?= baseUrl('/admin/clients.php?statut=' . $code) ?>" class="admin-etape">
          <span class="admin-etape-nom"><?= adminPastille($def['libelle'], $def['couleur']) ?></span>
          <span class="admin-etape-barre" aria-hidden="true">
            <span style="width:<?= round($n / $totalPipeline * 100) ?>%;background:<?= $def['couleur'] ?>;"></span>
          </span>
          <span class="admin-etape-nb"><?= $n ?></span>
        </a>
      <?php endforeach; ?>
    </div>
    <p style="font-size:var(--fs-2);color:var(--gris);margin-top:12px;">
      Point mort : <?= $pointMort['clients_requis'] ?> <?= pluriel($pointMort['clients_requis'], 'client') ?>
      à <?= eur($pointMort['panier_moyen']) ?> couvrent les <?= eur(FINANCE_CHARGES_MENSUELLES) ?> de charges mensuelles.
    </p>
  </section>

  <section class="admin-carte">
    <h2 class="admin-titre">Prochaines actions</h2>
    <?php if (empty($relances)): ?>
      <p style="color:var(--gris);font-size:var(--fs-3);">
        Rien en attente. Les actions notées sur une fiche client apparaissent ici à leur échéance.
      </p>
    <?php else: ?>
      <?php foreach ($relances as $r):
        $enRetard = strtotime($r['prochaine_action_le']) < strtotime('today');
      ?>
        <a href="<?= baseUrl('/admin/client.php?id=' . $r['client_id']) ?>"
           style="display:block;padding:10px 0;border-bottom:1px solid var(--gris-clair);text-decoration:none;color:inherit;">
          <span style="display:flex;align-items:baseline;justify-content:space-between;gap:10px;">
            <span style="font-weight:var(--fw-bold);font-size:var(--fs-4);"><?= htmlspecialchars($r['nom']) ?></span>
            <span style="font-size:var(--fs-1);font-weight:var(--fw-bold);white-space:nowrap;
                         color:<?= $enRetard ? 'var(--danger)' : 'var(--gris)' ?>;">
              <?= dateFr($r['prochaine_action_le'], 'j M') ?><?= $enRetard ? ' · en retard' : '' ?>
            </span>
          </span>
          <span style="display:block;font-size:var(--fs-2);color:var(--gris-fonce);margin-top:2px;">
            <?= htmlspecialchars($r['prochaine_action']) ?>
          </span>
        </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>

</div>

<?php adminFooter(); ?>
