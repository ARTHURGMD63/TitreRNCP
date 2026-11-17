/*
 * Outsee — comportements du site (vitrine, connexion, espace pro).
 * Volontairement minimal : chaque page fonctionne sans JavaScript, ce
 * fichier n'ajoute que le confort.
 */
(function () {
  'use strict';

  /** En-têtes d'un appel JSON en écriture : le jeton CSRF est exigé par le serveur. */
  function enTetesSite() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return {
      'Content-Type': 'application/json',
      'X-CSRF-Token': meta ? meta.getAttribute('content') : ''
    };
  }
  window.enTetesSite = enTetesSite;
  window.BASE_SITE = (document.querySelector('meta[name="base-url"]') || {}).content || '';

  document.addEventListener('DOMContentLoaded', function () {
    // Menu de l'espace pro sur petit écran.
    var pro = document.getElementById('pro');
    var bouton = document.querySelector('button[data-menu-pro]');
    document.querySelectorAll('[data-menu-pro]').forEach(function (el) {
      el.addEventListener('click', function () {
        if (!pro) return;
        var ouvert = pro.classList.toggle('menu-ouvert');
        if (bouton) bouton.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
      });
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && pro && pro.classList.contains('menu-ouvert')) {
        pro.classList.remove('menu-ouvert');
        if (bouton) bouton.setAttribute('aria-expanded', 'false');
      }
    });

    // Compte à rebours d'une mise en avant : <div data-restant-s="…" data-duree-s="…"> contenant
    // <strong data-compte-a-rebours> (et, en option, <span data-compte-jauge>). Le temps restant
    // vient du serveur, donc l'horloge du navigateur n'a pas d'importance.
    document.querySelectorAll('[data-restant-s]').forEach(function (bloc) {
      var restant = parseInt(bloc.getAttribute('data-restant-s'), 10);
      var duree = parseInt(bloc.getAttribute('data-duree-s'), 10);
      var fin = Date.now() + restant * 1000;
      var compte = bloc.querySelector('[data-compte-a-rebours]');
      var jauge = bloc.querySelector('[data-compte-jauge]');
      if (!compte || isNaN(restant)) return;
      function deux(n) { return (n < 10 ? '0' : '') + n; }
      var timer;
      function tick() {
        var s = Math.max(0, Math.round((fin - Date.now()) / 1000));
        var j = Math.floor(s / 86400), h = Math.floor((s % 86400) / 3600), m = Math.floor((s % 3600) / 60);
        compte.textContent = (j > 0 ? j + ' j ' : '') + (j > 0 || h > 0 ? h + ' h ' : '') + deux(m) + ' min ' + deux(s % 60) + ' s';
        if (jauge && duree > 0) jauge.style.width = Math.min(100, Math.max(0, (duree - s) / duree * 100)) + '%';
        if (s <= 0) {
          clearInterval(timer);
          compte.textContent = 'terminée';
          bloc.classList.add('sponso-chrono--fini');
        }
      }
      timer = setInterval(tick, 1000);
      tick();
    });

    // Confirmation avant une action destructrice : <form data-confirmer="…">.
    document.querySelectorAll('form[data-confirmer]').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        if (!window.confirm(form.getAttribute('data-confirmer'))) e.preventDefault();
      });
    });

    // Afficher / masquer un mot de passe : <button data-voir="id-du-champ">.
    document.querySelectorAll('[data-voir]').forEach(function (b) {
      b.addEventListener('click', function () {
        var champ = document.getElementById(b.getAttribute('data-voir'));
        if (!champ) return;
        var visible = champ.type === 'text';
        champ.type = visible ? 'password' : 'text';
        b.textContent = visible ? 'Afficher' : 'Masquer';
      });
    });
  });
})();
