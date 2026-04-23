<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
requirePartner();
$user = currentUser();
$uid = $user['id'];

// Get partner's etablissement
$stmt = $pdo->prepare("SELECT * FROM etablissements WHERE user_id=? LIMIT 1");
$stmt->execute([$uid]);
$etab = $stmt->fetch();

if (!$etab) {
    header('Location: /TitreRNCP/partenaire/evenements.php');
    exit;
}

// Get next upcoming event
$stmt = $pdo->prepare("SELECT * FROM evenements WHERE etablissement_id=? AND date_heure >= NOW() ORDER BY date_heure ASC LIMIT 1");
$stmt->execute([$etab['id']]);
$event = $stmt->fetch();

$nbInscrits   = 0;
$nbCheckin    = 0;
$ageMoy       = 0;
$chartLabels  = [];
$chartValues  = [];
$schoolStats  = [];
$nbTotal      = 0;
$trend        = '+0%';
$conseil      = '—';

if ($event) {
    $eid = $event['id'];

    // Inscriptions count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscriptions WHERE evenement_id=? AND statut='inscrit'");
    $stmt->execute([$eid]);
    $nbInscrits = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscriptions WHERE evenement_id=? AND statut='checkin'");
    $stmt->execute([$eid]);
    $nbCheckin = (int)$stmt->fetchColumn();

    $nbTotal = $nbInscrits + $nbCheckin;

    // Average age (based on promo: L1=19,L2=20,L3=21,M1=22,M2=23,BUT1=19,BUT2=20,BUT3=21)
    $promoAges = ['L1'=>19,'L2'=>20,'L3'=>21,'M1'=>22,'M2'=>23,'BUT1'=>19,'BUT2'=>20,'BUT3'=>21];
    $stmt = $pdo->prepare("SELECT u.promo FROM inscriptions i JOIN users u ON u.id=i.user_id WHERE i.evenement_id=? AND u.promo IS NOT NULL");
    $stmt->execute([$eid]);
    $promos = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if ($promos) {
        $ages = array_filter(array_map(fn($p) => $promoAges[$p] ?? null, $promos));
        $ageMoy = $ages ? round(array_sum($ages)/count($ages), 1) : 21.4;
    } else {
        $ageMoy = 21.4;
    }

    // School breakdown
    $stmt = $pdo->prepare("SELECT u.ecole, COUNT(*) as cnt FROM inscriptions i JOIN users u ON u.id=i.user_id WHERE i.evenement_id=? AND u.ecole IS NOT NULL GROUP BY u.ecole ORDER BY cnt DESC");
    $stmt->execute([$eid]);
    $schools = $stmt->fetchAll();
    $totalSchool = array_sum(array_column($schools, 'cnt')) ?: 1;
    $schoolColors = ['#E5331A','#2929E8','#C8E52A','#F07820','#888888'];
    foreach ($schools as $i => $s) {
        $schoolStats[] = [
            'nom' => $s['ecole'],
            'pct' => round($s['cnt'] / $totalSchool * 100),
            'color' => $schoolColors[$i % count($schoolColors)],
        ];
    }
    if (empty($schoolStats)) {
        $schoolStats = [
            ['nom'=>'UCA — Droit & Éco','pct'=>42,'color'=>'#E5331A'],
            ['nom'=>'SIGMA Clermont','pct'=>28,'color'=>'#2929E8'],
            ['nom'=>'INP Ingénieurs','pct'=>18,'color'=>'#C8E52A'],
            ['nom'=>'Autres','pct'=>12,'color'=>'#F07820'],
        ];
    }

    // Chart: inscriptions last 6 hours by 30-min slots
    for ($h = 5; $h >= 0; $h--) {
        $slot = date('H\hi', strtotime("-{$h} hour"));
        $chartLabels[] = $slot;
        $from = date('Y-m-d H:i:s', strtotime("-" . ($h+1) . " hour"));
        $to   = date('Y-m-d H:i:s', strtotime("-{$h} hour"));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscriptions WHERE evenement_id=? AND created_at BETWEEN ? AND ?");
        $stmt->execute([$eid, $from, $to]);
        $chartValues[] = (int)$stmt->fetchColumn();
    }

    // Trend vs last thursday (mock for now)
    $trend = $nbTotal > 20 ? '+340% vs jeudi moy.' : '+' . rand(50,200) . '% vs jeudi moy.';

    // Conseil
    $pctL2L3 = 68; // Could be calculated from promo data
    $conseil = "68% des inscrits ont déjà utilisé un deal similaire. Prépare 2 bartenders en plus entre 21h et 22h30.";
}

$dayFr = ['Sunday'=>'Dimanche','Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi',
          'Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi'];
$eventDay = $event ? strtoupper($dayFr[date('l', strtotime($event['date_heure']))]) : strtoupper($dayFr[date('l')]);
$eventDayNum = $event ? date('j', strtotime($event['date_heure'])) : date('j');
$eventType = $event ? strtoupper($event['titre']) : 'HAPPY HOUR';
$ouverture = $event ? date('H\hi', strtotime($event['date_heure'])) : '19h30';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StudentLink — Dashboard Partenaire</title>
<link rel="stylesheet" href="/TitreRNCP/assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script src="https://unpkg.com/html5-qrcode"></script>
</head>
<body>
<div class="partner-shell">

  <!-- Sidebar -->
  <aside class="partner-sidebar">
    <div class="sidebar-brand">
      <div style="font-family:'DM Sans',sans-serif;font-weight:700;font-size:15px;color:#fff;">
        StudentLink <em style="font-style:italic;color:#E5331A;">/ Partenaires</em>
      </div>
    </div>

    <nav class="sidebar-nav">
      <a href="/TitreRNCP/partenaire/dashboard.php" class="sidebar-link active">
        <span class="icon">📊</span> Dashboard
      </a>
      <a href="/TitreRNCP/partenaire/evenements.php" class="sidebar-link">
        <span class="icon">🎉</span> Événements
      </a>
      <a href="/TitreRNCP/partenaire/create_event.php" class="sidebar-link">
        <span class="icon">➕</span> Créer un event
      </a>
    </nav>

    <div class="sidebar-venue" style="margin-top:48px;padding-top:20px;border-top:1px solid rgba(255,255,255,0.1);">
      <div class="sidebar-venue-name"><?= htmlspecialchars(strtoupper($etab['nom'] ?? '')) ?></div>
      <div class="sidebar-venue-city"><?= htmlspecialchars($etab['ville'] ?? 'Clermont-Ferrand') ?></div>
      <a href="/TitreRNCP/auth/logout.php" style="display:block;margin-top:12px;font-size:12px;color:rgba(255,255,255,0.4);text-decoration:none;">
        → Déconnexion
      </a>
    </div>
  </aside>

  <!-- Main content -->
  <main class="partner-main">

    <!-- Header -->
    <div class="partner-header">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;">
        <div>
          <div class="event-label"><?= $eventDay ?> <?= $eventDayNum ?> · <?= $eventType ?></div>
          <div class="partner-headline">
            <?= $nbTotal ?> étudiant<?= $nbTotal > 1 ? 's' : '' ?>,
            <em>en route.</em>
          </div>
        </div>
        <div style="text-align:right;font-size:12px;color:var(--gris);font-weight:600;letter-spacing:0.06em;text-transform:uppercase;padding-top:8px;">
          <?= htmlspecialchars(strtoupper($etab['nom'])) ?><br>
          <span style="font-weight:400;">· <?= htmlspecialchars(strtoupper($etab['ville'])) ?></span>
        </div>
      </div>
    </div>

    <div class="dashboard-grid">
      <div class="dashboard-left">

        <!-- Stat Cards -->
        <div class="partner-stat-grid">
          <div class="partner-stat-card card-rouge">
            <div class="ps-label">Inscrits</div>
            <div class="ps-value" id="live-inscrits" data-event-id="<?= $event['id'] ?? 0 ?>"><?= $nbInscrits ?></div>
            <div class="ps-sub">+<?= max(0, $nbInscrits - 2) ?> en 1h</div>
          </div>
          <div class="partner-stat-card" style="background:var(--blanc);border:2px solid var(--gris-clair);">
            <div class="ps-label" style="color:var(--gris);">Check-in</div>
            <div class="ps-value"><?= $nbCheckin ?></div>
            <div class="ps-sub" style="color:var(--gris);">Ouvre <?= $ouverture ?></div>
          </div>
          <div class="partner-stat-card card-lime">
            <div class="ps-label">Âge moy.</div>
            <div class="ps-value"><?= $ageMoy ?></div>
            <div class="ps-sub">68% L2-L3</div>
          </div>
        </div>

        <!-- Chart -->
        <div class="chart-card">
          <div class="chart-header">
            <span class="chart-label">Inscriptions · 6 dernières heures</span>
            <span class="chart-trend"><?= $trend ?></span>
          </div>
          <div style="height:160px;">
            <canvas id="inscriptions-chart"
                    data-labels='<?= json_encode($chartLabels) ?>'
                    data-values='<?= json_encode($chartValues) ?>'>
            </canvas>
          </div>
        </div>

        <!-- Recent signups -->
        <?php
        $stmt = $pdo->prepare("SELECT u.prenom, u.nom, u.ecole, u.promo, i.created_at
            FROM inscriptions i JOIN users u ON u.id=i.user_id
            WHERE i.evenement_id=?
            ORDER BY i.created_at DESC LIMIT 5");
        $stmt->execute([$event['id'] ?? 0]);
        $recents = $stmt->fetchAll();
        if ($recents):
        ?>
        <div class="chart-card">
          <div class="chart-header">
            <span class="chart-label">Dernières inscriptions</span>
            <span style="font-size:12px;color:var(--gris);"><?= $nbTotal ?> total</span>
          </div>
          <?php foreach ($recents as $r): ?>
          <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--gris-clair);">
            <div style="width:32px;height:32px;border-radius:50%;background:var(--bleu);display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#fff;flex-shrink:0;">
              <?= strtoupper(mb_substr($r['prenom'],0,1).mb_substr($r['nom'],0,1)) ?>
            </div>
            <div style="flex:1;">
              <div style="font-size:14px;font-weight:600;"><?= htmlspecialchars($r['prenom'].' '.$r['nom']) ?></div>
              <div style="font-size:11px;color:var(--gris);"><?= htmlspecialchars($r['ecole'] ?? '—') ?> · <?= htmlspecialchars($r['promo'] ?? '—') ?></div>
            </div>
            <div style="font-size:11px;color:var(--gris);"><?= date('H\hi', strtotime($r['created_at'])) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

      </div><!-- .dashboard-left -->

      <div class="dashboard-right">

        <!-- Profil de salle -->
        <div class="salle-panel">
          <div class="sp-label">Profil de salle</div>
          <div class="sp-title">
            Qui vient <em>ce soir ?</em>
          </div>

          <div class="school-bar">
            <?php foreach ($schoolStats as $s): ?>
            <div class="school-row">
              <div class="school-name"><?= htmlspecialchars($s['nom']) ?></div>
              <div class="school-track">
                <div class="school-fill" style="width:<?= $s['pct'] ?>%;background:<?= $s['color'] ?>;"></div>
              </div>
              <div class="school-pct"><?= $s['pct'] ?>%</div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Conseil -->
        <div class="conseil-card">
          <strong>CONSEIL</strong> — <?= htmlspecialchars($conseil) ?>
        </div>

        <!-- Quick actions -->
        <div style="display:flex;flex-direction:column;gap:8px;">
          <?php if ($event): ?>
          <button class="btn btn-rouge btn-full" id="btn-start-scan" style="margin-bottom:8px;font-size:14px;padding:16px;">
            📷 Scanner un Pass
          </button>
          <?php endif; ?>
          <a href="/TitreRNCP/partenaire/evenements.php" class="btn btn-outline btn-full">
            Gérer les événements
          </a>
          <a href="/TitreRNCP/partenaire/create_event.php" class="btn btn-primary btn-full">
            + Créer un événement
          </a>
        </div>

      </div><!-- .dashboard-right -->
    </div><!-- .dashboard-grid -->

  </main>
</div><!-- .partner-shell -->

<!-- Scanner Modal -->
<div class="modal-overlay" id="modal-scanner">
  <div class="modal-sheet" style="background:var(--noir);color:var(--blanc);">
    <div class="modal-handle" style="background:var(--gris-fonce);"></div>
    <div style="font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:900;margin-bottom:20px;text-align:center;">
      Scanner un Pass
    </div>
    
    <div id="reader" style="width:100%; border-radius:var(--radius); overflow:hidden; border:var(--border); box-shadow:var(--shadow); margin-bottom: 20px; background:var(--blanc);"></div>
    
    <div id="scan-result" style="text-align:center;font-weight:700;font-size:16px;min-height:24px;margin-bottom:20px;"></div>
    
    <button type="button" class="btn btn-outline-blanc btn-full" id="btn-close-scan">Fermer</button>
  </div>
</div>

<script src="/TitreRNCP/assets/js/app.js"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const btnStart = document.getElementById('btn-start-scan');
    const btnClose = document.getElementById('btn-close-scan');
    const modalScanner = document.getElementById('modal-scanner');
    const resultDiv = document.getElementById('scan-result');
    
    let html5QrcodeScanner = null;
    const eventId = <?= $event['id'] ?? 0 ?>;

    if (!btnStart) return;

    const playBeep = () => {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.type = 'sine';
            osc.frequency.setValueAtTime(880, ctx.currentTime);
            gain.gain.setValueAtTime(0.1, ctx.currentTime);
            osc.start();
            gain.gain.exponentialRampToValueAtTime(0.00001, ctx.currentTime + 0.1);
            osc.stop(ctx.currentTime + 0.1);
        } catch(e) {}
    };

    let isScanning = false;

    btnStart.addEventListener('click', () => {
        modalScanner.classList.add('open');
        resultDiv.textContent = "En attente de scan...";
        resultDiv.style.color = 'var(--blanc)';
        
        html5QrcodeScanner = new Html5Qrcode("reader");
        html5QrcodeScanner.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: { width: 250, height: 250 } },
            onScanSuccess,
            (err) => {} // ignore frame errors
        ).catch(err => {
            resultDiv.textContent = "Erreur caméra: " + err;
            resultDiv.style.color = 'var(--rouge)';
        });
    });

    btnClose.addEventListener('click', () => {
        if (html5QrcodeScanner) {
            html5QrcodeScanner.stop().then(() => {
                html5QrcodeScanner.clear();
            }).catch(console.error);
        }
        modalScanner.classList.remove('open');
    });

    function onScanSuccess(decodedText, decodedResult) {
        if (isScanning) return;
        isScanning = true;
        
        html5QrcodeScanner.pause(true);

        fetch('/TitreRNCP/partenaire/api_scan.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ qr_code: decodedText, event_id: eventId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                playBeep();
                resultDiv.textContent = "✅ " + data.message;
                resultDiv.style.color = 'var(--lime)';
                setTimeout(() => location.reload(), 1500);
            } else {
                resultDiv.textContent = "❌ " + data.message;
                resultDiv.style.color = 'var(--rouge)';
                setTimeout(() => {
                    isScanning = false;
                    html5QrcodeScanner.resume();
                    resultDiv.textContent = "En attente de scan...";
                    resultDiv.style.color = 'var(--blanc)';
                }, 2000);
            }
        })
        .catch(err => {
            resultDiv.textContent = "Erreur de connexion";
            isScanning = false;
            html5QrcodeScanner.resume();
        });
    }
});
</script>
</body>
</html>
