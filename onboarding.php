<?php
require_once __DIR__ . '/includes/auth_check.php';
requireLogin();
$prenom = $_SESSION['user_prenom'] ?? 'toi';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title>Bienvenue — StudentLink</title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="icon" type="image/png" href="/Logo.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<style>
  body { background: var(--bg); overflow: hidden; height: 100dvh; }

  .onb-wrapper { height: 100dvh; display: flex; flex-direction: column; overflow: hidden; }

  .onb-skip {
    position: fixed;
    top: calc(env(safe-area-inset-top, 0px) + 16px);
    right: 20px;
    z-index: 10;
    background: none;
    border: none;
    font-size: 11px;
    font-weight: 800;
    color: var(--gris);
    text-transform: uppercase;
    letter-spacing: 0.1em;
    cursor: pointer;
    padding: 8px;
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

  .slide-1 .onb-visual { background: var(--rouge); }
  .slide-2 .onb-visual { background: var(--noir); }
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
    background: rgba(255,255,255,0.15);
    border: 3px solid rgba(255,255,255,0.4);
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .slide-4 .onb-big-icon { background: rgba(0,0,0,0.1); border-color: rgba(0,0,0,0.2); }
  .slide-4 .onb-big-icon svg { stroke: var(--noir); }

  /* Floating stat pills */
  .onb-stat {
    background: var(--blanc);
    border: 2px solid var(--noir);
    box-shadow: 3px 3px 0 var(--noir);
    padding: 10px 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    position: absolute;
  }

  .onb-stat-num {
    font-family: 'Playfair Display', serif;
    font-size: 1.6rem;
    font-weight: 900;
    color: var(--noir);
    line-height: 1;
  }

  .onb-stat-label {
    font-size: 10px;
    font-weight: 700;
    color: var(--gris-fonce);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    line-height: 1.3;
  }

  .stat-tl { top: 20px; left: 20px; }
  .stat-br { bottom: 20px; right: 20px; }

  /* Mockup cards inside visual */
  .onb-mock-card {
    background: var(--blanc);
    border: 2px solid var(--noir);
    box-shadow: 4px 4px 0 rgba(0,0,0,0.3);
    padding: 12px 16px;
    width: 200px;
  }

  .onb-mock-card .mc-tag {
    font-size: 9px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--rouge);
    margin-bottom: 4px;
  }

  .onb-mock-card .mc-title {
    font-family: 'Playfair Display', serif;
    font-size: 1.1rem;
    font-weight: 900;
    color: var(--noir);
    margin-bottom: 6px;
  }

  .onb-mock-card .mc-meta {
    font-size: 10px;
    color: var(--gris);
    font-weight: 600;
  }

  .onb-mock-badge {
    display: inline-block;
    background: var(--rouge);
    color: var(--blanc);
    font-size: 9px;
    font-weight: 800;
    padding: 3px 8px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
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
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--rouge);
    margin-bottom: 8px;
  }

  .slide-3 .onb-tag { color: var(--bleu); }
  .slide-4 .onb-tag { color: var(--noir); }

  .onb-title {
    font-family: 'Playfair Display', serif;
    font-size: 2.2rem;
    font-weight: 900;
    color: var(--noir);
    line-height: 1.05;
    margin-bottom: 14px;
  }

  .onb-title em { font-style: italic; color: var(--rouge); }
  .slide-3 .onb-title em { color: var(--bleu); }

  .onb-desc {
    font-size: 14px;
    color: var(--gris-fonce);
    line-height: 1.6;
  }

  /* ── Footer ── */
  .onb-footer {
    padding: 16px 28px calc(env(safe-area-inset-bottom, 0px) + 20px);
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-top: 2px solid var(--noir);
    background: var(--bg);
    flex-shrink: 0;
  }

  .onb-dots { display: flex; gap: 6px; align-items: center; }

  .onb-dot {
    width: 8px; height: 8px;
    background: var(--gris-clair);
    border: 1.5px solid var(--gris);
    transition: all 280ms ease;
  }

  .onb-dot.active { background: var(--rouge); border-color: var(--rouge); width: 24px; }

  .onb-btn {
    background: var(--noir);
    color: var(--blanc);
    border: 2px solid var(--noir);
    box-shadow: 3px 3px 0 var(--rouge);
    padding: 13px 22px;
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: box-shadow 100ms, transform 100ms;
  }

  .onb-btn:active { box-shadow: none; transform: translate(2px, 2px); }
</style>
</head>
<body>
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
          <div class="onb-big-icon">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
              <polyline points="9 22 9 12 15 12 15 22"/>
            </svg>
          </div>
        </div>
        <div class="onb-stat stat-br">
          <div>
            <div class="onb-stat-num">100%</div>
            <div class="onb-stat-label">étudiant<br>gratuit</div>
          </div>
        </div>
      </div>
      <div class="onb-text">
        <div class="onb-tag">StudentLink</div>
        <div class="onb-title">La vie étudiante<br>à prix <em>réduit.</em></div>
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
            <div class="onb-mock-badge" style="background:var(--bleu);">Entrée gratuite</div>
          </div>
        </div>
      </div>
      <div class="onb-text">
        <div class="onb-tag">Explore</div>
        <div class="onb-title">Les bons plans<br>du <em>moment.</em></div>
        <div class="onb-desc">Découvre les événements près de chez toi, inscris-toi en un tap et reçois ton pass numérique directement dans l'app.</div>
      </div>
    </div>

    <!-- SLIDE 3 : Squads -->
    <div class="onb-slide slide-3">
      <div class="onb-visual">
        <div class="onb-stat stat-tl" style="flex-direction:column;gap:2px;padding:10px 14px;">
          <div class="onb-stat-num" style="color:var(--bleu);">12</div>
          <div class="onb-stat-label">squads<br>actifs</div>
        </div>
        <div class="onb-visual-inner">
          <div class="onb-big-icon">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
              <circle cx="9" cy="7" r="4"/>
              <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
              <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
          </div>
        </div>
        <div class="onb-stat stat-br" style="flex-direction:column;gap:2px;padding:10px 14px;">
          <div class="onb-stat-num" style="color:var(--bleu);">3</div>
          <div class="onb-stat-label">sports<br>dispo</div>
        </div>
      </div>
      <div class="onb-text">
        <div class="onb-tag">Squads</div>
        <div class="onb-title">Bouge avec<br>les <em>bons.</em></div>
        <div class="onb-desc">Running, vélo, muscu… Rejoins un groupe d'étudiants qui partagent tes passions. Ou crée le tien en 30 secondes.</div>
      </div>
    </div>

    <!-- SLIDE 4 : Let's go -->
    <div class="onb-slide slide-4">
      <div class="onb-visual">
        <div class="onb-visual-inner">
          <div class="onb-big-icon">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--noir)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
              <line x1="1" y1="10" x2="23" y2="10"/>
            </svg>
          </div>
          <div class="onb-mock-card" style="background:var(--noir);border-color:var(--noir);">
            <div class="mc-tag" style="color:var(--lime);">Ton pass</div>
            <div class="mc-title" style="color:var(--blanc);"><?= htmlspecialchars($prenom) ?></div>
            <div class="mc-meta" style="color:var(--gris);">Happy Hour · Ce soir</div>
            <div style="margin-top:10px;background:var(--blanc);height:48px;width:48px;display:flex;align-items:center;justify-content:center;">
              <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="var(--noir)" stroke-width="1.5">
                <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                <rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="3" height="3"/>
              </svg>
            </div>
          </div>
        </div>
      </div>
      <div class="onb-text">
        <div class="onb-tag">C'est parti</div>
        <div class="onb-title">Prêt à<br><em>kiffer</em> ?</div>
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
const total = 4;
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
    btn.style.boxShadow = '3px 3px 0 var(--noir)';
  }
}

function next() {
  if (current < total - 1) { current++; updateUI(); }
  else finish();
}

function finish() { window.location.href = '/explore.php'; }

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
