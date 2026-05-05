<?php
require_once __DIR__ . '/includes/auth_check.php';
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

$typeColors = [
    'running' => ['bg'=>'#FFFFFF','text'=>'#1A1A1A','btn_bg'=>'#1A1A1A','btn_text'=>'#FFFFFF'],
    'velo'    => ['bg'=>'#2929E8','text'=>'#FFFFFF','btn_bg'=>'#FFFFFF','btn_text'=>'#2929E8'],
    'muscu'   => ['bg'=>'#C8E52A','text'=>'#1A1A1A','btn_bg'=>'#1A1A1A','btn_text'=>'#FFFFFF'],
    'autre'   => ['bg'=>'#F07820','text'=>'#FFFFFF','btn_bg'=>'#FFFFFF','btn_text'=>'#F07820'],
];
$typeLabels = ['running'=>'Running','velo'=>'Vélo','muscu'=>'Muscu','autre'=>'Autre'];
$niveauLabels = ['tous'=>'Tous niveaux','debutant'=>'Débutant','inter'=>'Inter.','avance'=>'Avancé'];

$dayFr = ['Sun'=>'Dim','Mon'=>'Lun','Tue'=>'Mar','Wed'=>'Mer','Thu'=>'Jeu','Fri'=>'Ven','Sat'=>'Sam'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StudentLink — Squads</title>
<?= themeBootScript() ?>
<link rel="stylesheet" href="<?= baseUrl() ?>/assets/css/style.css">
<link rel="icon" type="image/png" href="/Logo.png">
<link rel="apple-touch-icon" href="/Logo.png">
<link rel="manifest" href="/manifest.json">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="StudentLink">
</head>
<body>
<div class="app-shell">

  <!-- Header -->
  <div class="page-header">
    <div class="logo" style="margin-bottom:20px;">
      <svg class="logo-icon" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="15" y="20" width="50" height="30" rx="15" stroke="var(--noir)" stroke-width="10"/>
        <rect x="35" y="50" width="50" height="30" rx="15" class="accent" stroke-width="10"/>
        <circle cx="50" cy="50" r="6" fill="var(--noir)"/>
      </svg>
      StudentLink <em>/ Squads</em>
    </div>
    <div class="display" style="font-size:2.6rem;">Ne cours</div>
    <div class="display" style="font-size:2.6rem;">plus</div>
    <div class="display-italic" style="font-size:2.6rem;">seul·e.</div>
  </div>

  <!-- Filters -->
  <div class="filter-scroll">
    <button class="pill active" data-filter="all">Tout</button>
    <button class="pill" data-filter="running">Running</button>
    <button class="pill" data-filter="velo">Vélo</button>
    <button class="pill" data-filter="muscu">Muscu</button>
    <button class="pill" data-filter="autre">Autre</button>
  </div>

  <div class="page-content">
    <!-- Suggestion banner -->
    <div class="banner-card" style="background:var(--lime);margin-bottom:16px;">
      <div class="banner-icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path></svg>
      </div>
      <div class="banner-text">
        <strong>3 squads pour ton niveau</strong><br>
        <span style="font-size:12px;opacity:0.7;">Running inter. · &lt; 5 min à pied</span>
      </div>
    </div>

    <!-- Create squad button -->
    <button class="btn btn-outline btn-full mb-16" data-modal-open="modal-create-squad">
      + Créer un squad
    </button>

    <?php if (empty($squads)): ?>
      <div style="text-align:center;padding:48px 0;color:var(--gris);">
        <div style="font-size:2rem;margin-bottom:12px;display:flex;justify-content:center;">
          <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path></svg>
        </div>
        <div style="font-weight:600;">Pas encore de squads.</div>
        <div style="font-size:13px;margin-top:6px;">Crée le premier !</div>
      </div>
    <?php endif; ?>

    <?php foreach ($squads as $s):
      $colors  = $typeColors[$s['type']] ?? $typeColors['autre'];
      $isFull  = $s['nb_membres'] >= $s['quota'];
      $dateStr = ($dayFr[date('D', strtotime($s['date_heure']))] ?? '') . ' ' . date('H\hi', strtotime($s['date_heure']));
    ?>
    <div class="squad-card" style="background:<?= $colors['bg'] ?>;color:<?= $colors['text'] ?>;" data-type="<?= $s['type'] ?>">
      <div class="squad-badge" style="color:<?= $colors['text'] ?>;">
        <?= htmlspecialchars($niveauLabels[$s['niveau']] ?? $s['niveau']) ?>
      </div>

      <div class="squad-type-label"><?= strtoupper($typeLabels[$s['type']] ?? $s['type']) ?> · <?= $dateStr ?></div>
      <div class="squad-title"><?= htmlspecialchars($s['titre']) ?></div>
      <?php if ($s['lieu']): ?>
      <div class="squad-details"><?= htmlspecialchars($s['lieu']) ?></div>
      <?php endif; ?>
      <?php if ($s['description']): ?>
      <div class="squad-details" style="margin-bottom:12px;"><?= htmlspecialchars(mb_substr($s['description'], 0, 80)) ?>…</div>
      <?php endif; ?>

      <div class="squad-footer">
        <div style="display:flex;align-items:center;gap:6px;">
          <div class="avatar-stack">
            <?php 
            $initials = $s['membres_initials'] ?? '';
            $displayCount = min(mb_strlen($initials), 3);
            for ($i = 0; $i < $displayCount; $i++): 
            ?>
              <div class="avatar" style="color:<?= $colors['text'] ?>;border-color:<?= $colors['text'] ?>;">
                <?= strtoupper(mb_substr($initials, $i, 1)) ?>
              </div>
            <?php endfor; ?>
          </div>
          <div class="squad-count"><?= $s['nb_membres'] ?>/<?= $s['quota'] ?></div>
        </div>

        <?php if ($s['createur_id'] == $user['id']): ?>
          <button class="squad-cta btn-manage-squad" style="background:#F07820;color:var(--blanc);display:flex;align-items:center;gap:4px;" data-id="<?= $s['id'] ?>">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
            Gérer
          </button>
        <?php elseif ($s['deja_membre']): ?>
          <button class="squad-cta" style="background:<?= $colors['btn_bg'] ?>;color:<?= $colors['btn_text'] ?>;" disabled>✓ Rejoint</button>
        <?php elseif ($isFull): ?>
          <button class="squad-cta" style="background:rgba(0,0,0,0.15);color:<?= $colors['text'] ?>;" disabled>Complet</button>
        <?php else: ?>
          <button class="squad-cta btn-join-squad"
                  style="background:<?= $colors['btn_bg'] ?>;color:<?= $colors['btn_text'] ?>;"
                  data-squad-id="<?= $s['id'] ?>"
                  data-quota="<?= $s['quota'] ?>">
            JE REJOINS
          </button>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

</div><!-- .app-shell -->

<!-- Manage Squad Modal -->
<div class="modal-overlay" id="modal-manage-squad">
  <div class="modal-sheet">
    <div class="modal-handle"></div>
    <div style="font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:900;margin-bottom:20px;">
      Gérer mon Squad
    </div>
    
    <div id="manage-squad-loading" style="text-align:center;padding:20px;">Chargement...</div>
    
    <div id="manage-squad-content" style="display:none;">
      <h4 style="margin-bottom:12px;">Participants inscrits :</h4>
      <div id="squad-members-list" style="display:flex;flex-direction:column;gap:8px;margin-bottom:24px;">
        <!-- Injected via JS -->
      </div>
      
      <div class="section-divider"></div>
      <button class="btn btn-primary btn-full mt-16 btn-delete-squad-from-modal" style="background:var(--rouge);color:var(--blanc);" data-id="">Supprimer définitivement le Squad</button>
      <button type="button" class="btn btn-outline btn-full mt-8" data-modal-close>Fermer</button>
    </div>
  </div>
</div>

<!-- Create Squad Modal -->
<div class="modal-overlay" id="modal-create-squad">
  <div class="modal-sheet">
    <div class="modal-handle"></div>
    <div style="font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:900;margin-bottom:20px;">
      Créer un squad
    </div>

    <form id="create-squad-form">
      <div class="form-group">
        <label>Titre</label>
        <input type="text" name="titre" placeholder="Sortie Puy-de-Dôme" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Sport</label>
          <select name="type">
            <option value="running">Running</option>
            <option value="velo">Vélo</option>
            <option value="muscu">Muscu</option>
            <option value="autre">Autre</option>
          </select>
        </div>
        <div class="form-group">
          <label>Niveau</label>
          <select name="niveau">
            <option value="tous">Tous</option>
            <option value="debutant">Débutant</option>
            <option value="inter">Inter.</option>
            <option value="avance">Avancé</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Date & heure</label>
          <input type="datetime-local" name="date_heure" required>
        </div>
        <div class="form-group">
          <label>Max participants</label>
          <input type="number" name="quota" value="10" min="2" max="50">
        </div>
      </div>
      <div class="form-group">
        <label>Lieu de rendez-vous</label>
        <input type="text" name="lieu" placeholder="Parking Royat">
      </div>
      <div class="form-group">
        <label>Description</label>
        <textarea name="description" placeholder="Détails sur la sortie..."></textarea>
      </div>
      <button type="submit" class="btn btn-primary btn-full">Créer le squad</button>
      <button type="button" class="btn btn-outline btn-full mt-8" data-modal-close>Annuler</button>
    </form>
  </div>
</div>

<!-- Bottom Nav -->
<nav class="bottom-nav">
  <a href="/explore.php" class="nav-item">
    <span class="nav-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon></svg></span>
    <span>Explore</span>
  </a>
  <a href="/squads.php" class="nav-item active">
    <span class="nav-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg></span>
    <span>Squads</span>
  </a>
  <a href="/wallet.php" class="nav-item">
    <span class="nav-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg></span>
    <span>Wallet</span>
  </a>
  <a href="/profil.php" class="nav-item">
    <span class="nav-icon"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg></span>
    <span>Moi</span>
  </a>
</nav>

<div class="toast" id="toast"></div>

<script src="<?= baseUrl() ?>/assets/js/app.js"></script>
</body>
</html>
