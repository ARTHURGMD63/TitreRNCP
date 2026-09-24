<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/page.php';
require_once __DIR__ . '/includes/db.php';
requireStudent();
$user = currentUser();

// Fetch upcoming squads with member count
$stmt = $pdo->prepare("
    SELECT s.*,
           u.prenom AS createur_prenom, u.nom AS createur_nom,
           (SELECT COUNT(*) FROM squad_membres sm WHERE sm.squad_id = s.id) AS nb_membres,
           (SELECT COUNT(*) FROM squad_membres sm WHERE sm.squad_id = s.id AND sm.user_id = ?) AS deja_membre,
           (SELECT GROUP_CONCAT(LEFT(u2.prenom, 1) ORDER BY sm2.joined_at ASC SEPARATOR '') 
            FROM squad_membres sm2 
            JOIN users u2 ON u2.id = sm2.user_id 
            WHERE sm2.squad_id = s.id) AS membres_initials
    FROM squads s
    JOIN users u ON u.id = s.createur_id
    WHERE s.date_heure >= NOW()
    ORDER BY s.date_heure ASC
");
$stmt->execute([$user['id']]);
$squads = $stmt->fetchAll();

// Le type colore un rail, l'etiquette et le bouton — jamais le fond de la
// carte. Un aplat de marque derriere le gris des metadonnees tombait entre
// 1.6:1 et 2.4:1 (le blanc sur --orange ne donne que 2.42:1) : la carte reste
// donc sur la surface de l'app, ou --noir et --gris-fonce gardent leur
// contraste dans les deux themes. La couleur ne porte plus l'information
// seule non plus : le libelle du type reste ecrit en toutes lettres.
$typeClasses = [
    'running' => 'squad-running',
    'velo'    => 'squad-velo',
    'muscu'   => 'squad-muscu',
    'autre'   => 'squad-autre',
];
$typeLabels = ['running'=>'Running','velo'=>'Vélo','muscu'=>'Muscu','autre'=>'Autre'];
$niveauLabels = ['tous'=>'Tous niveaux','debutant'=>'Débutant','inter'=>'Inter.','avance'=>'Avancé'];

?>
<?php pageDebut('Linkee — Squads', ['pwa' => true]); ?>
<a href="#main-content" class="skip-nav">Aller au contenu principal</a>
<!-- univers-sport : les squads portent le dôme (charte), pas la lave. -->
<div class="app-shell univers-sport">

  <!-- Header -->
  <div class="page-header">
    <h1 class="titre-page">
      <div class="display" style="font-size:var(--fs-8);line-height:var(--lh-tight);">Ne cours plus</div>
      <div class="display-italic" style="font-size:var(--fs-8);line-height:var(--lh-tight);">seul·e.</div>
    </h1>
  </div>

  <!-- Filters -->
  <div class="filter-scroll" role="group" aria-label="Filtrer les squads par sport">
    <button type="button" class="pill active" data-filter="all" aria-pressed="true">Tout</button>
    <button type="button" class="pill" data-filter="running" aria-pressed="false">Running</button>
    <button type="button" class="pill" data-filter="velo" aria-pressed="false">Vélo</button>
    <button type="button" class="pill" data-filter="muscu" aria-pressed="false">Muscu</button>
    <button type="button" class="pill" data-filter="autre" aria-pressed="false">Autre</button>
  </div>

  <main id="main-content" class="page-content page-grid">
    <!-- Suggestion banner -->
    <div class="banner-card" style="background:var(--blanc);color:var(--noir);border:1px solid var(--gris-clair);margin-bottom:16px;">
      <div class="banner-icon" style="color:var(--sur-bleu-clair);">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path></svg>
      </div>
      <div class="banner-text">
        <strong>3 squads pour ton niveau</strong><br>
        <span style="font-size:var(--fs-3);color:var(--gris);">Running inter. · &lt; 5 min à pied</span>
      </div>
    </div>

    <!-- Create squad button -->
    <button class="btn btn-outline btn-full mb-16" data-modal-open="modal-create-squad">
      + Créer un squad
    </button>

    <?php if (empty($squads)): ?>
      <div style="text-align:center;padding:48px 0;color:var(--gris);">
        <div style="font-size:var(--fs-8);margin-bottom:12px;display:flex;justify-content:center;">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg>
        </div>
        <div style="font-weight:var(--fw-semibold);">Pas encore de squads.</div>
        <div style="font-size:var(--fs-3);margin-top:6px;">Crée le premier !</div>
      </div>
    <?php endif; ?>

    <?php foreach ($squads as $s):
      $classeType = $typeClasses[$s['type']] ?? $typeClasses['autre'];
      $isFull  = $s['nb_membres'] >= $s['quota'];
      $dateStr = dateFr($s['date_heure'], 'D H\hi');
    ?>
    <div class="squad-card <?= $classeType ?>" data-type="<?= $s['type'] ?>">
      <div class="squad-badge">
        <?= htmlspecialchars($niveauLabels[$s['niveau']] ?? $s['niveau']) ?>
      </div>

      <div class="squad-type-label"><?= mb_strtoupper($typeLabels[$s['type']] ?? $s['type']) ?> &middot; <?= $dateStr ?></div>
      <div class="squad-title"><?= htmlspecialchars($s['titre']) ?></div>
      <?php if ($s['lieu']): ?>
      <div class="squad-details"><?= htmlspecialchars($s['lieu']) ?></div>
      <?php endif; ?>
      <?php if ($s['description']): ?>
      <div class="squad-details" style="margin-bottom:12px;"><?= htmlspecialchars(mb_substr($s['description'], 0, 80)) ?>&hellip;</div>
      <?php endif; ?>

      <div class="squad-footer">
        <div style="display:flex;align-items:center;gap:6px;">
          <div class="avatar-stack">
            <?php 
            $initials = $s['membres_initials'] ?? '';
            $displayCount = min(mb_strlen($initials), 3);
            for ($i = 0; $i < $displayCount; $i++): 
            ?>
              <div class="avatar avatar-accent">
                <?= mb_strtoupper(mb_substr($initials, $i, 1)) ?>
              </div>
            <?php endfor; ?>
          </div>
          <div class="squad-count"><?= $s['nb_membres'] ?>/<?= $s['quota'] ?></div>
        </div>

        <?php if ($s['createur_id'] == $user['id']): ?>
          <button class="squad-cta squad-cta-gerer btn-manage-squad" data-id="<?= $s['id'] ?>">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
            G&eacute;rer
          </button>
        <?php elseif ($s['deja_membre']): ?>
          <button class="squad-cta squad-cta-etat" disabled><?= icon('check','icon-sm') ?> Rejoint</button>
        <?php elseif ($isFull): ?>
          <button class="squad-cta squad-cta-etat" disabled>Complet</button>
        <?php else: ?>
          <button class="squad-cta squad-cta-accent btn-join-squad"
                  data-squad-id="<?= $s['id'] ?>"
                  data-quota="<?= $s['quota'] ?>">
            Rejoindre
          </button>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <!-- Etat vide du filtre : sans lui, filtrer sur un sport sans squad
         laissait la page muette, ce qui se lit comme un bouton casse. -->
    <div id="filtre-vide" hidden style="text-align:center;padding:40px 0;color:var(--gris);">
      <div style="font-weight:var(--fw-semibold);">Aucun squad dans cette cat&eacute;gorie.</div>
      <div style="font-size:var(--fs-3);margin-top:6px;">Cr&eacute;e le premier, ou reviens &agrave; &laquo;&nbsp;Tout&nbsp;&raquo;.</div>
    </div>
  </main>

<!-- Manage Squad Modal -->
<div class="modal-overlay" id="modal-manage-squad">
  <div class="modal-sheet">
    <div class="modal-handle"></div>
    <div style="font-family:var(--font-display);font-size:var(--fs-7);font-weight:var(--fw-black);letter-spacing:var(--ls-display);margin-bottom:20px;">
      Gérer mon Squad
    </div>
    
    <div id="manage-squad-loading" style="text-align:center;padding:20px;">Chargement...</div>
    
    <div id="manage-squad-content" style="display:none;">
      <h2 class="t-overline" style="margin-bottom:12px;">Participants inscrits</h2>
      <div id="squad-members-list" style="display:flex;flex-direction:column;gap:8px;margin-bottom:24px;">
        <!-- Injected via JS -->
      </div>
      
      <div class="section-divider"></div>
      <button class="btn btn-primary btn-full mt-16 btn-delete-squad-from-modal" style="background:var(--alerte-vif);color:var(--sur-lave);" data-id="">Supprimer définitivement le Squad</button>
      <button type="button" class="btn btn-outline btn-full mt-8" data-modal-close>Fermer</button>
    </div>
  </div>
</div>

<!-- Create Squad Modal -->
<div class="modal-overlay" id="modal-create-squad">
  <div class="modal-sheet">
    <div class="modal-handle"></div>
    <div style="font-family:var(--font-display);font-size:var(--fs-7);font-weight:var(--fw-black);letter-spacing:var(--ls-display);margin-bottom:20px;">
      Créer un squad
    </div>

    <form id="create-squad-form">
      <div class="form-group">
        <label for="sq-titre">Titre</label>
        <input id="sq-titre" type="text" name="titre" placeholder="Sortie Puy-de-Dôme" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="sq-type">Sport</label>
          <select id="sq-type" name="type">
            <option value="running">Running</option>
            <option value="velo">Vélo</option>
            <option value="muscu">Muscu</option>
            <option value="autre">Autre</option>
          </select>
        </div>
        <div class="form-group">
          <label for="sq-niveau">Niveau</label>
          <select id="sq-niveau" name="niveau">
            <option value="tous">Tous</option>
            <option value="debutant">Débutant</option>
            <option value="inter">Inter.</option>
            <option value="avance">Avancé</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label for="sq-date">Date & heure</label>
          <input id="sq-date" type="datetime-local" name="date_heure" required>
        </div>
        <div class="form-group">
          <label for="sq-quota">Max participants</label>
          <input id="sq-quota" type="number" name="quota" value="10" min="2" max="50">
        </div>
      </div>
      <div class="form-group">
        <label for="sq-lieu">Lieu de rendez-vous</label>
        <input id="sq-lieu" type="text" name="lieu" placeholder="Parking Royat">
      </div>
      <div class="form-group">
        <label for="sq-desc">Description</label>
        <textarea id="sq-desc" name="description" placeholder="Détails sur la sortie..."></textarea>
      </div>
      <button type="submit" class="btn btn-primary btn-full">Créer le squad</button>
      <button type="button" class="btn btn-outline btn-full mt-8" data-modal-close>Annuler</button>
    </form>
  </div>
</div>

</div><!-- .app-shell -->

<!-- Bottom Nav -->
<nav class="bottom-nav" aria-label="Navigation principale">
  <span class="nav-marque" aria-hidden="true"><?= marqueLinkee() ?></span>
  <a href="<?= baseUrl('/explore.php') ?>" class="nav-item">
    <span class="nav-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></span>
    <span>Explorer</span>
  </a>
  <a href="<?= baseUrl('/squads.php') ?>" class="nav-item active" aria-current="page">
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
