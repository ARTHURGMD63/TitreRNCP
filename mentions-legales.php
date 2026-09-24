<?php require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/page.php'; ?>
<?php ob_start(); ?>
<style>
  .legal-page { max-width: 760px; margin: 0 auto; padding: 40px 24px 80px; }
  .legal-page h1 { font-family:var(--font-display); font-weight:var(--fw-black); font-size:var(--fs-9); letter-spacing:var(--ls-display); line-height:var(--lh-tight); margin-bottom:32px; }
  .legal-page h2 { font-family:var(--font-display); font-weight:var(--fw-black); font-size:var(--fs-6); letter-spacing:var(--ls-display); margin:32px 0 12px; }
  .legal-page p  { line-height:var(--lh-relaxed); margin-bottom:12px; }
  .legal-page a.back { color:var(--gris); font-size:var(--fs-4); text-decoration:none; }
  .legal-page a:not(.back) { color:var(--sur-rouge-clair); }
  .legal-page p, .legal-page li { color:var(--gris-fonce); }
  .legal-page strong { color:var(--noir); }
</style>
<?php pageDebut('Linkee — Mentions légales', ['tete' => ob_get_clean()]); ?>
<div class="legal-page">
  <a href="javascript:history.back()" class="back">← Retour</a>
  <h1>Mentions légales</h1>

  <h2>Éditeur du site</h2>
  <p><strong>Linkee</strong> — plateforme de mise en relation étudiants et établissements.<br>
  Projet réalisé à Clermont-Ferrand par <strong>Arthur Gramond</strong>, étudiant en 2<sup>e</sup> année (B2) à <strong>Hesias</strong>,
  dans le cadre du Titre professionnel Développeur Web et Web Mobile.<br>
  Contact : contact@linkee.fr</p>

  <h2>Hébergement</h2>
  <p>Application hébergée en environnement local (serveur WAMP), Clermont-Ferrand.</p>

  <h2>Directeur de la publication</h2>
  <p>Arthur Gramond — étudiant en B2 à Hesias, Clermont-Ferrand.</p>

  <h2>Propriété intellectuelle</h2>
  <p>L'ensemble des contenus présents sur ce site (textes, images, logo, design) est la propriété de Linkee ou de ses partenaires. Toute reproduction sans autorisation écrite est interdite.</p>

  <h2>Responsabilité</h2>
  <p>Les informations diffusées par les partenaires (événements, tarifs, horaires) sont publiées sous leur responsabilité. Linkee ne peut être tenu responsable d'inexactitudes ou d'événements annulés.</p>

  <h2>Droit applicable</h2>
  <p>Les présentes mentions légales sont soumises au droit français.</p>

  <p style="margin-top:40px;font-size:var(--fs-2);color:var(--gris);">Dernière mise à jour : <?= date('d/m/Y') ?></p>

  <div class="legal-links" style="margin-top:40px;padding-top:20px;border-top:1px solid var(--gris-clair);">
    <a href="<?= baseUrl('/cgu.php') ?>" style="color:var(--bleu);font-weight:var(--fw-semibold);text-decoration:none;">CGU</a>
    <a href="<?= baseUrl('/confidentialite.php') ?>" style="color:var(--bleu);font-weight:var(--fw-semibold);text-decoration:none;">Politique de confidentialité</a>
  </div>
</div>
</body>
</html>
