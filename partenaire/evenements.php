<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/crm.php';
require_once __DIR__ . '/../includes/sponsoring.php';
requirePartner();
$user = currentUser();
$uid = $user['id'];

$stmt = $pdo->prepare("SELECT * FROM etablissements WHERE user_id=? LIMIT 1");
$stmt->execute([$uid]);
$etab = $stmt->fetch();

exigerAbonnement($pdo, $etab);

if (!$etab) {
    echo "<p>Aucun établissement trouvé. <a href='" . baseUrl('/auth/logout.php') . "'>Déconnexion</a></p>";
    exit;
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event'])) {
    csrfVerify();
    $eid = (int)$_POST['delete_event'];
    $stmt = $pdo->prepare("DELETE FROM evenements WHERE id=? AND etablissement_id=?");
    $stmt->execute([$eid, $etab['id']]);
    header('Location: ' . baseUrl('/partenaire/evenements.php?deleted=1'));
    exit;
}

// Fetch events
$stmt = $pdo->prepare("
    SELECT e.*,
           (SELECT COUNT(*) FROM inscriptions i WHERE i.evenement_id=e.id AND i.statut != 'annule') AS nb_inscrits,
           (SELECT COUNT(*) FROM inscriptions i WHERE i.evenement_id=e.id AND i.statut='checkin') AS nb_checkin
    FROM evenements e
    WHERE e.etablissement_id=?
    ORDER BY e.date_heure DESC
");
$stmt->execute([$etab['id']]);
$evenements = $stmt->fetchAll();

$typeLabels = ['bar'=>'Bar','boite'=>'Boîte','resto'=>'Resto','afterwork'=>'Afterwork'];
?>
<?php pageDebut('Linkee — Mes événements', ['univers' => 'pro', 'scripts' => ['/assets/vendor/html5-qrcode.min.js']]); ?>
<div class="partner-shell">

  <aside class="partner-sidebar">
    <div class="sidebar-brand">
      <?= marqueLinkee('pro') ?>
    </div>
    <nav class="sidebar-nav">
      <a href="<?= baseUrl('/partenaire/dashboard.php') ?>" class="sidebar-link">
        <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg> Dashboard
      </a>
      <a href="<?= baseUrl('/partenaire/evenements.php') ?>" class="sidebar-link active">
        <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> Événements
      </a>
      <a href="<?= baseUrl('/partenaire/create_event.php') ?>" class="sidebar-link">
        <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg> Créer un event
      </a>
      <a href="<?= baseUrl('/partenaire/photos.php') ?>" class="sidebar-link">
        <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg> Photos
      </a>
      <a href="<?= baseUrl('/partenaire/abonnement.php') ?>" class="sidebar-link">
        <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg> Abonnement
      </a>
    </nav>
    <div class="sidebar-venue" style="margin-top:48px;padding-top:20px;border-top:1px solid var(--gris-clair);">
      <div class="sidebar-venue-name"><?= htmlspecialchars(mb_strtoupper($etab['nom'])) ?></div>
      <div class="sidebar-venue-city"><?= htmlspecialchars($etab['ville']) ?></div>
      <a href="<?= baseUrl('/auth/logout.php') ?>" class="lien-action" style="margin-top:12px;font-size:var(--fs-2);color:var(--gris);text-decoration:none;">
        → Déconnexion
      </a>
    </div>
  </aside>

  <main class="partner-main">
    <div style="display:flex;align-items:flex-end;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:32px;">
      <div>
        <div class="label text-gris" style="margin-bottom:6px;">Gestion des événements</div>
        <h1 class="titre-page" style="font-family:var(--font-display);font-size:var(--fs-9);font-weight:var(--fw-black);line-height:var(--lh-tight);letter-spacing:var(--ls-display);">
          Mes <?= count($evenements) ?> événement<?= count($evenements)>1?'s':'' ?>
        </h1>
      </div>
      <a href="<?= baseUrl('/partenaire/create_event.php') ?>" class="btn btn-primary">
        + Créer un événement
      </a>
    </div>

    <?= csrfFlash() ?>
    <?php if (isset($_GET['deleted'])): ?>
      <div class="form-success">Événement supprimé.</div>
    <?php endif; ?>
    <?php if (isset($_GET['created'])): ?>
      <div class="form-success">Événement créé avec succès !</div>
    <?php endif; ?>
    <?php if (isset($_GET['updated'])): ?>
      <div class="form-success">Événement mis à jour avec succès !</div>
    <?php endif; ?>

    <div style="background:var(--blanc);border:1px solid var(--gris-clair);border-radius:var(--radius);overflow:hidden;">
      <div style="overflow-x:auto;-webkit-overflow-scrolling:touch;">
      <table class="events-table">
        <thead>
          <tr>
            <th>Titre</th>
            <th>Type</th>
            <th>Date</th>
            <th>Inscrits</th>
            <th>Check-in</th>
            <th>Réduction</th>
            <th>Statut</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($evenements)): ?>
          <tr>
            <td colspan="8" style="text-align:center;padding:40px;color:var(--gris);">
              Aucun événement. <a href="<?= baseUrl('/partenaire/create_event.php') ?>" style="color:var(--sur-rouge-clair);font-weight:var(--fw-semibold);">Créez-en un !</a>
            </td>
          </tr>
          <?php endif; ?>
          <?php foreach ($evenements as $e):
            $isPast = strtotime($e['date_heure']) < time();
            $isFull = $e['nb_inscrits'] >= $e['quota'];
          ?>
          <tr>
            <td data-label="Titre">
              <div style="font-weight:var(--fw-semibold);"><?= htmlspecialchars($e['titre']) ?></div>
              <?php if ($e['is_flash']): ?><span class="badge badge-flash" style="margin-top:4px;display:inline-block;">Flash</span><?php endif; ?>
              <?php if ($e['is_gratuit']): ?><span class="badge badge-gratuit" style="margin-top:4px;display:inline-block;">Gratuit</span><?php endif; ?>
              <?php if (!empty($e['is_sponsorise'])): ?>
                <span class="badge badge-sponsorise" style="margin-top:4px;display:inline-block;"
                      title="<?= htmlspecialchars(libelleSponsoring($e['sponsor_formule'])) ?><?= $e['sponsor_jusqu_au'] ? " · jusqu'au " . dateFr($e['sponsor_jusqu_au'], 'j M') : '' ?>">
                  <?= sponsoringActif($e) ? 'Sponsorisé' : 'Sponsoring terminé' ?>
                </span>
              <?php endif; ?>
            </td>
            <td data-label="Type"><span class="badge badge-<?= $e['type'] ?>"><?= $typeLabels[$e['type']] ?></span></td>
            <td data-label="Date" style="white-space:nowrap;color:var(--gris-fonce);"><?= dateFr($e['date_heure'], 'D j M · H\hi') ?></td>
            <td data-label="Inscrits">
              <div style="font-weight:var(--fw-bold);"><?= $e['nb_inscrits'] ?>/<?= $e['quota'] ?></div>
              <div style="height:3px;background:var(--gris-clair);border-radius:2px;margin-top:4px;width:60px;overflow:hidden;">
                <div style="height:100%;background:var(--rouge);width:<?= $e['quota']>0?round($e['nb_inscrits']/$e['quota']*100):0 ?>%;border-radius:2px;"></div>
              </div>
            </td>
            <td data-label="Check-in" style="font-weight:var(--fw-bold);"><?= $e['nb_checkin'] ?></td>
            <td data-label="Réduction"><?= $e['reduction'] > 0 ? '-'.$e['reduction'].'%' : ($e['is_gratuit'] ? 'Gratuit' : '—') ?></td>
            <td data-label="Statut">
              <?php if ($isPast): ?>
                <span style="color:var(--gris);font-size:var(--fs-2);font-weight:var(--fw-semibold);">Passé</span>
              <?php elseif ($isFull): ?>
                <span style="color:var(--danger);font-size:var(--fs-2);font-weight:var(--fw-semibold);">Complet</span>
              <?php else: ?>
                <span style="color:var(--succes);font-size:var(--fs-2);font-weight:var(--fw-semibold);">Actif</span>
              <?php endif; ?>
            </td>
            <td data-label="Actions" style="white-space:nowrap;">
              <?php if (!$isPast): ?>
              <button type="button" class="btn-icon btn-open-scanner" data-event-id="<?= $e['id'] ?>"
                      title="Scanner" aria-label="Scanner les pass de cet événement">
                <svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><line x1="14" y1="14" x2="21" y2="14"/><line x1="14" y1="21" x2="21" y2="21"/><line x1="17.5" y1="14" x2="17.5" y2="21"/></svg>
              </button>
              <?php endif; ?>
              <a href="<?= baseUrl('/partenaire/edit_event.php?id=' . $e['id']) ?>"
                 class="btn-icon"
                 title="Modifier" aria-label="Modifier"><svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.12 2.12 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></a>
              <form method="POST" onsubmit="return confirm('Supprimer cet événement ?')" style="display:inline;">
                <?= csrfField() ?>
                <input type="hidden" name="delete_event" value="<?= $e['id'] ?>">
                <button type="submit" class="btn-icon danger" title="Supprimer"><svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </div>
  </main>
</div>

<!-- Scanner Modal — un bouton « Scanner » par événement (pas seulement la
     prochaine soirée du tableau de bord) : quand un établissement organise
     plusieurs soirées le même soir, chacune a son propre scan. -->
<div class="modal-overlay" id="modal-scanner">
  <div class="modal-sheet" style="background:var(--noir);color:var(--blanc);border-color:var(--noir);">
    <div class="modal-handle" style="background:var(--gris-fonce);"></div>
    <div style="font-family:var(--font-display);font-size:var(--fs-7);font-weight:var(--fw-black);letter-spacing:var(--ls-display);margin-bottom:20px;text-align:center;">
      Scanner un Pass
    </div>

    <div id="reader" style="width:100%; border-radius:var(--radius); overflow:hidden; border:var(--border); box-shadow:var(--shadow); margin-bottom: 20px; background:var(--blanc);"></div>

    <div id="scan-result" style="text-align:center;font-weight:var(--fw-bold);font-size:var(--fs-5);min-height:24px;margin-bottom:20px;"></div>

    <button type="button" class="btn btn-outline btn-full" id="btn-open-manuel" style="color:var(--blanc);border-color:var(--blanc);margin-bottom:8px;">
      Le scan ne marche pas ? Valider manuellement
    </button>
    <button type="button" class="btn btn-outline btn-full" id="btn-close-scan" style="color:var(--blanc);border-color:var(--blanc);">Fermer</button>
  </div>
</div>

<!-- Validation manuelle : cherche parmi les inscrits pas encore check-in de
     CET événement et les valide sans scanner. -->
<div class="modal-overlay" id="modal-manuel">
  <div class="modal-sheet">
    <div class="modal-handle"></div>
    <div style="font-family:var(--font-display);font-size:var(--fs-7);font-weight:var(--fw-black);letter-spacing:var(--ls-display);margin-bottom:8px;">
      Valider manuellement
    </div>
    <p class="aide-champ" style="margin-bottom:16px;">Cherche la personne parmi les inscrits pas encore validés.</p>
    <input type="text" id="manuel-recherche" placeholder="Prénom ou nom..." style="margin-bottom:16px;">
    <div id="manuel-liste" style="display:flex;flex-direction:column;gap:8px;margin-bottom:16px;max-height:50vh;overflow-y:auto;"></div>
    <button type="button" class="btn btn-outline btn-full" data-modal-close>Fermer</button>
  </div>
</div>

<script src="<?= asset('/assets/js/app.js') ?>"></script>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const modalScanner = document.getElementById('modal-scanner');
    const modalManuel = document.getElementById('modal-manuel');
    const resultDiv = document.getElementById('scan-result');
    const btnOpenManuel = document.getElementById('btn-open-manuel');
    const btnCloseScan = document.getElementById('btn-close-scan');
    const rechercheInput = document.getElementById('manuel-recherche');
    const listeDiv = document.getElementById('manuel-liste');

    let html5QrcodeScanner = null;
    let eventIdActif = 0;
    let isScanning = false;

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

    function validerInscription(payload) {
        return fetch(BASE + '/partenaire/api_scan.php', {
            method: 'POST', headers: enTetesJson(), body: JSON.stringify(payload)
        }).then(res => res.json());
    }

    function onScanSuccess(decodedText) {
        if (isScanning) return;
        isScanning = true;
        html5QrcodeScanner.pause(true);

        validerInscription({ qr_code: decodedText, event_id: eventIdActif }).then(data => {
            if (data.success) {
                playBeep();
                resultDiv.textContent = data.message;
                resultDiv.style.color = 'var(--lime)';
                setTimeout(() => location.reload(), 1500);
            } else {
                resultDiv.textContent = data.message || 'Pass invalide';
                resultDiv.style.color = 'var(--rouge)';
                setTimeout(() => { resultDiv.textContent = 'En attente de scan...'; resultDiv.style.color = 'var(--blanc)'; html5QrcodeScanner.resume(); isScanning = false; }, 2000);
            }
        }).catch(() => {
            resultDiv.textContent = 'Erreur réseau';
            resultDiv.style.color = 'var(--rouge)';
            setTimeout(() => { resultDiv.textContent = 'En attente de scan...'; resultDiv.style.color = 'var(--blanc)'; html5QrcodeScanner.resume(); isScanning = false; }, 2000);
        });
    }

    document.querySelectorAll('.btn-open-scanner').forEach(btn => {
        btn.addEventListener('click', () => {
            eventIdActif = parseInt(btn.dataset.eventId, 10);
            window.ouvrirModale ? window.ouvrirModale(modalScanner) : modalScanner.classList.add('open');
            resultDiv.textContent = 'En attente de scan...';
            resultDiv.style.color = 'var(--blanc)';

            html5QrcodeScanner = new Html5Qrcode('reader');
            html5QrcodeScanner.start(
                { facingMode: 'environment' },
                { fps: 10, qrbox: { width: 250, height: 250 } },
                onScanSuccess,
                () => {}
            ).catch(err => {
                resultDiv.textContent = 'Erreur caméra: ' + err;
                resultDiv.style.color = 'var(--rouge)';
            });
        });
    });

    if (btnCloseScan) btnCloseScan.addEventListener('click', () => {
        if (html5QrcodeScanner) {
            html5QrcodeScanner.stop().then(() => html5QrcodeScanner.clear()).catch(() => {});
        }
        window.fermerModale ? window.fermerModale(modalScanner) : modalScanner.classList.remove('open');
    });

    function chargerInvites(q) {
        listeDiv.innerHTML = '<p style="color:var(--gris);">Chargement...</p>';
        fetch(BASE + '/partenaire/api_invites.php?event_id=' + eventIdActif + '&q=' + encodeURIComponent(q || ''))
            .then(res => res.json())
            .then(data => {
                if (!data.success) { listeDiv.innerHTML = '<p style="color:var(--gris);">' + (data.message || 'Erreur') + '</p>'; return; }
                if (data.invites.length === 0) { listeDiv.innerHTML = '<p style="color:var(--gris);">Personne à faire correspondre.</p>'; return; }
                listeDiv.innerHTML = '';
                data.invites.forEach(inv => {
                    const ligne = document.createElement('div');
                    ligne.style.cssText = 'display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 0;border-bottom:1px solid var(--gris-clair);';
                    ligne.innerHTML = `
                        <div style="min-width:0;">
                          <div style="font-weight:var(--fw-bold);">${inv.prenom} ${inv.nom}</div>
                          <div style="font-size:var(--fs-2);color:var(--gris);">${inv.ecole || '—'}${inv.promo ? ' · ' + inv.promo : ''}</div>
                        </div>
                        <button type="button" class="btn btn-primary" style="white-space:nowrap;" data-inscription-id="${inv.inscription_id}">Valider</button>
                    `;
                    ligne.querySelector('button').addEventListener('click', (e) => {
                        const btn = e.currentTarget;
                        btn.disabled = true;
                        validerInscription({ inscription_id: parseInt(btn.dataset.inscriptionId, 10), event_id: eventIdActif }).then(data2 => {
                            if (data2.success) {
                                showToast(data2.message || 'Validé', 'success');
                                ligne.remove();
                            } else {
                                btn.disabled = false;
                                showToast(data2.message || 'Erreur', 'error');
                            }
                        }).catch(() => { btn.disabled = false; showToast('Erreur réseau', 'error'); });
                    });
                    listeDiv.appendChild(ligne);
                });
            })
            .catch(() => { listeDiv.innerHTML = '<p style="color:var(--gris);">Erreur réseau</p>'; });
    }

    if (btnOpenManuel) btnOpenManuel.addEventListener('click', () => {
        window.ouvrirModale ? window.ouvrirModale(modalManuel) : modalManuel.classList.add('open');
        rechercheInput.value = '';
        chargerInvites('');
    });

    let rechercheTimer = null;
    if (rechercheInput) rechercheInput.addEventListener('input', () => {
        clearTimeout(rechercheTimer);
        rechercheTimer = setTimeout(() => chargerInvites(rechercheInput.value.trim()), 250);
    });
});
</script>
</body>
</html>
