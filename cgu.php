<?php require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/page.php'; ?>
<?php ob_start(); ?>
<style>
  .legal-page { max-width: 760px; margin: 0 auto; padding: 40px 24px 80px; }
  .legal-page h1 { font-family:var(--font-display); font-weight:var(--fw-black); font-size:var(--fs-9); letter-spacing:var(--ls-display); line-height:var(--lh-tight); margin-bottom:32px; }
  .legal-page h2 { font-family:var(--font-display); font-weight:var(--fw-black); font-size:var(--fs-6); letter-spacing:var(--ls-display); margin:32px 0 12px; }
  .legal-page p, .legal-page li { line-height:var(--lh-relaxed); margin-bottom:10px; }
  .legal-page ul { padding-left: 22px; margin-bottom: 16px; }
  .legal-page a.back { color:var(--gris); font-size:var(--fs-4); text-decoration:none; }
  .legal-page a:not(.back) { color:var(--sur-rouge-clair); }
  .legal-page p, .legal-page li { color:var(--gris-fonce); }
  .legal-page strong { color:var(--noir); }
</style>
<?php pageDebut('Linkee — CGU', ['tete' => ob_get_clean()]); ?>
<div class="legal-page">
  <a href="javascript:history.back()" class="back">← Retour</a>
  <h1>Conditions Générales d'Utilisation</h1>

  <h2>Article 1 — Objet</h2>
  <p>Les présentes CGU régissent l'usage de la plateforme Linkee, qui permet aux étudiants de découvrir des événements, bénéficier de réductions et se regrouper pour des activités sportives ou sociales.</p>

  <h2>Article 2 — Inscription</h2>
  <p>L'inscription est gratuite et réservée aux étudiants majeurs ou aux établissements partenaires. L'utilisateur s'engage à fournir des informations exactes et à maintenir la confidentialité de son mot de passe.</p>

  <h2>Article 3 — Utilisation de la plateforme</h2>
  <p>L'utilisateur s'interdit de :</p>
  <ul>
    <li>Usurper l'identité d'une autre personne</li>
    <li>Publier des contenus illégaux, offensants ou discriminatoires</li>
    <li>Exploiter commercialement les données des autres utilisateurs</li>
    <li>Tenter d'accéder à des zones non autorisées du service</li>
  </ul>

  <h2>Article 4 — Réductions et passes</h2>
  <p>Les réductions proposées par les partenaires sont valables uniquement sur présentation du QR code lors du check-in. Linkee ne garantit pas la disponibilité des offres et décline toute responsabilité en cas de refus par l'établissement.</p>

  <h2>Article 5 — Responsabilité de l'utilisateur</h2>
  <p>L'utilisateur est seul responsable de son comportement au sein des événements et des squads. Linkee n'est pas partie prenante des interactions physiques organisées via la plateforme.</p>

  <h2>Article 6 — Suspension de compte</h2>
  <p>Linkee se réserve le droit de suspendre ou supprimer tout compte en cas de non-respect des présentes CGU, sans préavis ni justification.</p>

  <h2>Article 7 — Modification des CGU</h2>
  <p>Linkee peut modifier les présentes CGU à tout moment. Les utilisateurs seront informés par notification sur la plateforme.</p>

  <h2>Article 8 — Litiges</h2>
  <p>Tout litige relatif à l'utilisation de Linkee sera soumis au droit français et à la compétence des tribunaux de Clermont-Ferrand.</p>

  <p style="margin-top:40px;font-size:var(--fs-2);color:var(--gris);">
    Document réalisé à Clermont-Ferrand par Arthur Gramond, étudiant en B2 à Hesias.<br>
    Dernière mise à jour : <?= date('d/m/Y') ?></p>

  <div class="legal-links" style="margin-top:40px;padding-top:20px;border-top:1px solid var(--gris-clair);">
    <a href="<?= baseUrl('/mentions-legales.php') ?>" style="color:var(--bleu);font-weight:var(--fw-semibold);text-decoration:none;">Mentions légales</a>
    <a href="<?= baseUrl('/confidentialite.php') ?>" style="color:var(--bleu);font-weight:var(--fw-semibold);text-decoration:none;">Politique de confidentialité</a>
  </div>
</div>
</body>
</html>
