/*
 * Outsee pro — scanner les pass à l'entrée, ou valider une personne à la main.
 *
 * Tout bouton [data-scanner="<id événement>"] ouvre la modale #modale-scan
 * (voir modaleScanPro() dans includes/site.php). Deux chemins, comme dans
 * l'application :
 *   - la caméra lit le QR code et l'envoie à partenaire/api_scan.php ;
 *   - quand le scan ne passe pas (lumière, écran abîmé), on cherche la
 *     personne parmi les inscrits (partenaire/api_invites.php) et on valide.
 * Le serveur refuse un pass déjà scanné, y compris deux scans simultanés.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var modale = document.getElementById('modale-scan');
    if (!modale) return;

    var resultat = document.getElementById('scan-resultat');
    var titre = document.getElementById('scan-titre');
    var recherche = document.getElementById('scan-recherche');
    var liste = document.getElementById('scan-liste');
    var evenement = 0, lecteur = null, occupe = false, minuterie = null;
    var attente = 'Présentez le QR code devant la caméra.';

    function afficher(texte, etat) {
      resultat.textContent = texte;
      resultat.className = 'scan-resultat' + (etat ? ' scan-resultat--' + etat : '');
    }

    function arreterCamera() {
      if (!lecteur) return;
      var l = lecteur;
      lecteur = null;
      l.stop().then(function () { l.clear(); }).catch(function () {});
    }

    function fermer() {
      modale.classList.remove('ouverte');
      arreterCamera();
    }

    function valider(corps) {
      return fetch(window.BASE_SITE + '/partenaire/api_scan.php', {
        method: 'POST',
        headers: window.enTetesSite(),
        credentials: 'same-origin',
        body: JSON.stringify(Object.assign({ event_id: evenement }, corps))
      }).then(function (r) { return r.json(); }).then(function (d) {
        afficher(d.message || (d.success ? 'Entrée validée' : 'Pass refusé'), d.success ? 'ok' : 'ko');
        return d;
      }).catch(function () {
        afficher('Connexion impossible. Réessayez.', 'ko');
        return { success: false };
      });
    }

    function lu(texte) {
      if (occupe || !lecteur) return;
      occupe = true;
      lecteur.pause(true);
      valider({ qr_code: texte }).then(function () {
        setTimeout(function () {
          occupe = false;
          if (lecteur) { lecteur.resume(); afficher(attente, ''); }
        }, 1800);
      });
    }

    function chargerInvites() {
      var q = recherche.value.trim();
      fetch(window.BASE_SITE + '/partenaire/api_invites.php?event_id=' + evenement + '&q=' + encodeURIComponent(q), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (d) {
          liste.textContent = '';
          var invites = (d && d.invites) || [];
          if (!invites.length) {
            var vide = document.createElement('p');
            vide.className = 'carte__sous';
            vide.style.color = 'var(--gris-pale)';
            vide.textContent = q ? 'Personne de ce nom parmi les inscrits.' : 'Tous les inscrits sont déjà entrés.';
            liste.appendChild(vide);
            return;
          }
          invites.forEach(function (i) {
            var ligne = document.createElement('div');
            ligne.className = 'scan-invite';
            var nom = document.createElement('span');
            nom.textContent = i.prenom + ' ' + i.nom + (i.ecole ? ' · ' + i.ecole : '');
            var bouton = document.createElement('button');
            bouton.type = 'button';
            bouton.className = 'bouton bouton--plein bouton--petit';
            bouton.textContent = 'Valider';
            bouton.addEventListener('click', function () {
              bouton.disabled = true;
              valider({ inscription_id: i.inscription_id }).then(function (d) {
                if (d.success) ligne.remove(); else bouton.disabled = false;
              });
            });
            ligne.appendChild(nom);
            ligne.appendChild(bouton);
            liste.appendChild(ligne);
          });
        })
        .catch(function () { afficher('Liste indisponible pour le moment.', 'ko'); });
    }

    document.querySelectorAll('[data-scanner]').forEach(function (b) {
      b.addEventListener('click', function () {
        evenement = parseInt(b.getAttribute('data-scanner'), 10) || 0;
        titre.textContent = b.getAttribute('data-titre') || '';
        recherche.value = '';
        modale.classList.add('ouverte');
        afficher(attente, '');
        chargerInvites();
        if (typeof Html5Qrcode === 'undefined') { afficher('La caméra n\'est pas disponible : validez à la main ci-dessous.', 'ko'); return; }
        lecteur = new Html5Qrcode('lecteur');
        lecteur.start({ facingMode: 'environment' }, { fps: 10, qrbox: { width: 240, height: 240 } }, lu, function () {})
          .catch(function () { lecteur = null; afficher('Caméra indisponible : autorisez-la, ou validez à la main ci-dessous.', 'ko'); });
      });
    });

    recherche.addEventListener('input', function () {
      clearTimeout(minuterie);
      minuterie = setTimeout(chargerInvites, 250);
    });
    document.getElementById('fermer-scan').addEventListener('click', fermer);
    modale.addEventListener('click', function (e) { if (e.target === modale) fermer(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && modale.classList.contains('ouverte')) fermer(); });
  });
})();
