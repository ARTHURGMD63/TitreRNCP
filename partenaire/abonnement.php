<?php
/**
 * Formule et achats de l'établissement.
 *
 * C'est ici, sur le site, que se prennent les engagements payants : formule
 * mensuelle, et (depuis le formulaire d'événement) événements supplémentaires
 * et mises en avant. L'application ne vend rien — règles Apple 3.1.1 et
 * Google Play Billing : elle affiche seulement l'état du compte.
 *
 * Paiement en ligne par Stripe dès que STRIPE_SECRET_KEY est réglée
 * (includes/stripe.php) : première souscription sur la page de paiement
 * hébergée par Stripe, formule enregistrée au retour (webhook ou page de
 * retour), changement de formule appliqué à l'abonnement Stripe au prorata,
 * espace client Stripe pour la carte, les factures et la résiliation.
 *
 * Sans clé Stripe, la souscription enregistre l'engagement, la fiche client
 * et la date de première facturation (souscrireFormule(), mêmes règles que
 * l'application) ; le prélèvement est alors mis en place par l'équipe.
 */
require_once __DIR__ . '/../includes/site.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/crm.php';
require_once __DIR__ . '/../includes/comptes.php';
require_once __DIR__ . '/../includes/capacites.php';
require_once __DIR__ . '/../includes/souscription.php';
require_once __DIR__ . '/../includes/stripe.php';

requirePartner();
$etab = etablissementConnecte($pdo);
$uid  = (int) $_SESSION['user_id'];

if (!$etab) {
    // Ancien compte partenaire sans établissement : rien à facturer.
    siteDebut('Formule — Outsee pro');
    echo '<div class="conteneur" style="padding:80px 24px;max-width:560px;"><h1 class="titre-pro">Aucun établissement</h1>'
       . '<p class="pro-entete__sous" style="margin:12px 0 24px;">Ce compte n\'est rattaché à aucun établissement. Écrivez-nous pour le compléter.</p>'
       . formulaireDeconnexion('Se déconnecter', 'bouton bouton--clair') . '</div>';
    siteFin();
    exit;
}

$etabId = (int) $etab['id'];
$erreur = '';
$enLigne = stripeActif();

// Retour de la page de paiement Stripe. Le webhook fait le même travail, mais
// peut arriver après le client : on lit la session pour ne pas lui montrer
// une formule absente. La session doit appartenir à cet établissement.
if ($enLigne && ($_GET['paiement'] ?? '') === 'ok' && preg_match('/^cs_[A-Za-z0-9_]+$/', (string) ($_GET['session'] ?? ''))) {
    try {
        $session = stripeRequete('GET', 'checkout/sessions/' . $_GET['session']);
        if ((int) ($session['metadata']['etablissement_id'] ?? 0) === $etabId) {
            $res = stripeAppliquerCheckout($pdo, $session);
            flashPro($res['ok'] ? 'Paiement enregistré : votre formule est active.' : $res['message'], $res['ok'] ? 'succes' : 'erreur');
        }
    } catch (StripeErreur $e) {
        logErreur('Stripe : lecture de la session de retour impossible', $e);
        flashPro('Paiement reçu : votre formule s’active dans quelques instants.', 'alerte');
    }
    unset($_SESSION['formule_souhaitee']);
    header('Location: ' . baseUrl(abonnementChoisi(abonnementEtablissement($pdo, $etabId)) ? '/partenaire/dashboard.php' : '/partenaire/abonnement.php'));
    exit;
}
if (($_GET['paiement'] ?? '') === 'annule') {
    flashPro('Paiement annulé : aucune formule n’a été enregistrée.', 'alerte');
    header('Location: ' . baseUrl('/partenaire/abonnement.php'));
    exit;
}

// Espace client Stripe : carte, mandat SEPA, factures, résiliation.
if ($enLigne && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['portail'])) {
    csrfVerify();
    $client = abonnementEtablissement($pdo, $etabId);
    try {
        $url = !empty($client['stripe_customer_id']) ? stripeOuvrirPortail((string) $client['stripe_customer_id']) : '';
        if ($url !== '') {
            header('Location: ' . $url, true, 303);
            exit;
        }
        $erreur = 'Aucun paiement en ligne n’est encore rattaché à ce compte.';
    } catch (StripeErreur $e) {
        logErreur('Stripe : ouverture de l’espace client impossible', $e, ['etablissement' => $etabId]);
        $erreur = 'L’espace de paiement est momentanément indisponible. Réessayez dans un instant.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['offre']) && $enLigne) {
    csrfVerify();
    $offre  = (string) $_POST['offre'];
    $client = abonnementEtablissement($pdo, $etabId);
    $refus  = controlerSouscription($pdo, $etab, $offre);
    $memeFormuleSansPaiement = $refus !== null && $refus['code'] === 'inchangee' && !stripeAbonnementVivant($client);

    if (empty($_POST['engagement'])) {
        $erreur = 'Confirmez que vous avez pris connaissance du tarif.';
    } elseif ($refus !== null && !$memeFormuleSansPaiement) {
        if ($refus['ok']) {
            flashPro($refus['message']);
            header('Location: ' . baseUrl('/partenaire/abonnement.php'));
            exit;
        }
        $erreur = $refus['message'];
    } else {
        $tarif = (float) (formulesSouscriptibles($pdo, (string) $etab['type'], $client)[$offre]['tarif'] ?? 0);
        try {
            if (stripeAbonnementVivant($client)) {
                // Abonnement déjà payé par Stripe : on change sa formule, au prorata.
                stripeChangerFormule((string) $client['stripe_subscription_id'], $offre, $tarif);
                $res = souscrireFormule(
                    $pdo, $etab,
                    ['id' => $uid, 'prenom' => $_SESSION['user_prenom'] ?? '', 'nom' => $_SESSION['user_nom'] ?? ''],
                    $offre
                );
                flashPro($res['ok'] ? 'Formule ' . crmLibelleOffre($offre) . ' enregistrée. La différence du mois figurera sur votre prochaine facture.' : $res['message'], $res['ok'] ? 'succes' : 'erreur');
                header('Location: ' . baseUrl('/partenaire/abonnement.php'));
                exit;
            }
            $stmt = $pdo->prepare('SELECT email FROM users WHERE id = ?');
            $stmt->execute([$uid]);
            $urlPaiement = stripeOuvrirCheckout($etab, $client, $offre, $tarif, $stmt->fetchColumn() ?: null);
            header('Location: ' . $urlPaiement, true, 303);
            exit;
        } catch (StripeErreur $e) {
            logErreur('Stripe : souscription impossible', $e, ['etablissement' => $etabId, 'offre' => $offre]);
            $erreur = 'Le paiement en ligne est momentanément indisponible. Réessayez dans un instant.';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['offre']) && !$enLigne) {
    csrfVerify();
    if (empty($_POST['engagement'])) {
        $erreur = 'Confirmez que vous avez pris connaissance du tarif.';
    } else {
        $stmt = $pdo->prepare('SELECT email FROM users WHERE id = ?');
        $stmt->execute([$uid]);
        $res = souscrireFormule(
            $pdo, $etab,
            ['id' => $uid, 'prenom' => $_SESSION['user_prenom'] ?? '', 'nom' => $_SESSION['user_nom'] ?? ''],
            (string) $_POST['offre'],
            $stmt->fetchColumn() ?: null
        );
        if ($res['ok']) {
            unset($_SESSION['formule_souhaitee']);
            flashPro($res['code'] === 'inchangee' ? $res['message'] : 'Formule ' . crmLibelleOffre((string) $_POST['offre']) . ' enregistrée. Notre équipe vous contacte pour le prélèvement.');
            header('Location: ' . baseUrl($res['code'] === 'inchangee' ? '/partenaire/abonnement.php' : '/partenaire/dashboard.php'));
            exit;
        }
        $erreur = (string) $res['message'];
    }
}

$etat      = etatAbonnement($pdo, $etab);
$client    = abonnementEtablissement($pdo, $etabId);
$actuelle  = abonnementChoisi($client) ? (string) $client['offre'] : null;
$formules  = formulesSouscriptibles($pdo, (string) $etab['type'], $client);
$grille    = grilleCapacites();
$restantes = placesFondateurRestantes($pdo);
$voulue    = (string) ($_POST['offre'] ?? $_SESSION['formule_souhaitee'] ?? $actuelle ?? '');
$bloquee   = in_array($etat, ['validation_requise', 'validation_refusee'], true);
$changementCeMois = $actuelle !== null && substr((string) ($client['offre_changee_le'] ?? ''), 0, 7) === date('Y-m');

$usage = null;
$achats = [];
if ($etat === '') {
    $capacites = capacitesEtablissement($pdo, $etabId);
    $usage     = usagePartenaireMois($pdo, $etabId, $capacites);
    $stmt = $pdo->prepare('SELECT nature, montant, libelle FROM achats_partenaires WHERE etablissement_id = ? AND mois = ? ORDER BY id DESC');
    $stmt->execute([$etabId, moisCourant()]);
    $achats = $stmt->fetchAll();
}

$natures = ['evenement' => 'Événement', 'flash' => 'Offre flash', 'sponsoring' => 'Mise en avant'];

proDebut('Formule et achats', 'abonnement', $etab);
?>
<?= messageFlashPro() ?>

<header class="pro-entete">
  <div>
    <p class="surtitre">Formule et achats</p>
    <?php if (isset($_GET['bienvenue']) && $actuelle === null): ?>
      <h1 class="titre-pro">Bienvenue ! <em>Choisissez votre formule.</em></h1>
      <p class="pro-entete__sous">Dernière étape avant de publier votre première soirée.</p>
    <?php elseif ($actuelle !== null): ?>
      <h1 class="titre-pro">Formule <em><?= htmlspecialchars(crmLibelleOffre($actuelle)) ?>.</em></h1>
      <p class="pro-entete__sous"><?= htmlspecialchars(eurosSite((float) ($client['mrr'] ?? 0) > 0 ? (float) $client['mrr'] : (float) (crmOffres()[$actuelle]['tarif'] ?? 0))) ?> HT par mois<?= !empty($client['essai_jusqu_au']) && strtotime((string) $client['essai_jusqu_au']) >= strtotime('today') ? ', offerte jusqu\'au ' . htmlspecialchars(dateFr($client['essai_jusqu_au'], 'j M Y')) : '' ?>.</p>
    <?php else: ?>
      <h1 class="titre-pro">Choisissez <em>votre formule.</em></h1>
    <?php endif; ?>
  </div>
</header>

<?php if ($erreur !== ''): ?>
  <div class="message message--erreur" role="alert" style="margin-bottom:20px;"><?= iconeSite('alerte') ?><span><?= htmlspecialchars($erreur) ?></span></div>
<?php endif; ?>
<?php if (in_array((string) ($client['stripe_statut'] ?? ''), ['past_due', 'unpaid'], true)): ?>
  <div class="message message--alerte" role="alert" style="margin-bottom:20px;"><?= iconeSite('alerte') ?><span>Le dernier prélèvement a échoué. Mettez à jour votre moyen de paiement depuis « Gérer mon paiement » pour garder votre formule.</span></div>
<?php endif; ?>

<?php if ($bloquee): ?>
  <section class="carte vide">
    <div class="vide__icone"><?= iconeSite($etat === 'validation_refusee' ? 'alerte' : 'horloge', 'icon-lg') ?></div>
    <?php if ($etat === 'validation_refusee'): ?>
      <h3>Votre compte BDE n'a pas été validé</h3>
      <p><?= htmlspecialchars((string) ($etab['verification_note'] ?? '') ?: 'Écrivez-nous si vous pensez qu\'il s\'agit d\'une erreur.') ?></p>
    <?php else: ?>
      <h3>Votre compte BDE est en cours de validation</h3>
      <p>L'équipe Outsee vérifie que votre association est bien étudiante. Vous pourrez choisir votre formule dès la validation.</p>
    <?php endif; ?>
  </section>
<?php else: ?>

<div class="grille-tdb">
  <div class="pile">
    <section class="carte" aria-labelledby="t-formules">
      <div class="carte__tete">
        <h2 class="carte__titre" id="t-formules"><?= $actuelle ? 'Changer de formule' : 'Les formules pour votre établissement' ?></h2>
        <span class="carte__sous">Prix HT par mois</span>
      </div>
      <?php if ($changementCeMois): ?>
        <div class="message message--alerte" style="margin-bottom:16px;"><?= iconeSite('info') ?><span>Vous avez déjà changé de formule ce mois-ci : prochain changement possible le <?= date('d/m/Y', (int) strtotime('first day of next month')) ?>.</span></div>
      <?php endif; ?>
      <form method="post" class="formulaire">
        <?= csrfField() ?>
        <div class="choix choix--liste" role="radiogroup" aria-label="Formule">
          <?php foreach ($formules as $code => $f):
              $cap = $grille[$code] ?? null;
          ?>
            <label>
              <input type="radio" name="offre" value="<?= htmlspecialchars($code) ?>"<?= $voulue === $code ? ' checked' : '' ?>>
              <span style="display:flex;justify-content:space-between;gap:12px;align-items:baseline;">
                <span><?= htmlspecialchars($f['libelle']) ?><?= $code === $actuelle ? ' · formule actuelle' : '' ?></span>
                <span style="font-size:18px;"><?= htmlspecialchars(eurosSite((float) $f['tarif'])) ?></span>
              </span>
              <small>
                <?= htmlspecialchars($f['note']) ?><?php if ($cap): ?> · <?= (int) ($cap['evenements_debut'] ?? $cap['evenements_par_mois']) ?> événements par mois<?= ($cap['flash_par_mois'] ?? 0) > 0 ? ', ' . (int) $cap['flash_par_mois'] . ' offre flash' : '' ?><?= ($cap['mises_en_avant_offertes'] ?? 0) > 0 ? ', ' . (int) $cap['mises_en_avant_offertes'] . ' mise en avant offerte' : '' ?><?php endif; ?>
                <?= $code === 'fondateur' ? ' · ' . $restantes . ' place' . ($restantes > 1 ? 's' : '') . ' restante' . ($restantes > 1 ? 's' : '') : '' ?>
              </small>
            </label>
          <?php endforeach; ?>
        </div>
        <label class="case">
          <input type="checkbox" name="engagement" value="1" required>
          <?php if ($enLigne): ?>
            <span>J'ai pris connaissance du tarif et je souhaite souscrire. Facturation mensuelle par carte ou prélèvement SEPA, sans engagement de durée : la formule se change une fois par mois, la résiliation se fait à tout moment depuis l'espace de paiement.</span>
          <?php else: ?>
            <span>J'ai pris connaissance du tarif et je souhaite souscrire. Facturation mensuelle par prélèvement, sans engagement de durée : la formule se change une fois par mois.</span>
          <?php endif; ?>
        </label>
        <div class="formulaire__pied">
          <?php $vivant = $enLigne && stripeAbonnementVivant($client); ?>
          <button type="submit" class="bouton bouton--plein"<?= $changementCeMois && (!$enLigne || $vivant) ? ' disabled' : '' ?>>
            <?= $enLigne && !$vivant ? 'Continuer vers le paiement' : ($actuelle ? 'Changer de formule' : 'Souscrire') ?>
          </button>
        </div>
      </form>
    </section>
  </div>

  <aside class="pile">
    <?php if ($usage !== null): ?>
      <section class="carte carte--accent" aria-labelledby="t-mois">
        <p class="surtitre">Ce mois-ci</p>
        <h2 class="carte__titre" id="t-mois" style="margin-top:8px;font-size:28px;"><?= htmlspecialchars(eurosSite((float) $usage['achats_total'])) ?> <span style="font-size:15px;font-weight:500;">HT d'achats en plus</span></h2>
        <p class="carte__sous" style="margin-top:6px;"><?= (int) $usage['evenements']['publies'] ?> événement(s) publié(s) sur <?= (int) $usage['evenements']['quota'] ?> compris.</p>
      </section>
      <section class="carte" aria-labelledby="t-registre">
        <div class="carte__tete"><h2 class="carte__titre" id="t-registre">Registre du mois</h2></div>
        <?php if (!$achats): ?>
          <p class="carte__sous">Rien de consommé ce mois-ci.</p>
        <?php else: ?>
          <div class="liste">
            <?php foreach ($achats as $a): ?>
              <div class="liste__ligne">
                <div class="liste__corps">
                  <div class="liste__titre"><?= htmlspecialchars($natures[$a['nature']] ?? (string) $a['nature']) ?></div>
                  <div class="liste__sous"><?= htmlspecialchars((string) ($a['libelle'] ?? '')) ?></div>
                </div>
                <span class="liste__fin"><?= (float) $a['montant'] > 0 ? htmlspecialchars(eurosSite((float) $a['montant'])) : 'Compris' ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
    <?php endif; ?>
    <section class="carte">
      <h2 class="carte__titre"><?= iconeSite('facture', 'icon-sm') ?> Paiement</h2>
      <?php if ($enLigne): ?>
        <p class="carte__sous" style="margin-top:8px;">Votre formule et vos achats du mois sont réglés une fois par mois, par carte ou prélèvement SEPA, sur la page sécurisée de Stripe : vos coordonnées bancaires ne passent jamais par Outsee. Chaque échéance donne lieu à une facture avec TVA.</p>
        <?php if (!empty($client['stripe_customer_id'])): ?>
          <form method="post" style="margin-top:14px;">
            <?= csrfField() ?>
            <button type="submit" name="portail" value="1" class="bouton bouton--clair">Gérer mon paiement et mes factures</button>
          </form>
          <?php if (!empty($client['stripe_periode_fin']) && $vivant): ?>
            <p class="carte__sous" style="margin-top:10px;">Prochaine échéance le <?= htmlspecialchars(dateFr((string) $client['stripe_periode_fin'], 'j M Y')) ?>.</p>
          <?php endif; ?>
        <?php endif; ?>
      <?php else: ?>
        <p class="carte__sous" style="margin-top:8px;">Votre formule et vos achats du mois sont prélevés une fois par mois. Pour toute question sur une facture, écrivez à l'équipe Outsee.</p>
      <?php endif; ?>
    </section>
  </aside>
</div>
<?php endif; ?>
<?php proFin(); ?>
