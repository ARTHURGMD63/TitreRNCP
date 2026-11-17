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
