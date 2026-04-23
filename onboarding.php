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
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<style>
  body { background: var(--bg); overflow: hidden; height: 100dvh; }

  .onb-wrapper {
    height: 100dvh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
  }

  .onb-skip {
    position: fixed;
    top: env(safe-area-inset-top, 16px);
    right: 20px;
    z-index: 10;
    background: none;
    border: none;
    font-size: 12px;
    font-weight: 800;
    color: var(--gris);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    cursor: pointer;
    padding: 12px 4px;
  }

  .onb-slides {
    flex: 1;
    display: flex;
    transition: transform 360ms cubic-bezier(0.4, 0, 0.2, 1);
    will-change: transform;
  }

  .onb-slide {
    min-width: 100vw;
    padding: 80px 28px 28px;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }

  .onb-icon-wrap {
    width: 80px;
    height: 80px;
    border: 2px solid var(--noir);
    box-shadow: var(--shadow);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 40px;
    background: var(--blanc);
  }

  .onb-icon-wrap.accent { background: var(--rouge); border-color: var(--noir); }
  .onb-icon-wrap.accent svg { stroke: var(--blanc); }
  .onb-icon-wrap.blue { background: var(--bleu); border-color: var(--noir); }
  .onb-icon-wrap.blue svg { stroke: var(--blanc); }
  .onb-icon-wrap.lime { background: var(--lime); border-color: var(--noir); }

  .onb-tag {
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: var(--rouge);
    margin-bottom: 10px;
  }

  .onb-title {
    font-family: 'Playfair Display', serif;
    font-size: 2.8rem;
    font-weight: 900;
    color: var(--noir);
    line-height: 1.05;
    margin-bottom: 20px;
  }

  .onb-title em {
    font-style: italic;
    color: var(--rouge);
  }

  .onb-desc {
    font-size: 15px;
    color: var(--gris-fonce);
    line-height: 1.65;
    border-left: 3px solid var(--noir);
    padding-left: 16px;
    max-width: 320px;
  }

  .onb-footer {
    padding: 20px 28px calc(env(safe-area-inset-bottom, 16px) + 24px);
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-top: 2px solid var(--noir);
    background: var(--bg);
  }

  .onb-dots {
    display: flex;
    gap: 6px;
    align-items: center;
  }

  .onb-dot {
    width: 8px;
    height: 8px;
    background: var(--gris-clair);
    border: 1.5px solid var(--gris);
    transition: all 280ms ease;
  }

  .onb-dot.active {
    background: var(--rouge);
    border-color: var(--rouge);
    width: 28px;
  }

  .onb-btn {
    background: var(--noir);
    color: var(--blanc);
    border: 2px solid var(--noir);
    box-shadow: var(--shadow-sm);
    padding: 14px 24px;
    font-size: 13px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: box-shadow 120ms, transform 120ms;
  }

  .onb-btn:active {
    box-shadow: none;
    transform: translate(2px, 2px);
  }
</style>
</head>
<body>
<div class="onb-wrapper">

  <button class="onb-skip" onclick="finish()">Passer</button>

  <div class="onb-slides" id="slides">

    <!-- Slide 1 : Bienvenue -->
    <div class="onb-slide">
      <div class="onb-icon-wrap accent">
        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
        </svg>
      </div>
      <div class="onb-tag">Bienvenue</div>
      <div class="onb-title">Salut<br><em><?= htmlspecialchars($prenom) ?></em>.</div>
      <div class="onb-desc">StudentLink connecte les étudiants aux meilleurs plans de leur ville — événements, sport et rencontres.</div>
    </div>

    <!-- Slide 2 : Explore -->
    <div class="onb-slide">
      <div class="onb-icon-wrap">
        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="var(--noir)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
        </svg>
      </div>
      <div class="onb-tag">Explore</div>
      <div class="onb-title">Les bons<br>plans du<br><em>moment.</em></div>
      <div class="onb-desc">Bars, boîtes, restos, afterworks. Des réductions exclusives et des offres flash réservées aux étudiants StudentLink.</div>
    </div>

    <!-- Slide 3 : Squads -->
    <div class="onb-slide">
      <div class="onb-icon-wrap blue">
        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
          <circle cx="9" cy="7" r="4"/>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
        </svg>
      </div>
      <div class="onb-tag">Squads</div>
      <div class="onb-title">Trouve<br>ton <em>squad</em><br>sportif.</div>
      <div class="onb-desc">Running, vélo, muscu… Rejoins des groupes d'étudiants qui partagent tes passions et bougez ensemble.</div>
    </div>

    <!-- Slide 4 : Wallet -->
    <div class="onb-slide">
      <div class="onb-icon-wrap lime">
        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="var(--noir)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/>
          <line x1="1" y1="10" x2="23" y2="10"/>
        </svg>
      </div>
      <div class="onb-tag">Wallet</div>
      <div class="onb-title">Ton pass<br><em>numérique.</em></div>
      <div class="onb-desc">Tous tes pass événements au même endroit. Présente ton QR code à l'entrée — c'est tout.</div>
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
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
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
    btn.innerHTML = `C'est parti ! <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>`;
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
