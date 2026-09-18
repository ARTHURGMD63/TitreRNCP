<?php
/**
 * Base étudiants.
 *
 * Volontairement séparée des clients : ce sont deux populations qui n'ont
 * ni le même cycle de vie, ni la même valeur, ni le même interlocuteur.
 * Les mélanger dans une seule liste « utilisateurs » aurait donné un
 * fichier où l'on ne peut répondre ni à « combien de clients payants »
 * ni à « combien d'étudiants actifs ».
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/admin_layout.php';
require_once __DIR__ . '/../includes/uploads.php';
requireAdmin();

$q       = trim($_GET['q'] ?? '');
$fEcole  = trim($_GET['ecole'] ?? '');
$fEtat   = $_GET['etat'] ?? '';          // actifs | dormants
$page    = max(1, (int) ($_GET['p'] ?? 1));
$parPage = 40;

$ecoles = $pdo->query(
    "SELECT DISTINCT ecole FROM users WHERE type='etudiant' AND ecole IS NOT NULL AND ecole <> '' ORDER BY ecole"
)->fetchAll(PDO::FETCH_COLUMN);

/*
 * Le filtre est construit une fois et sert deux requêtes : le comptage, qui
 * ne touche que `users`, et la page affichée, qui va chercher les agrégats.
 * Compter en enveloppant la requête complète (SELECT COUNT(*) FROM (…)) aurait
 * fait calculer six sous-requêtes par étudiant pour ne lire qu'un nombre.
 */
$where  = "u.type = 'etudiant'";
$params = [];

if ($q) {
    $where .= " AND (u.prenom LIKE ? OR u.nom LIKE ? OR u.email LIKE ? OR u.interests LIKE ?)";
    $terme = '%' . addcslashes($q, '%_') . '%';
    array_push($params, $terme, $terme, $terme, $terme);
}
if ($fEcole) { $where .= " AND u.ecole = ?"; $params[] = $fEcole; }

// « Actif » au sens de SL-07 : au moins une action récente. 30 jours, parce
// que le rythme de l'app est hebdomadaire et qu'un mois sans réservation est
// déjà un décrochage.
$recent = "EXISTS (SELECT 1 FROM inscriptions i
                    WHERE i.user_id = u.id AND i.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))";
if ($fEtat === 'actifs') {
    $where .= " AND $recent";
} elseif ($fEtat === 'dormants') {
    $where .= " AND NOT $recent";
}

$stmtN = $pdo->prepare("SELECT COUNT(*) FROM users u WHERE $where");
$stmtN->execute($params);
$total = (int) $stmtN->fetchColumn();

// `derniere_activite` est la date de la dernière inscription à un événement :
// c'est le seul signal d'usage que le schéma enregistre aujourd'hui.
$stmt = $pdo->prepare(
    "SELECT u.id, u.prenom, u.nom, u.email, u.ecole, u.promo, u.photo, u.interests, u.created_at,
            (SELECT COUNT(*) FROM inscriptions i WHERE i.user_id = u.id AND i.statut <> 'annule') AS sorties,
            (SELECT COUNT(*) FROM inscriptions i WHERE i.user_id = u.id AND i.statut = 'checkin') AS presences,
            (SELECT COUNT(*) FROM squad_membres sm WHERE sm.user_id = u.id) AS squads,
            (SELECT COALESCE(SUM(e.montant), 0) FROM economies e WHERE e.user_id = u.id) AS economies,
            (SELECT MAX(i.created_at) FROM inscriptions i WHERE i.user_id = u.id) AS derniere_activite
       FROM users u
      WHERE $where
      ORDER BY u.created_at DESC
      LIMIT " . (int) $parPage . " OFFSET " . (int) (($page - 1) * $parPage)
);
$stmt->execute($params);
$etudiants = $stmt->fetchAll();

// Repères globaux, indépendants des filtres.
$nbTotal   = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE type='etudiant'")->fetchColumn();
$nb7j      = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE type='etudiant' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$nbActifs  = (int) $pdo->query("SELECT COUNT(DISTINCT user_id) FROM inscriptions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();
$nbAvecInt = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE type='etudiant' AND interests IS NOT NULL AND interests <> ''")->fetchColumn();

$nbPages = max(1, (int) ceil($total / $parPage));

adminHeader($pdo, 'utilisateurs', 'Étudiants', $total . ' ' . pluriel($total, 'résultat') . ' sur ' . $nbTotal);
?>

<div class="admin-tuiles">
  <?php
    adminTuile('Comptes', (string) $nbTotal, '+' . $nb7j . ' sur 7 jours');
    adminTuile('Actifs', (string) $nbActifs, 'au moins une résa sur 30 j',
               $nbTotal > 0 && $nbActifs / $nbTotal < 0.2 ? 'alerte' : 'succes');
    adminTuile('Profil renseigné', $nbTotal > 0 ? round($nbAvecInt / $nbTotal * 100) . ' %' : '—',
               $nbAvecInt . ' avec centres d\'intérêt');
    adminTuile('Dormants', (string) ($nbTotal - $nbActifs), 'aucune résa depuis 30 j');
  ?>
</div>

<form method="GET" class="admin-filtres">
  <div class="admin-filtre est-large">
    <label for="u-q">Rechercher</label>
    <input id="u-q" type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Nom, e-mail, centre d'intérêt…"
           >
  </div>
  <div class="admin-filtre">
    <label for="u-ecole">École</label>
    <select id="u-ecole" name="ecole">
      <option value="">Toutes</option>
      <?php foreach ($ecoles as $e): ?>
        <option value="<?= htmlspecialchars($e) ?>" <?= $fEcole === $e ? 'selected' : '' ?>><?= htmlspecialchars($e) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="admin-filtre">
    <label for="u-etat">Activité</label>
    <select id="u-etat" name="etat">
      <option value="">Tous</option>
      <option value="actifs"   <?= $fEtat === 'actifs' ? 'selected' : '' ?>>Actifs (30 j)</option>
      <option value="dormants" <?= $fEtat === 'dormants' ? 'selected' : '' ?>>Dormants</option>
    </select>
  </div>
  <button type="submit" class="btn btn-primary" style="padding:11px 22px;">Filtrer</button>
  <?php if ($q || $fEcole || $fEtat): ?>
    <a href="<?= baseUrl('/admin/utilisateurs.php') ?>" class="admin-lien-discret">Réinitialiser</a>
  <?php endif; ?>
</form>

<div class="admin-tableau">
  <div class="admin-tableau-defilant">
  <table class="events-table">
    <thead>
      <tr>
        <th scope="col">Étudiant</th>
        <th scope="col">École</th>
        <th scope="col">Sorties</th>
        <th scope="col">Présences</th>
        <th scope="col">Squads</th>
        <th scope="col">Économies</th>
        <th scope="col">Dernière résa</th>
        <th scope="col">Inscrit le</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($etudiants)): ?>
        <tr><td colspan="8" class="admin-vide">Aucun étudiant ne correspond.</td></tr>
      <?php endif; ?>
      <?php foreach ($etudiants as $u):
        $interets = $u['interests'] ? array_slice(array_map('trim', explode(',', $u['interests'])), 0, 3) : [];
        $joursInactif = $u['derniere_activite'] ? (int) ((time() - strtotime($u['derniere_activite'])) / 86400) : null;
      ?>
      <tr>
        <td data-label="Étudiant" class="est-entete-carte">
          <div style="display:flex;align-items:center;gap:12px;">
            <?= avatarHtml($u['photo'] ?? null, $u['prenom'], 36) ?>
            <div style="min-width:0;">
              <a href="<?= baseUrl('/view_profile.php?id=' . $u['id']) ?>"
                 style="font-weight:var(--fw-bold);color:inherit;text-decoration:none;">
                <?= htmlspecialchars($u['prenom'] . ' ' . $u['nom']) ?>
              </a>
              <div style="font-size:var(--fs-1);color:var(--gris);"><?= htmlspecialchars($u['email']) ?></div>
              <?php if ($interets): ?>
                <div style="display:flex;gap:4px;flex-wrap:wrap;margin-top:4px;">
                  <?php foreach ($interets as $i): ?>
                    <span style="font-size:var(--fs-1);background:var(--bleu-clair);color:var(--sur-bleu-clair);
                                 padding:1px 8px;border-radius:var(--radius-pill);font-weight:var(--fw-bold);">#<?= htmlspecialchars($i) ?></span>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </td>
        <td data-label="École" style="font-size:var(--fs-2);color:var(--gris-fonce);white-space:nowrap;">
          <?= htmlspecialchars($u['ecole'] ?: '—') ?><?= $u['promo'] ? ' · ' . htmlspecialchars($u['promo']) : '' ?>
        </td>
        <td data-label="Sorties" style="font-weight:var(--fw-bold);"><?= (int) $u['sorties'] ?></td>
        <td data-label="Présences" style="font-weight:var(--fw-bold);color:var(--succes);"><?= (int) $u['presences'] ?></td>
        <td data-label="Squads"><?= (int) $u['squads'] ?></td>
        <td data-label="Économies" style="white-space:nowrap;"><?= eur((float) $u['economies']) ?></td>
        <td data-label="Dernière résa" style="font-size:var(--fs-2);white-space:nowrap;
            color:<?= $joursInactif !== null && $joursInactif > 30 ? 'var(--alerte)' : 'var(--gris-fonce)' ?>;">
          <?= $u['derniere_activite'] ? dateFr($u['derniere_activite'], 'j M Y') : 'jamais' ?>
        </td>
        <td data-label="Inscrit le" style="font-size:var(--fs-2);color:var(--gris);white-space:nowrap;">
          <?= dateFr($u['created_at'], 'j M Y') ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
</div>

<?php if ($nbPages > 1): ?>
  <div style="display:flex;gap:8px;align-items:center;justify-content:center;margin-top:20px;flex-wrap:wrap;">
    <?php
      $lien = function (int $p) {
          $params = $_GET; $params['p'] = $p;
          return baseUrl('/admin/utilisateurs.php?' . http_build_query($params));
      };
    ?>
    <?php if ($page > 1): ?>
      <a href="<?= htmlspecialchars($lien($page - 1)) ?>" class="pill">← Précédent</a>
    <?php endif; ?>
    <span style="font-size:var(--fs-2);color:var(--gris);">Page <?= $page ?> sur <?= $nbPages ?></span>
    <?php if ($page < $nbPages): ?>
      <a href="<?= htmlspecialchars($lien($page + 1)) ?>" class="pill">Suivant →</a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<?php adminFooter(); ?>
