/* StudentLink — app.js */

// Detect base path for local WAMP vs Railway
const BASE = location.hostname === 'localhost' || location.hostname === '127.0.0.1' ? '/TitreRNCP' : '';

// Apply saved theme instantly (before first paint)
(function(){
  const t = localStorage.getItem('theme') || 'light';
  document.documentElement.setAttribute('data-theme', t);
})();

window.setTheme = function(theme) {
  document.documentElement.setAttribute('data-theme', theme);
  localStorage.setItem('theme', theme);
};

// ─── Jeton CSRF ──────────────────────────────────────────────────────────────
//
// Les points d'écriture de l'API exigent ce jeton, en plus du cookie de
// session. Le cookie est en SameSite=Lax, ce qui bloque déjà l'envoi depuis un
// autre site — mais c'était la seule défense, et elle tenait à une ligne de
// configuration de session. Le jeton en ajoute une seconde, qu'un site tiers
// ne peut pas obtenir : il est publié dans une balise <meta> de la page, et la
// politique d'origine identique lui en interdit la lecture.
//
// La balise est posée par metaCsrf() (includes/security.php), appelée dans le
// <head> de chaque page à côté de themeBootScript().
function jetonCsrf() {
  const meta = document.querySelector('meta[name="csrf-token"]');
  return meta ? meta.getAttribute('content') : '';
}
window.jetonCsrf = jetonCsrf;

// Les en-têtes de tout appel d'écriture. Passer par cette fonction plutôt que
// de recopier l'objet à chaque appel : quatorze copies, c'est quatorze
// occasions d'oublier le jeton sur le prochain point d'API ajouté.
function enTetesJson() {
  return { 'Content-Type': 'application/json', 'X-CSRF-Token': jetonCsrf() };
}
window.enTetesJson = enTetesJson;

// Réponse 403 « csrf » : la session a expiré ou la page a été laissée ouverte
// assez longtemps pour que le jeton change. Recharger est la seule sortie, et
// le dire vaut mieux qu'un bouton qui reste bloqué sur « … ».
function estRefusCsrf(data) {
  return data && data.success === false && data.code === 'csrf';
}
window.estRefusCsrf = estRefusCsrf;

// ─── Échappement HTML (protection XSS côté client) ───────────────────────────
// Toute donnée venant de la base est échappée avant injection dans le DOM.
function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, c => ({
    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
  })[c]);
}

// ─── Coche ───────────────────────────────────────────────────────────────────
// Meme trace que icon('check') cote PHP. Constante et ecrite en dur : aucune
// donnee utilisateur n'y transite, donc innerHTML est sans risque ici.
const COCHE = '<svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true">'
            + '<polyline points="20 6 9 17 4 12"/></svg>';
window.COCHE = COCHE;

const CROIX = '<svg class="icon icon-sm" viewBox="0 0 24 24" aria-hidden="true">'
            + '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
window.CROIX = CROIX;

// ─── Jetons de la charte ─────────────────────────────────────────────────────
// Le JavaScript ne redéclare aucune couleur : il lit celles du CSS. Les canvas
// (QR code, graphique) suivent ainsi le thème clair comme le thème sombre.
function jeton(nom, repli) {
  const v = getComputedStyle(document.documentElement).getPropertyValue(nom).trim();
  return v || repli;
}

// ─── Toast ───────────────────────────────────────────────────────────────────
function showToast(msg, type = '') {
  const t = document.getElementById('toast');
  if (!t) return;
  t.textContent = msg;
  t.className = 'toast' + (type ? ' ' + type : '');
  // Une erreur interrompt la lecture en cours ; une confirmation attend une
  // pause naturelle, pour ne pas hacher la navigation.
  t.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite');
  requestAnimationFrame(() => {
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 2800);
  });
}

// ─── All page logic wrapped so it can be re-run after SPA navigation ─────────
function initApp() {

  // Filter Pills
  // Les pilules-liens (explore, moderation) filtrent cote serveur : seules
  // celles qui portent data-filter ont un travail a faire ici.
  document.querySelectorAll('.pill[data-filter]').forEach(pill => {
    if (pill._init) return; pill._init = true;
    pill.addEventListener('click', () => {
      const group = pill.closest('.filter-scroll') || pill.closest('.pill-group');
      group?.querySelectorAll('.pill').forEach(p => {
        p.classList.remove('active');
        if (p.hasAttribute('aria-pressed')) p.setAttribute('aria-pressed', 'false');
      });
      pill.classList.add('active');
      if (pill.hasAttribute('aria-pressed')) pill.setAttribute('aria-pressed', 'true');

      const filtre = pill.dataset.filter;
      // C'est la carte elle-meme qui porte data-type, et elle est fille
      // directe du conteneur de la liste : masquer son parent masquait donc
      // la liste entiere, y compris la carte censee rester. Le filtre semblait
      // casse alors qu'il cachait tout.
      const cartes = document.querySelectorAll('.squad-card[data-type]');
      let visibles = 0;
      cartes.forEach(card => {
        const montrer = filtre === 'all' || card.dataset.type === filtre;
        card.hidden = !montrer;
        if (montrer) visibles++;
      });
      const vide = document.getElementById('filtre-vide');
      if (vide) vide.hidden = (visibles > 0 || cartes.length === 0);
    });
  });

  // Join Event
  document.querySelectorAll('.btn-join-event').forEach(btn => {
    if (btn._init) return; btn._init = true;
    btn.addEventListener('click', async () => {
      const id = btn.dataset.eventId;
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner"></span>';
      try {
        const res = await fetch(BASE + '/api/inscrire.php', {
          method: 'POST', headers: enTetesJson(),
          body: JSON.stringify({ evenement_id: id })
        });
        const data = await res.json();
        if (data.success) {
          btn.innerHTML = COCHE + ' Inscrit';
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

  // Join Squad
  document.querySelectorAll('.btn-join-squad').forEach(btn => {
    if (btn._init) return; btn._init = true;
    btn.addEventListener('click', async () => {
      const id = btn.dataset.squadId;
      btn.disabled = true;
      try {
        const res = await fetch(BASE + '/api/rejoindre_squad.php', {
          method: 'POST', headers: enTetesJson(),
          body: JSON.stringify({ squad_id: id })
        });
        const data = await res.json();
        if (data.success) {
          btn.innerHTML = COCHE;
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

  // QR Reveal
  if (typeof QRCode !== 'undefined') {
    document.querySelectorAll('.qr-body').forEach(qrBody => {
      if (qrBody._init) return; qrBody._init = true;
      const qrCanvas = qrBody.querySelector('.qr-canvas');
      const qrRevealText = qrBody.querySelector('.qr-reveal-text');
      let qrRevealed = false;
      if (qrCanvas) {
        qrBody.addEventListener('click', () => {
          if (qrRevealed) return;
          qrRevealed = true;
          qrRevealText?.classList.add('hidden');
          qrBody.classList.add('revealed');
          new QRCode(qrCanvas, {
            text: 'studentlink:' + qrCanvas.dataset.code,
            width: 200, height: 200,
            // Un QR code doit rester très contrasté : encre de la charte sur blanc pur.
            colorDark: jeton('--noir', '#1C1916'), colorLight: '#FFFFFF',
            correctLevel: QRCode.CorrectLevel.H
          });
        });
      }
    });
  }

  // ─── Fenetres modales ──────────────────────────────────────────────────
  const FOCUSABLES = 'a[href], button:not([disabled]), input:not([disabled]),'
                   + ' select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
  let declencheur = null;

  function ouvrirModale(modale) {
    if (!modale) return;
    declencheur = document.activeElement;
    modale.removeAttribute('inert');
    modale.classList.add('open');
    // Le focus entre dans la fenetre : sans cela, rien n'est annonce et la
    // tabulation continue derriere la modale.
    const premier = modale.querySelector(FOCUSABLES);
    (premier || modale.querySelector('.modal-sheet'))?.focus?.();
  }

  function fermerModale(modale) {
    if (!modale) return;
    modale.classList.remove('open');
    // Fermee, elle sort de l'ordre de tabulation : on ne tabule plus a
    // l'aveugle dans une fenetre invisible.
    modale.setAttribute('inert', '');
    declencheur?.focus?.();
    declencheur = null;
  }

  document.querySelectorAll('.modal-overlay').forEach(overlay => {
    if (overlay._init) return; overlay._init = true;
    if (!overlay.classList.contains('open')) overlay.setAttribute('inert', '');
    const feuille = overlay.querySelector('.modal-sheet');
    if (feuille && !feuille.hasAttribute('tabindex')) feuille.setAttribute('tabindex', '-1');
    overlay.addEventListener('click', e => {
      if (e.target === overlay) fermerModale(overlay);
    });
  });

  document.querySelectorAll('[data-modal-open]').forEach(trigger => {
    if (trigger._init) return; trigger._init = true;
    trigger.addEventListener('click', () => {
      ouvrirModale(document.getElementById(trigger.dataset.modalOpen));
    });
  });

  document.querySelectorAll('[data-modal-close]').forEach(btn => {
    if (btn._init) return; btn._init = true;
    btn.addEventListener('click', () => fermerModale(btn.closest('.modal-overlay')));
  });

  // Echap ferme, et la tabulation boucle a l'interieur de la fenetre.
  if (!document._modalesClavier) {
    document._modalesClavier = true;
    document.addEventListener('keydown', e => {
      const ouverte = document.querySelector('.modal-overlay.open');
      if (!ouverte) return;

      if (e.key === 'Escape') { e.preventDefault(); fermerModale(ouverte); return; }
      if (e.key !== 'Tab') return;

      const cibles = [...ouverte.querySelectorAll(FOCUSABLES)].filter(el => el.offsetParent !== null);
      if (!cibles.length) return;
      const debut = cibles[0], fin = cibles[cibles.length - 1];
      if (e.shiftKey && document.activeElement === debut) { e.preventDefault(); fin.focus(); }
      else if (!e.shiftKey && document.activeElement === fin) { e.preventDefault(); debut.focus(); }
    });
  }

  // Ouverture par programme (signalement, invitation…) : meme chemin, donc
  // meme contrat clavier.
  window.ouvrirModale = ouvrirModale;
  window.fermerModale = fermerModale;

  // ─── Centres d'intérêt ───────────────────────────────────────────
  // Ce qu'on vient de cocher remonte en tête, et au-delà de quatre choix le
  // surplus se replie derrière un « +N ». Les étiquettes repliées restent des
  // cases cochées dans le formulaire : replier n'est pas décocher.
  const listeInterets = document.getElementById('interets-liste');
  if (listeInterets && !listeInterets._init) {
    listeInterets._init = true;

    const plus = listeInterets.querySelector('#interets-plus');
    const MAX  = parseInt(listeInterets.dataset.maxVisible || '4', 10);
    let deplie = false;
    let horloge = 0;

    const etiquettes = [...listeInterets.querySelectorAll('.interest-chip')];
    const coche = el => el.querySelector('input').checked;

    // Le rang de départ sert de second critère de tri : les étiquettes non
    // cochées retrouvent toujours leur ordre d'origine, elles ne dérivent pas
    // au fil des clics.
    etiquettes.forEach((el, i) => {
      el.dataset.rang = i;
      if (coche(el)) el.dataset.ordre = horloge++;
    });

    function appliquer(focusSur) {
      const ordonnees = etiquettes.slice().sort((a, b) => {
        const ca = coche(a), cb = coche(b);
        if (ca !== cb) return ca ? -1 : 1;
        if (ca) return (+a.dataset.ordre) - (+b.dataset.ordre);
        return (+a.dataset.rang) - (+b.dataset.rang);
      });
      ordonnees.forEach(el => { el.hidden = false; listeInterets.appendChild(el); });

      const choisies = ordonnees.filter(coche);
      const surplus  = choisies.length - MAX;

      if (surplus > 0 && !deplie) {
        choisies.slice(MAX).forEach(el => { el.hidden = true; });
        plus.hidden = false;
        plus.textContent = '+' + surplus;
        plus.setAttribute('aria-expanded', 'false');
        plus.setAttribute('aria-label', 'Afficher ' + surplus + ' centre' + (surplus > 1 ? 's' : '') + " d'int\u00e9r\u00eat de plus");
        choisies[MAX - 1].after(plus);
      } else if (surplus > 0) {
        plus.hidden = false;
        plus.textContent = '\u2212';
        plus.setAttribute('aria-expanded', 'true');
        plus.setAttribute('aria-label', 'Replier les centres d\'int\u00e9r\u00eat');
        choisies[choisies.length - 1].after(plus);
      } else {
        plus.hidden = true;
        deplie = false;
        listeInterets.appendChild(plus);
      }

      // Déplacer un élément dans le DOM lui fait perdre le focus : on le
      // rend à l'étiquette qu'on vient d'actionner, sinon la tabulation
      // repart du début de la page à chaque clic.
      focusSur?.focus?.({ preventScroll: true });
    }

    listeInterets.addEventListener('change', e => {
      const champ = e.target.closest('input[type=checkbox]');
      if (!champ) return;
      const etiquette = champ.closest('.interest-chip');
      if (champ.checked) {
        etiquette.dataset.ordre = horloge++;
      } else {
        delete etiquette.dataset.ordre;
      }
      appliquer(champ);
    });

    plus.addEventListener('click', () => {
      deplie = !deplie;
      appliquer(plus);
    });

    appliquer();
  }

  // ─── Inviter un ami ────────────────────────────────────────────
  // Cette logique vivait dans un <script> en ligne d'explore.php. Le routeur
  // ne rejoue que les scripts a src : apres une navigation interne (le seul
  // chemin qu'emprunte la barre du bas) le bouton appelait une fonction qui
  // n'existait plus, et l'invitation ne partait jamais. Elle est donc ici,
  // rejouee par initApp() comme le reste.
  const modaleInvite = document.getElementById('modal-invite');
  if (modaleInvite) {
    let inviteType = '', inviteCible = 0;

    document.querySelectorAll('.btn-ouvrir-invitation').forEach(btn => {
      if (btn._init) return; btn._init = true;
      btn.addEventListener('click', () => {
        inviteType  = btn.dataset.inviteType || 'event';
        inviteCible = btn.dataset.inviteCible;
        const titre = modaleInvite.querySelector('#invite-target-name');
        if (titre) titre.textContent = btn.dataset.inviteNom || '';
        // Chaque ouverture repart d'une ardoise propre : sans cela un
        // « Envoye » d'un evenement precedent restait affiche sur le suivant.
        modaleInvite.querySelectorAll('.btn-send-invite').forEach(b => {
          b.textContent = 'Inviter';
          b.disabled = false;
          b.style.background = 'var(--noir)';
          b.style.color = 'var(--blanc)';
        });
        window.ouvrirModale?.(modaleInvite);
      });
    });

    modaleInvite.querySelectorAll('.btn-send-invite').forEach(btn => {
      if (btn._init) return; btn._init = true;
      btn.addEventListener('click', async () => {
        if (!inviteType || !inviteCible) return;
        btn.disabled = true; btn.textContent = '…';
        try {
          const res = await fetch(BASE + '/api/inviter.php', {
            method: 'POST',
            headers: enTetesJson(),
            credentials: 'same-origin',
            body: JSON.stringify({
              action: 'send',
              to_user_id: btn.dataset.userId,
              type: inviteType,
              target_id: inviteCible
            })
          });
          const data = await res.json();
          if (data.success) {
            btn.innerHTML = COCHE + ' Envoyé';
            btn.style.background = 'var(--succes)';
            btn.style.color = '#fff';
            showToast(data.message || 'Invitation envoyée !', 'success');
          } else {
            btn.disabled = false; btn.textContent = 'Inviter';
            showToast(data.message || 'Erreur', 'error');
          }
        } catch {
          btn.disabled = false; btn.textContent = 'Inviter';
          showToast('Erreur réseau', 'error');
        }
      });
    });
  }

  // ─── Répondre à une invitation ────────────────────────────────────
  // Même raison que l'envoi : vivait dans un <script> en ligne du profil, que
  // le routeur ne rejoue jamais. Une invitation qu'on ne peut pas accepter
  // n'est pas une invitation.
  document.querySelectorAll('.btn-accept-invite, .btn-decline-invite').forEach(btn => {
    if (btn._init) return; btn._init = true;
    const accepter = btn.classList.contains('btn-accept-invite');
    const libelle  = accepter ? 'Accepter' : 'Refuser';

    btn.addEventListener('click', async () => {
      const carte = btn.closest('.carte-invitation');
      btn.disabled = true;
      btn.textContent = '\u2026';
      try {
        const res = await fetch(BASE + '/api/inviter.php', {
          method: 'POST',
          headers: enTetesJson(),
          credentials: 'same-origin',
          body: JSON.stringify({ action: accepter ? 'accept' : 'decline', invite_id: btn.dataset.id })
        });
        const data = await res.json();
        if (!data.success) {
          // L'ancienne version laissait le bouton sur « … » sans un mot :
          // un événement complet ou supprimé ressemblait à un plantage.
          btn.disabled = false; btn.textContent = libelle;
          showToast(data.message || 'Erreur', 'error');
          return;
        }
        showToast(accepter ? 'Invitation accept\u00e9e ! Tu es inscrit.' : 'Invitation refus\u00e9e.',
                  accepter ? 'success' : '');
        if (carte) {
          carte.style.opacity = '0';
          carte.style.transition = 'opacity .3s ease';
          setTimeout(() => { carte.remove(); majCompteurInvitations(); }, 320);
        }
      } catch {
        btn.disabled = false; btn.textContent = libelle;
        showToast('Erreur r\u00e9seau', 'error');
      }
    });
  });

  function majCompteurInvitations() {
    majPastilleCloche();

    const liste = document.getElementById('liste-invitations');
    if (!liste) return;
    const reste = liste.querySelectorAll('.carte-invitation').length;
    const compteur = document.getElementById('compteur-invitations');
    if (compteur) compteur.textContent = reste;
    // Plus rien à traiter : le bloc disparaît au lieu de laisser un titre
    // « Invitations 0 » au-dessus du vide.
    if (!reste) document.getElementById('section-invitations')?.remove();
  }

  // Pastille de la cloche : elle compte ce qui attend une reponse, donc les
  // cartes qui portent encore des boutons. Repondre a la derniere l'efface,
  // sans rechargement.
  function majPastilleCloche() {
    const pastille = document.getElementById('cloche-compteur');
    if (!pastille) return;
    const reste = document.querySelectorAll('#liste-notifs .notif-item__actions').length;
    pastille.textContent = reste;
    pastille.hidden = reste === 0;
  }

  // Create Squad Form
  const createSquadForm = document.getElementById('create-squad-form');
  if (createSquadForm && !createSquadForm._init) {
    createSquadForm._init = true;
    createSquadForm.addEventListener('submit', async e => {
      e.preventDefault();
      const btn = createSquadForm.querySelector('[type=submit]');
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner"></span>';
      const body = Object.fromEntries(new FormData(createSquadForm));
      try {
        const res = await fetch(BASE + '/api/create_squad.php', {
          method: 'POST', headers: enTetesJson(),
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

  // Partner Chart
  const chartCtx = document.getElementById('inscriptions-chart');
  if (chartCtx && typeof Chart !== 'undefined' && !chartCtx._init) {
    chartCtx._init = true;
    const labels = chartCtx.dataset.labels ? JSON.parse(chartCtx.dataset.labels) : [];
    const values = chartCtx.dataset.values ? JSON.parse(chartCtx.dataset.values) : [];
    new Chart(chartCtx, {
      type: 'line',
      data: { labels, datasets: [{ data: values, fill: true,
        backgroundColor: jeton('--rouge-clair', '#FBE7E1'),
        borderColor: jeton('--rouge', '#E0492B'),
        borderWidth: 2, pointRadius: 0, tension: 0.4 }] },
      options: { responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { display: false },
               ticks: { font: { size: 11 }, color: jeton('--gris-fonce', '#5B554C') } },
          y: { grid: { color: jeton('--gris-clair', '#EAE3D6') },
               ticks: { font: { size: 11 }, color: jeton('--gris-fonce', '#5B554C') }, beginAtZero: true } } }
    });
  }

  // Le compteur en direct du tableau de bord partenaire n'a plus son propre
  // interrogateur : il porte data-live-event et rejoint le flux commun,
  // en bas de ce fichier.

  // Type toggle on register
  document.querySelectorAll('.type-toggle-btn').forEach(btn => {
    if (btn._init) return; btn._init = true;
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

  // Flash countdown
  document.querySelectorAll('[data-expiry]').forEach(el => {
    if (el._init) return; el._init = true;
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

  // Cancel Pass
  document.querySelectorAll('.btn-cancel-pass').forEach(btn => {
    if (btn._init) return; btn._init = true;
    btn.addEventListener('click', async () => {
      if (!confirm("Veux-tu vraiment annuler ce pass ?")) return;
      const id = btn.dataset.id;
      btn.disabled = true;
      try {
        const res = await fetch(BASE + '/api/annuler_pass.php', {
          method: 'POST', headers: enTetesJson(),
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
      } catch { btn.disabled = false; showToast('Erreur réseau', 'error'); }
    });
  });

  // Leave Squad
  document.querySelectorAll('.btn-leave-squad').forEach(btn => {
    if (btn._init) return; btn._init = true;
    btn.addEventListener('click', async () => {
      if (!confirm("Veux-tu vraiment quitter ce groupe de sport ?")) return;
      const id = btn.dataset.id;
      btn.disabled = true;
      try {
        const res = await fetch(BASE + '/api/quitter_squad.php', {
          method: 'POST', headers: enTetesJson(),
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
      } catch { btn.disabled = false; showToast('Erreur réseau', 'error'); }
    });
  });

  // Manage Squad
  document.querySelectorAll('.btn-manage-squad').forEach(btn => {
    if (btn._init) return; btn._init = true;
    btn.addEventListener('click', async () => {
      const id = btn.dataset.id;
      const modal = document.getElementById('modal-manage-squad');
      ouvrirModale(modal);
      document.body.style.overflow = 'hidden';
      document.getElementById('manage-squad-loading').style.display = 'block';
      document.getElementById('manage-squad-content').style.display = 'none';
      document.querySelector('.btn-delete-squad-from-modal').dataset.id = id;
      try {
        const res = await fetch(`${BASE}/api/squad_members.php?id=${id}`);
        const data = await res.json();
        if (data.success) {
          const list = document.getElementById('squad-members-list');
          list.innerHTML = '';
          data.members.forEach(m => {
            const isMe = m.id == data.my_id;
            const kickBtn = isMe
              ? `<span style="font-size:12px;color:var(--gris-fonce);padding-right:8px;">Créateur</span>`
              : `<button class="btn-kick-member" data-squad="${id}" data-user="${m.id}" aria-label="Retirer ce membre" title="Retirer ce membre" style="background:none;border:none;color:var(--sur-rouge-clair);cursor:pointer;display:flex;align-items:center;">${CROIX}</button>`;
            const nomAffiche = escapeHtml(m.prenom) + ' ' + escapeHtml(m.nom.charAt(0)) + '.';
            const ecoleAffichee = escapeHtml(m.ecole || 'Étudiant');
            list.innerHTML += `<div style="display:flex;justify-content:space-between;align-items:center;padding:12px;border:1px solid var(--gris-clair);border-radius:var(--radius-sm);background:var(--blanc);">
              <div><strong>${nomAffiche}</strong><div style="font-size:12px;color:var(--gris);">${ecoleAffichee}</div></div>${kickBtn}</div>`;
          });
          document.getElementById('manage-squad-loading').style.display = 'none';
          document.getElementById('manage-squad-content').style.display = 'block';
          list.querySelectorAll('.btn-kick-member').forEach(kbtn => {
            kbtn.addEventListener('click', async () => {
              if (!confirm("Retirer cette personne du groupe ?")) return;
              const kres = await fetch(BASE + '/api/remove_squad_member.php', {
                method: 'POST', headers: enTetesJson(),
                body: JSON.stringify({ squad_id: kbtn.dataset.squad, member_id: kbtn.dataset.user })
              });
              const kdata = await kres.json();
              if (kdata.success) { kbtn.parentElement.remove(); showToast('Membre retiré', 'success'); }
              else showToast(kdata.message, 'error');
            });
          });
        }
      } catch { showToast('Erreur de chargement', 'error'); }
    });
  });

  document.querySelector('.btn-delete-squad-from-modal')?.addEventListener('click', async (e) => {
    if (e.target._init) return; e.target._init = true;
    if (!confirm("Veux-tu vraiment supprimer définitivement ce groupe ?")) return;
    const btn = e.target;
    const id = btn.dataset.id;
    btn.disabled = true;
    try {
      const res = await fetch(BASE + '/api/delete_squad.php', {
        method: 'POST', headers: enTetesJson(),
        body: JSON.stringify({ squad_id: id })
      });
      const data = await res.json();
      if (data.success) { showToast('Squad supprimé !', 'success'); setTimeout(() => location.reload(), 1000); }
      else { btn.disabled = false; showToast(data.message || 'Erreur', 'error'); }
    } catch { btn.disabled = false; showToast('Erreur réseau', 'error'); }
  });

  // Social Follow
  // Abonnement entre étudiants : trois états (aucun -> en attente -> abonné).
  // Cliquer sur « en attente » annule la demande ; sur « abonné », se désabonne.
  const LIBELLES = {
    none:     { court: '+ Suivre',          long: '+ DEMANDER À SUIVRE' },
    pending:  { court: 'En attente',        long: 'DEMANDE ENVOYÉE · ANNULER' },
    accepted: { court: COCHE + ' Suivi',    long: COCHE + ' ABONNÉ' }
  };

  function peindreBoutonSuivi(btn, etat) {
    const long = btn.classList.contains('btn-full');
    btn.dataset.etat = etat;
    btn.innerHTML = LIBELLES[etat][long ? 'long' : 'court'];
    if (etat === 'accepted') {
      btn.style.background = 'var(--noir)';
      btn.style.color = 'var(--blanc)';
    } else if (etat === 'pending') {
      btn.style.background = 'var(--surface-2)';
      btn.style.color = 'var(--gris-fonce)';
    } else {
      btn.style.background = long ? 'var(--bleu)' : 'transparent';
      btn.style.color = long ? 'var(--blanc)' : 'var(--noir)';
    }
  }

  async function handleFollow(btn, type) {
    if (btn.disabled) return;
    const targetId = type === 'user' ? btn.dataset.userId : btn.dataset.etabId;
    const etat = btn.dataset.etat || (btn.dataset.following === '1' ? 'accepted' : 'none');
    // Depuis « en attente » comme depuis « abonné », l'action est un retrait.
    const action = etat === 'none' ? 'follow' : 'unfollow';
    btn.disabled = true;
    const ancienTexte = btn.textContent;
    btn.textContent = '...';
    try {
      const res = await fetch(BASE + '/api/follow.php', {
        method: 'POST', headers: enTetesJson(),
        body: JSON.stringify({ action, type, target_id: targetId })
      });
      const data = await res.json();
      if (data.success) {
        peindreBoutonSuivi(btn, data.etat || 'none');
        showToast(data.message || (data.etat === 'none' ? 'Abonnement retiré' : 'Demande envoyée'));
      } else {
        btn.textContent = ancienTexte;
        showToast(data.message || 'Erreur', 'error');
      }
    } catch {
      btn.textContent = ancienTexte;
      showToast('Erreur réseau', 'error');
    } finally { btn.disabled = false; }
  }

  // Champ fichier : le bouton natif est masqué, donc c'est à nous d'afficher
  // le nom du fichier choisi — sans quoi on ne sait plus ce qu'on a sélectionné.
  document.querySelectorAll('.champ-fichier input[type=file]').forEach(input => {
    if (input._init) return; input._init = true;
    const etiquette = input.parentElement.querySelector('.nom-fichier');
    const apercu = document.getElementById('apercu-avatar');

    input.addEventListener('change', () => {
      const f = input.files && input.files[0];
      if (etiquette) {
        etiquette.textContent = f ? f.name : 'Aucune image choisie';
        etiquette.classList.toggle('vide', !f);
        if (f) etiquette.title = f.name;
      }

      // Aperçu immédiat : on voit sa photo avant d'enregistrer, plutôt que
      // de devoir valider pour découvrir le résultat.
      //
      // Lecture en data: et non en blob: — la CSP autorise « img-src 'self'
      // data: https: », donc une URL blob serait bloquée. Lire le fichier
      // évite d'avoir à élargir la politique de sécurité pour un aperçu.
      if (apercu && f && f.type.startsWith('image/')) {
        const lecteur = new FileReader();
        lecteur.onload = () => {
          const img = new Image();
          img.onload = () => {
            img.style.cssText = 'width:72px;height:72px;border-radius:50%;object-fit:cover;display:block;';
            img.alt = 'Aperçu de la photo choisie';
            apercu.replaceChildren(img);
          };
          img.src = lecteur.result;
        };
        lecteur.readAsDataURL(f);
      }

      // Choisir une photo annule l'intention de la retirer.
      const retirer = document.querySelector('input[name=supprimer_photo]');
      if (retirer && f) retirer.checked = false;
    });
  });

  // Cocher « retirer » vide la sélection : l'aperçu doit dire la vérité.
  document.querySelectorAll('input[name=supprimer_photo]').forEach(box => {
    if (box._init) return; box._init = true;
    box.addEventListener('change', () => {
      const input = document.querySelector('.champ-fichier input[type=file]');
      if (box.checked && input) {
        input.value = '';
        const etiquette = document.querySelector('.nom-fichier');
        if (etiquette) {
          etiquette.textContent = 'Aucune image choisie';
          etiquette.classList.add('vide');
        }
      }
    });
  });

  // Accepter / refuser une demande reçue
  document.querySelectorAll('.btn-accept-follow, .btn-decline-follow').forEach(btn => {
    if (btn._init) return; btn._init = true;
    btn.addEventListener('click', async () => {
      const accepte = btn.classList.contains('btn-accept-follow');
      btn.disabled = true;
      try {
        const res = await fetch(BASE + '/api/follow.php', {
          method: 'POST', headers: enTetesJson(),
          body: JSON.stringify({
            action: accepte ? 'accept' : 'decline',
            type: 'user',
            target_id: btn.dataset.userId
          })
        });
        const data = await res.json();
        if (data.success) {
          showToast(data.message, accepte ? 'success' : '');
          // .notif-item est la carte du panneau de notifications ; le
          // selecteur sur le style en ligne servait l'ancien bloc du profil.
          btn.closest('.notif-item, div[style*="background:var(--blanc)"]')?.remove();
          majPastilleCloche();
          setTimeout(() => location.reload(), 700);
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

  // Bloquer / débloquer
  document.querySelectorAll('.btn-block-user').forEach(btn => {
    if (btn._init) return; btn._init = true;
    btn.addEventListener('click', async () => {
      const bloque = btn.dataset.bloque === '1';
      if (!bloque && !confirm("Bloquer cette personne ? Vous ne verrez plus vos profils respectifs et vos abonnements seront supprimés.")) return;
      btn.disabled = true;
      try {
        const res = await fetch(BASE + '/api/moderation.php', {
          method: 'POST', headers: enTetesJson(),
          body: JSON.stringify({ action: bloque ? 'unblock' : 'block', target_id: btn.dataset.userId })
        });
        const data = await res.json();
        showToast(data.message || 'Erreur', data.success ? '' : 'error');
        if (data.success) {
          if (!bloque) { setTimeout(() => location.href = BASE + '/explore.php?view=people', 700); }
          else { btn.dataset.bloque = '0'; btn.textContent = 'Bloquer'; btn.disabled = false; }
        } else { btn.disabled = false; }
      } catch {
        btn.disabled = false;
        showToast('Erreur réseau', 'error');
      }
    });
  });

  // Signaler : ouvre la feuille dediee (plus de prompt() du navigateur)
  document.querySelectorAll('.btn-report-user').forEach(btn => {
    if (btn._init) return; btn._init = true;
    btn.addEventListener('click', () => {
      ouvrirModale(document.getElementById('modal-signalement'));
    });
  });

  const formSignalement = document.getElementById('form-signalement');
  if (formSignalement && !formSignalement._init) {
    formSignalement._init = true;
    formSignalement.addEventListener('submit', async e => {
      e.preventDefault();
      const envoi = formSignalement.querySelector('[type=submit]');
      envoi.disabled = true;
      const libelle = envoi.textContent;
      envoi.innerHTML = '<span class="spinner"></span>';
      try {
        const res = await fetch(BASE + '/api/moderation.php', {
          method: 'POST', headers: enTetesJson(),
          body: JSON.stringify({
            action: 'report',
            target_id: formSignalement.dataset.userId,
            motif: formSignalement.motif.value,
            details: formSignalement.details.value
          })
        });
        const data = await res.json();
        showToast(data.message || 'Erreur', data.success ? '' : 'error');
        if (data.success) {
          fermerModale(document.getElementById('modal-signalement'));
          formSignalement.reset();
        }
      } catch {
        showToast('Erreur réseau', 'error');
      } finally {
        envoi.disabled = false;
        envoi.textContent = libelle;
      }
    });
  }

  document.querySelectorAll('.btn-follow-user').forEach(btn => {
    if (btn._init) return; btn._init = true;
    btn.addEventListener('click', () => handleFollow(btn, 'user'));
  });
  document.querySelectorAll('.btn-follow-etab').forEach(btn => {
    if (btn._init) return; btn._init = true;
    btn.addEventListener('click', () => handleFollow(btn, 'etablissement'));
  });

  // Event countdowns
  document.querySelectorAll('.event-countdown').forEach(el => {
    if (el._init) return; el._init = true;
    const ts = parseInt(el.dataset.ts) * 1000;
    function tickCountdown() {
      const diff = ts - Date.now();
      if (diff <= 0) { el.textContent = 'EN COURS'; return; }
      const h = Math.floor(diff / 3600000);
      const m = Math.floor((diff % 3600000) / 60000);
      const s = Math.floor((diff % 60000) / 1000);
      if (h > 48) {
        const days = Math.floor(h / 24);
        el.textContent = `dans ${days}j`;
      } else if (h > 0) {
        el.textContent = `dans ${h}h${m < 10 ? '0' : ''}${m}`;
      } else {
        el.textContent = `dans ${m}min${s < 10 ? '0' : ''}${s}s`;
      }
      setTimeout(tickCountdown, 1000);
    }
    tickCountdown();
  });

}

// Auto-init on page load
window.initApp = initApp;
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initApp);
} else {
  initApp();
}

/* ─── Navigation sans rechargement ───────────────────────────────────────────
 * Le terrain etait deja pret : .app-shell porte les transitions, initApp() est
 * concu pour etre rejoue et chaque ecouteur a sa garde _init. Il ne manquait
 * que l'echange de contenu. On remplace la coquille et la nav, on met a jour
 * l'historique, et on rejoue l'initialisation.
 *
 * Regle de prudence : au moindre doute (lien externe, formulaire, erreur
 * reseau, reponse inattendue) on laisse le navigateur faire une navigation
 * classique. Le routeur n'est qu'une amelioration, jamais un passage oblige.
 */
(function () {
  if (!window.history || !window.fetch || !document.querySelector('.app-shell')) return;

  // 13 pages portent leur propre bloc <style> dans <head>. Sans échange,
  // une page atteinte par le routeur garde le style de la précédente : la
  // fiche événement perdait tout son hero. On les marque au démarrage pour
  // ne jamais toucher aux styles qu'une bibliothèque injecterait ensuite.
  document.head.querySelectorAll('style').forEach(st => st.dataset.pageStyle = '1');

  // Les bibliotheques propres a une page (le generateur de QR code du wallet,
  // par exemple) sont chargees hors de .app-shell : le routeur doit donc les
  // charger lui-meme, sinon la page arrive sans son outil.
  const scriptsCharges = new Set(
    [...document.querySelectorAll('script[src]')].map(el => el.src)
  );

  function chargerScriptsDe(doc) {
    const aCharger = [...doc.querySelectorAll('script[src]')]
      .map(el => new URL(el.getAttribute('src'), location.href).href)
      .filter(src => !scriptsCharges.has(src));
    return Promise.all(aCharger.map(src => new Promise(fini => {
      scriptsCharges.add(src);
      const el = document.createElement('script');
      el.src = src;
      // Un echec de chargement ne doit pas bloquer la navigation :
      // la page s'affiche, seule la fonctionnalite concernee manque.
      el.onload = fini;
      el.onerror = fini;
      document.body.appendChild(el);
    })));
  }

  const barre = document.createElement('div');
  barre.className = 'nav-progress';
  barre.setAttribute('aria-hidden', 'true');
  document.body.appendChild(barre);

  let enCours = null;

  function interne(url) {
    return url.origin === location.origin
        && /\.php$/.test(url.pathname)
        && !url.pathname.includes('/api/')
        && !url.pathname.includes('/auth/')          // connexion, deconnexion : rechargement franc
        && !url.pathname.includes('/partenaire/')    // autre coquille, autre mise en page
        && !url.pathname.includes('/admin/');
  }

  async function aller(href, poussuerHistorique = true) {
    const cible = new URL(href, location.href);
    if (enCours) enCours.abort();
    enCours = new AbortController();
    barre.classList.add('active');
    document.body.setAttribute('aria-busy', 'true');

    try {
      const res = await fetch(cible.href, {
        signal: enCours.signal,
        headers: { 'X-Requested-With': 'spa' },
        credentials: 'same-origin'
      });
      if (!res.ok || !(res.headers.get('content-type') || '').includes('text/html')) {
        location.href = cible.href; return;
      }
      const html = await res.text();
      const doc = new DOMParser().parseFromString(html, 'text/html');
      const coquille = doc.querySelector('.app-shell');
      const nav = doc.querySelector('.bottom-nav');
      if (!coquille) { location.href = cible.href; return; }

      // Une redirection du serveur (session expiree) sort du routeur.
      if (res.redirected && !interne(new URL(res.url))) { location.href = res.url; return; }

      document.querySelector('.app-shell').replaceWith(coquille);
      const navActuelle = document.querySelector('.bottom-nav');
      if (nav && navActuelle) navActuelle.replaceWith(nav);

      // Styles propres à la page : on remplace les anciens par ceux du
      // document reçu, sans toucher au reste du <head>.
      document.head.querySelectorAll('style[data-page-style]').forEach(st => st.remove());
      doc.head.querySelectorAll('style').forEach(st => {
        const copie = document.importNode(st, true);
        copie.dataset.pageStyle = '1';
        document.head.appendChild(copie);
      });

      document.title = doc.title;
      await chargerScriptsDe(doc);

      if (poussuerHistorique) history.pushState({ spa: true }, '', cible.href);
      window.scrollTo(0, 0);
      initApp();
      // L'annonce du changement de page pour les lecteurs d'ecran, que la
      // navigation classique fait gratuitement et que le SPA doit refaire.
      const h = coquille.querySelector('h1, .display');
      if (h) { h.setAttribute('tabindex', '-1'); h.focus({ preventScroll: true }); }
    } catch (e) {
      if (e.name !== 'AbortError') location.href = cible.href;
    } finally {
      barre.classList.remove('active');
      document.body.removeAttribute('aria-busy');
      enCours = null;
    }
  }

  document.addEventListener('click', e => {
    if (e.defaultPrevented || e.button !== 0) return;
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    const a = e.target.closest('a');
    if (!a || a.target || a.hasAttribute('download') || a.getAttribute('href')?.startsWith('#')) return;
    const url = new URL(a.href, location.href);
    if (!interne(url) || url.href === location.href) return;
    e.preventDefault();
    aller(url.href);
  });

  window.addEventListener('popstate', () => {
    if (interne(new URL(location.href))) aller(location.href, false);
  });

  history.replaceState({ spa: true }, '', location.href);
})();

/* ─── Flux temps réel ─────────────────────────────────────────── */
/*
 * Un seul interrogateur pour toute la page, et non un par compteur.
 *
 * Trois règles, et chacune se traduit directement en charge évitée sur le
 * serveur :
 *
 *  1. Une requête par cycle, quel que soit le nombre de cartes à l'écran.
 *     Vingt soirées affichées, ce n'était pas vingt appels : c'est un seul,
 *     qui transporte les vingt identifiants.
 *
 *  2. Rien ne part quand l'onglet n'est pas regardé. C'est la règle la plus
 *     rentable de toutes : la plupart des onglets ouverts sur une application
 *     sont en arrière-plan, et chacun d'eux interrogeait le serveur toutes
 *     les quinze secondes pour un écran que personne ne voyait. Le retour au
 *     premier plan déclenche une interrogation immédiate : l'utilisateur
 *     retrouve un écran à jour, sans avoir attendu le prochain cycle.
 *
 *  3. L'ETag fait le reste. Le navigateur renvoie If-None-Match, le serveur
 *     répond 304 sans corps, et aucune requête de données n'est exécutée
 *     derrière. Le coût du temps réel devient proportionnel aux changements
 *     réels, et non au nombre de spectateurs.
 */
(function () {
  if (!window.fetch) return;

  const PERIODE = 8000;      // rythme quand l'onglet est regardé
  const MAX_CANAUX = 60;     // aligné sur LIVE_MAX_CANAUX, côté serveur

  let etag = null;
  let minuteur = null;
  let enCours = false;
  let arrete = false;

  /** Les événements actuellement à l'écran. Relu à chaque cycle : le routeur
   *  remplace le contenu sans recharger la page, la liste change donc seule. */
  function cartes() {
    return [...document.querySelectorAll('[data-live-event]')]
      .filter(el => +el.dataset.liveEvent > 0);
  }

  function appliquerCompteurs(inscrits) {
    if (!inscrits) return;
    cartes().forEach(carte => {
      const nb = inscrits[carte.dataset.liveEvent];
      if (nb === undefined) return;

      // La carte elle-même peut être la cible (tableau de bord partenaire),
      // ou la contenir (cartes du hub).
      const cible = carte.matches('[data-live="inscrits"]')
        ? carte
        : carte.querySelector('[data-live="inscrits"]');
      if (cible && cible.textContent !== String(nb)) cible.textContent = nb;

      const quota = +carte.dataset.liveQuota || 0;
      if (!quota) return;
      const pct = Math.min(100, Math.round(nb / quota * 100));

      const etiquette = carte.querySelector('[data-live="pct"]');
      if (etiquette) etiquette.textContent = pct + '%';
      const jauge = carte.querySelector('[data-live="jauge"]');
      if (jauge) jauge.style.width = pct + '%';

      // Complet : le bouton doit se fermer tout de suite, sinon l'utilisateur
      // clique dans le vide et reçoit un refus qu'il ne comprend pas.
      if (nb >= quota) {
        carte.querySelectorAll('.btn-join-event:not([disabled])')
             .forEach(b => { b.disabled = true; b.textContent = 'Complet'; });
      }
    });
  }

  function appliquerPastille(aTraiter) {
    const pastille = document.getElementById('cloche-compteur');
    if (!pastille) return;
    pastille.textContent = aTraiter;
    pastille.hidden = !aTraiter;
  }

  async function interroger() {
    if (enCours || arrete || document.hidden) return;
    enCours = true;
    try {
      const ids = cartes().map(el => el.dataset.liveEvent).slice(0, MAX_CANAUX);
      const url = `${BASE}/api/live.php` + (ids.length ? `?ev=${ids.join(',')}` : '');

      // cache:'no-store' + En-tête posé à la main : on veut voir le 304
      // nous-mêmes plutôt que de laisser le cache du navigateur le convertir
      // en 200 silencieux, ne serait-ce que pour pouvoir le mesurer.
      const entetes = etag ? { 'If-None-Match': etag } : {};
      const res = await fetch(url, { cache: 'no-store', headers: entetes });

      if (res.status === 304) return;          // rien n'a bougé, cas le plus fréquent
      if (res.status === 401) { arrete = true; return; }  // session fermée : on cesse
      if (!res.ok) return;

      etag = res.headers.get('ETag') || etag;
      const data = await res.json();
      if (!data || !data.success) return;

      appliquerCompteurs(data.inscrits);
      if (typeof data.aTraiter === 'number') appliquerPastille(data.aTraiter);
    } catch {
      // Réseau coupé, serveur qui redémarre : le prochain cycle reprendra.
      // Rien à signaler à l'utilisateur, le contenu affiché reste valable.
    } finally {
      enCours = false;
    }
  }

  function rythmer() {
    clearInterval(minuteur);
    if (arrete || document.hidden) return;
    minuteur = setInterval(interroger, PERIODE);
  }

  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      clearInterval(minuteur);
    } else {
      interroger();   // rattrapage immédiat au retour sur l'onglet
      rythmer();
    }
  });

  // Le premier cycle attend une période : la page vient d'être rendue, ses
  // compteurs sont frais, l'interroger tout de suite ne servirait qu'à
  // doubler le coût de chaque chargement de page.
  rythmer();
})();

/* ─── Service worker ─────────────────────────────────────────────────────── */
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    const portee = BASE ? BASE + '/' : '/';
    navigator.serviceWorker.register(portee + 'sw.js', { scope: portee })
      .catch(() => { /* l'app fonctionne sans : rien a signaler a l'utilisateur */ });
  });
}
