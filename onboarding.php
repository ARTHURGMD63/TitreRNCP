<?php
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/page.php';
requireLogin();
$prenom = $_SESSION['user_prenom'] ?? 'toi';
?>
<?php ob_start(); ?>
<style>
  body { background: var(--bg); overflow: hidden; height: 100dvh; }

  .onb-wrapper { height: 100dvh; display: flex; flex-direction: column; overflow: hidden; }

  .onb-skip {
    position: fixed;
    top: calc(env(safe-area-inset-top, 0px) + 16px);
    right: 20px;
    z-index: 10;
    /* Posé sur des aplats différents d'un écran à l'autre : une pastille
       basalte le garde lisible partout. */
    background: rgba(17,16,19,.6);
    border: none;
    border-radius: var(--radius-pill);
    font-family: var(--font-mono);
    font-size: var(--fs-1);
    font-weight: var(--fw-medium);
    color: #F5F1E8;
    text-transform: uppercase;
    letter-spacing: var(--ls-label);
    cursor: pointer;
    padding: 8px 14px;
  }

  .onb-slides {
    flex: 1;
    display: flex;
    transition: transform 340ms cubic-bezier(0.4, 0, 0.2, 1);
    will-change: transform;
  }

  .onb-slide {
    min-width: 100vw;
    padding: 0 0 0 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }

  /* ── Top visual block ── */
  .onb-visual {
    flex: 0 0 48%;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    overflow: hidden;
  }

  /* Aplats de la charte : lave (marque), surface, dôme (sport), volt (pass).
     Le visuel s'arrondit en bas comme la maquette 01. */
  .onb-visual { border-radius: 0 0 var(--radius-xl) var(--radius-xl); color: var(--sur-lave); }
  .slide-1 .onb-visual { background: var(--rouge); }
  .slide-2 .onb-visual { background: var(--blanc); }
  .slide-3 .onb-visual { background: var(--bleu); }
  .slide-4 .onb-visual { background: var(--lime); }

  .onb-visual-inner {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 20px;
    padding: 24px;
    position: relative;
    z-index: 2;
  }

  /* Big icon circle */
  .onb-big-icon {
    width: 96px;
    height: 96px;
    border-radius: 50%;
    background: rgba(17,16,19,0.1);
    border: 1px solid rgba(17,16,19,0.25);
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .onb-big-icon svg { stroke: #111013; }
  /* Écran 1 : les deux anneaux de la marque, basalte et craie, sur la lave. */
  .onb-anneaux { width: 190px; height: auto; }

  /* Pilules flottantes (« -30 % ce soir », « 1200+ étudiants ») :
     basalte en haut, craie en bas, comme la maquette 01. */
  .onb-stat {
    background: #111013;
    color: #F5F1E8;
    border-radius: var(--radius-md);
    border: none;
    box-shadow: none;
    padding: 10px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    position: absolute;
  }
  .stat-br { background: #F5F1E8; color: #111013; }

  .onb-stat-num {
    font-family: var(--font-display);
    font-size: var(--fs-6);
    font-weight: var(--fw-black);
    letter-spacing: var(--ls-display);
    color: inherit;
    line-height: var(--lh-display);
  }

  .onb-stat-label {
    font-size: var(--fs-2);
    font-weight: var(--fw-semibold);
    color: inherit;
    opacity: .8;
    line-height: var(--lh-snug);
  }

  .stat-tl { top: 20px; left: 20px; }
  .stat-br { bottom: 20px; right: 20px; }

  /* Mockup cards inside visual */
  .onb-mock-card {
    background: var(--bg);
    color: var(--noir);
    border-radius: var(--radius-md);
    border: 1px solid var(--gris-clair);
    box-shadow: none;
    padding: 14px 16px;
    width: 210px;
  }

  .onb-mock-card .mc-tag {
    font-family: var(--font-mono);
    font-size: var(--fs-1);
    font-weight: var(--fw-medium);
    text-transform: uppercase;
    letter-spacing: var(--ls-label);
    color: var(--sur-rouge-clair);
    margin-bottom: 4px;
  }

  .onb-mock-card .mc-title {
    font-family: var(--font-display);
    font-size: var(--fs-5);
    font-weight: var(--fw-black);
    letter-spacing: var(--ls-display);
    color: var(--noir);
    margin-bottom: 6px;
  }

  .onb-mock-card .mc-meta {
    font-size: var(--fs-1);
    color: var(--gris);
    font-weight: var(--fw-semibold);
  }

  .onb-mock-badge {
    display: inline-block;
    border-radius: var(--radius-pill);
    background: var(--rouge);
    color: var(--sur-lave);
    font-size: var(--fs-2);
    font-weight: var(--fw-bold);
    padding: 3px 10px;
    margin-top: 8px;
  }

  /* ── Bottom text block ── */
  .onb-text {
    flex: 1;
    padding: 28px 28px 16px;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }

  .onb-tag {
    font-family: var(--font-mono);
    font-size: var(--fs-1);
    font-weight: var(--fw-medium);
    letter-spacing: var(--ls-label);
    text-transform: uppercase;
    color: var(--sur-rouge-clair);
    margin-bottom: 10px;
  }

  .slide-3 .onb-tag { color: var(--sur-bleu-clair); }
  .slide-4 .onb-tag { color: var(--sur-lime-clair); }

  .onb-title {
    font-family: var(--font-display);
    font-size: var(--fs-8);
    font-weight: var(--fw-black);
    letter-spacing: var(--ls-display);
    color: var(--noir);
    line-height: var(--lh-tight);
    margin-bottom: 14px;
  }

  /* h1.titre-page remet la police à « inherit » (style.css) : sans ce
     sélecteur plus précis, le titre retombait en police de texte. */
  h1.onb-title.titre-page {
    font-family: var(--font-display); font-size: var(--fs-8); font-weight: var(--fw-black);
    letter-spacing: var(--ls-display); line-height: var(--lh-tight); margin-bottom: 14px;
  }
  .onb-title em { font-style: normal; color: var(--sur-rouge-clair); }
  .slide-3 .onb-title em { color: var(--sur-bleu-clair); }
  .slide-4 .onb-title em { color: var(--sur-lime-clair); }

  .onb-desc {
    font-size: var(--fs-5);
    color: var(--gris-fonce);
    line-height: var(--lh-relaxed);
  }

  /* ── Footer ── */
  .onb-footer {
    padding: 16px 28px calc(env(safe-area-inset-bottom, 0px) + 20px);
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-top: none;
    background: var(--bg);
    flex-shrink: 0;
  }

  .onb-dots { display: flex; gap: 6px; align-items: center; }

  .onb-dot {
    width: 8px; height: 8px; border-radius: var(--radius-pill);
    background: var(--line-2);
    border: none;
    transition: all 280ms ease;
  }

  .onb-dot.active { background: var(--rouge); width: 24px; }

  /* « Suivant » : l'action principale, donc la pilule lave. */
  .onb-btn {
    background: var(--rouge);
    color: var(--sur-lave);
    border-radius: var(--radius-pill);
    border: 1px solid var(--rouge);
    box-shadow: none;
    padding: 14px 26px;
    font-size: var(--fs-5);
    font-weight: var(--fw-bold);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: box-shadow 100ms, transform 100ms;
  }

  .onb-btn:active { transform: scale(0.98); }
</style>
<?php pageDebut('Bienvenue — Linkee', ['pwa' => true, 'viewport' => 'width=device-width, initial-scale=1.0, viewport-fit=cover', 'tete' => ob_get_clean()]); ?>
<div class="onb-wrapper">

  <button class="onb-skip" onclick="finish()">Passer</button>

  <div class="onb-slides" id="slides">

    <!-- SLIDE 1 : Hook -->
    <div class="onb-slide slide-1">
      <div class="onb-visual">
        <div class="onb-stat stat-tl">
          <div>
            <div class="onb-stat-num">-50%</div>
            <div class="onb-stat-label">sur tes<br>sorties</div>
          </div>
        </div>
        <div class="onb-visual-inner">
          <svg class="onb-anneaux" viewBox="0 0 52 32" fill="none" aria-hidden="true">
            <circle cx="16" cy="18" r="12" stroke="#111013" stroke-width="4.5"/>
            <circle cx="34" cy="14" r="12" stroke="#F5F1E8" stroke-width="4.5"/>
          </svg>
        </div>
        <div class="onb-stat stat-br">
          <div>
            <div class="onb-stat-num">100%</div>
            <div class="onb-stat-label">étudiant<br>gratuit</div>
          </div>
        </div>
      </div>
      <div class="onb-text">
        <div class="onb-tag">Bienvenue sur Linkee</div>
        <h1 class="onb-title titre-page">La vie étudiante<br>à prix <em>réduit.</em></h1>
        <div class="onb-desc">Bars, boîtes, restos — accède aux meilleures sorties de ta ville avec des réductions exclusives réservées aux étudiants.</div>
      </div>
    </div>

    <!-- SLIDE 2 : Explore -->
    <div class="onb-slide slide-2">
      <div class="onb-visual">
        <div class="onb-visual-inner" style="gap:12px;">
          <div class="onb-mock-card">
            <div class="mc-tag">Bar · Ce soir</div>
            <div class="mc-title">Happy Hour</div>
            <div class="mc-meta">Le Bec qui Pique — 18h</div>
            <div class="onb-mock-badge">-50% · Flash</div>
          </div>
          <div class="onb-mock-card" style="transform:translateX(24px);opacity:0.7;">
            <div class="mc-tag">Boîte · Vendredi</div>
            <div class="mc-title">Soirée Étudiante</div>
            <div class="mc-meta">Le Baromètre — 23h</div>
            <div class="onb-mock-badge" style="background:var(--bleu);color:var(--sur-lave);">Entrée gratuite</div>
          </div>
        </div>
      </div>
      <div class="onb-text">
        <div class="onb-tag">Explore</div>
        <h1 class="onb-title titre-page">Les bons plans<br>du <em>moment.</em></h1>
        <div class="onb-desc">Découvre les événements près de chez toi, inscris-toi en un tap et reçois ton pass numérique directement dans l'app.</div>
      </div>
    </div>

    <!-- SLIDE 3 : Squads -->
    <div class="onb-slide slide-3">
      <div class="onb-visual">
        <div class="onb-stat stat-tl" style="flex-direction:column;gap:2px;padding:10px 14px;">
          <div class="onb-stat-num">12</div>
          <div class="onb-stat-label">squads<br>actifs</div>
        </div>
        <div class="onb-visual-inner">
          <div class="onb-big-icon">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#111013" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
              <circle cx="9" cy="7" r="4"/>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
              <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
          </div>
        </div>
        <div class="onb-stat stat-br" style="flex-direction:column;gap:2px;padding:10px 14px;">
          <div class="onb-stat-num">3</div>
          <div class="onb-stat-label">sports<br>dispo</div>
        </div>
      </div>
      <div class="onb-text">
        <div class="onb-tag">Squads</div>
        <h1 class="onb-title titre-page">Bouge avec<br>les <em>bons.</em></h1>
        <div class="onb-desc">Running, vélo, muscu… Rejoins un groupe d'étudiants qui partagent tes passions. Ou crée le tien en 30 secondes.</div>
      </div>
    </div>

    <!-- SLIDE 4 : Rencontres -->
    <div class="onb-slide slide-4-meet" style="--meet-color: var(--orange);">
      <div class="onb-visual" style="background: var(--orange);">
        <div class="onb-visual-inner" style="gap:12px;width:100%;padding:24px 28px;">
          <div class="onb-mock-card" style="width:100%;">
            <div style="display:flex;align-items:center;justify-content:space-between;">
              <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:36px;height:36px;background:var(--bleu);border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-weight:var(--fw-display);font-size:var(--fs-4);color:var(--sur-lave);flex-shrink:0;">L</div>
                <div><div style="font-weight:var(--fw-bold);font-size:var(--fs-4);color:var(--noir);">Léa M.</div><div style="font-size:var(--fs-1);color:var(--gris);">SIGMA · M1</div></div>
              </div>
              <span style="font-size:var(--fs-2);background:var(--rouge);color:var(--sur-lave);padding:5px 12px;border-radius:var(--radius-pill);font-weight:var(--fw-bold);">+ Suivre</span>
            </div>
            <div style="margin-top:8px;display:flex;gap:4px;">\
              <span style="font-size:var(--fs-2);color:var(--noir);border:1px solid var(--line-2);border-radius:var(--radius-pill);padding:2px 9px;font-weight:var(--fw-medium);">#muscu</span>
              <span style="font-size:var(--fs-2);color:var(--noir);border:1px solid var(--line-2);border-radius:var(--radius-pill);padding:2px 9px;font-weight:var(--fw-medium);">#boites</span>
            </div>
          </div>
          <div class="onb-mock-card" style="width:100%;">
            <div style="display:flex;align-items:center;justify-content:space-between;">
              <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:36px;height:36px;background:var(--rouge);border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:var(--font-display);font-weight:var(--fw-display);font-size:var(--fs-4);color:var(--sur-lave);flex-shrink:0;">A</div>
                <div><div style="font-weight:var(--fw-bold);font-size:var(--fs-4);color:var(--noir);">Arthur M.</div><div style="font-size:var(--fs-1);color:var(--gris);">UCA · L2</div></div>
              </div>
              <span style="font-size:var(--fs-2);background:var(--rouge);color:var(--sur-lave);padding:5px 12px;border-radius:var(--radius-pill);font-weight:var(--fw-bold);">+ Suivre</span>
            </div>
            <div style="margin-top:8px;display:flex;gap:4px;">
              <span style="font-size:var(--fs-2);color:var(--noir);border:1px solid var(--line-2);border-radius:var(--radius-pill);padding:2px 9px;font-weight:var(--fw-medium);">#running</span>
              <span style="font-size:var(--fs-2);color:var(--noir);border:1px solid var(--line-2);border-radius:var(--radius-pill);padding:2px 9px;font-weight:var(--fw-medium);">#bars</span>
            </div>
          </div>
        </div>
      </div>
      <div class="onb-text">
        <div class="onb-tag" style="color:var(--sur-orange-clair);">Rencontres</div>
        <h1 class="onb-title titre-page">Trouve tes<br>futurs <em style="color:var(--sur-orange-clair);">potes.</em></h1>
        <div class="onb-desc">Découvre des étudiants qui partagent tes intérêts, vont aux mêmes événements que toi — et abonne-toi pour rester connecté.</div>
      </div>
    </div>

    <!-- SLIDE 5 : Let's go -->
    <div class="onb-slide slide-4">
      <div class="onb-visual">
        <div class="onb-visual-inner">
          <div class="onb-big-icon">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#111013" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
              <line x1="1" y1="10" x2="23" y2="10"/>
            </svg>
          </div>
          <div class="onb-mock-card" style="background:var(--rouge);border-color:var(--rouge);border-radius:var(--radius);">
            <div class="mc-tag" style="color:var(--sur-lave);">linkee pass</div>
            <div class="mc-title" style="color:var(--sur-lave);"><?= htmlspecialchars($prenom) ?></div>
            <div class="mc-meta" style="color:rgba(17,16,19,.75);">Happy Hour · Ce soir</div>
            <div style="margin-top:10px;background:#FFFFFF;height:48px;width:48px;border-radius:10px;display:flex;align-items:center;justify-content:center;">
              <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#111013" stroke-width="1.5">
                <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                <rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="3" height="3"/>
              </svg>
            </div>
          </div>
        </div>
      </div>
      <div class="onb-text">
        <div class="onb-tag">C'est parti</div>
        <h1 class="onb-title titre-page">Prêt à<br><em>kiffer</em> ?</h1>
        <div class="onb-desc">Ton pass numérique, tes événements, tes amis. Tout est là. Il ne reste plus qu'à sortir.</div>
      </div>
    </div>

  </div>

  <div class="onb-footer">
    <div class="onb-dots" id="dots">
      <div class="onb-dot active"></div>
      <div class="onb-dot"></div>
      <div class="onb-dot"></div>
      <div class="onb-dot"></div>
      <div class="onb-dot"></div>
    </div>
    <button class="onb-btn" id="next-btn" onclick="next()">
      Suivant
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
      </svg>
    </button>
  </div>

</div>

<script>
let current = 0;
const total = 5;
const slides = document.getElementById('slides');
const dots = document.querySelectorAll('.onb-dot');
const btn = document.getElementById('next-btn');

function updateUI() {
  slides.style.transform = `translateX(-${current * 100}vw)`;
  dots.forEach((d, i) => d.classList.toggle('active', i === current));
  if (current === total - 1) {
    btn.innerHTML = `Explorer <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>`;
    btn.style.background = 'var(--rouge)';
    btn.style.borderColor = 'var(--rouge)';
    btn.style.boxShadow = 'var(--shadow-sm)';
  }
}

function next() {
  if (current < total - 1) { current++; updateUI(); }
  else finish();
}

function finish() { window.location.href = <?= json_encode(baseUrl('/explore.php')) ?>; }

let startX = 0;
slides.addEventListener('touchstart', e => { startX = e.touches[0].clientX; }, { passive: true });
slides.addEventListener('touchend', e => {
  const diff = startX - e.changedTouches[0].clientX;
  if (Math.abs(diff) > 50) {
    if (diff > 0 && current < total - 1) { current++; updateUI(); }
    else if (diff < 0 && current > 0) { current--; updateUI(); }
  }
}, { passive: true });
</script>
</body>
</html>
