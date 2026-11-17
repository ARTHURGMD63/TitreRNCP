<?php
/**
 * Paiement des établissements par Stripe.
 *
 * Le parcours, de bout en bout :
 *
 *   1. l'établissement choisit sa formule sur le site (partenaire/abonnement.php)
 *      et part sur la page de paiement hébergée par Stripe (Checkout). Aucune
 *      donnée bancaire ne passe par notre serveur : carte et prélèvement SEPA
 *      se saisissent chez Stripe ;
 *   2. Stripe crée l'abonnement mensuel, émet les factures avec TVA, et
 *      prévient le serveur à chaque étape par un webhook (webhooks/stripe.php) :
 *      paiement, renouvellement, impayé, résiliation ;
 *   3. le webhook met à jour `crm_clients` (formule, statut) par les mêmes
 *      fonctions que le reste du site — souscrireFormule() — si bien que les
 *      quotas lus par l'application suivent sans autre changement ;
 *   4. les suppléments du mois (événement au-delà du quota, mise en avant
 *      payante) restent inscrits au registre `achats_partenaires` à l'instant
 *      où ils sont consommés, puis partent chez Stripe comme lignes de la
 *      prochaine facture mensuelle (stripeSynchroniserAchats(), lancée par
 *      cron/stripe.php). La règle commerciale reste celle affichée : un seul
 *      prélèvement par mois ;
 *   5. l'établissement change de carte, télécharge ses factures ou résilie
 *      seul, depuis l'espace client Stripe (stripeOuvrirPortail()).
 *
 * Pas de bibliothèque : l'API de Stripe est un REST encodé en formulaire, et
 * nous n'en utilisons que cinq points. La vérification de signature des
 * webhooks suit la spécification publiée (HMAC-SHA256 de « horodatage.corps »,
 * comparaison à temps constant, tolérance de cinq minutes).
 *
 * Sans STRIPE_SECRET_KEY, rien de ce fichier ne s'active : la page Formule
 * garde son fonctionnement d'avant (engagement enregistré, prélèvement mis en
 * place par l'équipe). C'est ce qui permet de livrer le code avant les clés.
 *
 * Réglages (variables d'environnement, ou config.local.php) :
 *   STRIPE_SECRET_KEY      stripe_secret        sk_live_… / sk_test_…
 *   STRIPE_WEBHOOK_SECRET  stripe_webhook       whsec_… (Développeurs → Webhooks)
 *   STRIPE_TAUX_TVA        stripe_taux_tva      txr_… : taux de TVA 20 % créé dans
 *                                               Stripe, appliqué aux abonnements et
 *                                               aux suppléments (prix affichés HT)
 *   APP_URL                url_publique         https://outsee.fr (sans / final)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/log.php';
require_once __DIR__ . '/urls.php';
require_once __DIR__ . '/crm.php';
require_once __DIR__ . '/capacites.php';

const STRIPE_API = 'https://api.stripe.com/v1/';

/** Écart toléré entre l'horodatage signé d'un webhook et l'heure du serveur. */
const STRIPE_TOLERANCE_WEBHOOK = 300;

final class StripeErreur extends RuntimeException
{
}

function stripeCleSecrete(): string
{
    return trim(reglage('STRIPE_SECRET_KEY', 'stripe_secret'));
}

/** Le paiement en ligne est-il branché sur cette installation ? */
function stripeActif(): bool
{
    return stripeCleSecrete() !== '';
}

/** Un montant en euros, en centimes pour Stripe. */
function stripeCentimes(float $euros): int
{
    return (int) round($euros * 100);
}

/**
 * Encode des paramètres à la manière de Stripe : `a[b][0][c]=…`.
 *
 * http_build_query() écrirait les listes `a[0]` aussi, mais transforme les
 * booléens en 1/0, que Stripe refuse : ils partent ici en true/false.
 *
 * @param array<string|int, mixed> $params
 */
function stripeEncoder(array $params, string $prefixe = ''): string
{
    $paires = [];
    foreach ($params as $cle => $valeur) {
        $nom = $prefixe === '' ? (string) $cle : $prefixe . '[' . $cle . ']';
        if ($valeur === null) {
            continue;
        }
        if (is_array($valeur)) {
            $sous = stripeEncoder($valeur, $nom);
            if ($sous !== '') {
                $paires[] = $sous;
            }
            continue;
        }
        if (is_bool($valeur)) {
            $valeur = $valeur ? 'true' : 'false';
        }
        $paires[] = rawurlencode($nom) . '=' . rawurlencode((string) $valeur);
    }

    return implode('&', $paires);
}

/**
 * Un appel à l'API Stripe.
 *
 * @param array<string, mixed> $params
 * @return array<string, mixed>
 * @throws StripeErreur
 */
function stripeRequete(string $methode, string $chemin, array $params = [], ?string $cleIdempotence = null): array
{
    $cle = stripeCleSecrete();
    if ($cle === '') {
        throw new StripeErreur('Stripe n’est pas configuré.');
    }

    $url   = STRIPE_API . ltrim($chemin, '/');
    $corps = stripeEncoder($params);
    if ($methode === 'GET' && $corps !== '') {
        $url  .= '?' . $corps;
        $corps = '';
    }

    $entetes = [
        'Authorization: Bearer ' . $cle,
        'Content-Type: application/x-www-form-urlencoded',
        'Stripe-Version: 2024-06-20',
    ];
    if ($cleIdempotence !== null) {
        // Rejouer un appel (cron relancé, double clic) ne crée pas deux fois
        // la même ligne de facture : Stripe renvoie le premier résultat.
        $entetes[] = 'Idempotency-Key: ' . $cleIdempotence;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST  => $methode,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $entetes,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    if ($methode !== 'GET') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $corps);
    }
    // Le PHP de WAMP n'a aucune liste d'autorités de certification : sans
    // elle, chaque appel échoue en « unable to get local issuer certificate ».
    // Sous Windows, on prend le magasin de certificats du système. Le
    // certificat reste vérifié ; le serveur Linux, lui, a sa propre liste.
    if (PHP_OS_FAMILY === 'Windows' && (string) ini_get('curl.cainfo') === '' && defined('CURLSSLOPT_NATIVE_CA')) {
        curl_setopt($ch, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
    }
    $reponse = curl_exec($ch);
    $code    = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $reseau  = curl_error($ch);
    curl_close($ch);

    if (!is_string($reponse)) {
        throw new StripeErreur('Stripe injoignable : ' . $reseau);
    }
    $json = json_decode($reponse, true);
    if (!is_array($json)) {
        throw new StripeErreur('Réponse Stripe illisible (HTTP ' . $code . ').');
    }
    if ($code >= 400) {
        $message = (string) ($json['error']['message'] ?? 'erreur inconnue');
        throw new StripeErreur('Stripe a refusé la demande (HTTP ' . $code . ') : ' . $message, $code);
    }

    return $json;
}

/**
 * Vérifie la signature d'un webhook et renvoie l'événement décodé.
 *
 * En-tête `Stripe-Signature: t=1700000000,v1=<hex>[,v1=<hex>…]` ; la
 * signature est le HMAC-SHA256 de « t.corps » avec le secret du point de
 * terminaison. Un horodatage trop ancien est refusé : sans cela, un corps
 * intercepté pourrait être rejoué indéfiniment.
 *
 * @return array<string, mixed>
 * @throws StripeErreur
 */
function stripeVerifierWebhook(string $corps, string $entete, string $secret, ?int $maintenant = null): array
{
    if ($secret === '') {
        throw new StripeErreur('Secret de webhook absent.');
    }

    $horodatage = null;
    $signatures = [];
    foreach (explode(',', $entete) as $morceau) {
        $paire = explode('=', trim($morceau), 2);
        if (count($paire) !== 2) {
            continue;
        }
        if ($paire[0] === 't' && ctype_digit($paire[1])) {
            $horodatage = (int) $paire[1];
        } elseif ($paire[0] === 'v1') {
            $signatures[] = $paire[1];
        }
    }
    if ($horodatage === null || $signatures === []) {
        throw new StripeErreur('En-tête de signature invalide.');
    }

    $attendue = hash_hmac('sha256', $horodatage . '.' . $corps, $secret);
    $valide   = false;
    foreach ($signatures as $sig) {
        if (hash_equals($attendue, $sig)) {
            $valide = true;
            break;
        }
    }
    if (!$valide) {
        throw new StripeErreur('Signature invalide.');
    }
    if (abs(($maintenant ?? time()) - $horodatage) > STRIPE_TOLERANCE_WEBHOOK) {
        throw new StripeErreur('Horodatage hors tolérance.');
    }

    $evenement = json_decode($corps, true);
    if (!is_array($evenement) || !isset($evenement['id'], $evenement['type'])) {
        throw new StripeErreur('Corps d’événement illisible.');
    }

    return $evenement;
}

/** @return list<string> les taux de TVA à appliquer, s'il y en a un de réglé */
function stripeTauxTva(): array
{
    $taux = trim(reglage('STRIPE_TAUX_TVA', 'stripe_taux_tva'));

    return $taux === '' ? [] : [$taux];
}

/**
 * Le produit Stripe d'une formule, créé au premier besoin.
 *
 * Son identifiant est fixe (`outsee_premium`…) : il se retrouve sans base de
 * correspondance, et la facture porte le nom de la formule. Un changement de
 * formule exige un produit existant (Stripe n'accepte pas de description de
 * produit en ligne sur une ligne d'abonnement).
 *
 * @throws StripeErreur
 */
function stripeProduitFormule(string $offre): string
{
    static $connus = [];
    $id = 'outsee_' . $offre;
    if (isset($connus[$id])) {
        return $id;
    }
    try {
        stripeRequete('GET', 'products/' . $id);
    } catch (StripeErreur $e) {
        if ($e->getCode() !== 404) {
            throw $e;
        }
        stripeRequete('POST', 'products', ['id' => $id, 'name' => 'Outsee ' . crmLibelleOffre($offre)], 'produit-' . $id);
    }
    $connus[$id] = true;

    return $id;
}

/**
 * Le prix mensuel d'une formule, décrit en ligne : la grille vit dans le code
 * (crmOffres()), pas dans le tableau de bord Stripe. Un tarif changé ici
 * s'applique aux nouvelles souscriptions sans rien recréer chez Stripe ; les
 * abonnements en cours gardent leur prix, comme le veut `crm_clients.mrr`.
 *
 * @return array<string, mixed>
 * @throws StripeErreur
 */
function stripePrixFormule(string $offre, float $tarif): array
{
    return [
        'currency'     => 'eur',
        'unit_amount'  => stripeCentimes($tarif),
        'recurring'    => ['interval' => 'month'],
        'tax_behavior' => 'exclusive',
        'product'      => stripeProduitFormule($offre),
    ];
}

/**
 * Ouvre une page de paiement Stripe pour une première souscription.
 *
 * La formule n'est enregistrée qu'au retour de Stripe (webhook ou page de
 * retour) : une souscription abandonnée sur la page de paiement ne donne
 * aucun droit. La période offerte (finEssaiPourFormule()) devient la période
 * d'essai de l'abonnement : la carte ou le mandat SEPA est enregistré tout de
 * suite, rien n'est prélevé avant sa fin.
 *
 * @param array<string, mixed>      $etab
 * @param array<string, mixed>|null $client la fiche crm_clients
 * @return string l'adresse de la page de paiement
 * @throws StripeErreur
 */
function stripeOuvrirCheckout(array $etab, ?array $client, string $offre, float $tarif, ?string $email): string
{
    $etabId   = (int) $etab['id'];
    $premiere = $client === null || empty($client['signe_le']);

    $abonnement = [
        'metadata' => ['etablissement_id' => (string) $etabId, 'offre' => $offre],
    ];
    // Première souscription : la période offerte de la formule. Un client
    // déjà signé (prélèvement manuel jusque-là) garde la sienne si elle court
    // encore. Stripe exige une fin d'essai à plus de 48 heures.
    $essai    = $premiere ? finEssaiPourFormule($offre) : ($client['essai_jusqu_au'] ?? null);
    $finEssai = $essai ? strtotime($essai . ' 12:00:00') : false;
    if ($finEssai !== false && $finEssai > time() + 172800) {
        $abonnement['trial_end'] = $finEssai;
    }
    $taux = stripeTauxTva();
    if ($taux !== []) {
        $abonnement['default_tax_rates'] = $taux;
    }

    $params = [
        'mode'                       => 'subscription',
        'locale'                     => 'fr',
        'client_reference_id'        => (string) $etabId,
        'line_items'                 => [['price_data' => stripePrixFormule($offre, $tarif), 'quantity' => 1]],
        'subscription_data'          => $abonnement,
        'metadata'                   => ['etablissement_id' => (string) $etabId, 'offre' => $offre],
        'billing_address_collection' => 'required',
        'tax_id_collection'          => ['enabled' => true],
        'allow_promotion_codes'      => true,
        'success_url'                => urlPublique('/partenaire/abonnement.php?paiement=ok&session={CHECKOUT_SESSION_ID}'),
        'cancel_url'                 => urlPublique('/partenaire/abonnement.php?paiement=annule'),
    ];
    if (!empty($client['stripe_customer_id'])) {
        $params['customer']        = (string) $client['stripe_customer_id'];
        $params['customer_update'] = ['address' => 'auto', 'name' => 'auto'];
    } elseif ($email !== null && $email !== '') {
        $params['customer_email'] = $email;
    }

    $session = stripeRequete('POST', 'checkout/sessions', $params);
    if (empty($session['url'])) {
        throw new StripeErreur('Stripe n’a pas renvoyé de page de paiement.');
    }

    return (string) $session['url'];
}

/**
 * Change la formule d'un abonnement Stripe déjà en cours.
 *
 * L'écart du mois est calculé au prorata par Stripe et ajouté à la prochaine
 * facture ; la base est mise à jour par l'appelant (souscrireFormule()), et
 * le webhook `customer.subscription.updated` qui suit ne fait que confirmer.
 *
 * @throws StripeErreur
 */
function stripeChangerFormule(string $abonnementId, string $offre, float $tarif): void
{
    $abonnement = stripeRequete('GET', 'subscriptions/' . rawurlencode($abonnementId));
    $ligne      = $abonnement['items']['data'][0]['id'] ?? null;
    if (!is_string($ligne)) {
        throw new StripeErreur('Abonnement Stripe sans ligne de formule.');
    }

    stripeRequete('POST', 'subscriptions/' . rawurlencode($abonnementId), [
        'items'              => [['id' => $ligne, 'price_data' => stripePrixFormule($offre, $tarif)]],
        'proration_behavior' => 'create_prorations',
        'metadata'           => ['offre' => $offre],
    ]);
}

/**
 * Ouvre l'espace client Stripe : moyen de paiement, factures, résiliation.
 *
 * @throws StripeErreur
 */
function stripeOuvrirPortail(string $clientStripe): string
{
    $portail = stripeRequete('POST', 'billing_portal/sessions', [
        'customer'   => $clientStripe,
        'locale'     => 'fr',
        'return_url' => urlPublique('/partenaire/abonnement.php'),
    ]);

    return (string) ($portail['url'] ?? '');
}

/**
 * L'abonnement Stripe est-il en cours (essai compris) ?
 *
 * @param array<string, mixed>|null $client
 */
function stripeAbonnementVivant(?array $client): bool
{
    return $client !== null
        && !empty($client['stripe_subscription_id'])
        && in_array((string) ($client['stripe_statut'] ?? ''), ['trialing', 'active', 'past_due', 'unpaid', 'incomplete'], true);
}

// ─── Traitement des événements ─────────────────────────────────────────────

/**
 * Enregistre la souscription payée sur Stripe.
 *
 * Appelée par le webhook `checkout.session.completed` et par la page de
 * retour (le webhook peut arriver après le client) : les deux chemins sont
 * idempotents, la seconde arrivée ne fait rien.
 *
 * @param array<string, mixed> $session l'objet Checkout Session
 * @return array{ok:bool, message:string}
 */
function stripeAppliquerCheckout(PDO $pdo, array $session): array
{
    if (($session['mode'] ?? '') !== 'subscription' || empty($session['subscription'])) {
        return ['ok' => true, 'message' => 'Session sans abonnement : rien à faire.'];
    }
    if (!in_array((string) ($session['status'] ?? 'complete'), ['complete'], true)) {
        return ['ok' => false, 'message' => 'Paiement non terminé.'];
    }

    $etabId = (int) ($session['metadata']['etablissement_id'] ?? $session['client_reference_id'] ?? 0);
    $offre  = (string) ($session['metadata']['offre'] ?? '');
    $abonId = is_array($session['subscription']) ? (string) $session['subscription']['id'] : (string) $session['subscription'];
    $custId = is_array($session['customer'] ?? null) ? (string) $session['customer']['id'] : (string) ($session['customer'] ?? '');

    $stmt = $pdo->prepare('SELECT * FROM etablissements WHERE id = ?');
    $stmt->execute([$etabId]);
    $etab = $stmt->fetch();
    if (!$etab || $offre === '') {
        logErreur('Stripe : session sans établissement reconnu', null, ['session' => $session['id'] ?? '']);
        return ['ok' => false, 'message' => 'Établissement inconnu.'];
    }

    $client = abonnementEtablissement($pdo, $etabId);
    if ($client && ($client['stripe_subscription_id'] ?? '') === $abonId) {
        return ['ok' => true, 'message' => 'Déjà enregistrée.'];
    }

    $stmt = $pdo->prepare('SELECT id, prenom, nom, email FROM users WHERE id = ?');
    $stmt->execute([(int) $etab['user_id']]);
    $user = $stmt->fetch() ?: ['id' => 0, 'prenom' => '', 'nom' => '', 'email' => null];

    $res = souscrireFormule($pdo, $etab, $user, $offre, $user['email'] ?? null);
    if (!$res['ok']) {
        // La place fondateur a pu partir entre le choix et le paiement, ou le
        // compte a changé d'état : rien n'a encore été prélevé (essai), on
        // annule l'abonnement plutôt que de facturer une formule refusée.
        logErreur('Stripe : souscription refusée après paiement, abonnement annulé', null, [
            'etablissement' => $etabId, 'offre' => $offre, 'motif' => $res['code'],
        ]);
        try {
            stripeRequete('DELETE', 'subscriptions/' . rawurlencode($abonId));
        } catch (StripeErreur $e) {
            logErreur('Stripe : annulation impossible', $e, ['abonnement' => $abonId]);
        }

        return ['ok' => false, 'message' => $res['message']];
    }

    $statut = 'trialing';
    try {
        $abonnement = stripeRequete('GET', 'subscriptions/' . rawurlencode($abonId));
        $statut     = (string) ($abonnement['status'] ?? 'trialing');
    } catch (StripeErreur $e) {
        logErreur('Stripe : lecture de l’abonnement impossible', $e, ['abonnement' => $abonId]);
    }

    $pdo->prepare(
        'UPDATE crm_clients
            SET stripe_customer_id = ?, stripe_subscription_id = ?, stripe_statut = ?,
                stripe_lie_le = COALESCE(stripe_lie_le, NOW())
          WHERE etablissement_id = ?'
    )->execute([$custId ?: null, $abonId, $statut, $etabId]);

    return ['ok' => true, 'message' => $res['message']];
}

/**
 * Reporte l'état d'un abonnement Stripe sur la fiche client.
 *
 * `trialing` et `active` laissent la formule ouverte. `past_due` (paiement en
 * échec, Stripe relance) la laisse aussi : l'espace pro affiche l'alerte, et
 * c'est la fin des relances qui tranche. `canceled` — résiliation par le
 * client, ou relances épuisées si le tableau de bord Stripe est réglé pour
 * annuler — ferme la formule : sans formule, l'espace pro et l'application
 * renvoient vers la page Formule (etatAbonnement()).
 *
 * @param array<string, mixed> $abonnement l'objet Subscription
 */
function stripeAppliquerAbonnement(PDO $pdo, array $abonnement, bool $supprime = false): void
{
    $abonId = (string) ($abonnement['id'] ?? '');
    $statut = $supprime ? 'canceled' : (string) ($abonnement['status'] ?? '');

    $stmt = $pdo->prepare('SELECT * FROM crm_clients WHERE stripe_subscription_id = ?');
    $stmt->execute([$abonId]);
    $client = $stmt->fetch();
    if (!$client) {
        return; // Abonnement d'avant le lien (checkout pas encore traité) : le checkout s'en chargera.
    }

    $finPeriode = isset($abonnement['current_period_end']) ? date('Y-m-d', (int) $abonnement['current_period_end']) : null;

    if (in_array($statut, ['canceled', 'incomplete_expired'], true)) {
        $pdo->prepare(
            "UPDATE crm_clients
                SET stripe_statut = ?, offre = 'aucune', mrr = 0, statut = 'perdu', perdu_le = CURDATE(),
                    motif_perte = COALESCE(motif_perte, 'Abonnement résilié (Stripe)')
              WHERE id = ?"
        )->execute([$statut, $client['id']]);

        return;
    }

    $statutCrm = match ($statut) {
        'trialing' => 'essai',
        'active'   => 'actif',
        default    => (string) $client['statut'],
    };
    $pdo->prepare('UPDATE crm_clients SET stripe_statut = ?, stripe_periode_fin = ?, statut = ? WHERE id = ?')
        ->execute([$statut, $finPeriode, $statutCrm, $client['id']]);
}

/**
 * Une facture Stripe payée entre au registre financier.
 *
 * L'échéance « prévue » posée à la souscription (souscrireFormule()) devient
 * « réglée » ; à défaut, une recette est ajoutée. Les factures à 0 € (début
 * d'essai) ne s'inscrivent pas.
 *
 * @param array<string, mixed> $facture l'objet Invoice
 */
function stripeEnregistrerFacturePayee(PDO $pdo, array $facture): void
{
    $abonId = (string) ($facture['subscription'] ?? '');
    $horsTaxe = ((int) ($facture['total_excluding_tax'] ?? $facture['subtotal'] ?? 0)) / 100;
    if ($abonId === '' || $horsTaxe <= 0) {
        return;
    }

    $stmt = $pdo->prepare('SELECT * FROM crm_clients WHERE stripe_subscription_id = ?');
    $stmt->execute([$abonId]);
    $client = $stmt->fetch();
    if (!$client) {
        return;
    }

    $numero = (string) ($facture['number'] ?? $facture['id'] ?? '');
    $note   = 'Facture Stripe ' . $numero;
    $date   = date('Y-m-d', (int) ($facture['status_transitions']['paid_at'] ?? time()));

    $deja = $pdo->prepare('SELECT COUNT(*) FROM finance_mouvements WHERE client_id = ? AND note = ?');
    $deja->execute([$client['id'], $note]);
    if ((int) $deja->fetchColumn() > 0) {
        return;
    }

    $prevu = $pdo->prepare(
        "SELECT id FROM finance_mouvements
          WHERE client_id = ? AND sens = 'recette' AND categorie = 'abonnement' AND statut = 'prevu'
          ORDER BY date_mouvement LIMIT 1"
    );
    $prevu->execute([$client['id']]);
    $idPrevu = $prevu->fetchColumn();

    if ($idPrevu) {
        $pdo->prepare(
            "UPDATE finance_mouvements
                SET statut = 'regle', montant_ht = ?, date_mouvement = ?, moyen = 'stripe', note = ?
              WHERE id = ?"
        )->execute([$horsTaxe, $date, $note, $idPrevu]);
    } else {
        $pdo->prepare(
            "INSERT INTO finance_mouvements
                (sens, categorie, client_id, libelle, montant_ht, date_mouvement, statut, moyen, note)
             VALUES ('recette','abonnement',?,?,?,?,'regle','stripe',?)"
        )->execute([
            $client['id'], 'Abonnement ' . crmLibelleOffre((string) $client['offre']) . ' — ' . $client['nom'],
            $horsTaxe, $date, $note,
        ]);
    }

    $pdo->prepare("UPDATE crm_clients SET stripe_statut = 'active' WHERE id = ? AND stripe_statut = 'past_due'")
        ->execute([$client['id']]);
}

/**
 * Traite un événement Stripe déjà vérifié.
 *
 * Chaque événement n'est traité qu'une fois (`stripe_evenements`) : Stripe
 * renvoie un même événement tant qu'il n'a pas reçu de 2xx, et peut le
 * renvoyer même après.
 *
 * @param array<string, mixed> $evenement
 * @return bool vrai si l'événement était nouveau
 */
function stripeTraiterEvenement(PDO $pdo, array $evenement): bool
{
    $id   = (string) $evenement['id'];
    $type = (string) $evenement['type'];
    $obj  = is_array($evenement['data']['object'] ?? null) ? $evenement['data']['object'] : [];

    $insere = $pdo->prepare('INSERT IGNORE INTO stripe_evenements (id, type) VALUES (?, ?)');
    $insere->execute([$id, $type]);
    if ($insere->rowCount() === 0) {
        return false;
    }

    try {
        switch ($type) {
            case 'checkout.session.completed':
            case 'checkout.session.async_payment_succeeded':
                stripeAppliquerCheckout($pdo, $obj);
                break;
            case 'customer.subscription.created':
            case 'customer.subscription.updated':
                stripeAppliquerAbonnement($pdo, $obj);
                break;
            case 'customer.subscription.deleted':
                stripeAppliquerAbonnement($pdo, $obj, true);
                break;
            case 'invoice.paid':
                stripeEnregistrerFacturePayee($pdo, $obj);
                break;
            case 'invoice.payment_failed':
                $pdo->prepare("UPDATE crm_clients SET stripe_statut = 'past_due' WHERE stripe_subscription_id = ?")
                    ->execute([(string) ($obj['subscription'] ?? '')]);
                logErreur('Stripe : paiement en échec', null, ['facture' => $obj['id'] ?? '', 'abonnement' => $obj['subscription'] ?? '']);
                break;
        }
    } catch (Throwable $e) {
        // Le traitement a échoué : on oublie l'événement pour que la relance
        // de Stripe le rejoue, plutôt que de le croire traité.
        $pdo->prepare('DELETE FROM stripe_evenements WHERE id = ?')->execute([$id]);
        throw $e;
    }

    return true;
}

// ─── Suppléments du mois ───────────────────────────────────────────────────

/**
 * Envoie à Stripe les achats payants pas encore facturés.
 *
 * Seuls partent les achats d'un établissement abonné par Stripe, faits après
 * la mise en place de son abonnement (`stripe_lie_le`) : ce qui précède a été
 * facturé à la main. Chaque achat devient une ligne en attente sur le client,
 * que Stripe ajoute à la prochaine facture de l'abonnement. La clé
 * d'idempotence `achat-<id>` empêche un double envoi si la mise à jour de la
 * base échoue après l'appel.
 *
 * @return int le nombre de lignes envoyées
 */
function stripeSynchroniserAchats(PDO $pdo, ?int $etablissementId = null, int $limite = 200): int
{
    if (!stripeActif()) {
        return 0;
    }

    $sql = "SELECT a.id, a.montant, a.nature, a.libelle, a.mois, c.stripe_customer_id, c.stripe_subscription_id
              FROM achats_partenaires a
              JOIN crm_clients c ON c.etablissement_id = a.etablissement_id
             WHERE a.montant > 0 AND a.stripe_ligne IS NULL
               AND c.stripe_subscription_id IS NOT NULL
               AND c.stripe_statut IN ('trialing','active','past_due')
               AND a.created_at >= c.stripe_lie_le";
    $params = [];
    if ($etablissementId !== null) {
        $sql     .= ' AND a.etablissement_id = ?';
        $params[] = $etablissementId;
    }
    $stmt = $pdo->prepare($sql . ' ORDER BY a.id LIMIT ' . max(1, $limite));
    $stmt->execute($params);

    $natures = ['evenement' => 'Événement supplémentaire', 'sponsoring' => 'Mise en avant', 'flash' => 'Offre flash'];
    $envoyes = 0;
    foreach ($stmt->fetchAll() as $achat) {
        $libelle = ($natures[$achat['nature']] ?? 'Supplément') . ($achat['libelle'] ? ' — ' . $achat['libelle'] : '');
        $params  = [
            'customer'     => (string) $achat['stripe_customer_id'],
            'subscription' => (string) $achat['stripe_subscription_id'],
            'currency'     => 'eur',
            'amount'       => stripeCentimes((float) $achat['montant']),
            'description'  => mb_substr($libelle, 0, 250),
            'tax_behavior' => 'exclusive',
            'metadata'     => ['achat_id' => (string) $achat['id'], 'mois' => (string) $achat['mois']],
        ];
        $taux = stripeTauxTva();
        if ($taux !== []) {
            $params['tax_rates'] = $taux;
        }
        try {
            $ligne = stripeRequete('POST', 'invoiceitems', $params, 'achat-' . $achat['id']);
            $pdo->prepare('UPDATE achats_partenaires SET stripe_ligne = ? WHERE id = ? AND stripe_ligne IS NULL')
                ->execute([(string) ($ligne['id'] ?? 'envoye'), $achat['id']]);
            $envoyes++;
        } catch (StripeErreur $e) {
            logErreur('Stripe : supplément non envoyé', $e, ['achat' => $achat['id']]);
        }
    }

    return $envoyes;
}
