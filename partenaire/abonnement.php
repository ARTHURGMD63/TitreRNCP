<?php
/**
 * Choix de l'abonnement — passage obligé après l'inscription d'un établissement.
 *
 * Un compte partenaire créé sans formule n'était rattaché à rien : il publiait
 * des soirées, consommait l'audience, et n'apparaissait dans le CRM qu'en
 * « essai » sans que personne n'ait rien décidé. La formule se choisit donc ici,
 * avant l'accès au tableau de bord, et l'engagement pris atterrit dans la fiche
 * client et dans le registre financier.
 *
 * Aucun paiement en ligne : l'application ne collecte aucune donnée bancaire.
 * La page enregistre l'engagement et la date de première facturation ; la mise
 * en place du prélèvement se fait hors de l'outil.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/page.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/crm.php';
requirePartner();

$user = currentUser();
$uid  = $user['id'];

$stmt = $pdo->prepare("SELECT * FROM etablissements WHERE user_id = ? LIMIT 1");
$stmt->execute([$uid]);
$etab = $stmt->fetch();

// currentUser() ne lit que la session, qui ne porte pas l'e-mail : sans cette
// requete la fiche client naissait sans aucun moyen de joindre le partenaire.
$stmtC = $pdo->prepare("SELECT email FROM users WHERE id = ?");
$stmtC->execute([$uid]);
$emailContact = $stmtC->fetchColumn() ?: null;

if (!$etab) {
    // Compte partenaire sans établissement : rien à facturer.
    header('Location: ' . baseUrl('/partenaire/dashboard.php'));
    exit;
}

$client     = abonnementEtablissement($pdo, (int) $etab['id']);
$formules   = formulesSouscriptibles($pdo, (string) $etab['type'], $client);
$restantes  = placesFondateurRestantes($pdo);
$erreurs    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['souscrire'])) {
    csrfVerify();

    if (empty($_POST['engagement'])) {
        $erreurs[] = 'Merci de confirmer que tu as pris connaissance du tarif.';
    } else {
        // Les règles (formule permise pour le type du compte, une formule par
        // mois, période offerte une seule fois, place fondateur) sont celles
        // de l'application : elles vivent dans souscrireFormule().
        $res = souscrireFormule(
            $pdo, $etab,
            ['id' => $uid, 'prenom' => $user['prenom'] ?? '', 'nom' => $user['nom'] ?? ''],
            (string) ($_POST['offre'] ?? ''), $emailContact
        );
        if ($res['ok']) {
            header('Location: ' . baseUrl('/partenaire/abonnement.php?ok=1'));
            exit;
        }
        if ($res['code'] === 'fondateur_complet') {
            header('Location: ' . baseUrl('/partenaire/abonnement.php?complet=1'));
            exit;
        }
        $erreurs[] = $res['message'];
    }
}

// Relecture après écriture : la page doit montrer l'état réel, pas celui
// qu'on croit avoir posé.
$client    = abonnementEtablissement($pdo, (int) $etab['id']);
$formules  = formulesSouscriptibles($pdo, (string) $etab['type'], $client);
$restantes = placesFondateurRestantes($pdo);
$actuelle  = abonnementChoisi($client) ? $client['offre'] : null;
?>
<?php pageDebut('Outsee — Abonnement', ['univers' => 'pro']); ?>
<a href="#contenu" class="skip-nav">Aller au contenu</a>

<div class="abo-page">
  <div class="abo-entete">
    <div style="margin-bottom:22px;">
      <?= marqueOutsee('pro') ?>
    </div>
    <h1 class="titre-page">
      <span class="display" style="font-size:var(--fs-9);">Choisis ta</span><br>
      <span class="display-italic" style="font-size:var(--fs-9);">formule.</span>
    </h1>
    <p class="abo-intro">
      <strong><?= htmlspecialchars($etab['nom']) ?></strong> est créé. Il reste à choisir la formule
      avant de publier ta première soirée. Sans engagement de durée, résiliable au mois par simple message.
    </p>
  </div>

  <main id="contenu" class="abo-contenu">

    <?php if (isset($_GET['ok']) && $actuelle): ?>
      <div class="form-success" style="margin-bottom:22px;">
        Formule <strong><?= htmlspecialchars(crmLibelleOffre($actuelle)) ?></strong> enregistrée.
        <?php if ($client['essai_jusqu_au']): ?>
          Première facturation le <?= dateFr($client['essai_jusqu_au'], 'j M Y') ?>.
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if (isset($_GET['complet'])): ?>
      <div class="form-errors" style="margin-bottom:22px;">
        La dernière place fondateur vient d'être prise. Choisis une autre formule.
      </div>
    <?php endif; ?>

    <?php if ($erreurs): ?>
      <ul class="form-errors" style="margin-bottom:22px;">
        <?php foreach ($erreurs as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($actuelle): ?>
      <div class="abo-actuelle">
        <div>
          <div class="t-overline">Formule en cours</div>
          <div class="abo-actuelle-nom"><?= htmlspecialchars(crmLibelleOffre($actuelle)) ?></div>
          <div class="abo-actuelle-detail">
            <?= (float) $client['mrr'] > 0 ? htmlspecialchars(number_format((float) $client['mrr'], 0, ',', ' ')) . ' € HT / mois' : 'Gratuit' ?>
            <?php if ($client['essai_jusqu_au'] && strtotime($client['essai_jusqu_au']) > time()): ?>
              · offert jusqu'au <?= dateFr($client['essai_jusqu_au'], 'j M Y') ?>
            <?php endif; ?>
          </div>
        </div>
        <a href="<?= baseUrl('/partenaire/dashboard.php') ?>" class="btn btn-primary">Aller au tableau de bord</a>
      </div>
      <h2 class="abo-titre-section">Changer de formule</h2>
    <?php endif; ?>

    <form method="POST">
      <?= csrfField() ?>

      <div class="abo-grille">
        <?php foreach ($formules as $code => $f):
          $estFondateur = $code === 'fondateur';
          $estActuelle  = $actuelle === $code;
        ?>
          <label class="abo-carte<?= $estActuelle ? ' est-actuelle' : '' ?>">
            <input type="radio" name="offre" value="<?= $code ?>"
                   <?= $estActuelle ? 'checked' : '' ?> required>
            <span class="abo-carte-corps">
              <span class="abo-carte-entete">
                <span class="abo-carte-nom"><?= htmlspecialchars($f['libelle']) ?></span>
                <?php if ($estFondateur): ?>
                  <span class="badge" style="background:var(--lime-clair);color:var(--sur-lime-clair);">
                    <?= $restantes ?> place<?= $restantes > 1 ? 's' : '' ?> restante<?= $restantes > 1 ? 's' : '' ?>
                  </span>
                <?php endif; ?>
              </span>

              <span class="abo-carte-prix">
                <?= $f['tarif'] > 0 ? htmlspecialchars(number_format((float) $f['tarif'], 0, ',', ' ')) : '0' ?>
                <small>€ HT / mois</small>
              </span>

              <span class="abo-carte-note"><?= htmlspecialchars($f['note']) ?></span>

              <span class="abo-carte-detail">
                <?php if ($estFondateur): ?>
                  Offert les 3 premiers mois, puis 39 € gelés tant que l'abonnement n'est pas interrompu.
                <?php else: ?>
                  Offert jusqu'à ta première soirée test publiée et scannée.
                <?php endif; ?>
              </span>
            </span>
          </label>
        <?php endforeach; ?>
      </div>

      <div class="abo-inclus">
        <strong>Dans toutes les formules</strong>
        Soirées illimitées, page établissement avec photos, tableau de bord des inscrits et des présents,
        scan des pass à l'entrée, rappel automatique la veille. Aucune exclusivité demandée : tu gardes tes
        affiches, ton Instagram et tes partenariats BDE. Tu fixes ta remise étudiante, y compris à zéro.
      </div>

      <label class="case abo-engagement">
        <input type="checkbox" name="engagement" value="1" required>
        <span>
          J'ai pris connaissance du tarif et je souhaite souscrire. Facturation mensuelle,
          sans engagement de durée, résiliable par simple message.
        </span>
      </label>

      <p class="abo-paiement">
        Aucune donnée bancaire n'est demandée ici. L'équipe Outsee te contacte pour la mise
        en place du prélèvement avant la première échéance.
      </p>

      <button type="submit" name="souscrire" value="1" class="btn btn-primary btn-full" style="font-size:var(--fs-5);padding:15px;">
        <?= $actuelle ? 'Changer de formule' : 'Souscrire et accéder à mon espace' ?>
      </button>
    </form>

    <p class="abo-bde">
      Tu représentes un <strong>BDE ou une association étudiante</strong> ? Outsee est gratuit,
      définitivement. Écris-nous à <a href="mailto:contact@outsee.fr">contact@outsee.fr</a>
      et nous ouvrons l'accès sans formule payante.
    </p>
  </main>
</div>

<style>
  .abo-page { max-width: 940px; margin: 0 auto; padding: 40px 22px 72px; }
  .abo-entete { margin-bottom: 32px; }
  .abo-intro { font-size: var(--fs-4); color: var(--gris-fonce); line-height: var(--lh-normal); margin-top: 16px; max-width: 62ch; }
  .abo-titre-section { font-family: var(--font-display); font-weight: var(--fw-black); font-size: var(--fs-6); letter-spacing: var(--ls-display); margin-bottom: 14px; }

  .abo-actuelle {
    display: flex; align-items: center; justify-content: space-between; gap: 18px; flex-wrap: wrap;
    background: var(--blanc); border: 1px solid var(--gris-clair); border-radius: var(--radius);
    padding: 20px 22px; margin-bottom: 30px;
  }
  .abo-actuelle-nom { font-family: var(--font-display); font-weight: var(--fw-black); font-size: var(--fs-7); letter-spacing: var(--ls-display); }
  .abo-actuelle-detail { font-size: var(--fs-3); color: var(--gris-fonce); margin-top: 2px; }

  .abo-grille { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 14px; margin-bottom: 22px; }

  /* La carte entiere est la cible : un tarif se choisit en tapant sur l'offre,
     pas sur un bouton radio de 13 pixels. */
  .abo-carte { position: relative; display: block; cursor: pointer; }
  .abo-carte input {
    position: absolute; inset: 0; width: 100%; height: 100%; margin: 0; opacity: 0; cursor: pointer;
  }
  .abo-carte-corps {
    display: flex; flex-direction: column; gap: 8px; height: 100%;
    background: var(--blanc); border: 1px solid var(--gris-clair); border-radius: var(--radius);
    padding: 22px; transition: border-color .15s ease, box-shadow .15s ease;
  }
  .abo-carte:hover .abo-carte-corps { border-color: var(--gris); }
  .abo-carte input:checked + .abo-carte-corps {
    border-color: var(--rouge); box-shadow: 0 0 0 1px var(--rouge);
  }
  .abo-carte input:focus-visible + .abo-carte-corps { outline: 2px solid var(--rouge); outline-offset: 2px; }
  .abo-carte-entete { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
  .abo-carte-nom { font-weight: var(--fw-bold); font-size: var(--fs-5); }
  .abo-carte-prix { font-family: var(--font-display); font-weight: var(--fw-black); font-size: var(--fs-9); line-height: var(--lh-display); letter-spacing: var(--ls-display); }
  .abo-carte-prix small { font-family: var(--font-sans); font-weight: var(--fw-semibold); font-size: var(--fs-2); color: var(--gris); }
  .abo-carte-note { font-size: var(--fs-3); color: var(--gris-fonce); }
  .abo-carte-detail { font-size: var(--fs-2); color: var(--gris); margin-top: auto; padding-top: 8px; border-top: 1px solid var(--gris-clair); }

  .abo-inclus {
    background: var(--surface-2); border: 1px solid var(--gris-clair); border-radius: var(--radius-md);
    padding: 18px 20px; font-size: var(--fs-3); color: var(--gris-fonce); line-height: var(--lh-normal);
    margin-bottom: 20px;
  }
  .abo-inclus strong { display: block; color: var(--noir); margin-bottom: 6px; }
  .abo-engagement { align-items: flex-start; gap: 12px; margin-bottom: 14px; line-height: var(--lh-normal); font-size: var(--fs-3); }
  .abo-paiement { font-size: var(--fs-2); color: var(--gris); margin-bottom: 18px; }
  .abo-bde { margin-top: 28px; font-size: var(--fs-3); color: var(--gris-fonce); line-height: var(--lh-normal); }
  .abo-bde a { color: var(--sur-rouge-clair); font-weight: var(--fw-bold); }
</style>

<script src="<?= asset('/assets/js/app.js') ?>"></script>
</body>
</html>
