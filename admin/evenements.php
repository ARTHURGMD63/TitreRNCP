<?php
/**
 * Vue d'ensemble des soirées, tous établissements confondus.
 *
 * Le tableau de bord partenaire montre à chacun ses propres soirées ; celui-ci
 * sert à la question que seuls les fondateurs se posent : quelles soirées
 * tiennent leur promesse, et lesquelles font chuter le taux de présence que
 * la plaquette met en avant.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_once __DIR__ . '/../includes/sponsoring.php';
requireAdmin();

$fEtab    = (int) ($_GET['etab'] ?? 0);
$fPeriode = in_array($_GET['periode'] ?? '', ['avenir', 'passes', 'tous'], true) ? $_GET['periode'] : 'tous';
$fType    = in_array($_GET['type'] ?? '', ['bar', 'boite', 'resto', 'afterwork'], true) ? $_GET['type'] : '';

$sql = "SELECT e.*, et.nom AS etab_nom, et.ville, c.id AS client_id,
               (SELECT COUNT(*) FROM inscriptions i WHERE i.evenement_id = e.id AND i.statut <> 'annule') AS inscrits,
               (SELECT COUNT(*) FROM inscriptions i WHERE i.evenement_id = e.id AND i.statut = 'checkin') AS presents
          FROM evenements e
          JOIN etablissements et ON et.id = e.etablissement_id
          LEFT JOIN crm_clients c ON c.etablissement_id = et.id
         WHERE 1 = 1";
$params = [];

if ($fEtab)  { $sql .= " AND et.id = ?";  $params[] = $fEtab; }
if ($fType)  { $sql .= " AND e.type = ?"; $params[] = $fType; }
if ($fPeriode === 'avenir') $sql .= " AND e.date_heure >= NOW()";
if ($fPeriode === 'passes') $sql .= " AND e.date_heure <  NOW()";

$sql .= " ORDER BY e.date_heure DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$evenements = $stmt->fetchAll();

$etabs = $pdo->query("SELECT id, nom FROM etablissements ORDER BY nom")->fetchAll();

// Repères globaux — indépendants des filtres, pour garder un point fixe.
$nbTotal   = (int) $pdo->query("SELECT COUNT(*) FROM evenements")->fetchColumn();
$nbAvenir  = (int) $pdo->query("SELECT COUNT(*) FROM evenements WHERE date_heure >= NOW()")->fetchColumn();
$nb7j      = (int) $pdo->query("SELECT COUNT(*) FROM evenements WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$taux      = tauxPresenceGlobal($pdo);
$nbSponso  = (int) $pdo->query(
    "SELECT COUNT(*) FROM evenements
      WHERE is_sponsorise = 1 AND (sponsor_jusqu_au IS NULL OR sponsor_jusqu_au > NOW())"
)->fetchColumn();
$caSponso  = (float) $pdo->query("SELECT COALESCE(SUM(sponsor_tarif), 0) FROM evenements WHERE is_sponsorise = 1")->fetchColumn();

$typeLabels = ['bar' => 'Bar', 'boite' => 'Boîte', 'resto' => 'Resto', 'afterwork' => 'Afterwork'];

$nbAffichees = count($evenements);
adminHeader($pdo, 'evenements', 'Événements',
    $nbAffichees . ' ' . pluriel($nbAffichees, 'soirée') . ' ' . pluriel($nbAffichees, 'affichée'));
?>

<div class="admin-tuiles">
  <?php
    adminTuile('Soirées publiées', (string) $nbTotal, $nbAvenir . ' à venir');
    adminTuile('Cette semaine', (string) $nb7j, $nb7j < 10 ? 'seuil d\'alerte : 10' : 'au-dessus du seuil',
               $nb7j < 10 ? 'alerte' : 'succes');
    adminTuile('Taux de présence',
               $taux === null ? '—' : number_format($taux, 1, ',', ' ') . ' %',
               'cible 70 % · 30 derniers jours',
               $taux !== null && $taux < 60 ? 'danger' : 'neutre');
    adminTuile('Mises en avant', (string) $nbSponso, eur($caSponso) . ' facturés au total', 'marque');
  ?>
</div>

<form method="GET" class="admin-filtres">
  <div class="admin-filtre">
    <label for="e-etab">Établissement</label>
    <select id="e-etab" name="etab">
      <option value="">Tous</option>
      <?php foreach ($etabs as $et): ?>
        <option value="<?= $et['id'] ?>" <?= $fEtab === (int) $et['id'] ? 'selected' : '' ?>><?= htmlspecialchars($et['nom']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="admin-filtre">
    <label for="e-periode">Période</label>
    <select id="e-periode" name="periode">
      <option value="tous"   <?= $fPeriode === 'tous'   ? 'selected' : '' ?>>Toutes</option>
      <option value="avenir" <?= $fPeriode === 'avenir' ? 'selected' : '' ?>>À venir</option>
      <option value="passes" <?= $fPeriode === 'passes' ? 'selected' : '' ?>>Passées</option>
    </select>
  </div>
  <div class="admin-filtre">
    <label for="e-type">Type</label>
    <select id="e-type" name="type">
      <option value="">Tous</option>
      <?php foreach ($typeLabels as $code => $lib): ?>
        <option value="<?= $code ?>" <?= $fType === $code ? 'selected' : '' ?>><?= $lib ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit" class="btn btn-primary" style="padding:11px 22px;">Filtrer</button>
  <?php if ($fEtab || $fType || $fPeriode !== 'tous'): ?>
    <a href="<?= baseUrl('/admin/evenements.php') ?>" class="admin-lien-discret">Réinitialiser</a>
  <?php endif; ?>
</form>

<div class="admin-tableau">
  <div class="admin-tableau-defilant">
  <table class="events-table">
    <thead>
      <tr>
        <th scope="col">Soirée</th>
        <th scope="col">Établissement</th>
        <th scope="col">Date</th>
        <th scope="col">Inscrits</th>
        <th scope="col">Présents</th>
        <th scope="col">Taux</th>
        <th scope="col">Remise</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($evenements)): ?>
        <tr><td colspan="7" class="admin-vide">Aucune soirée ne correspond.</td></tr>
      <?php endif; ?>
      <?php foreach ($evenements as $e):
        $passe = strtotime($e['date_heure']) < time();
        $tauxE = $e['inscrits'] > 0 ? $e['presents'] / $e['inscrits'] * 100 : null;
        $remplissage = $e['quota'] > 0 ? min(100, $e['inscrits'] / $e['quota'] * 100) : 0;
      ?>
      <tr>
        <td data-label="Soirée" class="est-entete-carte">
          <a href="<?= baseUrl('/view_event.php?id=' . $e['id']) ?>"
             style="font-weight:var(--fw-bold);color:inherit;text-decoration:none;"><?= htmlspecialchars($e['titre']) ?></a>
          <div style="display:flex;gap:5px;flex-wrap:wrap;margin-top:4px;">
            <span class="badge badge-<?= $e['type'] ?>"><?= $typeLabels[$e['type']] ?? $e['type'] ?></span>
            <?php if ($e['is_flash']): ?><span class="badge badge-flash">Flash</span><?php endif; ?>
            <?php if ($e['is_gratuit']): ?><span class="badge badge-gratuit">Gratuit</span><?php endif; ?>
            <?php if (sponsoringActif($e)): ?>
              <span class="badge badge-sponsorise" title="<?= htmlspecialchars(libelleSponsoring($e['sponsor_formule'])) ?>">Sponsorisé</span>
            <?php endif; ?>
          </div>
        </td>
        <td data-label="Établissement" style="font-size:var(--fs-3);">
          <?php if ($e['client_id']): ?>
            <a href="<?= baseUrl('/admin/client.php?id=' . $e['client_id']) ?>" style="color:inherit;text-decoration:none;font-weight:var(--fw-semibold);">
              <?= htmlspecialchars($e['etab_nom']) ?>
            </a>
          <?php else: ?>
            <?= htmlspecialchars($e['etab_nom']) ?>
          <?php endif; ?>
          <div style="font-size:var(--fs-1);color:var(--gris);"><?= htmlspecialchars($e['ville']) ?></div>
        </td>
        <td data-label="Date" style="white-space:nowrap;font-size:var(--fs-2);
            color:<?= $passe ? 'var(--gris)' : 'var(--gris-fonce)' ?>;">
          <?= dateFr($e['date_heure'], 'D j M Y') ?>
          <div style="font-size:var(--fs-1);color:var(--gris);"><?= $passe ? 'passée' : 'à venir' ?></div>
        </td>
        <td data-label="Inscrits">
          <div style="font-weight:var(--fw-bold);"><?= (int) $e['inscrits'] ?>/<?= (int) $e['quota'] ?></div>
          <div style="height:3px;background:var(--gris-clair);border-radius:2px;margin-top:4px;width:60px;overflow:hidden;">
            <div style="height:100%;background:var(--rouge);width:<?= round($remplissage) ?>%;border-radius:2px;"></div>
          </div>
        </td>
        <td data-label="Présents" style="font-weight:var(--fw-bold);"><?= $passe ? (int) $e['presents'] : '—' ?></td>
        <td data-label="Taux" style="font-weight:var(--fw-bold);white-space:nowrap;
            color:<?= $tauxE === null || !$passe ? 'var(--gris)' : ($tauxE < 60 ? 'var(--danger)' : 'var(--succes)') ?>;">
          <?= $passe && $tauxE !== null ? number_format($tauxE, 0, ',', ' ') . ' %' : '—' ?>
        </td>
        <td data-label="Remise" style="font-size:var(--fs-2);white-space:nowrap;">
          <?= (int) $e['reduction'] > 0 ? '−' . (int) $e['reduction'] . ' %' : ($e['is_gratuit'] ? 'Gratuit' : '—') ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<p style="font-size:var(--fs-2);color:var(--gris);margin-top:14px;">
  Une soirée sous 60 % de présence déclenche une action : vérifier que le rappel de la veille est bien parti,
  puis appeler le partenaire avant qu'il ne tire ses propres conclusions.
</p>

<?php adminFooter(); ?>
