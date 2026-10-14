<?php require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/page.php';
require_once __DIR__ . '/includes/textes_legaux.php';
$doc = textesLegaux()['cgu']; ?>
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
  <h1><?= htmlspecialchars($doc['titre'], ENT_QUOTES, 'UTF-8') ?></h1>

  <?= legalCorpsHtml($doc) ?>

  <div class="legal-links" style="margin-top:40px;padding-top:20px;border-top:1px solid var(--gris-clair);">
    <a href="<?= baseUrl('/mentions-legales.php') ?>" style="color:var(--bleu);font-weight:var(--fw-semibold);text-decoration:none;">Mentions légales</a>
    <a href="<?= baseUrl('/confidentialite.php') ?>" style="color:var(--bleu);font-weight:var(--fw-semibold);text-decoration:none;">Politique de confidentialité</a>
  </div>
</div>
</body>
</html>
