<?php
/**
 * Fiche client.
 *
 * Trois choses au même endroit, parce qu'elles se lisent ensemble : l'état
 * commercial (statut, offre, montant), l'historique des échanges, et ce que
 * le compte produit réellement dans l'application (soirées, présences,
 * encaissements). Séparées, on négocie sans savoir si le partenaire publie.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin_layout.php';
requireAdmin();

$moi = currentUser();
$id  = (int) ($_GET['id'] ?? 0);

$erreurs = [];
$succes  = '';

// ─── Écritures ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $id = (int) ($_POST['client_id'] ?? $id);

    if (isset($_POST['enregistrer'])) {
        $statut = array_key_exists($_POST['statut'] ?? '', crmStatuts()) ? $_POST['statut'] : 'prospect';
        $offre  = array_key_exists($_POST['offre'] ?? '', crmOffres()) ? $_POST['offre'] : 'aucune';
        $nom    = trim($_POST['nom'] ?? '');

        if ($nom === '') $erreurs[] = 'Le nom est obligatoire.';

        if (!$erreurs) {
            // Les dates de signature et de perte se posent toutes seules au
            // passage de statut, et ne s'écrasent jamais ensuite : elles
            // servent au calcul du churn, qui n'a de sens que si elles
            // reflètent le premier passage et non le dernier enregistrement.
            $pdo->prepare(
                "UPDATE crm_clients SET
                    nom = ?, categorie = ?, ville = ?, adresse = ?,
                    contact_nom = ?, contact_role = ?, contact_email = ?, contact_tel = ?,
                    statut = ?, offre = ?, mrr = ?, essai_jusqu_au = ?,
                    motif_perte = ?, responsable_id = ?, notes = ?,
                    signe_le = CASE WHEN signe_le IS NULL AND ? IN ('actif','essai') THEN CURDATE() ELSE signe_le END,
                    perdu_le = CASE WHEN ? = 'perdu' THEN COALESCE(perdu_le, CURDATE()) ELSE NULL END
                  WHERE id = ?"
            )->execute([
                $nom,
                array_key_exists($_POST['categorie'] ?? '', crmCategoriesClient()) ? $_POST['categorie'] : 'bar',
                trim($_POST['ville'] ?? '') ?: 'Clermont-Ferrand',
                trim($_POST['adresse'] ?? '') ?: null,
                trim($_POST['contact_nom'] ?? '')   ?: null,
                trim($_POST['contact_role'] ?? '')  ?: null,
                trim($_POST['contact_email'] ?? '') ?: null,
                trim($_POST['contact_tel'] ?? '')   ?: null,
                $statut, $offre,
                max(0, (float) ($_POST['mrr'] ?? 0)),
                trim($_POST['essai_jusqu_au'] ?? '') ?: null,
                trim($_POST['motif_perte'] ?? '') ?: null,
                (int) ($_POST['responsable_id'] ?? 0) ?: null,
                trim($_POST['notes'] ?? '') ?: null,
                $statut, $statut, $id,
            ]);
            header('Location: ' . baseUrl('/admin/client.php?id=' . $id . '&ok=1'));
            exit;
        }
    }

    if (isset($_POST['ajouter_interaction'])) {
        $contenu = trim($_POST['contenu'] ?? '');
        if ($contenu === '') {
            $erreurs[] = 'Le compte rendu ne peut pas être vide.';
        } else {
            $pdo->prepare(
                "INSERT INTO crm_interactions (client_id, auteur_id, type, contenu, prochaine_action, prochaine_action_le)
                 VALUES (?,?,?,?,?,?)"
            )->execute([
                $id, $moi['id'],
                array_key_exists($_POST['type'] ?? '', crmTypesInteraction()) ? $_POST['type'] : 'note',
                $contenu,
                trim($_POST['prochaine_action'] ?? '') ?: null,
                trim($_POST['prochaine_action_le'] ?? '') ?: null,
            ]);
            header('Location: ' . baseUrl('/admin/client.php?id=' . $id . '&ok=1'));
            exit;
        }
    }

    if (isset($_POST['action_faite'])) {
        $pdo->prepare("UPDATE crm_interactions SET fait = 1 WHERE id = ? AND client_id = ?")
            ->execute([(int) $_POST['action_faite'], $id]);
        header('Location: ' . baseUrl('/admin/client.php?id=' . $id));
        exit;
    }

    // Rattachement manuel à un compte partenaire existant. Utile pour les
    // établissements créés avant le CRM, ou quand le partenaire s'inscrit
    // sous un nom légèrement différent.
    if (isset($_POST['rattacher'])) {
        $etabId = (int) $_POST['rattacher'] ?: null;
        try {
            $pdo->prepare("UPDATE crm_clients SET etablissement_id = ? WHERE id = ?")->execute([$etabId, $id]);
        } catch (PDOException $e) {
            // L'index unique refuse qu'un établissement serve deux fiches.
            $erreurs[] = $e->getCode() === '23000'
                ? 'Cet établissement est déjà rattaché à une autre fiche client.'
                : 'Rattachement impossible.';
        }
        if (!$erreurs) {
            header('Location: ' . baseUrl('/admin/client.php?id=' . $id . '&ok=1'));
            exit;
        }
    }
}

// ─── Lecture ────────────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT c.*, et.nom AS etab_nom, et.adresse AS etab_adresse, u.email AS compte_email
      FROM crm_clients c
      LEFT JOIN etablissements et ON et.id = c.etablissement_id
      LEFT JOIN users u ON u.id = et.user_id
     WHERE c.id = ?
");
$stmt->execute([$id]);
$client = $stmt->fetch();

if (!$client) {
    header('Location: ' . baseUrl('/admin/clients.php'));
    exit;
}

$stmtI = $pdo->prepare("
    SELECT i.*, u.prenom AS auteur_prenom
      FROM crm_interactions i
      LEFT JOIN users u ON u.id = i.auteur_id
     WHERE i.client_id = ?
     ORDER BY i.created_at DESC
");
$stmtI->execute([$id]);
$interactions = $stmtI->fetchAll();

$evenements = [];
$stats = ['soirees' => 0, 'inscrits' => 0, 'presents' => 0];
if ($client['etablissement_id']) {
    $stmtE = $pdo->prepare("
        SELECT e.id, e.titre, e.date_heure, e.quota, e.is_sponsorise,
               (SELECT COUNT(*) FROM inscriptions i WHERE i.evenement_id = e.id AND i.statut != 'annule') AS inscrits,
               (SELECT COUNT(*) FROM inscriptions i WHERE i.evenement_id = e.id AND i.statut = 'checkin') AS presents
          FROM evenements e
         WHERE e.etablissement_id = ?
         ORDER BY e.date_heure DESC
         LIMIT 12
    ");
    $stmtE->execute([$client['etablissement_id']]);
    $evenements = $stmtE->fetchAll();

    $stmtS = $pdo->prepare("
        SELECT COUNT(DISTINCT e.id) AS soirees,
               COUNT(CASE WHEN i.statut != 'annule' THEN 1 END) AS inscrits,
               COUNT(CASE WHEN i.statut = 'checkin' THEN 1 END) AS presents
          FROM evenements e
          LEFT JOIN inscriptions i ON i.evenement_id = e.id
         WHERE e.etablissement_id = ?
    ");
    $stmtS->execute([$client['etablissement_id']]);
    $stats = $stmtS->fetch() ?: $stats;
}

$stmtF = $pdo->prepare("
    SELECT COALESCE(SUM(CASE WHEN statut = 'regle' THEN montant_ht END), 0) AS encaisse,
           COALESCE(SUM(CASE WHEN statut = 'prevu' THEN montant_ht END), 0) AS attendu
      FROM finance_mouvements WHERE client_id = ? AND sens = 'recette'
");
$stmtF->execute([$id]);
$finance = $stmtF->fetch();

// Établissements sans fiche, proposés au rattachement.
$libres = $pdo->prepare("
    SELECT et.id, et.nom, et.ville FROM etablissements et
     WHERE NOT EXISTS (SELECT 1 FROM crm_clients c WHERE c.etablissement_id = et.id AND c.id <> ?)
     ORDER BY et.nom
");
$libres->execute([$id]);
$etabsLibres = $libres->fetchAll();

$admins = $pdo->query("SELECT id, prenom, nom FROM users WHERE type = 'admin' ORDER BY prenom")->fetchAll();

$tauxPresence = $stats['inscrits'] > 0 ? $stats['presents'] / $stats['inscrits'] * 100 : null;

adminHeader($pdo, 'clients', $client['nom'], crmLibelleStatut($client['statut']) . ' · ' . $client['ville']);
?>

<div style="margin-bottom:20px;">
  <a href="<?= baseUrl('/admin/clients.php') ?>" style="font-size:var(--fs-2);color:var(--gris);text-decoration:none;">← Retour aux clients</a>
</div>

<?php if (isset($_GET['ok']) || isset($_GET['cree'])): ?>
  <div class="form-success" style="margin-bottom:20px;"><?= isset($_GET['cree']) ? 'Fiche créée.' : 'Fiche mise à jour.' ?></div>
<?php endif; ?>

<?php if ($erreurs): ?>
  <ul class="form-errors" style="margin-bottom:20px;">
    <?php foreach ($erreurs as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
  </ul>
<?php endif; ?>

<div class="admin-tuiles">
  <?php
    adminTuile('Abonnement', $client['mrr'] > 0 ? eur((float) $client['mrr']) . '/mois' : '—', crmLibelleOffre($client['offre']), 'marque');
    adminTuile('Encaissé', eur((float) $finance['encaisse']), $finance['attendu'] > 0 ? eur((float) $finance['attendu']) . ' attendus' : 'depuis le début');
    adminTuile('Soirées publiées', (string) (int) $stats['soirees'], (int) $stats['inscrits'] . ' inscrits cumulés');
    adminTuile(
        'Taux de présence',
        $tauxPresence === null ? '—' : number_format($tauxPresence, 1, ',', ' ') . ' %',
        $tauxPresence === null ? 'aucune inscription' : (int) $stats['presents'] . ' pass scannés',
        $tauxPresence !== null && $tauxPresence < 60 ? 'danger' : 'neutre'
    );
  ?>
</div>

<div class="admin-2col">

  <!-- Colonne gauche : échanges et activité -->
  <div style="display:flex;flex-direction:column;gap:22px;min-width:0;">

    <section class="admin-carte">
      <h2 class="admin-titre">Nouvel échange</h2>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="client_id" value="<?= $id ?>">
        <div class="form-row">
          <div class="form-group">
            <label for="i-type">Type</label>
            <select id="i-type" name="type">
              <?php foreach (crmTypesInteraction() as $code => $lib): ?>
                <option value="<?= $code ?>"><?= $lib ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label for="i-date">Prochaine action le</label>
            <input id="i-date" type="date" name="prochaine_action_le">
          </div>
        </div>
        <div class="form-group">
          <label for="i-contenu">Compte rendu *</label>
          <textarea id="i-contenu" name="contenu" required rows="3" placeholder="Ce qui s'est dit, ce qui a été promis, par qui."></textarea>
        </div>
        <div class="form-group">
          <label for="i-action">Prochaine action</label>
          <input id="i-action" type="text" name="prochaine_action" placeholder="Rappeler pour confirmer la soirée test">
        </div>
        <button type="submit" name="ajouter_interaction" value="1" class="btn btn-primary">Enregistrer l'échange</button>
      </form>
    </div>

    <section class="admin-carte">
      <h2 class="admin-titre">Historique</h2>
      <?php if (empty($interactions)): ?>
        <p style="color:var(--gris);font-size:var(--fs-3);">Aucun échange enregistré.</p>
      <?php endif; ?>
      <?php foreach ($interactions as $it):
        $enRetard = !$it['fait'] && $it['prochaine_action_le'] && strtotime($it['prochaine_action_le']) < strtotime('today');
      ?>
        <div style="padding:14px 0;border-bottom:1px solid var(--gris-clair);">
          <div style="display:flex;align-items:baseline;gap:10px;flex-wrap:wrap;margin-bottom:6px;">
            <span class="badge" style="background:var(--surface-2);color:var(--gris-fonce);border:1px solid var(--line-2);">
              <?= htmlspecialchars(crmTypesInteraction()[$it['type']] ?? $it['type']) ?>
            </span>
            <span style="font-size:var(--fs-2);color:var(--gris);">
              <?= dateFr($it['created_at'], 'j M Y') ?><?= $it['auteur_prenom'] ? ' · ' . htmlspecialchars($it['auteur_prenom']) : '' ?>
            </span>
          </div>
          <div style="font-size:var(--fs-4);line-height:var(--lh-normal);white-space:pre-line;"><?= htmlspecialchars($it['contenu']) ?></div>
          <?php if ($it['prochaine_action']): ?>
            <div style="margin-top:10px;display:flex;align-items:center;gap:10px;flex-wrap:wrap;
                        background:<?= $it['fait'] ? 'var(--surface-2)' : ($enRetard ? 'var(--danger-clair)' : 'var(--alerte-clair)') ?>;
                        color:<?= $it['fait'] ? 'var(--gris)' : ($enRetard ? 'var(--danger)' : 'var(--alerte)') ?>;
                        border-radius:var(--radius-sm);padding:9px 13px;font-size:var(--fs-3);">
              <span style="flex:1;<?= $it['fait'] ? 'text-decoration:line-through;' : '' ?>">
                <strong><?= htmlspecialchars($it['prochaine_action']) ?></strong>
                <?= $it['prochaine_action_le'] ? ' — ' . dateFr($it['prochaine_action_le'], 'j M') : '' ?>
              </span>
              <?php if (!$it['fait']): ?>
                <form method="POST" style="margin:0;">
                  <?= csrfField() ?>
                  <input type="hidden" name="client_id" value="<?= $id ?>">
                  <button type="submit" name="action_faite" value="<?= $it['id'] ?>"
                          style="background:none;border:1px solid currentColor;border-radius:var(--radius-pill);
                                 color:inherit;font-size:var(--fs-1);font-weight:var(--fw-bold);padding:4px 12px;cursor:pointer;">
                    Fait
                  </button>
                </form>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <section class="admin-carte">
      <div style="display:flex;align-items:baseline;justify-content:space-between;margin-bottom:16px;gap:12px;">
        <h2 class="admin-titre" style="margin-bottom:0;">Soirées</h2>
        <?php if ($client['etablissement_id']): ?>
          <a href="<?= baseUrl('/admin/evenements.php?etab=' . $client['etablissement_id']) ?>"
             class="admin-lien-discret" style="padding-bottom:0;">Toutes →</a>
        <?php endif; ?>
      </div>

      <?php if (!$client['etablissement_id']): ?>
        <p style="color:var(--gris);font-size:var(--fs-3);margin-bottom:14px;">
          Ce client n'a pas encore de compte partenaire dans l'application.
        </p>
        <?php if ($etabsLibres): ?>
          <form method="POST" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
            <?= csrfField() ?>
            <input type="hidden" name="client_id" value="<?= $id ?>">
            <select name="rattacher">
              <option value="">— Rattacher un compte existant —</option>
              <?php foreach ($etabsLibres as $et): ?>
                <option value="<?= $et['id'] ?>"><?= htmlspecialchars($et['nom']) ?> (<?= htmlspecialchars($et['ville']) ?>)</option>
              <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-outline" style="padding:10px 18px;">Rattacher</button>
          </form>
        <?php endif; ?>
      <?php elseif (empty($evenements)): ?>
        <p style="color:var(--gris);font-size:var(--fs-3);">Compte créé, aucune soirée publiée. C'est le moment d'appeler.</p>
      <?php else: ?>
        <?php foreach ($evenements as $e):
          $taux = $e['inscrits'] > 0 ? $e['presents'] / $e['inscrits'] * 100 : null;
          $passe = strtotime($e['date_heure']) < time();
        ?>
          <div style="display:flex;align-items:center;gap:14px;padding:11px 0;border-bottom:1px solid var(--gris-clair);flex-wrap:wrap;">
            <div style="flex:1;min-width:160px;">
              <div style="font-weight:var(--fw-semibold);font-size:var(--fs-4);"><?= htmlspecialchars($e['titre']) ?></div>
              <div style="font-size:var(--fs-1);color:var(--gris);"><?= dateFr($e['date_heure'], 'D j M Y') ?></div>
            </div>
            <div style="font-size:var(--fs-2);color:var(--gris-fonce);white-space:nowrap;">
              <?= (int) $e['inscrits'] ?>/<?= (int) $e['quota'] ?> inscrits
            </div>
            <div style="font-size:var(--fs-2);font-weight:var(--fw-bold);white-space:nowrap;min-width:92px;text-align:right;
                        color:<?= $taux === null ? 'var(--gris)' : ($taux < 60 ? 'var(--danger)' : 'var(--succes)') ?>;">
              <?= $passe ? ($taux === null ? '—' : (int) $e['presents'] . ' présents') : 'à venir' ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>

  <!-- Colonne droite : la fiche -->
  <form method="POST" class="admin-colle" style="background:var(--blanc);border:1px solid var(--line-2);border-radius:var(--radius);padding:22px;position:sticky;top:24px;">
    <?= csrfField() ?>
    <input type="hidden" name="client_id" value="<?= $id ?>">
    <h2 class="admin-titre">Fiche</h2>

    <div class="form-group">
      <label for="c-nom">Nom *</label>
      <input id="c-nom" type="text" name="nom" value="<?= htmlspecialchars($client['nom']) ?>" required>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label for="c-categorie">Catégorie</label>
        <select id="c-categorie" name="categorie">
          <?php foreach (crmCategoriesClient() as $code => $lib): ?>
            <option value="<?= $code ?>" <?= $client['categorie'] === $code ? 'selected' : '' ?>><?= $lib ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="c-ville">Ville</label>
        <input id="c-ville" type="text" name="ville" value="<?= htmlspecialchars($client['ville']) ?>">
      </div>
    </div>
    <div class="form-group">
      <label for="c-adresse">Adresse</label>
      <input id="c-adresse" type="text" name="adresse" value="<?= htmlspecialchars($client['adresse'] ?? '') ?>">
    </div>

    <div class="section-divider" style="margin:18px 0;"></div>

    <div class="form-row">
      <div class="form-group">
        <label for="c-contact">Contact</label>
        <input id="c-contact" type="text" name="contact_nom" value="<?= htmlspecialchars($client['contact_nom'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label for="c-role">Fonction</label>
        <input id="c-role" type="text" name="contact_role" value="<?= htmlspecialchars($client['contact_role'] ?? '') ?>" placeholder="Gérant">
      </div>
    </div>
    <div class="form-group">
      <label for="c-email">E-mail</label>
      <input id="c-email" type="email" name="contact_email" value="<?= htmlspecialchars($client['contact_email'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label for="c-tel">Téléphone</label>
      <input id="c-tel" type="tel" name="contact_tel" value="<?= htmlspecialchars($client['contact_tel'] ?? '') ?>">
    </div>

    <div class="section-divider" style="margin:18px 0;"></div>

    <div class="form-row">
      <div class="form-group">
        <label for="c-statut">Statut</label>
        <select id="c-statut" name="statut">
          <?php foreach (crmStatuts() as $code => $def): ?>
            <option value="<?= $code ?>" <?= $client['statut'] === $code ? 'selected' : '' ?>><?= $def['libelle'] ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="c-offre">Offre</label>
        <select id="c-offre" name="offre" data-tarifs='<?= json_encode(array_map(fn($o) => $o['tarif'], crmOffres())) ?>'>
          <?php foreach (crmOffres() as $code => $def): ?>
            <option value="<?= $code ?>" <?= $client['offre'] === $code ? 'selected' : '' ?>><?= $def['libelle'] ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label for="c-mrr">Montant mensuel HT</label>
        <input id="c-mrr" type="number" name="mrr" min="0" step="1" value="<?= (float) $client['mrr'] ?>">
        <span style="font-size:var(--fs-1);color:var(--gris);">Le tarif fondateur est gelé : il ne suit pas la grille.</span>
      </div>
      <div class="form-group">
        <label for="c-essai">Essai gratuit jusqu'au</label>
        <input id="c-essai" type="date" name="essai_jusqu_au" value="<?= htmlspecialchars($client['essai_jusqu_au'] ?? '') ?>">
      </div>
    </div>
    <div class="form-group">
      <label for="c-resp">Responsable</label>
      <select id="c-resp" name="responsable_id">
        <option value="">—</option>
        <?php foreach ($admins as $a): ?>
          <option value="<?= $a['id'] ?>" <?= (int) $client['responsable_id'] === (int) $a['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($a['prenom'] . ' ' . $a['nom']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" id="bloc-motif" <?= $client['statut'] === 'perdu' ? '' : 'hidden' ?>>
      <label for="c-motif">Motif de la perte</label>
      <input id="c-motif" type="text" name="motif_perte" value="<?= htmlspecialchars($client['motif_perte'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label for="c-notes">Notes</label>
      <textarea id="c-notes" name="notes" rows="3"><?= htmlspecialchars($client['notes'] ?? '') ?></textarea>
    </div>

    <?php if ($client['etablissement_id']): ?>
      <div style="background:var(--surface-2);border-radius:var(--radius-sm);padding:12px 14px;font-size:var(--fs-2);color:var(--gris-fonce);margin-bottom:16px;">
        Compte partenaire rattaché : <strong><?= htmlspecialchars($client['etab_nom']) ?></strong>
        <?= $client['compte_email'] ? '<br>' . htmlspecialchars($client['compte_email']) : '' ?>
      </div>
    <?php endif; ?>

    <button type="submit" name="enregistrer" value="1" class="btn btn-primary btn-full">Enregistrer</button>
  </form>

</div>

<script>
  // L'offre propose son tarif catalogue, sans jamais l'imposer : un fondateur
  // gelé à 39 € ne doit pas repasser à 79 € parce qu'on a rouvert sa fiche.
  (function () {
    const offre = document.getElementById('c-offre');
    const mrr   = document.getElementById('c-mrr');
    const statut = document.getElementById('c-statut');
    const motif  = document.getElementById('bloc-motif');
    if (offre && mrr) {
      const tarifs = JSON.parse(offre.dataset.tarifs || '{}');
      offre.addEventListener('change', () => {
        if (Number(mrr.value) === 0) mrr.value = tarifs[offre.value] ?? 0;
      });
    }
    if (statut && motif) {
      statut.addEventListener('change', () => { motif.hidden = statut.value !== 'perdu'; });
    }
  })();
</script>

<?php adminFooter(); ?>
