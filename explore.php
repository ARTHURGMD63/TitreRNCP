<?php
/**
 * Le hub étudiant — le gabarit.
 *
 * Toute la construction des requêtes vit dans includes/hub.php. Ce fichier ne
 * fait plus que trois choses : lire les critères de l'URL, demander les
 * données, les afficher. Aucune requête ne part d'ici.
 *
 * Pourquoi ce découpage : à 1 051 lignes, avec dix requêtes mêlées au HTML,
 * ce fichier était le plus complexe de l'application et le seul qu'aucun test
 * ne pouvait atteindre — le charger exécutait aussi son affichage. Les
 * fonctions de includes/hub.php se testent, elles (tests/Unit/HubTest.php).
 */
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/page.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/hub.php';
require_once __DIR__ . '/includes/uploads.php';
require_once __DIR__ . '/includes/notifications.php';
require_once __DIR__ . '/includes/icons.php';
requireStudent();
$user = currentUser();
$uid  = (int) $user['id'];

// ── Ce que l'URL demande, ramené à des valeurs sûres ────────────────────────
$criteres = hubCriteres($_GET);
$view     = $criteres['vue'];
$filter   = $criteres['type'];
$musique  = $criteres['musique'];
$q        = $criteres['q'];
$page     = $criteres['page_profils'];
$pageE    = $criteres['page_evenements'];

// Valeurs par défaut du gabarit : il ne doit pas dépendre de la branche qui a
// créé — ou non — chaque variable.
$students          = [];
$suggestions       = [];
$myInterests       = [];
$allEcoles         = [];
$allInterests      = [];
$resteProfils      = false;
$resteEvenements   = false;
$interetsManquants = false;
$filterEcole       = $criteres['ecole'];
$filterInterest    = $criteres['interet'];
$filtreCommeMoi    = $criteres['comme_moi'];
$evenements        = [];
$friendsByEvent    = [];
$followedEtabIds   = [];

// ── L'annuaire ──────────────────────────────────────────────────────────────
if ($view === 'people') {
    $annuaire = hubAnnuaire($pdo, $user, $criteres);

    $students     = $annuaire['profils'];
    $suggestions  = $annuaire['suggestions'];
    $myInterests  = $annuaire['mes_interets'];
    $allEcoles    = $annuaire['ecoles'];
    $allInterests = $annuaire['catalogue'];
    $resteProfils = $annuaire['reste'];
    // Le filtre « comme moi » se retire tout seul quand aucun goût n'est
    // déclaré : on affiche l'état appliqué, pas celui demandé.
    $interetsManquants = $annuaire['interets_manquants'];
    $filtreCommeMoi    = $annuaire['comme_moi'];
    $filterInterest    = $annuaire['interet'];
}

// Les abonnements alimentent la fenêtre d'invitation, présente sur les deux vues.
$following = hubAbonnements($pdo, $uid);

// ── Le fil des soirées ──────────────────────────────────────────────────────
if ($view === 'events') {
    $followedEtabIds = hubEtablissementsSuivis($pdo, $uid);
    $friendsByEvent  = hubAmisParEvenement($pdo, $uid);

    $fil             = hubEvenements($pdo, $uid, $criteres, $followedEtabIds, $friendsByEvent);
    $evenements      = $fil['evenements'];
    $resteEvenements = $fil['reste'];
}

// La cloche est dans l'en-tête, donc présente sur les deux vues du hub.
$notifs = notificationsEtudiant($pdo, $uid);

/** Lien d'un filtre, en gardant l'autre dimension. Voir hubLienFiltre(). */
$lienFiltre = static fn(array $change): string => hubLienFiltre($filter, $musique, $change);

$typeLabels = ['bar'=>'Bar','boite'=>'Boîte','resto'=>'Resto','afterwork'=>'Afterwork'];
?>
<?php ob_start(); ?>
<style>
  /* ─── Personnes : suggestions ───
     Quatre vignettes en tête de page, deux par ligne sur téléphone. */
  .suggestions { margin-bottom: 22px; }
  /* Sur téléphone les quatre vignettes tiennent sur une seule rangée qui
     défile : en grille 2×2 elles mangeaient la moitié de l'écran avant
     même le premier profil de la liste. */
  .suggestions__grille {
    display: flex; gap: 10px; overflow-x: auto; padding-bottom: 4px;
    scroll-snap-type: x mandatory;
    scrollbar-width: none; -ms-overflow-style: none;
  }
  .suggestions__grille::-webkit-scrollbar { display: none; }
  .suggestions__grille > .carte-suggestion {
    flex: 0 0 136px; scroll-snap-align: start;
  }
  @media (min-width: 560px) {
    .suggestions__grille { display: grid; grid-template-columns: repeat(4, 1fr); overflow: visible; }
    .suggestions__grille > .carte-suggestion { flex: initial; }
  }
  /* Le bloc traverse la grille de la page, il n'en occupe pas une case. */
  @media (min-width: 760px) {
    .page-content.page-grid > .suggestions { grid-column: 1 / -1; }
  }
  .carte-suggestion {
    display: flex; flex-direction: column; align-items: center; gap: 8px;
    background: var(--blanc); border: 1px solid var(--line-2);
    border-radius: var(--radius); box-shadow: var(--shadow-xs);
    padding: 12px 8px; text-align: center; min-width: 0;
  }
  .carte-suggestion__lien {
    display: flex; flex-direction: column; align-items: center; gap: 6px;
    width: 100%; min-width: 0; text-decoration: none; color: inherit;
  }
  .carte-suggestion__nom,
  .carte-suggestion__motif {
    max-width: 100%; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }
  .carte-suggestion__nom { font-weight: var(--fw-bold); font-size: var(--fs-3); }
  .carte-suggestion__motif {
    font-size: var(--fs-1); font-weight: var(--fw-bold); color: var(--sur-rouge-clair);
  }
  .carte-suggestion__suivre {
    width: 100%; min-height: var(--touch-min);
    border: 1px solid var(--gris-clair); border-radius: var(--radius-pill);
    font-size: var(--fs-1); font-weight: var(--fw-bold); cursor: pointer;
  }

</style>
<?php pageDebut('StudentLink — Hub', ['pwa' => true, 'tete' => ob_get_clean()]); ?>
<a href="#main-content" class="skip-nav">Aller au contenu principal</a>
<div class="app-shell">

  <!-- Hub Header -->
  <div class="page-header" style="padding-bottom:10px;">
    <div class="entete-hub">
      <div class="logo">
        <svg class="logo-icon" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect x="15" y="20" width="50" height="30" rx="15" stroke="var(--noir)" stroke-width="10"/>
          <rect x="35" y="50" width="50" height="30" rx="15" class="accent" stroke-width="10"/>
          <circle cx="50" cy="50" r="6" fill="var(--noir)"/>
        </svg>
        StudentLink <em>/ Hub</em>
      </div>

      <!-- Cloche : tout ce qui vient d'arriver, là où l'on passe déjà. -->
      <button type="button" class="cloche" data-modal-open="modal-notifs">
        <span class="sr-only">Notifications</span>
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
        <span class="cloche__pastille" id="cloche-compteur" <?= $notifs['aTraiter'] ? '' : 'hidden' ?>><?= (int) $notifs['aTraiter'] ?></span>
      </button>
    </div>
    
    <div class="hub-toggle">
      <a href="?view=events" class="<?= $view==='events'?'active':'' ?>">Événements</a>
      <a href="?view=people" class="<?= $view==='people'?'active':'' ?>">Personnes</a>
    </div>

    <?php if ($view === 'events'): ?>
      <h1 class="titre-page">
        <div class="display" style="font-size:var(--fs-9); line-height:var(--lh-tight);">Les bons plans</div>
        <div class="display-italic" style="font-size:var(--fs-9); line-height:var(--lh-tight);">du moment.</div>
      </h1>
    <?php else: ?>
      <h1 class="titre-page">
        <div class="display" style="font-size:var(--fs-9); line-height:var(--lh-tight);">Trouve tes</div>
        <div class="display-italic" style="font-size:var(--fs-9); line-height:var(--lh-tight);">futurs potes.</div>
      </h1>
    <?php endif; ?>
  </div>

  <main id="main-content" class="page-content page-grid" style="padding-top:20px;">

    <?php if ($view === 'events'): ?>
      <!-- Event Filters (Pills) -->
      <div class="filter-scroll bleed" role="group" aria-label="Filtrer les événements" style="margin-bottom: 24px;">
        <a href="<?= $lienFiltre(['type' => 'all']) ?>" class="pill <?= $filter==='all'?'active':'' ?>" <?= $filter==='all'?'aria-current="true"':'' ?>>Tout</a>
        <a href="<?= $lienFiltre(['type' => 'pour-moi']) ?>" class="pill <?= $filter==='pour-moi'?'active':'' ?>" <?= $filter==='pour-moi'?'aria-current="true"':'' ?> style="<?= $filter==='pour-moi'?'':'border-color:var(--sur-rouge-clair);color:var(--sur-rouge-clair);' ?>">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="vertical-align:text-bottom;margin-right:4px;"><path d="M12 3l1.912 5.813h6.111l-4.943 3.591 1.887 5.804-4.967-3.607-4.967 3.607 1.887-5.804-4.943-3.591h6.111z"></path></svg>
          Pour moi
        </a>
        <a href="<?= $lienFiltre(['type' => 'bar']) ?>"   class="pill <?= $filter==='bar'?'active':'' ?>"   <?= $filter==='bar'?'aria-current="true"':'' ?>>Bars</a>
        <a href="<?= $lienFiltre(['type' => 'boite']) ?>" class="pill <?= $filter==='boite'?'active':'' ?>" <?= $filter==='boite'?'aria-current="true"':'' ?>>Boîtes</a>
        <a href="<?= $lienFiltre(['type' => 'resto']) ?>" class="pill <?= $filter==='resto'?'active':'' ?>" <?= $filter==='resto'?'aria-current="true"':'' ?>>Restos</a>
      </div>

      <!-- Styles de musique : une seconde dimension, et non des pilules de plus
           dans la première — « Bars » et « Techno » ne s'excluent pas. -->
      <div class="filter-scroll bleed" role="group" aria-label="Filtrer par style de musique" style="margin-top:-12px;margin-bottom:24px;">
        <a href="<?= $lienFiltre(['musique' => '']) ?>" class="pill pill-musique <?= $musique===''?'active':'' ?>" <?= $musique===''?'aria-current="true"':'' ?>>
          <?= icon('musique', 'icon-sm') ?>Toute musique
        </a>
        <?php foreach (stylesMusique() as $code => $libelle): ?>
          <a href="<?= $lienFiltre(['musique' => $code]) ?>" class="pill pill-musique <?= $musique===$code?'active':'' ?>" <?= $musique===$code?'aria-current="true"':'' ?>><?= htmlspecialchars($libelle) ?></a>
        <?php endforeach; ?>
      </div>

      <?php if (empty($evenements)): ?>
        <?php $filtreActif = ($filter !== 'all') || $musique !== '' || $q !== ''; ?>
        <div class="card" style="padding:36px 26px;text-align:center;">
          <div style="display:flex;justify-content:center;color:var(--gris);margin-bottom:14px;">
            <?= icon($filtreActif ? 'loupe' : 'vide', 'icon-lg') ?>
          </div>
          <div class="t-card-title" style="margin-bottom:6px;">
            <?= $filtreActif ? 'Aucun résultat' : 'Rien de prévu pour le moment' ?>
          </div>
          <p class="t-caption" style="max-width:34ch;margin:0 auto;">
            <?= $filtreActif
                ? "Aucun événement ne correspond à cette recherche. Essaie un autre filtre."
                : "Les établissements n'ont pas encore publié de soirée. Reviens d'ici quelques jours." ?>
          </p>
          <?php if ($filtreActif): ?>
            <a href="<?= baseUrl('/explore.php') ?>" class="btn btn-primary" style="margin-top:20px;">
              Voir tous les événements
            </a>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- Rich Event Cards (Restored Original Design) -->
      <?php $firstCard = true; foreach ($evenements as $e):
        $isFlash = $e['is_flash'] && strtotime($e['flash_expiry'] ?? '') > time();
        $isFull = $e['nb_inscrits'] >= $e['quota'];
        $pct = $e['quota'] > 0 ? round($e['nb_inscrits'] / $e['quota'] * 100) : 0;
        $friends = $friendsByEvent[$e['id']] ?? null;
        $isFollowedEtab = in_array($e['etab_id'], $followedEtabIds);
        $expiryTs = strtotime($e['flash_expiry'] ?? '');
      ?>

        <?php if ($isFlash): ?>
          <div class="event-card event-card-flash" style="margin-bottom:20px; position:relative;"
               data-live-event="<?= (int) $e['id'] ?>" data-live-quota="<?= (int) $e['quota'] ?>">
            <div class="event-entete">
              <div class="event-entete__meta">
                <div class="label" style="opacity:0.8;">CE SOIR · <?= date('H\hi', strtotime($e['date_heure'])) ?></div>
                <div class="flash-badge" data-expiry="<?= $expiryTs ?>">FLASH</div>
                <?php if ($style = libelleStyleMusique($e['style_musique'] ?? null)): ?>
                  <span class="badge badge-musique badge-musique-media"><?= htmlspecialchars($style) ?></span>
                <?php endif; ?>
                <?php if (!empty($e['sponso_actif'])): ?><span class="badge badge-sponsorise badge-sponsorise-media">Sponsorisé</span><?php endif; ?>
              </div>
            </div>

            <a href="view_event.php?id=<?= $e['id'] ?>" style="text-decoration:none; color:inherit; display:block;">
              <div class="event-title"><?= htmlspecialchars($e['titre']) ?></div>
            </a>

            <div class="event-lieu-rang">
              <a class="event-lieu" href="view_event.php?id=<?= $e['id'] ?>" style="text-decoration:none;color:inherit;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($e['etablissement_nom']) ?> — <?= htmlspecialchars($e['ville']) ?></a>
              <button class="btn-follow-etab btn-suivre-lieu btn-suivre-lieu--media" data-etab-id="<?= $e['etab_id'] ?>" data-following="<?= $isFollowedEtab?'1':'0' ?>"
                      style="background:rgba(255,255,255,<?= $isFollowedEtab?'0.3':'0.15' ?>);">
                <?= $isFollowedEtab ? icon('check','icon-sm').' SUIVI' : '+ SUIVRE' ?>
              </button>
            </div>

            <a href="view_event.php?id=<?= $e['id'] ?>" style="text-decoration:none; color:inherit; display:block;">
              <?php if ($firstCard): ?>
              <div style="margin-top:14px;padding:10px 14px;background:rgba(0,0,0,0.25);border-radius:var(--radius-sm);display:flex;align-items:center;gap:10px;">
                <svg width="16" height="16" fill="none" stroke="rgba(255,255,255,0.8)" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                <span style="font-size:var(--fs-1);font-weight:var(--fw-bold);color:rgba(255,255,255,0.7);text-transform:uppercase;letter-spacing:var(--ls-wide);">Commence dans</span>
                <span class="event-countdown" data-ts="<?= strtotime($e['date_heure']) ?>" style="font-size:var(--fs-6);font-weight:var(--fw-black);color:#fff;letter-spacing:var(--ls-display);font-family:var(--font-sans);">--:--:--</span>
              </div>
              <?php $firstCard = false; endif; ?>
            </a>
            
            <?php if ($friends): ?>
              <div style="margin-top:8px;font-size:var(--fs-1);font-weight:var(--fw-bold);color:rgba(255,255,255,0.9);display:flex;align-items:center;gap:4px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                <?= implode(', ', array_slice($friends['prenoms'], 0, 2)) ?><?= $friends['nb']>2?' +'.($friends['nb']-2):'' ?> y vont
              </div>
            <?php endif; ?>

            <div style="display:flex;align-items:flex-end;justify-content:space-between;margin-top:16px;">
              <div>
                <?php if ((int) $e['reduction'] > 0): ?>
                  <div class="event-reduction">-<?= (int) $e['reduction'] ?>%</div>
                  <div style="font-size:var(--fs-1);opacity:0.8;"><?= $e['is_gratuit'] ? 'entrée gratuite' : 'sur conso' ?></div>
                <?php else: ?>
                  <div class="event-reduction"><?= $e['is_gratuit'] ? 'Gratuit' : 'Soirée' ?></div>
                  <div style="font-size:var(--fs-1);opacity:0.8;">sans remise</div>
                <?php endif; ?>
              </div>
              <button class="btn btn-outline-blanc btn-join-event" data-event-id="<?= $e['id'] ?>" <?= $e['deja_inscrit']?'disabled':'' ?>>
                <?= $e['deja_inscrit'] ? icon('check','icon-sm').' Inscrit' : 'Je fonce' ?>
              </button>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:14px; margin-bottom:4px; font-size:var(--fs-1); font-weight:var(--fw-bold); color:rgba(255,255,255,0.9); text-transform:uppercase; letter-spacing:var(--ls-wide);">
              <span>Remplissage</span>
              <span data-live="pct"><?= $pct ?>%</span>
            </div>
            <div class="progress-bar" style="margin-top:0;background:rgba(0,0,0,0.2);">
              <div class="progress-bar-fill" data-live="jauge" style="width:<?= $pct ?>%;background:var(--blanc);"></div>
            </div>
          </div>

        <?php else: ?>
          <div class="event-card event-card-regular type-<?= $e['type'] ?>" style="margin-bottom:20px; position:relative;"
               data-live-event="<?= (int) $e['id'] ?>" data-live-quota="<?= (int) $e['quota'] ?>">
            <div class="event-entete">
              <div class="event-entete__meta">
                <span class="event-meta"><?= mb_strtoupper($typeLabels[$e['type']]) ?> · <?= dateFr($e['date_heure'], 'D j M') ?></span>
                <?php if ($style = libelleStyleMusique($e['style_musique'] ?? null)): ?>
                  <span class="badge badge-musique"><?= icon('musique', 'icon-sm') ?><?= htmlspecialchars($style) ?></span>
                <?php endif; ?>
                <?php if ($e['is_gratuit']): ?><span class="badge badge-gratuit">GRATUIT</span><?php endif; ?>
                <?php if (!empty($e['sponso_actif'])): ?><span class="badge badge-sponsorise">Sponsorisé</span><?php endif; ?>
              </div>
            </div>

            <a href="view_event.php?id=<?= $e['id'] ?>" style="text-decoration:none; color:inherit; display:block;">
              <div class="event-title" style="font-size:var(--fs-6);"><?= htmlspecialchars($e['titre']) ?></div>
            </a>

            <!-- Le bouton suit l'établissement, pas la soirée : sa place est sur la
                 ligne du lieu, et non dans un coin au-dessus des badges. -->
            <div class="event-lieu-rang">
              <a class="event-lieu" href="view_event.php?id=<?= $e['id'] ?>" style="text-decoration:none;color:inherit;display:flex;align-items:center;gap:8px;min-width:0;">
                <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars($e['etablissement_nom']) ?></span>
                <?php if ($e['etab_nb_avis'] > 0): ?>
                  <span class="with-icon" style="color:var(--sur-lime-clair);font-weight:var(--fw-bold);font-size:var(--fs-2);gap:4px;flex-shrink:0;"><?= icon('etoile', 'icon-sm') ?><?= $e['etab_note'] ?></span>
                  <span style="color:var(--gris);font-size:var(--fs-1);flex-shrink:0;">(<?= $e['etab_nb_avis'] ?>)</span>
                <?php endif; ?>
              </a>
              <button class="btn-follow-etab btn-suivre-lieu" data-etab-id="<?= $e['etab_id'] ?>" data-following="<?= $isFollowedEtab?'1':'0' ?>"
                      style="background:<?= $isFollowedEtab?'var(--noir)':'transparent' ?>;color:<?= $isFollowedEtab?'var(--blanc)':'var(--noir)' ?>;">
                <?= $isFollowedEtab ? icon('check','icon-sm').' SUIVI' : '+ SUIVRE' ?>
              </button>
            </div>
            
            <?php if ($friends): ?>
              <div style="font-size:var(--fs-1);font-weight:var(--fw-bold);color:var(--sur-rouge-clair);margin-top:8px;display:flex;align-items:center;gap:4px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                <?= implode(', ', array_slice($friends['prenoms'], 0, 2)) ?> y vont
              </div>
            <?php endif; ?>

            <div style="font-size:var(--fs-3);color:var(--gris-fonce);margin:12px 0;line-height:var(--lh-snug);">
              <?= htmlspecialchars(mb_substr($e['description'] ?? '', 0, 100)) ?>...
            </div>

            <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
              <div style="font-size:var(--fs-1);font-weight:var(--fw-bold);"><span data-live="inscrits"><?= (int) $e['nb_inscrits'] ?></span>/<?= (int) $e['quota'] ?> places</div>
              <div style="display:flex;gap:6px;">
                <button type="button" class="btn-ouvrir-invitation"
                        data-invite-type="event"
                        data-invite-cible="<?= $e['id'] ?>"
                        data-invite-nom="<?= htmlspecialchars($e['titre'], ENT_QUOTES) ?>"
                        style="background:none;border:1px solid var(--gris-clair);padding:7px 10px;font-size:var(--fs-1);font-weight:var(--fw-display);cursor:pointer;display:flex;align-items:center;gap:4px;">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                  Inviter
                </button>
                <button class="btn btn-primary btn-join-event" data-event-id="<?= $e['id'] ?>" style="padding:8px 16px;font-size:var(--fs-2);" <?= $e['deja_inscrit']?'disabled':'' ?>>
                  <?= $e['deja_inscrit'] ? icon('check','icon-sm').' Inscrit' : 'Rejoindre' ?>
                </button>
              </div>
            </div>
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:12px; margin-bottom:4px; font-size:var(--fs-1); font-weight:var(--fw-bold); color:var(--noir); text-transform:uppercase; letter-spacing:var(--ls-wide);">
              <span>Taux d'inscription</span>
              <span data-live="pct"><?= $pct ?>%</span>
            </div>
            <div class="progress-bar" style="margin-top:0;background:var(--gris-clair);">
              <div class="progress-bar-fill dark" data-live="jauge" style="width:<?= $pct ?>%"></div>
            </div>
          </div>
        <?php endif; ?>

      <?php endforeach; ?>

      <?php if (!empty($resteEvenements)): ?>
        <?php $suiteE = $_GET; $suiteE['pe'] = ($pageE ?? 1) + 1; ?>
        <a href="?<?= htmlspecialchars(http_build_query($suiteE), ENT_QUOTES) ?>"
           class="btn btn-outline btn-full" style="margin-top:4px;">
          Voir plus d'événements
        </a>
      <?php endif; ?>

    <?php else: ?>
      <!-- People Section (Matching Original Design) -->
      <form method="GET" id="people-filters">
        <input type="hidden" name="view" value="people">

        <!-- Search -->
        <div style="position:relative;margin-bottom:14px;">
          <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Chercher un nom ou une passion..."
                 style="width:100%;padding:14px 16px;border:1px solid var(--gris-clair);box-shadow:var(--shadow);font-size:var(--fs-4);outline:none;background:var(--blanc);">
          <button type="submit" style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--noir);">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
          </button>
        </div>

        <!-- Filter row -->
        <div style="display:flex;gap:8px;margin-bottom:20px;">
          <div style="flex:1;position:relative;">
            <select name="ecole" onchange="this.form.submit()"
                    style="width:100%;padding:10px 32px 10px 12px;border:1px solid var(--gris-clair);background:<?= $filterEcole?'var(--noir)':'var(--blanc)' ?>;color:<?= $filterEcole?'var(--blanc)':'var(--noir)' ?>;font-size:var(--fs-2);font-weight:var(--fw-bold);appearance:none;cursor:pointer;">
              <option value="">Toutes les écoles</option>
              <?php foreach ($allEcoles as $e): ?>
                <option value="<?= htmlspecialchars($e) ?>" <?= $filterEcole===$e?'selected':'' ?>><?= htmlspecialchars($e) ?></option>
              <?php endforeach; ?>
            </select>
            <svg style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="<?= $filterEcole?'white':'var(--noir)' ?>" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
          </div>

          <?php if (!empty($allInterests)): ?>
          <div style="flex:1;position:relative;">
            <select name="interest" onchange="this.form.submit()"
                    style="width:100%;padding:10px 32px 10px 12px;border:1px solid var(--gris-clair);background:<?= ($filterInterest||$filtreCommeMoi)?'var(--bleu)':'var(--blanc)' ?>;color:<?= ($filterInterest||$filtreCommeMoi)?'var(--blanc)':'var(--noir)' ?>;font-size:var(--fs-2);font-weight:var(--fw-bold);appearance:none;cursor:pointer;">
              <option value="">Tous les intérêts</option>
              <?php if ($myInterests): ?>
                <option value="<?= FILTRE_MES_INTERETS ?>" <?= $filtreCommeMoi?'selected':'' ?>>★ Comme moi</option>
              <?php endif; ?>
              <?php foreach ($allInterests as $int): ?>
                <option value="<?= htmlspecialchars($int) ?>" <?= $filterInterest===$int?'selected':'' ?>>#<?= htmlspecialchars($int) ?></option>
              <?php endforeach; ?>
            </select>
            <svg style="position:absolute;right:10px;top:50%;transform:translateY(-50%);pointer-events:none;" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="<?= ($filterInterest||$filtreCommeMoi)?'white':'var(--noir)' ?>" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
          </div>
          <?php endif; ?>
        </div>

        <?php if ($interetsManquants): ?>
          <div style="background:var(--alerte-clair);color:var(--alerte);border-radius:var(--radius-sm);padding:12px 16px;margin-bottom:16px;font-size:var(--fs-3);font-weight:var(--fw-semibold);">
            Tu n'as pas encore de centres d'intérêt.
            <a href="<?= baseUrl('/profil.php#champ-interets') ?>" style="color:inherit;font-weight:var(--fw-bold);">Ajoute-les depuis ton profil →</a>
          </div>
        <?php endif; ?>

        <?php if ($filterEcole || $filterInterest || $q): ?>
          <div style="margin-bottom:16px;display:flex;align-items:center;justify-content:space-between;">
            <span style="font-size:var(--fs-2);font-weight:var(--fw-bold);color:var(--gris);"><?= count($students) ?> résultat<?= count($students)>1?'s':'' ?></span>
            <a href="?view=people" style="font-size:var(--fs-1);font-weight:var(--fw-bold);color:var(--sur-rouge-clair);text-transform:uppercase;letter-spacing:var(--ls-wide);text-decoration:none;">Réinitialiser</a>
          </div>
        <?php endif; ?>
      </form>

      <?php if ($suggestions): ?>
        <!-- Suggestions : quatre profils choisis sur les gouts partages. -->
        <section class="suggestions" aria-labelledby="titre-suggestions">
          <div class="section-divider" style="margin-top:4px;">
            <h2 class="sd-label" id="titre-suggestions" style="margin:0;">À suivre · d'après tes goûts</h2>
          </div>
          <div class="suggestions__grille">
            <?php foreach ($suggestions as $sg): ?>
              <?php
                // Le motif affiché dit sur quoi repose la suggestion : sans ça,
                // c'est un visage de plus dont on ne sait pas ce qu'il fait là.
                if ($sg['common_interests']) {
                    $motif = '#' . implode(' #', array_slice($sg['common_interests'], 0, 2));
                } elseif ($sg['shared_squads'] > 0) {
                    $motif = 'Squad en commun';
                } else {
                    $motif = 'Même école';
                }
              ?>
              <article class="carte-suggestion">
                <a class="carte-suggestion__lien" href="<?= baseUrl('/view_profile.php?id=' . (int)$sg['id']) ?>">
                  <?= avatarHtml($sg['photo'] ?? null, $sg['prenom'], 52) ?>
                  <span class="carte-suggestion__nom"><?= htmlspecialchars($sg['prenom'] . ' ' . mb_substr((string)$sg['nom'], 0, 1) . '.') ?></span>
                  <span class="carte-suggestion__motif"><?= htmlspecialchars($motif) ?></span>
                </a>
                <button class="btn-follow-user carte-suggestion__suivre" data-user-id="<?= (int)$sg['id'] ?>" data-etat="none"
                        style="background:transparent;color:var(--noir);">+ Suivre</button>
              </article>
            <?php endforeach; ?>
          </div>
        </section>
      <?php endif; ?>


      <div class="list-grid liste-personnes">
        <?php foreach ($students as $s): ?>
          <?php
            $fs = $s['follow_statut'] ?? null;
            $libelle = $fs === 'accepted' ? icon('check','icon-sm').' Suivi' : ($fs === 'pending' ? 'En attente' : '+ Suivre');
            $fond    = $fs === 'accepted' ? 'var(--noir)' : ($fs === 'pending' ? 'var(--surface-2)' : 'transparent');
            $encre   = $fs === 'accepted' ? 'var(--blanc)' : ($fs === 'pending' ? 'var(--gris-fonce)' : 'var(--noir)');
          ?>
          <div class="ligne-personne">
            <a class="ligne-personne__lien" href="<?= baseUrl('/view_profile.php?id=' . (int)$s['id']) ?>">
              <?= avatarHtml($s['photo'] ?? null, $s['prenom'], 40) ?>
              <span class="ligne-personne__infos">
                <span class="ligne-personne__nom"><?= htmlspecialchars($s['prenom'] . ' ' . mb_substr((string)$s['nom'], 0, 1) . '.') ?></span>
                <span class="ligne-personne__meta"><?= htmlspecialchars((string)$s['ecole']) ?><?= $s['promo'] ? ' · ' . htmlspecialchars((string)$s['promo']) : '' ?></span>
                <?php if ($s['score'] > 0 || $s['shared_squads'] > 0): ?>
                  <span class="ligne-personne__affinites">
                    <?php if ($s['shared_squads'] > 0): ?>
                      <span class="ligne-personne__squad">
                        <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path></svg>
                        Squad commun
                      </span>
                    <?php endif; ?>
                    <?php foreach (array_slice($s['common_interests'], 0, 3) as $interest): ?>
                      <span>#<?= htmlspecialchars($interest) ?></span>
                    <?php endforeach; ?>
                  </span>
                <?php endif; ?>
              </span>
            </a>
            <button class="btn-follow-user ligne-personne__suivre" data-user-id="<?= (int)$s['id'] ?>" data-etat="<?= $fs ?: 'none' ?>"
                    style="background:<?= $fond ?>;color:<?= $encre ?>;">
              <?= $libelle ?>
            </button>
          </div>
        <?php endforeach; ?>
        <?php if (!$students): ?>
          <p class="liste-personnes__vide">Aucun autre profil à afficher pour le moment.</p>
        <?php endif; ?>
      </div>

      <?php if (!empty($resteProfils)): ?>
        <?php $suite = $_GET; $suite['p'] = ($page ?? 1) + 1; ?>
        <a href="?<?= htmlspecialchars(http_build_query($suite), ENT_QUOTES) ?>"
           class="btn btn-outline btn-full" style="margin-top:4px;">
          Voir plus de profils
        </a>
      <?php endif; ?>
    <?php endif; ?>

  </main>

  <!-- Panneau des notifications — même mécanique de feuille que l'invitation. -->
  <div class="modal-overlay" id="modal-notifs">
    <div class="modal-sheet">
      <div class="modal-handle"></div>
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:18px;">
        <div>
          <div style="font-size:var(--fs-1);font-weight:var(--fw-bold);text-transform:uppercase;letter-spacing:var(--ls-label);color:var(--sur-rouge-clair);margin-bottom:4px;">Notifications</div>
          <div class="display" style="font-size:var(--fs-6);">
            <?= $notifs['aTraiter'] ? (int) $notifs['aTraiter'] . ' à traiter' : 'Quoi de neuf' ?>
          </div>
        </div>
        <button type="button" data-modal-close aria-label="Fermer"
                style="background:none;border:1px solid var(--line-2);border-radius:var(--radius-bouton);width:36px;height:36px;flex-shrink:0;cursor:pointer;"><?= icon('croix', 'icon-sm') ?></button>
      </div>

      <div class="notif-liste" id="liste-notifs">
        <?php foreach ($notifs['items'] as $n): ?>
          <?php if ($n['type'] === 'demande'): $d = $n['acteur']; ?>
            <div class="notif-item notif-item--demande">
              <a href="<?= baseUrl('/view_profile.php?id=' . (int)$d['id']) ?>" class="notif-item__lien">
                <?= avatarHtml($d['photo'] ?? null, $d['prenom'], 38) ?>
                <span class="notif-item__corps">
                  <span class="notif-item__texte">
                    <strong><?= htmlspecialchars($d['prenom'] . ' ' . mb_substr((string)$d['nom'], 0, 1) . '.') ?></strong>
                    demande à te suivre
                  </span>
                  <span class="notif-item__date"><?= htmlspecialchars(depuisQuand((string)$n['ts'])) ?></span>
                </span>
              </a>
              <div class="notif-item__actions">
                <button class="btn-accept-follow notif-oui" data-user-id="<?= (int)$d['id'] ?>">Accepter</button>
                <button class="btn-decline-follow notif-non" data-user-id="<?= (int)$d['id'] ?>">Refuser</button>
              </div>
            </div>

          <?php elseif ($n['type'] === 'invitation'): $inv = $n['invit']; ?>
            <div class="notif-item carte-invitation">
              <div class="notif-item__lien">
                <?= avatarHtml($inv['from_photo'] ?? null, $inv['from_prenom'], 38, $inv['cible_type'] === 'event' ? 'var(--rouge)' : 'var(--bleu)') ?>
                <span class="notif-item__corps">
                  <span class="notif-item__texte">
                    <strong><?= htmlspecialchars($inv['from_prenom'] . ' ' . mb_substr((string)$inv['from_nom'], 0, 1) . '.') ?></strong>
                    t'invite à <em><?= htmlspecialchars($inv['cible_nom'] ?? 'une sortie') ?></em>
                  </span>
                  <span class="notif-item__date"><?= htmlspecialchars(depuisQuand((string)$n['ts'])) ?></span>
                </span>
              </div>
              <div class="notif-item__actions">
                <button type="button" class="btn-accept-invite notif-oui" data-id="<?= (int)$inv['id'] ?>">Accepter</button>
                <button type="button" class="btn-decline-invite notif-non" data-id="<?= (int)$inv['id'] ?>">Refuser</button>
              </div>
            </div>

          <?php elseif ($n['type'] === 'ami'): $a = $n['activite']; ?>
            <a class="notif-item notif-item__lien" href="<?= $a['cible_type'] === 'event'
                  ? baseUrl('/view_event.php?id=' . (int)$a['cible_id'])
                  : baseUrl('/squads.php') ?>">
              <?= avatarHtml($a['photo'] ?? null, $a['prenom'], 38, $a['cible_type'] === 'event' ? 'var(--rouge)' : 'var(--lime)') ?>
              <span class="notif-item__corps">
                <span class="notif-item__texte">
                  <strong><?= htmlspecialchars($a['prenom']) ?></strong>
                  <?= $a['cible_type'] === 'event' ? 'va à' : 'rejoint' ?>
                  <em><?= htmlspecialchars($a['cible_nom']) ?></em>
                </span>
                <span class="notif-item__date"><?= htmlspecialchars($a['lieu'] . ' · ' . depuisQuand((string)$n['ts'])) ?></span>
              </span>
            </a>

          <?php else: $e = $n['event']; ?>
            <a class="notif-item notif-item__lien" href="<?= baseUrl('/view_event.php?id=' . (int)$e['id']) ?>">
              <span class="notif-item__vignette"><?= icon('calendrier', 'icon-sm') ?></span>
              <span class="notif-item__corps">
                <span class="notif-item__texte">
                  Nouveau chez <strong><?= htmlspecialchars($e['lieu']) ?></strong> :
                  <em><?= htmlspecialchars($e['titre']) ?></em>
                </span>
                <span class="notif-item__date"><?= htmlspecialchars(dateFr($e['date_heure'], 'j M') . ' · ' . depuisQuand((string)$n['ts'])) ?></span>
              </span>
            </a>
          <?php endif; ?>
        <?php endforeach; ?>

        <?php if (!$notifs['items']): ?>
          <p class="notif-vide">
            Rien de neuf pour l'instant.<br>
            <a href="?view=people" style="color:var(--sur-rouge-clair);font-weight:var(--fw-bold);">Suis des étudiants et des lieux →</a>
          </p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Modal Invitation — dans .app-shell : c'est ce que le routeur remplace. -->
  <div class="modal-overlay" id="modal-invite">
    <div class="modal-sheet">
      <div class="modal-handle"></div>
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:20px;">
        <div>
          <div style="font-size:var(--fs-1);font-weight:var(--fw-bold);text-transform:uppercase;letter-spacing:var(--ls-label);color:var(--sur-rouge-clair);margin-bottom:4px;">Inviter un ami</div>
          <div class="display" id="invite-target-name" style="font-size:var(--fs-6);"></div>
        </div>
        <button type="button" data-modal-close aria-label="Fermer"
                style="background:none;border:1px solid var(--line-2);border-radius:var(--radius-bouton);width:36px;height:36px;flex-shrink:0;cursor:pointer;"><?= icon('croix', 'icon-sm') ?></button>
      </div>
      <div id="invite-user-list" style="display:flex;flex-direction:column;gap:10px;">
        <?php foreach ($following as $f): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px;background:var(--blanc);border:1px solid var(--gris-clair);border-radius:var(--radius);">
          <div style="display:flex;align-items:center;gap:12px;min-width:0;">
            <?= avatarHtml($f['photo'] ?? null, $f['prenom'], 40) ?>
            <div style="font-weight:var(--fw-bold);font-size:var(--fs-4);"><?= htmlspecialchars($f['prenom'] . ' ' . mb_substr($f['nom'], 0, 1) . '.') ?></div>
          </div>
          <button type="button" class="btn-send-invite" data-user-id="<?= $f['id'] ?>"
                  style="background:var(--noir);color:var(--blanc);border:1px solid transparent;border-radius:var(--radius-pill);padding:9px 16px;font-size:var(--fs-1);font-weight:var(--fw-bold);cursor:pointer;text-transform:uppercase;white-space:nowrap;">
            Inviter
          </button>
        </div>
        <?php endforeach; ?>
        <?php if (empty($following)): ?>
        <div style="text-align:center;padding:24px;color:var(--gris);font-size:var(--fs-4);">
          Tu ne suis personne encore.<br>
          <a href="<?= baseUrl('/explore.php?view=people') ?>" style="color:var(--sur-rouge-clair);font-weight:var(--fw-bold);">Trouve des étudiants à suivre →</a>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

</div>

<!-- Navigation -->
<nav class="bottom-nav" aria-label="Navigation principale">
  <a href="<?= baseUrl('/explore.php') ?>" class="nav-item active" aria-current="page">
    <span class="nav-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg></span>
    <span>Explore</span>
  </a>
  <a href="<?= baseUrl('/squads.php') ?>" class="nav-item">
    <span class="nav-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg></span>
    <span>Squads</span>
  </a>
  <a href="<?= baseUrl('/wallet.php') ?>" class="nav-item">
    <span class="nav-icon" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg></span>
    <span>Wallet</span>
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
