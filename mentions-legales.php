<?php require_once __DIR__ . '/includes/auth_check.php'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StudentLink — Mentions légales</title>
<?= themeBootScript() ?>
<link rel="stylesheet" href="<?= baseUrl() ?>/assets/css/style.css">
<style>
  .legal-page { max-width: 760px; margin: 0 auto; padding: 40px 24px 80px; }
  .legal-page h1 { font-family:'Playfair Display',serif; font-weight:900; font-size:2.4rem; margin-bottom:32px; }
  .legal-page h2 { font-family:'Playfair Display',serif; font-weight:900; font-size:1.3rem; margin:32px 0 12px; }
  .legal-page p  { line-height:1.6; margin-bottom:12px; }
  .legal-page a.back { color:var(--gris); font-size:13px; text-decoration:none; }
</style>
</head>
<body>
<div class="legal-page">
  <a href="javascript:history.back()" class="back">← Retour</a>
  <h1>Mentions légales</h1>

  <h2>Éditeur du site</h2>
  <p><strong>StudentLink</strong> — plateforme de mise en relation étudiants et établissements.<br>
  Projet étudiant réalisé dans le cadre du Titre RNCP.<br>
  Contact : contact@studentlink.fr</p>

  <h2>Hébergement</h2>
  <p>Railway Corporation — 548 Market St PMB 97005, San Francisco, CA 94104, USA<br>
  Site : railway.app</p>

  <h2>Directeur de la publication</h2>
  <p>Arthur Martin — étudiant responsable du projet.</p>

  <h2>Propriété intellectuelle</h2>
  <p>L'ensemble des contenus présents sur ce site (textes, images, logo, design) est la propriété de StudentLink ou de ses partenaires. Toute reproduction sans autorisation écrite est interdite.</p>

  <h2>Responsabilité</h2>
  <p>Les informations diffusées par les partenaires (événements, tarifs, horaires) sont publiées sous leur responsabilité. StudentLink ne peut être tenu responsable d'inexactitudes ou d'événements annulés.</p>

  <h2>Droit applicable</h2>
  <p>Les présentes mentions légales sont soumises au droit français.</p>

  <p style="margin-top:40px;font-size:12px;color:var(--gris);">Dernière mise à jour : <?= date('d/m/Y') ?></p>

  <div style="margin-top:40px;padding-top:20px;border-top:2px solid var(--noir);display:flex;gap:16px;flex-wrap:wrap;">
    <a href="<?= baseUrl('/cgu.php') ?>" style="color:var(--bleu);font-weight:600;text-decoration:none;">CGU</a>
    <a href="<?= baseUrl('/confidentialite.php') ?>" style="color:var(--bleu);font-weight:600;text-decoration:none;">Politique de confidentialité</a>
  </div>
</div>
</body>
</html>
