<?php
/**
 * Notifications en pleine page (maquette 15).
 *
 * Mêmes éléments que la feuille de la cloche du hub — notificationsEtudiant(),
 * rendus par notificationHtml() —, rangés par période. Ouvrir la page vaut
 * lecture (migration v17) : la pastille lave marque ce qui est arrivé depuis
 * la visite précédente, et disparaît à la suivante.
 * Accepter ou refuser une demande ou une invitation passe par les mêmes
 * boutons et les mêmes points d'API que dans la feuille.
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/page.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/notifications.php';
requireStudent();
$user = currentUser();
$uid  = (int) $user['id'];

$notifs = notificationsEtudiant($pdo, $uid);
$luesLe = notificationsLuesLe($pdo, $uid);
$luesTs = $luesLe !== null ? strtotime($luesLe) : null;

// Rangement : aujourd'hui, les sept derniers jours, puis le reste — les
// demandes et invitations en attente peuvent être anciennes.
$minuit  = strtotime('today');
$semaine = strtotime('-7 days', $minuit);
$sections = ["Aujourd'hui" => [], 'Cette semaine' => [], 'Plus tôt' => []];
foreach ($notifs['items'] as $n) {
    $ts = strtotime((string) $n['ts']) ?: 0;
    $cle = $ts >= $minuit ? "Aujourd'hui" : ($ts >= $semaine ? 'Cette semaine' : 'Plus tôt');
    $sections[$cle][] = ['n' => $n, 'nouvelle' => $luesTs === null || $ts > $luesTs];
}
// Les pastilles sont calculées sur la lecture précédente ; celle-ci est
// enregistrée pour la prochaine visite.
marquerNotificationsLues($pdo, $uid);
?>
<?php ob_start(); ?>
<style>
  .notifs-entete { display: flex; align-items: center; justify-content: space-between; gap: 14px; margin-bottom: 18px; }
  .notifs-section { margin-top: 22px; }
  .notifs-section .t-overline { margin-bottom: 10px; display: block; }
  /* Le point lave des notifications non lues (maquette 15). */
  .notif-conteneur { position: relative; }
  .notif-conteneur.est-nouvelle > .notif-item { border-color: var(--line-2); }
  .notif-conteneur.est-nouvelle::after {
    content: ''; position: absolute; top: 14px; right: 14px;
    width: 8px; height: 8px; border-radius: 50%; background: var(--rouge);
  }
</style>
<?php pageDebut('Linkee — Notifications', ['pwa' => true, 'tete' => ob_get_clean()]); ?>
<a href="#main-content" class="skip-nav">Aller au contenu principal</a>
<div class="app-shell">

  <div class="page-header">
    <div class="notifs-entete">
      <a href="<?= baseUrl('/explore.php') ?>" class="bouton-retour" aria-label="Retour au hub"><?= icon('fleche-g') ?></a>
    </div>
    <h1 class="titre-page">
      <div class="display" style="font-size:var(--fs-8);line-height:var(--lh-tight);">Notifications</div>
    </h1>
  </div>

  <main id="main-content" class="page-content" style="padding-top:0;">
    <?php foreach ($sections as $titre => $liste): if (!$liste) continue; ?>
      <section class="notifs-section" aria-label="<?= htmlspecialchars($titre) ?>">
        <span class="t-overline"><?= htmlspecialchars($titre) ?></span>
        <div class="notif-liste">
          <?php foreach ($liste as $e): ?>
            <div class="notif-conteneur<?= $e['nouvelle'] ? ' est-nouvelle' : '' ?>">
              <?php if ($e['nouvelle']): ?><span class="sr-only">Nouveau :</span><?php endif; ?>
              <?= notificationHtml($e['n']) ?>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>

    <?php if (!$notifs['items']): ?>
      <p class="notif-vide">
        Rien de neuf pour l'instant.
        <a href="<?= baseUrl('/explore.php?view=people') ?>">Suis des étudiants et des lieux →</a>
      </p>
    <?php endif; ?>
  </main>
</div>

<nav class="bottom-nav" aria-label="Navigation principale">
  <span class="nav-marque" aria-hidden="true"><?= marqueLinkee() ?></span>
  <a href="<?= baseUrl('/explore.php') ?>" class="nav-item active" aria-current="page">
    <span class="nav-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></span>
    <span>Explorer</span>
  </a>
  <a href="<?= baseUrl('/squads.php') ?>" class="nav-item">
    <span class="nav-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg></span>
    <span>Squads</span>
  </a>
  <a href="<?= baseUrl('/wallet.php') ?>" class="nav-item">
    <span class="nav-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg></span>
    <span>Pass</span>
  </a>
  <a href="<?= baseUrl('/profil.php') ?>" class="nav-item">
    <span class="nav-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></span>
    <span>Moi</span>
  </a>
</nav>

<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script src="<?= asset('/assets/js/app.js') ?>"></script>
</body>
</html>
