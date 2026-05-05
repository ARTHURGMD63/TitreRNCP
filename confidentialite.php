<?php require_once __DIR__ . '/includes/auth_check.php'; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>StudentLink — Politique de confidentialité</title>
<?= themeBootScript() ?>
<link rel="stylesheet" href="<?= baseUrl() ?>/assets/css/style.css">
<style>
  .legal-page { max-width: 760px; margin: 0 auto; padding: 40px 24px 80px; }
  .legal-page h1 { font-family:'Playfair Display',serif; font-weight:900; font-size:2.4rem; margin-bottom:32px; }
  .legal-page h2 { font-family:'Playfair Display',serif; font-weight:900; font-size:1.3rem; margin:32px 0 12px; }
  .legal-page p, .legal-page li { line-height:1.6; margin-bottom:10px; }
  .legal-page ul { padding-left: 22px; margin-bottom: 16px; }
  .legal-page a.back { color:var(--gris); font-size:13px; text-decoration:none; }
</style>
</head>
<body>
<div class="legal-page">
  <a href="javascript:history.back()" class="back">← Retour</a>
  <h1>Politique de confidentialité</h1>

  <p>Chez StudentLink, nous prenons la protection de tes données personnelles très au sérieux. Cette politique explique comment nous collectons, utilisons et protégeons tes informations, conformément au Règlement Général sur la Protection des Données (RGPD).</p>

  <h2>1. Données collectées</h2>
  <ul>
    <li><strong>Identité :</strong> nom, prénom, email</li>
    <li><strong>Profil étudiant :</strong> école, promotion, centres d'intérêt</li>
    <li><strong>Activité :</strong> inscriptions aux events, squads rejointes, abonnements</li>
    <li><strong>Techniques :</strong> adresse IP, logs de connexion</li>
  </ul>

  <h2>2. Finalités</h2>
  <p>Tes données sont utilisées pour :</p>
  <ul>
    <li>Gérer ton compte et t'authentifier</li>
    <li>Te proposer des events et personnes pertinents</li>
    <li>Permettre le check-in aux événements via QR code</li>
    <li>Fournir aux partenaires des statistiques anonymisées</li>
  </ul>

  <h2>3. Base légale</h2>
  <p>Le traitement repose sur ton consentement (inscription) et sur l'exécution du contrat (utilisation du service).</p>

  <h2>4. Durée de conservation</h2>
  <p>Tes données sont conservées tant que ton compte est actif. En cas de suppression, elles sont effacées sous 30 jours (sauf obligations légales).</p>

  <h2>5. Destinataires</h2>
  <p>Tes données sont accessibles uniquement par :</p>
  <ul>
    <li>L'équipe StudentLink</li>
    <li>Les partenaires pour les events auxquels tu t'inscris (prénom, nom, école, promo)</li>
    <li>L'hébergeur (Railway)</li>
  </ul>
  <p>Aucune donnée n'est vendue à des tiers.</p>

  <h2>6. Tes droits (RGPD)</h2>
  <p>Tu disposes d'un droit d'accès, de rectification, d'effacement, de limitation, de portabilité et d'opposition. Pour les exercer : contact@studentlink.fr</p>

  <h2>7. Cookies</h2>
  <p>StudentLink utilise uniquement un cookie de session pour te maintenir connecté. Aucun cookie publicitaire ou de tracking n'est déposé.</p>

  <h2>8. Sécurité</h2>
  <p>Les mots de passe sont hachés avec bcrypt. Les échanges sont chiffrés en HTTPS. Les sessions sont régénérées à chaque connexion pour éviter les détournements.</p>

  <p style="margin-top:40px;font-size:12px;color:var(--gris);">Dernière mise à jour : <?= date('d/m/Y') ?></p>

  <div style="margin-top:40px;padding-top:20px;border-top:2px solid var(--noir);display:flex;gap:16px;flex-wrap:wrap;">
    <a href="<?= baseUrl('/mentions-legales.php') ?>" style="color:var(--bleu);font-weight:600;text-decoration:none;">Mentions légales</a>
    <a href="<?= baseUrl('/cgu.php') ?>" style="color:var(--bleu);font-weight:600;text-decoration:none;">CGU</a>
  </div>
</div>
</body>
</html>
