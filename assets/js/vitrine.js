/**
 * Vitrine : animations légères à l'apparition et barre « Télécharger » fixe
 * sur téléphone. Rien ici n'est indispensable : sans JavaScript (ou avec
 * prefers-reduced-motion) la page reste entière, simplement immobile.
 */
(function () {
  'use strict';

  // Avec « réduire les animations », le CSS ne garde qu'un fondu (pas de mouvement).
  var observe = 'IntersectionObserver' in window;

  // ─── Apparition au défilement ───────────────────────────────────────────
  if (observe) {
    var cibles = document.querySelectorAll(
      '.lp-entete, .lp-bande, .lp-tuile, .lp-etape, .lp-tarif, .lp-social__scene, ' +
      '.lp-social__liste, .lp-scene__texte, .lp-scene__visuel, .lp-final'
    );
    var rang = new Map();
    document.documentElement.classList.add('js-anim');

    var vigie = new IntersectionObserver(function (entrees) {
      entrees.forEach(function (e) {
        if (!e.isIntersecting) return;
        var el = e.target;
        vigie.unobserve(el);
        el.classList.add('lp-vu');
        // La classe retirée après coup, pour ne pas gêner les survols.
        window.setTimeout(function () {
          el.classList.remove('lp-revele', 'lp-vu');
          el.style.removeProperty('--d');
        }, 1200);
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

    cibles.forEach(function (el) {
      var n = rang.get(el.parentNode) || 0;
      rang.set(el.parentNode, n + 1);
      el.style.setProperty('--d', Math.min(n, 4) * 70 + 'ms');
      el.classList.add('lp-revele');
      vigie.observe(el);
    });
  }

  // ─── Barre « Télécharger » fixe (téléphone) ─────────────────────────────
  var barre = document.getElementById('lp-collant');
  var accroche = document.querySelector('.lp-hero .lp-actions');
  if (barre && accroche && observe) {
    var etat = { accrocheSortie: false, finVisible: false };
    var maj = function () {
      barre.classList.toggle('est-visible', etat.accrocheSortie && !etat.finVisible);
    };
    new IntersectionObserver(function (entrees) {
      var e = entrees[0];
      etat.accrocheSortie = !e.isIntersecting && e.boundingClientRect.top < 0;
      maj();
    }).observe(accroche);

    var fin = [document.getElementById('telecharger'), document.querySelector('.lp-pied')];
    var visibles = new Set();
    var vigieFin = new IntersectionObserver(function (entrees) {
      entrees.forEach(function (e) {
        if (e.isIntersecting) visibles.add(e.target); else visibles.delete(e.target);
      });
      etat.finVisible = visibles.size > 0;
      maj();
    });
    fin.forEach(function (el) { if (el) vigieFin.observe(el); });
  }
})();
