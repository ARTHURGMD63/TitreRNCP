<?php
/**
 * Traitement des signalements.
 *
 * Les stores demandent qu'un signalement puisse être traité rapidement
 * (Apple, règle 1.2). Sans cet écran les lignes de user_reports
 * s'accumulaient sans que personne ne puisse les lire.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/uploads.php';
require_once __DIR__ . '/../includes/admin_layout.php';
requireAdmin();

$moi = currentUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['traiter'])) {
    csrfVerify();
    $pdo->prepare(
        "UPDATE user_reports SET statut='traite', traite_par=?, traite_le=NOW()
          WHERE id=? AND statut='nouveau'"
    )->execute([$moi['id'], (int) $_POST['traiter']]);
    header('Location: ' . baseUrl('/admin/moderation.php'));
    exit;
}

$filtre = ($_GET['statut'] ?? 'nouveau') === 'traite' ? 'traite' : 'nouveau';

$stmt = $pdo->prepare("
    SELECT r.*,
           s.prenom AS s_prenom, s.nom AS s_nom, s.photo AS s_photo,
           c.prenom AS c_prenom, c.nom AS c_nom, c.photo AS c_photo, c.id AS c_id,
           a.prenom AS a_prenom,
           (SELECT COUNT(*) FROM user_reports r2 WHERE r2.reported_id = r.reported_id) AS total_cible
    FROM user_reports r
    JOIN users s ON s.id = r.reporter_id
    JOIN users c ON c.id = r.reported_id
    LEFT JOIN users a ON a.id = r.traite_par
    WHERE r.statut = ?
    ORDER BY r.created_at DESC
");
$stmt->execute([$filtre]);
$signalements = $stmt->fetchAll();

$nbNouveaux = (int) $pdo->query("SELECT COUNT(*) FROM user_reports WHERE statut='nouveau'")->fetchColumn();

$libelles = [
    'harcelement'         => 'Harcèlement',
    'contenu_inapproprie' => 'Contenu inapproprié',
    'usurpation'          => "Usurpation d'identité",
    'spam'                => 'Spam',
    'autre'               => 'Autre',
];

adminHeader($pdo, 'moderation', 'Signalements',
    $nbNouveaux . ' signalement' . ($nbNouveaux > 1 ? 's' : '') . ' à traiter');
?>

<style>
  .signalement { background: var(--blanc); border: 1px solid var(--gris-clair);
                 border-radius: var(--radius); box-shadow: var(--shadow-sm);
                 padding: 20px; margin-bottom: 14px; }
  .ligne { display: flex; align-items: center; gap: 12px; }
  .separateur { width: 1px; align-self: stretch; background: var(--gris-clair); margin: 0 4px; }
  .recidive { background: var(--rouge-clair); color: var(--sur-rouge-clair);
              font-size: var(--fs-1); font-weight: var(--fw-bold); padding: 3px 10px;
              border-radius: var(--radius-pill); text-transform: uppercase;
              letter-spacing: var(--ls-wide); }
</style>

  <div class="filter-scroll" style="padding-left:0;padding-right:0;margin-bottom:18px;">
    <a class="pill <?= $filtre === 'nouveau' ? 'active' : '' ?>"
       href="<?= baseUrl('/admin/moderation.php?statut=nouveau') ?>">
      À traiter<?= $nbNouveaux ? ' · ' . $nbNouveaux : '' ?>
    </a>
    <a class="pill <?= $filtre === 'traite' ? 'active' : '' ?>"
       href="<?= baseUrl('/admin/moderation.php?statut=traite') ?>">Traités</a>
  </div>


  <?php if (empty($signalements)): ?>
    <div class="signalement" style="text-align:center;color:var(--gris);">
      <?= $filtre === 'nouveau' ? 'Aucun signalement en attente.' : 'Aucun signalement traité.' ?>
    </div>
  <?php endif; ?>

  <?php foreach ($signalements as $r): ?>
    <div class="signalement">
      <div class="ligne" style="justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <div class="ligne">
          <?= avatarHtml($r['c_photo'], $r['c_prenom'], 44, 'var(--rouge)') ?>
          <div>
            <div class="t-overline" style="color:var(--gris);">Personne signalée</div>
            <a href="<?= baseUrl('/view_profile.php?id=' . (int) $r['c_id']) ?>"
               style="font-weight:var(--fw-bold);font-size:var(--fs-4);color:var(--noir);text-decoration:none;">
              <?= htmlspecialchars($r['c_prenom'] . ' ' . $r['c_nom']) ?>
            </a>
          </div>
        </div>

        <div class="ligne" style="gap:8px;">
          <?php if ((int) $r['total_cible'] > 1): ?>
            <span class="recidive"><?= (int) $r['total_cible'] ?> signalements</span>
          <?php endif; ?>
          <span class="badge badge-boite"><?= htmlspecialchars($libelles[$r['motif']] ?? $r['motif']) ?></span>
        </div>
      </div>

      <?php if (!empty($r['details'])): ?>
        <div class="t-body" style="margin-top:14px;padding:12px 14px;background:var(--surface-2);border-radius:var(--radius-sm);">
          <?= nl2br(htmlspecialchars($r['details'])) ?>
        </div>
      <?php endif; ?>

      <div class="ligne" style="justify-content:space-between;margin-top:16px;padding-top:14px;border-top:1px solid var(--gris-clair);flex-wrap:wrap;gap:12px;">
        <div class="t-meta">
          Signalé par <?= htmlspecialchars($r['s_prenom'] . ' ' . mb_substr($r['s_nom'], 0, 1)) ?>.
          · <?= date('j M Y \à H\hi', strtotime($r['created_at'])) ?>
          <?php if ($filtre === 'traite' && $r['a_prenom']): ?>
            <br>Traité par <?= htmlspecialchars($r['a_prenom']) ?>
            le <?= date('j M Y', strtotime($r['traite_le'])) ?>
          <?php endif; ?>
        </div>

        <?php if ($filtre === 'nouveau'): ?>
          <form method="POST" style="margin:0;">
            <?= csrfField() ?>
            <input type="hidden" name="traiter" value="<?= (int) $r['id'] ?>">
            <button type="submit" class="btn btn-primary">Marquer comme traité</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>

<?php adminFooter(); ?>
