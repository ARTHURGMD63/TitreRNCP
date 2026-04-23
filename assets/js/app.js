/* StudentLink — app.js */

// ─── Toast ───────────────────────────────────────────────────────────────────
function showToast(msg, type = '') {
  const t = document.getElementById('toast');
  if (!t) return;
  t.textContent = msg;
  t.className = 'toast' + (type ? ' ' + type : '');
  requestAnimationFrame(() => {
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2800);
  });
}

// ─── Filter Pills ─────────────────────────────────────────────────────────────
document.querySelectorAll('.pill').forEach(pill => {
  pill.addEventListener('click', () => {
    const group = pill.closest('.filter-scroll') || pill.closest('.pill-group');
    group?.querySelectorAll('.pill').forEach(p => p.classList.remove('active'));
    pill.classList.add('active');

    const filter = pill.dataset.filter;
    if (!filter) return;

    document.querySelectorAll('[data-type]').forEach(card => {
      if (filter === 'all') {
        card.closest('[data-card]')?.removeAttribute('style');
        card.parentElement.style.display = '';
      } else {
        const match = card.dataset.type === filter;
        card.parentElement.style.display = match ? '' : 'none';
      }
    });
  });
});

// ─── Join Event ───────────────────────────────────────────────────────────────
document.querySelectorAll('.btn-join-event').forEach(btn => {
  btn.addEventListener('click', async () => {
    const id = btn.dataset.eventId;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>';

    try {
      const res = await fetch('/TitreRNCP/api/inscrire.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ evenement_id: id })
      });
      const data = await res.json();

      if (data.success) {
        btn.textContent = '✓ Inscrit';
        btn.disabled = true;
        btn.classList.add('btn-outline');
        btn.classList.remove('btn-primary', 'btn-rouge');
        showToast('Tu es inscrit ! Rendez-vous ce soir.', 'success');

        const counter = document.querySelector(`[data-inscrits="${id}"]`);
        if (counter) counter.textContent = data.inscrits;
      } else {
        btn.textContent = data.message || 'Erreur';
        btn.disabled = false;
        showToast(data.message || 'Erreur', 'error');
      }
    } catch {
      btn.textContent = '→ je rejoins';
      btn.disabled = false;
      showToast('Erreur réseau', 'error');
    }
  });
});

// ─── Join Squad ───────────────────────────────────────────────────────────────
document.querySelectorAll('.btn-join-squad').forEach(btn => {
  btn.addEventListener('click', async () => {
    const id = btn.dataset.squadId;
    btn.disabled = true;

    try {
      const res = await fetch('/TitreRNCP/api/rejoindre_squad.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ squad_id: id })
      });
      const data = await res.json();

      if (data.success) {
        btn.textContent = '✓';
        btn.disabled = true;
        showToast('Tu rejoins le squad !', 'success');
        const cnt = btn.closest('.squad-card')?.querySelector('.squad-count');
        if (cnt && data.membres) cnt.textContent = data.membres + '/' + btn.dataset.quota;
      } else {
        btn.disabled = false;
        showToast(data.message || 'Erreur', 'error');
      }
    } catch {
      btn.disabled = false;
      showToast('Erreur réseau', 'error');
    }
  });
});

// ─── QR Reveal ────────────────────────────────────────────────────────────────
if (typeof QRCode !== 'undefined') {
  document.querySelectorAll('.qr-body').forEach(qrBody => {
    const qrCanvas = qrBody.querySelector('.qr-canvas');
    const qrRevealText = qrBody.querySelector('.qr-reveal-text');
    let qrRevealed = false;

    if (qrCanvas) {
      qrBody.addEventListener('click', () => {
        if (qrRevealed) return;
        qrRevealed = true;
        qrRevealText?.classList.add('hidden');
        qrBody.classList.add('revealed');

        const code = qrCanvas.dataset.code;
        new QRCode(qrCanvas, {
          text: 'studentlink:' + code,
          width: 200,
          height: 200,
          colorDark: '#1A1A1A',
          colorLight: '#FFFFFF',
          correctLevel: QRCode.CorrectLevel.H
        });
      });
    }
  });
}

// ─── Modal Bottom Sheet ───────────────────────────────────────────────────────
document.querySelectorAll('[data-modal-open]').forEach(trigger => {
  trigger.addEventListener('click', () => {
    const target = document.getElementById(trigger.dataset.modalOpen);
    target?.classList.add('open');
  });
});

document.querySelectorAll('.modal-overlay').forEach(overlay => {
  overlay.addEventListener('click', e => {
    if (e.target === overlay) overlay.classList.remove('open');
  });
});

document.querySelectorAll('[data-modal-close]').forEach(btn => {
  btn.addEventListener('click', () => {
    btn.closest('.modal-overlay')?.classList.remove('open');
  });
});

// ─── Create Squad Form ────────────────────────────────────────────────────────
const createSquadForm = document.getElementById('create-squad-form');
if (createSquadForm) {
  createSquadForm.addEventListener('submit', async e => {
    e.preventDefault();
    const btn = createSquadForm.querySelector('[type=submit]');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span>';

    const body = Object.fromEntries(new FormData(createSquadForm));

    try {
      const res = await fetch('/TitreRNCP/api/create_squad.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(body)
      });
      const data = await res.json();

      if (data.success) {
        showToast('Squad créé !', 'success');
        setTimeout(() => location.reload(), 1000);
      } else {
        btn.disabled = false;
        btn.textContent = 'Créer le squad';
        showToast(data.message || 'Erreur', 'error');
      }
    } catch {
      btn.disabled = false;
      btn.textContent = 'Créer le squad';
      showToast('Erreur réseau', 'error');
    }
  });
}

// ─── Partner Chart (Chart.js) ─────────────────────────────────────────────────
const chartCtx = document.getElementById('inscriptions-chart');
if (chartCtx && typeof Chart !== 'undefined') {
  const labels = chartCtx.dataset.labels ? JSON.parse(chartCtx.dataset.labels) : [];
  const values = chartCtx.dataset.values ? JSON.parse(chartCtx.dataset.values) : [];

  new Chart(chartCtx, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        data: values,
        fill: true,
        backgroundColor: 'rgba(229, 51, 26, 0.12)',
        borderColor: '#E5331A',
        borderWidth: 2,
        pointRadius: 0,
        tension: 0.4
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { display: false }, ticks: { font: { size: 11 }, color: '#888' } },
        y: { grid: { color: '#f0f0f0' }, ticks: { font: { size: 11 }, color: '#888' }, beginAtZero: true }
      }
    }
  });
}

// ─── Real-time polling for partner dashboard ──────────────────────────────────
const liveCounter = document.getElementById('live-inscrits');
if (liveCounter) {
  const eventId = liveCounter.dataset.eventId;

  setInterval(async () => {
    try {
      const res = await fetch(`/TitreRNCP/api/stats.php?event_id=${eventId}`);
      const data = await res.json();
      if (data.inscrits !== undefined) {
        liveCounter.textContent = data.inscrits;
      }
    } catch {}
  }, 15000); // refresh every 15s
}

// ─── Type toggle on register ──────────────────────────────────────────────────
document.querySelectorAll('.type-toggle-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    btn.closest('.type-toggle').querySelectorAll('.type-toggle-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const typeInput = document.getElementById('user-type-input');
    if (typeInput) typeInput.value = btn.dataset.type;

    const studentFields = document.getElementById('student-fields');
    const partnerFields = document.getElementById('partner-fields');
    if (btn.dataset.type === 'etudiant') {
      studentFields?.classList.remove('hidden');
      partnerFields?.classList.add('hidden');
    } else {
      studentFields?.classList.add('hidden');
      partnerFields?.classList.remove('hidden');
    }
  });
});

// ─── Flash deal countdown ─────────────────────────────────────────────────────
document.querySelectorAll('[data-expiry]').forEach(el => {
  const expiry = new Date(el.dataset.expiry * 1000);

  function tick() {
    const diff = Math.max(0, expiry - Date.now());
    const mins = Math.floor(diff / 60000);
    const secs = Math.floor((diff % 60000) / 1000);
    el.textContent = `FLASH · ${mins}MIN ${secs < 10 ? '0' : ''}${secs}S`;
    if (diff > 0) setTimeout(tick, 1000);
    else el.textContent = 'EXPIRÉ';
  }
  tick();
});

// ─── Cancel Pass ──────────────────────────────────────────────────────────────
document.querySelectorAll('.btn-cancel-pass').forEach(btn => {
  btn.addEventListener('click', async () => {
    if (!confirm("Veux-tu vraiment annuler ce pass ?")) return;
    const id = btn.dataset.id;
    btn.disabled = true;

    try {
      const res = await fetch('/TitreRNCP/api/annuler_pass.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ inscription_id: id })
      });
      const data = await res.json();

      if (data.success) {
        showToast('Pass annulé !', 'success');
        setTimeout(() => location.reload(), 1000);
      } else {
        btn.disabled = false;
        showToast(data.message || 'Erreur', 'error');
      }
    } catch {
      btn.disabled = false;
      showToast('Erreur réseau', 'error');
    }
  });
});

// ─── Leave Squad ──────────────────────────────────────────────────────────────
document.querySelectorAll('.btn-leave-squad').forEach(btn => {
  btn.addEventListener('click', async () => {
    if (!confirm("Veux-tu vraiment quitter ce groupe de sport ?")) return;
    const id = btn.dataset.id;
    btn.disabled = true;

    try {
      const res = await fetch('/TitreRNCP/api/quitter_squad.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ squad_id: id })
      });
      const data = await res.json();

      if (data.success) {
        showToast('Groupe quitté !', 'success');
        setTimeout(() => location.reload(), 1000);
      } else {
        btn.disabled = false;
        showToast(data.message || 'Erreur', 'error');
      }
    } catch {
      btn.disabled = false;
      showToast('Erreur réseau', 'error');
    }
  });
});

// ─── Manage Squad ──────────────────────────────────────────────────────────────
document.querySelectorAll('.btn-manage-squad').forEach(btn => {
  btn.addEventListener('click', async () => {
    const id = btn.dataset.id;
    const modal = document.getElementById('modal-manage-squad');
    modal.classList.add('open');
    document.body.style.overflow = 'hidden';
    document.getElementById('manage-squad-loading').style.display = 'block';
    document.getElementById('manage-squad-content').style.display = 'none';
    
    // Setup delete button
    document.querySelector('.btn-delete-squad-from-modal').dataset.id = id;

    try {
      const res = await fetch(`/TitreRNCP/api/squad_members.php?id=${id}`);
      const data = await res.json();
      
      if (data.success) {
        const list = document.getElementById('squad-members-list');
        list.innerHTML = '';
        data.members.forEach(m => {
          const isMe = m.id == data.my_id;
          const kickBtn = isMe ? `<span style="font-size:12px;color:var(--gris-fonce);padding-right:8px;">Créateur</span>` 
                               : `<button class="btn-kick-member" data-squad="${id}" data-user="${m.id}" style="background:none;border:none;color:var(--rouge);font-size:20px;cursor:pointer;" title="Retirer cette personne">✕</button>`;
          
          list.innerHTML += `
            <div style="display:flex;justify-content:space-between;align-items:center;padding:12px;border:2px solid var(--noir);border-radius:4px;background:var(--blanc);">
              <div>
                <strong style="color:var(--noir);">${m.prenom} ${m.nom[0]}.</strong>
                <div style="font-size:12px;color:var(--gris);">${m.ecole || 'Étudiant'}</div>
              </div>
              ${kickBtn}
            </div>
          `;
        });
        
        document.getElementById('manage-squad-loading').style.display = 'none';
        document.getElementById('manage-squad-content').style.display = 'block';
        
        // Bind kick buttons
        list.querySelectorAll('.btn-kick-member').forEach(kbtn => {
          kbtn.addEventListener('click', async () => {
             if (!confirm("Retirer cette personne du groupe ?")) return;
             const sqId = kbtn.dataset.squad;
             const usrId = kbtn.dataset.user;
             
             const kres = await fetch('/TitreRNCP/api/remove_squad_member.php', {
               method: 'POST',
               headers: { 'Content-Type': 'application/json' },
               body: JSON.stringify({ squad_id: sqId, member_id: usrId })
             });
             const kdata = await kres.json();
             if (kdata.success) {
               kbtn.parentElement.remove();
               showToast('Membre retiré', 'success');
             } else {
               showToast(kdata.message, 'error');
             }
          });
        });
        
      }
    } catch {
      showToast('Erreur de chargement', 'error');
    }
  });
});

// Update the delete squad logic to use .btn-delete-squad-from-modal
document.querySelector('.btn-delete-squad-from-modal')?.addEventListener('click', async (e) => {
    if (!confirm("Veux-tu vraiment supprimer définitivement ce groupe ? Tous les membres seront désinscrits.")) return;
    const btn = e.target;
    const id = btn.dataset.id;
    btn.disabled = true;

    try {
      const res = await fetch('/TitreRNCP/api/delete_squad.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ squad_id: id })
      });
      const data = await res.json();

      if (data.success) {
        showToast('Squad supprimé !', 'success');
        setTimeout(() => location.reload(), 1000);
      } else {
        btn.disabled = false;
        showToast(data.message || 'Erreur', 'error');
      }
    } catch {
      btn.disabled = false;
      showToast('Erreur réseau', 'error');
    }
});

// ─── Social Follow System ───────────────────────────────────────────────────

async function handleFollow(btn, type) {
  const targetId = type === 'user' ? btn.dataset.userId : btn.dataset.etabId;
  const isFollowing = btn.dataset.following === '1';
  const action = isFollowing ? 'unfollow' : 'follow';
  
  btn.disabled = true;
  const originalText = btn.textContent;
  btn.textContent = '...';

  try {
    const res = await fetch('/TitreRNCP/api/follow.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action, type, target_id: targetId })
    });
    const data = await res.json();

    if (data.success) {
      const nowFollowing = data.action === 'follow';
      btn.dataset.following = nowFollowing ? '1' : '0';
      btn.textContent = nowFollowing ? '✓ Suivi' : '+ Suivre';
      
      // Visual feedback
      if (type === 'user') {
        btn.style.background = nowFollowing ? 'var(--noir)' : 'transparent';
        btn.style.color = nowFollowing ? 'var(--blanc)' : 'var(--noir)';
      } else {
        // Etab button on explore cards
        const isFlash = btn.closest('.event-card-flash');
        if (isFlash) {
          btn.style.background = nowFollowing ? 'rgba(255,255,255,0.3)' : 'rgba(255,255,255,0.15)';
        } else {
          btn.style.background = nowFollowing ? 'var(--noir)' : 'transparent';
          btn.style.color = nowFollowing ? 'var(--blanc)' : 'var(--noir)';
        }
      }
      
      showToast(nowFollowing ? 'Abonnement ajouté !' : 'Abonnement retiré');
    } else {
      btn.textContent = originalText;
      showToast(data.message || 'Erreur', 'error');
    }
  } catch (e) {
    btn.textContent = originalText;
    showToast('Erreur réseau', 'error');
  } finally {
    btn.disabled = false;
  }
}

document.querySelectorAll('.btn-follow-user').forEach(btn => {
  btn.addEventListener('click', () => handleFollow(btn, 'user'));
});

document.querySelectorAll('.btn-follow-etab').forEach(btn => {
  btn.addEventListener('click', () => handleFollow(btn, 'etablissement'));
});
