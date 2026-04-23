<?php
require_once __DIR__ . '/includes/auth_check.php';
requireLogin();
$prenom = $_SESSION['user_prenom'] ?? 'toi';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Bienvenue sur StudentLink</title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="icon" type="image/png" href="/Logo.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<style>
  body { background: var(--noir); overflow: hidden; }

  .onb-wrapper {
    height: 100dvh;
    display: flex;
    flex-direction: column;
    position: relative;
  }

  .onb-slides {
    flex: 1;
    display: flex;
    transition: transform 380ms cubic-bezier(0.4, 0, 0.2, 1);
    will-change: transform;
  }

  .onb-slide {
    min-width: 100vw;
    padding: 60px 32px 32px;
    display: flex;
    flex-direction: column;
    justify-content: center;
  }

  .onb-icon {
    font-size: 64px;
    margin-bottom: 32px;
    display: block;
  }

  .onb-tag {
    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--gris);
    margin-bottom: 12px;
  }

  .onb-title {
    font-family: 'Playfair Display', serif;
    font-size: 2.6rem;
    font-weight: 900;
    color: var(--blanc);
    line-height: 1.1;
    margin-bottom: 20px;
  }

  .onb-title em {
    font-style: italic;
    color: var(--rouge);
  }

  .onb-desc {
    font-size: 15px;
    color: rgba(255,255,255,0.6);
    line-height: 1.6;
    max-width: 320px;
  }

  .onb-footer {
    padding: 24px 32px 48px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
  }

  .onb-dots {
    display: flex;
    gap: 8px;
  }

  .onb-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: rgba(255,255,255,0.2);
    transition: all 300ms ease;
  }

  .onb-dot.active {
    background: var(--rouge);
    width: 24px;
    border-radius: 4px;
  }

  .onb-btn {
    background: var(--rouge);
    color: var(--blanc);
    border: none;
    padding: 14px 28px;
    font-size: 14px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    cursor: pointer;
    transition: opacity 150ms;
  }

  .onb-btn:active { opacity: 0.8; }

  .onb-skip {
    position: absolute;
    top: 20px;
    right: 24px;
    background: none;
    border: none;
    color: rgba(255,255,255,0.3);
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    padding: 8px;
  }

  /* Slide accent colors */
  .onb-slide:nth-child(1) .onb-icon { filter: drop-shadow(0 0 20px rgba(229,51,26,0.5)); }
  .onb-slide:nth-child(2) .onb-icon { filter: drop-shadow(0 0 20px rgba(41,41,232,0.5)); }
  .onb-slide:nth-child(3) .onb-icon { filter: drop-shadow(0 0 20px rgba(200,229,42,0.4)); }
  .onb-slide:nth-child(4) .onb-icon { filter: drop-shadow(0 0 20px rgba(229,51,26,0.5)); }
</style>
</head>
<body>
<div class="onb-wrapper">

  <button class="onb-skip" onclick="finish()">Passer</button>

  <div class="onb-slides" id="slides">

    <!-- Slide 1 -->
    <div class="onb-slide">
      <span class="onb-icon">👋</span>
      <div class="onb-tag">Bienvenue</div>
      <div class="onb-title">Salut <em><?= htmlspecialchars($prenom) ?></em>,<br>content de t'avoir.</div>
      <div class="onb-desc">StudentLink, c'est la plateforme qui connecte les étudiants aux meilleurs plans de leur ville.</div>
    </div>

    <!-- Slide 2 -->
    <div class="onb-slide">
      <span class="onb-icon">🎉</span>
      <div class="onb-tag">Explore</div>
      <div class="onb-title">Des <em>événements</em><br>rien que pour toi.</div>
      <div class="onb-desc">Bars, boîtes, restos, afterworks… Découvre les bons plans du moment avec des réductions exclusives et des offres flash.</div>
    </div>

    <!-- Slide 3 -->
    <div class="onb-slide">
      <span class="onb-icon">🏃</span>
      <div class="onb-tag">Squads</div>
      <div class="onb-title">Rejoins un <em>squad</em><br>sportif.</div>
      <div class="onb-desc">Running, vélo, muscu… Trouve des étudiants qui partagent tes passions et organise des sorties ensemble.</div>
    </div>

    <!-- Slide 4 -->
    <div class="onb-slide">
      <span class="onb-icon">💳</span>
      <div class="onb-tag">Wallet</div>
      <div class="onb-title">Ton pass<br><em>numérique.</em></div>
      <div class="onb-desc">Retrouve tous tes pass événements en un seul endroit. Présente ton QR code à l'entrée et profite !</div>
    </div>

  </div>

  <div class="onb-footer">
    <div class="onb-dots" id="dots">
      <div class="onb-dot active"></div>
      <div class="onb-dot"></div>
      <div class="onb-dot"></div>
      <div class="onb-dot"></div>
    </div>
    <button class="onb-btn" id="next-btn" onclick="next()">Suivant →</button>
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
  btn.textContent = current === total - 1 ? "C'est parti !" : 'Suivant →';
}

function next() {
  if (current < total - 1) {
    current++;
    updateUI();
  } else {
    finish();
  }
}

function finish() {
  window.location.href = '/explore.php';
}

// Swipe support
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
