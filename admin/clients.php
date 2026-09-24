<?php
/**
 * Fichier clients — le pipeline commercial.
 *
 * Un client ici n'est pas forcément un compte partenaire : le pipeline
 * commence au prospect, bien avant toute inscription dans l'application
 * (SL-05 demande une liste de 30 cibles clermontoises). La colonne
 * « Compte » dit justement si le partenaire a franchi ce pas.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin_layout.php';
requireAdmin();

$moi = currentUser();

// ─── Création ───────────────────────────────────────────────────────────────
$erreurs = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['creer_client'])) {
    csrfVerify();

    $nom       = trim($_POST['nom'] ?? '');
    $categorie = array_key_exists($_POST['categorie'] ?? '', crmCategoriesClient()) ? $_POST['categorie'] : 'bar';
    $ville     = trim($_POST['ville'] ?? '') ?: 'Clermont-Ferrand';
    $statut    = array_key_exists($_POST['statut'] ?? '', crmStatuts()) ? $_POST['statut'] : 'prospect';

    if ($nom === '') $erreurs[] = 'Le nom de l\'établissement est obligatoire.';

    if (!$erreurs) {
        $pdo->prepare(
            "INSERT INTO crm_clients (nom, categorie, ville, contact_nom, contact_email, contact_tel, statut, responsable_id)
             VALUES (?,?,?,?,?,?,?,?)"
        )->execute([
            $nom, $categorie, $ville,
            trim($_POST['contact_nom'] ?? '')   ?: null,
            trim($_POST['contact_email'] ?? '') ?: null,
            trim($_POST['contact_tel'] ?? '')   ?: null,
            $statut, $moi['id'],
        ]);
        header('Location: ' . baseUrl('/admin/client.php?id=' . $pdo->lastInsertId() . '&cree=1'));
        exit;
    }
}

// ─── Filtres ────────────────────────────────────────────────────────────────
$fStatut   = array_key_exists($_GET['statut'] ?? '', crmStatuts()) ? $_GET['statut'] : '';
$fOffre    = array_key_exists($_GET['offre'] ?? '', crmOffres()) ? $_GET['offre'] : '';
$fRelances = !empty($_GET['relances']);
$q         = trim($_GET['q'] ?? '');

$sql = "SELECT c.*,
               u.prenom AS resp_prenom,
               et.id AS etab_id,
               (SELECT COUNT(*) FROM evenements e WHERE e.etablissement_id = c.etablissement_id) AS nb_events,
               (SELECT MAX(e.created_at) FROM evenements e WHERE e.etablissement_id = c.etablissement_id) AS derniere_publication,
               (SELECT COUNT(*) FROM crm_interactions i
                 WHERE i.client_id = c.id AND i.fait = 0
                   AND i.prochaine_action_le IS NOT NULL
                   AND i.prochaine_action_le <= CURDATE()) AS relances_dues
          FROM crm_clients c
          LEFT JOIN users u ON u.id = c.responsable_id
          LEFT JOIN etablissements et ON et.id = c.etablissement_id
         WHERE 1 = 1";
$params = [];

if ($fStatut) { $sql .= " AND c.statut = ?";    $params[] = $fStatut; }
if ($fOffre)  { $sql .= " AND c.offre = ?";     $params[] = $fOffre; }
if ($q) {
    $sql .= " AND (c.nom LIKE ? OR c.ville LIKE ? OR c.contact_nom LIKE ? OR c.contact_email LIKE ?)";
    $terme = '%' . addcslashes($q, '%_') . '%';
    array_push($params, $terme, $terme, $terme, $terme);
}
if ($fRelances) {
    $sql .= " AND EXISTS (SELECT 1 FROM crm_interactions i
                           WHERE i.client_id = c.id AND i.fait = 0
                             AND i.prochaine_action_le IS NOT NULL
                             AND i.prochaine_action_le <= CURDATE())";
}

// Les comptes qui rapportent d'abord, les sorties de pipeline en dernier.
$sql .= " ORDER BY FIELD(c.statut,'actif','essai','rdv','contacte','prospect','pause','perdu'), c.mrr DESC, c.nom ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll();

$pipeline = crmPipeline($pdo);
$mrrTotal = crmMrr($pdo);

adminHeader($pdo, 'clients', 'Clients', count($clients) . ' ' . pluriel(count($clients), 'fiche'));
?>

<div class="admin-tuiles">
  <?php
    $payants = crmClientsPayants($pdo);
    adminTuile('MRR', eur($mrrTotal), $payants . ' ' . pluriel($payants, 'client') . ' ' . pluriel($payants, 'payant'), 'marque');
    adminTuile('Clients actifs', (string) ($pipeline['actif'] + $pipeline['essai']), 'actifs et essais');
    $aConvertir = $pipeline['prospect'] + $pipeline['contacte'] + $pipeline['rdv'];
    adminTuile('Dans le pipeline', (string) $aConvertir, pluriel($aConvertir, 'prospect') . ' à convertir');
    adminTuile('Perdus', (string) $pipeline['perdu'], '', $pipeline['perdu'] > 0 ? 'alerte' : 'neutre');
  ?>
</div>

<?php if ($erreurs): ?>
  <ul class="form-errors" style="margin-bottom:20px;">
    <?php foreach ($erreurs as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
  </ul>
<?php endif; ?>

<!-- Filtres -->
<form method="GET" class="admin-filtres">
  <div class="admin-filtre est-large">
    <label for="f-q">Rechercher</label>
    <input id="f-q" type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Nom, ville, contact…"
           >
  </div>
  <div class="admin-filtre">
    <label for="f-statut">Statut</label>
    <select id="f-statut" name="statut">
      <option value="">Tous</option>
      <?php foreach (crmStatuts() as $code => $def): ?>
        <option value="<?= $code ?>" <?= $fStatut === $code ? 'selected' : '' ?>><?= $def['libelle'] ?> (<?= $pipeline[$code] ?>)</option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="admin-filtre">
    <label for="f-offre">Offre</label>
    <select id="f-offre" name="offre">
      <option value="">Toutes</option>
      <?php foreach (crmOffres() as $code => $def): ?>
        <option value="<?= $code ?>" <?= $fOffre === $code ? 'selected' : '' ?>><?= $def['libelle'] ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <label class="case" style="padding-bottom:10px;">
    <input type="checkbox" name="relances" value="1" <?= $fRelances ? 'checked' : '' ?>>
    <span style="font-size:var(--fs-3);">Relances dues</span>
  </label>
  <button type="submit" class="btn btn-primary" style="padding:11px 22px;">Filtrer</button>
  <?php if ($q || $fStatut || $fOffre || $fRelances): ?>
    <a href="<?= baseUrl('/admin/clients.php') ?>" class="admin-lien-discret">Réinitialiser</a>
  <?php endif; ?>
  <button type="button" class="btn btn-outline" data-modal-open="modal-nouveau-client" style="margin-left:auto;padding:11px 22px;">+ Nouveau client</button>
</form>

<div class="admin-tableau">
  <div class="admin-tableau-defilant">
  <table class="events-table">
    <thead>
      <tr>
        <th scope="col">Établissement</th>
        <th scope="col">Statut</th>
        <th scope="col">Offre</th>
        <th scope="col">MRR</th>
        <th scope="col">Compte</th>
        <th scope="col">Dernière publication</th>
        <th scope="col">Responsable</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($clients)): ?>
        <tr><td colspan="7" class="admin-vide">
          Aucun client ne correspond. <a href="<?= baseUrl('/admin/clients.php') ?>" style="color:var(--sur-rouge-clair);font-weight:var(--fw-semibold);">Tout afficher</a>
        </td></tr>
      <?php endif; ?>
      <?php foreach ($clients as $c):
        $joursSansPublication = $c['derniere_publication'] ? (int) ((time() - strtotime($c['derniere_publication'])) / 86400) : null;
      ?>
      <tr>
        <td data-label="Établissement" class="est-entete-carte">
          <a href="<?= baseUrl('/admin/client.php?id=' . $c['id']) ?>"
             style="font-weight:var(--fw-bold);color:inherit;text-decoration:none;"><?= htmlspecialchars($c['nom']) ?></a>
          <div style="font-size:var(--fs-1);color:var(--gris);margin-top:2px;">
            <?= htmlspecialchars(crmCategoriesClient()[$c['categorie']] ?? $c['categorie']) ?> · <?= htmlspecialchars($c['ville']) ?>
          </div>
          <?php if ($c['relances_dues'] > 0): ?>
            <span class="badge" style="background:var(--alerte-clair);color:var(--alerte);margin-top:4px;display:inline-block;">
              <?= $c['relances_dues'] ?> relance<?= $c['relances_dues'] > 1 ? 's' : '' ?>
            </span>
          <?php endif; ?>
        </td>
        <td data-label="Statut"><?= adminPastille(crmLibelleStatut($c['statut']), crmCouleurStatut($c['statut'])) ?></td>
        <td data-label="Offre" style="font-size:var(--fs-3);"><?= htmlspecialchars(crmLibelleOffre($c['offre'])) ?></td>
        <td data-label="MRR" style="font-weight:var(--fw-bold);white-space:nowrap;">
          <?= $c['mrr'] > 0 ? eur((float) $c['mrr']) : '—' ?>
        </td>
        <td data-label="Compte" style="font-size:var(--fs-2);">
          <?php if ($c['etab_id']): ?>
            <span style="color:var(--succes);font-weight:var(--fw-bold);"><?= (int) $c['nb_events'] ?> soirée<?= $c['nb_events'] > 1 ? 's' : '' ?></span>
          <?php else: ?>
            <span style="color:var(--gris);">Pas de compte</span>
          <?php endif; ?>
        </td>
        <td data-label="Dernière publication" style="font-size:var(--fs-2);white-space:nowrap;
            color:<?= $joursSansPublication !== null && $joursSansPublication >= 21 ? 'var(--danger)' : 'var(--gris-fonce)' ?>;">
          <?php if ($joursSansPublication === null): ?>
            —
          <?php else: ?>
            <?= dateFr($c['derniere_publication'], 'j M') ?>
            <?= $joursSansPublication >= 21 ? ' · ' . $joursSansPublication . ' j sans rien publier' : '' ?>
          <?php endif; ?>
        </td>
        <td data-label="Responsable" style="font-size:var(--fs-2);color:var(--gris-fonce);">
          <?= htmlspecialchars($c['resp_prenom'] ?? '—') ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<!-- Nouveau client -->
<div class="modal-overlay" id="modal-nouveau-client">
  <div class="modal-sheet">
    <div class="modal-handle"></div>
    <div style="font-family:var(--font-display);font-size:var(--fs-7);font-weight:var(--fw-black);margin-bottom:6px;">Nouveau client</div>
    <p style="font-size:var(--fs-2);color:var(--gris);margin-bottom:18px;">
      Un prospect n'a pas besoin de compte dans l'application. Il se rattachera tout seul le jour où le partenaire s'inscrit.
    </p>
    <form method="POST">
      <?= csrfField() ?>
      <div class="form-group">
        <label for="nc-nom">Nom de l'établissement *</label>
        <input id="nc-nom" type="text" name="nom" required placeholder="Le Bec qui Pique">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="nc-categorie">Catégorie</label>
          <select id="nc-categorie" name="categorie">
            <?php foreach (crmCategoriesClient() as $code => $lib): ?>
              <option value="<?= $code ?>"><?= $lib ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="nc-ville">Ville</label>
          <input id="nc-ville" type="text" name="ville" value="Clermont-Ferrand">
        </div>
      </div>
      <div class="form-group">
        <label for="nc-contact">Contact</label>
        <input id="nc-contact" type="text" name="contact_nom" placeholder="Prénom Nom">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="nc-email">E-mail</label>
          <input id="nc-email" type="email" name="contact_email">
        </div>
        <div class="form-group">
          <label for="nc-tel">Téléphone</label>
          <input id="nc-tel" type="tel" name="contact_tel">
        </div>
      </div>
      <div class="form-group">
        <label for="nc-statut">Statut</label>
        <select id="nc-statut" name="statut">
          <?php foreach (crmStatuts() as $code => $def): ?>
            <option value="<?= $code ?>"><?= $def['libelle'] ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" name="creer_client" value="1" class="btn btn-primary btn-full">Créer la fiche</button>
      <button type="button" class="btn btn-outline btn-full mt-8" data-modal-close>Annuler</button>
    </form>
  </div>
</div>

<?php adminFooter(); ?>
