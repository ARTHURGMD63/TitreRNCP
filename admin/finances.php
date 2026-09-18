<?php
/**
 * Finances.
 *
 * Deux chiffres qu'il ne faut jamais confondre, et c'est toute la raison
 * d'être de cette page :
 *   — le MRR, somme des abonnements facturés, qui dit ce que vaut le parc ;
 *   — la trésorerie, capital plus encaissements moins décaissements, qui dit
 *     ce qu'il y a réellement en banque.
 * Une facture émise gonfle le premier et pas le second. SL-13 fixe un
 * plancher de trésorerie à 1 000 € : une règle qu'on ne peut pas vérifier
 * est une règle inutile, d'où ce registre.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin_layout.php';
requireAdmin();

$moi     = currentUser();
$erreurs = [];

// ─── Écritures ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();

    if (isset($_POST['ajouter'])) {
        $sens      = ($_POST['sens'] ?? 'depense') === 'recette' ? 'recette' : 'depense';
        $categories = financeCategories()[$sens];
        $categorie = array_key_exists($_POST['categorie'] ?? '', $categories)
            ? $_POST['categorie']
            : array_key_first($categories);
        $libelle = trim($_POST['libelle'] ?? '');
        $montant = (float) str_replace(',', '.', $_POST['montant_ht'] ?? '0');
        $date    = trim($_POST['date_mouvement'] ?? '') ?: date('Y-m-d');

        if ($libelle === '')  $erreurs[] = 'Le libellé est obligatoire.';
        if ($montant <= 0)    $erreurs[] = 'Le montant doit être supérieur à zéro.';
        if (!strtotime($date)) $erreurs[] = 'Date invalide.';

        if (!$erreurs) {
            $pdo->prepare(
                "INSERT INTO finance_mouvements
                    (sens, categorie, client_id, libelle, montant_ht, tva_taux, date_mouvement, statut, moyen, note, cree_par)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?)"
            )->execute([
                $sens, $categorie,
                (int) ($_POST['client_id'] ?? 0) ?: null,
                $libelle, $montant,
                (float) str_replace(',', '.', $_POST['tva_taux'] ?? '20'),
                date('Y-m-d', strtotime($date)),
                ($_POST['statut'] ?? 'regle') === 'prevu' ? 'prevu' : 'regle',
                trim($_POST['moyen'] ?? '') ?: null,
                trim($_POST['note'] ?? '') ?: null,
                $moi['id'],
            ]);
            header('Location: ' . baseUrl('/admin/finances.php?ok=1'));
            exit;
        }
    }

    if (isset($_POST['marquer_regle'])) {
        $pdo->prepare("UPDATE finance_mouvements SET statut = 'regle' WHERE id = ?")
            ->execute([(int) $_POST['marquer_regle']]);
        header('Location: ' . baseUrl('/admin/finances.php'));
        exit;
    }

    if (isset($_POST['supprimer'])) {
        $pdo->prepare("DELETE FROM finance_mouvements WHERE id = ?")->execute([(int) $_POST['supprimer']]);
        header('Location: ' . baseUrl('/admin/finances.php'));
        exit;
    }

    // Les charges de structure sont les mêmes tous les mois (SL-13 §2). Les
    // ressaisir à la main garantit qu'on finira par en oublier une, et une
    // charge oubliée fausse la trésorerie dans le sens rassurant.
    if (isset($_POST['reporter'])) {
        $moisPrecedent = date('Y-m', strtotime('first day of last month'));
        $moisCible     = date('Y-m');
        $stmt = $pdo->prepare(
            "SELECT categorie, libelle, montant_ht, tva_taux, moyen
               FROM finance_mouvements
              WHERE sens = 'depense'
                AND DATE_FORMAT(date_mouvement, '%Y-%m') = ?"
        );
        $stmt->execute([$moisPrecedent]);
        $lignes = $stmt->fetchAll();

        $insert = $pdo->prepare(
            "INSERT INTO finance_mouvements
                (sens, categorie, libelle, montant_ht, tva_taux, date_mouvement, statut, moyen, cree_par)
             VALUES ('depense',?,?,?,?,?, 'prevu', ?, ?)"
        );
        $existe = $pdo->prepare(
            "SELECT COUNT(*) FROM finance_mouvements
              WHERE sens = 'depense' AND libelle = ?
                AND DATE_FORMAT(date_mouvement, '%Y-%m') = ?"
        );

        $reportees = 0;
        foreach ($lignes as $l) {
            // Relancer le report ne doit pas dupliquer : on saute ce qui
            // porte déjà le même libellé sur le mois cible.
            $existe->execute([$l['libelle'], $moisCible]);
            if ($existe->fetchColumn()) continue;

            $insert->execute([
                $l['categorie'], $l['libelle'], $l['montant_ht'], $l['tva_taux'],
                $moisCible . '-' . date('d'), $l['moyen'], $moi['id'],
            ]);
            $reportees++;
        }
        header('Location: ' . baseUrl('/admin/finances.php?reportees=' . $reportees));
        exit;
    }
}

// ─── Lecture ────────────────────────────────────────────────────────────────
$mrr        = crmMrr($pdo);
$payants    = crmClientsPayants($pdo);
$solde      = financeSolde($pdo);
$pointMort  = financePointMort($pdo);
$autonomie  = financeAutonomieMois($pdo);
$serie      = financeSerieMensuelle($pdo, 12);
$moisCourant = financeMois($pdo, date('Y-m'));

$fMois = preg_match('/^\d{4}-\d{2}$/', $_GET['mois'] ?? '') ? $_GET['mois'] : '';
$fSens = in_array($_GET['sens'] ?? '', ['recette', 'depense'], true) ? $_GET['sens'] : '';

$sql = "SELECT m.*, c.nom AS client_nom, u.prenom AS auteur
          FROM finance_mouvements m
          LEFT JOIN crm_clients c ON c.id = m.client_id
          LEFT JOIN users u ON u.id = m.cree_par
         WHERE 1 = 1";
$params = [];
if ($fMois) { $sql .= " AND DATE_FORMAT(m.date_mouvement, '%Y-%m') = ?"; $params[] = $fMois; }
if ($fSens) { $sql .= " AND m.sens = ?"; $params[] = $fSens; }
$sql .= " ORDER BY m.date_mouvement DESC, m.id DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$mouvements = $stmt->fetchAll();

// Récapitulatif par catégorie sur le mois affiché (ou le mois courant).
$moisRecap = $fMois ?: date('Y-m');
$stmtR = $pdo->prepare(
    "SELECT sens, categorie, SUM(montant_ht) AS total
       FROM finance_mouvements
      WHERE DATE_FORMAT(date_mouvement, '%Y-%m') = ? AND statut = 'regle'
      GROUP BY sens, categorie
      ORDER BY total DESC"
);
$stmtR->execute([$moisRecap]);
$recap = $stmtR->fetchAll();

$clients = $pdo->query("SELECT id, nom FROM crm_clients ORDER BY nom")->fetchAll();

// Graphique : hauteurs relatives au plus gros mois de la série.
$maxSerie = 0.0;
foreach ($serie as $m) $maxSerie = max($maxSerie, $m['recettes'], $m['depenses']);
$maxSerie = max($maxSerie, 1);

adminHeader($pdo, 'finances', 'Finances', 'Trésorerie, abonnements et registre');
?>

<?php if (isset($_GET['ok'])): ?>
  <div class="form-success" style="margin-bottom:20px;">Mouvement enregistré.</div>
<?php endif; ?>
<?php if (isset($_GET['reportees'])): ?>
  <div class="form-success" style="margin-bottom:20px;">
    <?php $n = (int) $_GET['reportees']; ?><?= $n ?> <?= pluriel($n, 'charge') ?> <?= pluriel($n, 'reportée') ?> sur ce mois, en prévisionnel.
  </div>
<?php endif; ?>
<?php if ($erreurs): ?>
  <ul class="form-errors" style="margin-bottom:20px;">
    <?php foreach ($erreurs as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
  </ul>
<?php endif; ?>

<div class="admin-tuiles">
  <?php
    adminTuile('Trésorerie', eur($solde), 'plancher ' . eur(FINANCE_PLANCHER),
               $solde < FINANCE_PLANCHER ? 'danger' : 'succes');
    adminTuile('MRR', eur($mrr), $payants . ' ' . pluriel($payants, 'client') . ' ' . pluriel($payants, 'payant'), 'marque');
    adminTuile('Résultat du mois', eur($moisCourant['resultat']),
               eur($moisCourant['recettes']) . ' − ' . eur($moisCourant['depenses']),
               $moisCourant['resultat'] >= 0 ? 'succes' : 'alerte');
    adminTuile('Point mort',
               $pointMort['atteint'] ? 'Atteint' : $pointMort['clients_requis'] . ' ' . pluriel($pointMort['clients_requis'], 'client'),
               'à ' . eur($pointMort['panier_moyen']) . ' pour ' . eur(FINANCE_CHARGES_MENSUELLES) . ' de charges',
               $pointMort['atteint'] ? 'succes' : 'alerte');
    adminTuile('Autonomie',
               $autonomie === null ? '∞' : number_format($autonomie, 1, ',', ' ') . ' mois',
               $autonomie === null ? 'aucune consommation nette' : 'au rythme des 3 derniers mois',
               $autonomie !== null && $autonomie < 3 ? 'danger' : 'neutre');
  ?>
</div>

<!-- Graphique 12 mois -->
<section class="admin-carte" style="margin-bottom:26px;" aria-labelledby="titre-graph">
  <div style="display:flex;align-items:baseline;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
    <h2 class="admin-titre" style="margin-bottom:0;" id="titre-graph">12 derniers mois</h2>
    <p class="admin-legende">
      <span><i class="admin-graph-recette"></i>Recettes</span>
      <span><i class="admin-graph-depense"></i>Dépenses</span>
    </p>
  </div>

  <?php if ($maxSerie <= 1): ?>
    <p style="color:var(--gris);font-size:var(--fs-3);">
      Aucun mouvement enregistré. Le graphique se remplira au fur et à mesure des saisies.
    </p>
  <?php else: ?>
    <!-- Les barres se partagent la largeur : une largeur minimale fixe
         poussait le mois courant hors du cadre, et le graphique s'ouvrait
         donc vide sur la seule colonne qui compte. -->
    <div class="admin-graph" aria-hidden="true">
      <?php foreach ($serie as $mois => $m):
        // Un montant non nul garde au moins deux pixels : une barre invisible
        // se lit comme une absence de données, pas comme un petit montant.
        $hR = $m['recettes'] > 0 ? max(2, (int) round($m['recettes'] / $maxSerie * 140)) : 0;
        $hD = $m['depenses'] > 0 ? max(2, (int) round($m['depenses'] / $maxSerie * 140)) : 0;
        $courant = $mois === date('Y-m');
      ?>
        <div class="admin-graph-mois<?= $courant ? ' est-courant' : '' ?>">
          <span class="admin-graph-barres"
                title="<?= htmlspecialchars(dateFr($mois . '-01', 'M Y')) ?> · recettes <?= htmlspecialchars(eur($m['recettes'])) ?> · dépenses <?= htmlspecialchars(eur($m['depenses'])) ?>">
            <span class="admin-graph-recette" style="height:<?= $hR ?>px;"></span>
            <span class="admin-graph-depense" style="height:<?= $hD ?>px;"></span>
          </span>
          <small><?= dateFr($mois . '-01', 'M') ?></small>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Le même contenu sous forme de tableau, pour qui n'a pas accès au
         dessin : un graphique sans équivalent textuel n'est pas une
         information, c'est une image. -->
    <table class="sr-only">
      <caption>Recettes et dépenses encaissées des 12 derniers mois</caption>
      <thead>
        <tr><th scope="col">Mois</th><th scope="col">Recettes</th><th scope="col">Dépenses</th></tr>
      </thead>
      <tbody>
        <?php foreach ($serie as $mois => $m): ?>
          <tr>
            <th scope="row"><?= dateFr($mois . '-01', 'M Y') ?></th>
            <td><?= htmlspecialchars(eur($m['recettes'])) ?></td>
            <td><?= htmlspecialchars(eur($m['depenses'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>

<div class="admin-2col">

  <!-- Registre -->
  <div style="min-width:0;">
    <form method="GET" class="admin-filtres">
      <div class="admin-filtre">
        <label for="f-mois">Mois</label>
        <input id="f-mois" type="month" name="mois" value="<?= htmlspecialchars($fMois) ?>"
               >
      </div>
      <div class="admin-filtre">
        <label for="f-sens">Sens</label>
        <select id="f-sens" name="sens">
          <option value="">Tous</option>
          <option value="recette" <?= $fSens === 'recette' ? 'selected' : '' ?>>Recettes</option>
          <option value="depense" <?= $fSens === 'depense' ? 'selected' : '' ?>>Dépenses</option>
        </select>
      </div>
      <button type="submit" class="btn btn-primary" style="padding:11px 22px;">Filtrer</button>
      <?php if ($fMois || $fSens): ?>
        <a href="<?= baseUrl('/admin/finances.php') ?>" class="admin-lien-discret">Réinitialiser</a>
      <?php endif; ?>
    </form>

    <div class="admin-tableau">
      <div class="admin-tableau-defilant">
      <table class="events-table">
        <thead>
          <tr>
            <th scope="col">Date</th>
            <th scope="col">Libellé</th>
            <th scope="col">Catégorie</th>
            <th scope="col">Montant HT</th>
            <th scope="col">Statut</th>
            <th scope="col"><span class="sr-only">Actions</span></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($mouvements)): ?>
            <tr><td colspan="6" class="admin-vide">
              Aucun mouvement. Saisis la première ligne dans le formulaire à droite.
            </td></tr>
          <?php endif; ?>
          <?php foreach ($mouvements as $m):
            $recette = $m['sens'] === 'recette';
          ?>
          <tr>
            <td data-label="Date" style="white-space:nowrap;font-size:var(--fs-2);color:var(--gris-fonce);">
              <?= dateFr($m['date_mouvement'], 'j M Y') ?>
            </td>
            <td data-label="Libellé">
              <div style="font-weight:var(--fw-semibold);"><?= htmlspecialchars($m['libelle']) ?></div>
              <?php if ($m['client_nom']): ?>
                <div style="font-size:var(--fs-1);color:var(--gris);"><?= htmlspecialchars($m['client_nom']) ?></div>
              <?php endif; ?>
            </td>
            <td data-label="Catégorie" style="font-size:var(--fs-2);color:var(--gris-fonce);">
              <?= htmlspecialchars(financeLibelleCategorie($m['categorie'])) ?>
            </td>
            <td data-label="Montant HT" style="font-weight:var(--fw-bold);white-space:nowrap;
                color:<?= $recette ? 'var(--succes)' : 'var(--danger)' ?>;">
              <?= ($recette ? '+' : '−') . eur((float) $m['montant_ht'], 2) ?>
            </td>
            <td data-label="Statut">
              <?php if ($m['statut'] === 'prevu'): ?>
                <?= adminPastille('Prévu', 'var(--alerte)') ?>
              <?php else: ?>
                <?= adminPastille('Réglé', 'var(--succes)') ?>
              <?php endif; ?>
            </td>
            <td data-label="Actions" style="white-space:nowrap;">
              <?php if ($m['statut'] === 'prevu'): ?>
                <form method="POST" style="display:inline;">
                  <?= csrfField() ?>
                  <button type="submit" name="marquer_regle" value="<?= $m['id'] ?>"
                          style="background:none;border:1px solid var(--succes);color:var(--succes);border-radius:var(--radius-pill);
                                 font-size:var(--fs-1);font-weight:var(--fw-bold);padding:4px 12px;cursor:pointer;">Régler</button>
                </form>
              <?php endif; ?>
              <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer cette ligne ?')">
                <?= csrfField() ?>
                <button type="submit" name="supprimer" value="<?= $m['id'] ?>" class="btn-icon danger" title="Supprimer" aria-label="Supprimer">
                  <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                </button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>

    <?php if ($recap): ?>
      <section class="admin-carte" style="margin-top:18px;">
        <h2 class="admin-titre">
          Par catégorie — <?= dateFr($moisRecap . '-01', 'M Y') ?>
        </h2>
        <?php foreach ($recap as $r): ?>
          <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:8px 0;border-bottom:1px solid var(--gris-clair);">
            <span style="font-size:var(--fs-3);"><?= htmlspecialchars(financeLibelleCategorie($r['categorie'])) ?></span>
            <span style="font-weight:var(--fw-bold);white-space:nowrap;
                         color:<?= $r['sens'] === 'recette' ? 'var(--succes)' : 'var(--danger)' ?>;">
              <?= ($r['sens'] === 'recette' ? '+' : '−') . eur((float) $r['total'], 2) ?>
            </span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- Saisie -->
  <div class="admin-colle" style="position:sticky;top:24px;display:flex;flex-direction:column;gap:18px;">
    <form method="POST" style="background:var(--blanc);border:1px solid var(--line-2);border-radius:var(--radius);padding:22px;">
      <?= csrfField() ?>
      <h2 class="admin-titre">Nouveau mouvement</h2>

      <div class="form-group">
        <label for="m-sens">Sens</label>
        <select id="m-sens" name="sens">
          <option value="depense">Dépense</option>
          <option value="recette">Recette</option>
        </select>
      </div>
      <div class="form-group">
        <label for="m-categorie">Catégorie</label>
        <select id="m-categorie" name="categorie">
          <?php foreach (financeCategories() as $sens => $cats): ?>
            <?php foreach ($cats as $code => $lib): ?>
              <option value="<?= $code ?>" data-sens="<?= $sens ?>"><?= $lib ?></option>
            <?php endforeach; ?>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="m-libelle">Libellé *</label>
        <input id="m-libelle" type="text" name="libelle" required placeholder="Abonnement octobre — Le Bec qui Pique">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="m-montant">Montant HT *</label>
          <input id="m-montant" type="number" name="montant_ht" min="0" step="0.01" required placeholder="79">
        </div>
        <div class="form-group">
          <label for="m-tva">TVA (%)</label>
          <input id="m-tva" type="number" name="tva_taux" min="0" max="100" step="0.1" value="20">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="m-date">Date</label>
          <input id="m-date" type="date" name="date_mouvement" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group">
          <label for="m-statut">Statut</label>
          <select id="m-statut" name="statut">
            <option value="regle">Réglé</option>
            <option value="prevu">Prévu</option>
          </select>
        </div>
      </div>
      <div class="form-group" id="bloc-client">
        <label for="m-client">Client</label>
        <select id="m-client" name="client_id">
          <option value="">—</option>
          <?php foreach ($clients as $c): ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="m-moyen">Moyen</label>
        <input id="m-moyen" type="text" name="moyen" placeholder="Virement, carte, prélèvement…">
      </div>
      <button type="submit" name="ajouter" value="1" class="btn btn-primary btn-full">Enregistrer</button>
    </form>

    <form method="POST" style="background:var(--surface-2);border:1px solid var(--line-2);border-radius:var(--radius);padding:18px 20px;">
      <?= csrfField() ?>
      <div style="font-weight:var(--fw-bold);font-size:var(--fs-4);margin-bottom:6px;">Charges récurrentes</div>
      <p style="font-size:var(--fs-2);color:var(--gris);margin-bottom:12px;">
        Recopie les dépenses du mois précédent sur ce mois, en prévisionnel. Les libellés déjà présents sont ignorés.
      </p>
      <button type="submit" name="reporter" value="1" class="btn btn-outline btn-full">Reporter le mois précédent</button>
    </form>

    <div class="admin-encart">
      <strong style="color:var(--noir);">Règles de dépense</strong><br>
      Chacun engage librement jusqu'à 150 € par mois dans son domaine. Au-delà de 500 €, ou pour tout engagement
      de plus de 12 mois, décision commune. Sous <?= eur(FINANCE_PLANCHER) ?> de trésorerie : arrêt de toute dépense
      non indispensable et décision commune sous 15 jours.
    </div>
  </div>

</div>

<script>
  // Les catégories suivent le sens : proposer « Hébergement » sous une recette
  // produirait des lignes incohérentes que plus personne ne saurait relire.
  (function () {
    const sens = document.getElementById('m-sens');
    const cat  = document.getElementById('m-categorie');
    const client = document.getElementById('bloc-client');
    if (!sens || !cat) return;

    function filtrer() {
      let premierVisible = null;
      [...cat.options].forEach(o => {
        const ok = o.dataset.sens === sens.value;
        o.hidden = !ok;
        o.disabled = !ok;
        if (ok && !premierVisible) premierVisible = o;
      });
      if (cat.selectedOptions[0]?.disabled && premierVisible) premierVisible.selected = true;
      // Une dépense n'est rattachée à aucun client : le champ n'a pas de sens.
      if (client) client.hidden = sens.value !== 'recette';
    }
    sens.addEventListener('change', filtrer);
    filtrer();
  })();
</script>

<?php adminFooter(); ?>
