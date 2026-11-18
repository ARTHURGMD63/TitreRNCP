<?php
/**
 * Créer ou modifier un événement depuis l'espace pro web.
 *
 * Une seule fonction de traitement pour la création et la modification
 * (partenaire/create_event.php et edit_event.php en étaient deux copies), et
 * les mêmes règles que api/v1/partenaire_evenement.php :
 *   - le type découle du compte (typeEvenementPour) ;
 *   - quotas d'offres flash revérifiés à l'enregistrement ;
 *   - mise en avant résolue par resoudreSponsoring(), tarif jamais lu du
 *     formulaire ;
 *   - tout ce qui est consommé s'inscrit au registre des achats.
 *
 * Différence assumée avec l'application : c'est ICI qu'un établissement qui
 * a épuisé son quota publie un événement supplémentaire, en acceptant
 * explicitement son prix. Les achats passent par le site, jamais par
 * l'application (règles des stores).
 *
 * Paiement à la publication (migration v34) : quand Stripe est branché, un
 * événement supplémentaire ou une mise en avant payante se paie sur la page
 * de paiement de Stripe AVANT d'être mis en ligne. Les valeurs validées
 * attendent dans `paiements_evenements` ; publierEvenementPaye() écrit
 * l'événement au retour (webhook ou page de retour), une seule fois. Sans
 * Stripe, le supplément s'ajoute au mois, comme avant.
 */

require_once __DIR__ . '/crm.php';
require_once __DIR__ . '/comptes.php';
require_once __DIR__ . '/sponsoring.php';
require_once __DIR__ . '/capacites.php';
require_once __DIR__ . '/evenements_temps.php';
require_once __DIR__ . '/musique.php';
require_once __DIR__ . '/temps_reel.php';
require_once __DIR__ . '/stripe.php';

/** Une date ISO en valeur de champ datetime-local. */
function valeurDateLocale(?string $date): string
{
    return $date ? date('Y-m-d\TH:i', (int) strtotime($date)) : '';
}

/**
 * Valide et enregistre le formulaire. Redirige vers la liste en cas de
 * succès ; sinon renvoie les erreurs et les valeurs saisies.
 *
 * @param array<string, mixed>      $etab
 * @param array<string, mixed>      $capacites
 * @param array<string, mixed>|null $existant l'événement modifié, null à la création
 * @return array{erreurs: list<string>, valeurs: array<string, mixed>}
 */
function traiterFormulaireEvenement(PDO $pdo, array $etab, array $capacites, ?array $existant): array
{
    $etabId  = (int) $etab['id'];
    $p       = static fn (string $cle): string => trim((string) ($_POST[$cle] ?? ''));
    $erreurs = [];

    $v = [
        'titre'         => mb_substr($p('titre'), 0, 255),
        'description'   => $p('description'),
        'type'          => typeEvenementPour((string) ($etab['type'] ?? ''), $p('type')) ?? '',
        'style_musique' => $p('style_musique'),
        'date_heure'    => $p('date_heure'),
        'date_fin'      => $p('date_fin') ?: null,
        'quota'         => (int) ($_POST['quota'] ?? 0),
        'reduction'     => max(0, min(100, (int) ($_POST['reduction'] ?? 0))),
        'prix_normal'   => max(0, (float) str_replace(',', '.', $p('prix_normal'))),
        'is_gratuit'    => isset($_POST['is_gratuit']) ? 1 : 0,
        'is_flash'      => isset($_POST['is_flash']) ? 1 : 0,
        'flash_expiry'  => isset($_POST['is_flash']) ? ($p('flash_expiry') ?: null) : null,
        'lieu'          => mb_substr($p('lieu'), 0, 255),
        'is_sponsorise' => isset($_POST['is_sponsorise']) ? 1 : 0,
        'sponsor_formule' => isset($_POST['is_sponsorise']) ? ($p('sponsor_formule') ?: null) : null,
    ];
    $accepteSupplement = !empty($_POST['accepte_supplement']);

    if ($v['titre'] === '') {
        $erreurs[] = 'Donnez un titre à l\'événement.';
    }
    if ($v['type'] === '' || $v['type'] === 'sport') {
        $erreurs[] = 'Type d\'événement invalide pour ce compte.';
    }
    if ($v['style_musique'] !== '' && !styleMusiqueValide($v['style_musique'])) {
        $erreurs[] = 'Style de musique invalide.';
    }
    if ($v['date_heure'] === '' || strtotime($v['date_heure']) === false) {
        $erreurs[] = 'Indiquez la date et l\'heure de début.';
    } elseif (evenementFinEffective($v['date_heure'], $v['date_fin']) <= time() && $existant === null) {
        $erreurs[] = 'Cet événement serait déjà terminé : choisissez une date à venir.';
    }
    if ($v['date_fin'] && $v['date_heure'] !== '' && strtotime($v['date_fin']) <= strtotime($v['date_heure'])) {
        $erreurs[] = 'La fin doit être après le début.';
    }
    if ($v['quota'] < 1 || $v['quota'] > 10000) {
        $erreurs[] = 'Le nombre de places doit être compris entre 1 et 10 000.';
    }
    if ($v['is_flash'] && !$v['flash_expiry']) {
        $erreurs[] = 'Indiquez jusqu\'à quand court l\'offre flash.';
    }
    if ($v['is_flash'] && !flashDisponible($pdo, $etabId, $capacites, $existant ? (int) $existant['id'] : null)) {
        $erreurs[] = 'Votre quota d\'offres flash du mois est atteint (' . (int) $capacites['flash_par_mois'] . ' par mois).';
    }
    if ($v['is_sponsorise'] && !formuleSponsoringValide($v['sponsor_formule'])) {
        $erreurs[] = 'Choisissez une formule de mise en avant.';
    }

    // Événement au-delà du quota : seulement à la création, et seulement si le
    // prix a été accepté noir sur blanc.
    $supplement = 0.0;
    if ($existant === null && evenementSeraSupplementaire($pdo, $etabId, $capacites)) {
        if (!$accepteSupplement) {
            $erreurs[] = 'Votre quota d\'événements du mois est atteint : cochez l\'acceptation du supplément de '
                       . eurosSite((float) PRIX_EVENEMENT_SUPPLEMENTAIRE) . ' HT pour publier celui-ci.';
        }
        $supplement = (float) PRIX_EVENEMENT_SUPPLEMENTAIRE;
    }

    $sponso = null;
    if (!$erreurs) {
        $sponso = resoudreSponsoring($pdo, $etabId, $capacites, $existant, (bool) $v['is_sponsorise'], $v['sponsor_formule'], $v['date_heure'], $v['date_fin']);
        if ($sponso['erreur'] !== null) {
            $erreurs[] = $sponso['erreur'];
        }
    }

    if ($erreurs || $sponso === null) {
        return ['erreurs' => $erreurs, 'valeurs' => $v + ['accepte_supplement' => $accepteSupplement]];
    }

    $sponsoPaye = $sponso['nouvelle'] && (float) $sponso['tarif'] > 0 ? (float) $sponso['tarif'] : 0.0;

    // Quelque chose à payer, et Stripe branché : le paiement d'abord, la
    // publication au retour. Rien n'est mis en ligne d'ici.
    if (stripeActif() && ($supplement > 0 || $sponsoPaye > 0)) {
        $erreur = ouvrirPaiementEvenement($pdo, $etab, $existant, $v, $supplement, $sponso);

        return ['erreurs' => [$erreur], 'valeurs' => $v + ['accepte_supplement' => $accepteSupplement]];
    }

    try {
        $id = ecrireEvenementPro($pdo, $etabId, $existant, $v, $supplement, $sponso);
    } catch (Throwable $e) {
        logErreur('Enregistrement d\'événement impossible', $e, ['etablissement' => $etabId]);

        return ['erreurs' => ['L\'événement n\'a pas pu être enregistré. Réessayez dans un instant.'], 'valeurs' => $v];
    }

    $message = $existant === null ? 'Événement publié.' : 'Événement mis à jour.';
    if ($supplement > 0) {
        $message .= ' Supplément de ' . eurosSite($supplement) . ' HT ajouté à votre mois.';
    }
    if ($sponsoPaye > 0) {
        $message .= ' Mise en avant de ' . eurosSite($sponsoPaye) . ' HT ajoutée à votre mois.';
    }
    if (!empty($sponso['nouvelle'])) {
        $message .= messageLancementMiseEnAvant($pdo, $id);
    }
    flashPro($message);
    header('Location: ' . baseUrl('/partenaire/evenements.php'));
    exit;
}

/**
 * Écrit l'événement (création ou modification) et ce qu'il consomme au
 * registre des achats, dans une transaction. Renvoie son identifiant.
 *
 * @param array<string, mixed>      $v        valeurs validées du formulaire
 * @param array<string, mixed>      $sponso   résultat de resoudreSponsoring()
 * @param array<string, mixed>|null $existant l'événement modifié, null à la création
 */
function ecrireEvenementPro(PDO $pdo, int $etabId, ?array $existant, array $v, float $supplement, array $sponso): int
{
    $commun = [
        $v['titre'], $v['description'], $v['type'], $v['style_musique'] ?: null, $v['date_heure'], $v['date_fin'],
        $v['quota'], $v['reduction'], $v['prix_normal'], $v['is_flash'], $v['flash_expiry'], $v['is_gratuit'], $v['lieu'],
        $sponso['is_sponsorise'], $sponso['formule'], $sponso['tarif'], $sponso['fin'],
        // Le moment où une NOUVELLE mise en avant part (heure de Paris, comme sa fin) ;
        // null quand elle ne change pas : COALESCE garde alors le début déjà enregistré.
        !empty($sponso['nouvelle']) ? date('Y-m-d H:i:s') : null,
    ];

    $pdo->beginTransaction();
    try {
        // Verrou de l'établissement : deux publications simultanées ne lisent
        // pas toutes deux « il reste une place dans le quota ».
        verrouillerEtablissement($pdo, $etabId);

        if ($existant === null) {
            $pdo->prepare(
                'INSERT INTO evenements (titre, description, type, style_musique, date_heure, date_fin, quota, reduction, prix_normal,
                                         is_flash, flash_expiry, is_gratuit, lieu, is_sponsorise, sponsor_formule, sponsor_tarif, sponsor_jusqu_au, sponsor_debut,
                                         supplement_tarif, etablissement_id)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            )->execute([...$commun, $supplement, $etabId]);
            $id = (int) $pdo->lastInsertId();
        } else {
            $id = (int) $existant['id'];
            $pdo->prepare(
                'UPDATE evenements SET titre=?, description=?, type=?, style_musique=?, date_heure=?, date_fin=?, quota=?, reduction=?, prix_normal=?,
                                       is_flash=?, flash_expiry=?, is_gratuit=?, lieu=?, is_sponsorise=?, sponsor_formule=?, sponsor_tarif=?, sponsor_jusqu_au=?, sponsor_debut=COALESCE(?, sponsor_debut)
                  WHERE id = ? AND etablissement_id = ?'
            )->execute([...$commun, $id, $etabId]);
        }

        enregistrerAchatsEvenement($pdo, $etabId, $id, $existant, [
            'titre' => $v['titre'], 'supplement_tarif' => $supplement, 'is_flash' => $v['is_flash'],
            'is_sponsorise' => $sponso['is_sponsorise'], 'sponsor_formule' => $sponso['formule'],
            'sponsor_tarif' => $sponso['tarif'], 'sponsor_nouvelle' => $sponso['nouvelle'],
        ]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    if ($existant === null) {
        fluxToucher($pdo, CANAL_GLOBAL);
    }
    fluxToucher($pdo, canalEvenement($id));

    return $id;
}

// ─── Déroulé d'une mise en avant ───────────────────────────────────────────

/** « Mar 17 Nov à 15h59 », pour une heure de base (heure de Paris). */
function quandSponsoring(string $horodatage): string
{
    return dateFr($horodatage, 'D j M') . ' à ' . date("H\\hi", (int) strtotime($horodatage));
}

/**
 * Le déroulé d'une mise en avant, à afficher : quand elle a démarré, quand
 * elle se termine, et un compte à rebours qui tourne (assets/js/site.js,
 * attribut `data-compte-a-rebours`). Une chaîne vide si l'événement n'a
 * jamais eu de mise en avant.
 *
 * @param array<string, mixed> $e       une ligne `evenements`
 * @param bool                 $compact une seule ligne, pour la liste des événements
 */
function sponsoringChronoHtml(array $e, bool $compact = false): string
{
    $c = sponsoringChrono($e);
    if ($c === null) {
        return '';
    }

    $code   = (string) $c['formule'];
    $nom    = (string) (formulesSponsoring()[$code]['nom'] ?? formulesSponsoringAnciennes()[$code]['nom'] ?? 'Mise en avant');
    $debut  = $c['debut'] !== null ? quandSponsoring($c['debut']) : null;
    $fin    = $c['fin'] !== null ? quandSponsoring($c['fin']) : null;
    $tourne = $c['actif'] && $c['restant_s'] !== null;
    $compte = $tourne ? '<strong data-compte-a-rebours>' . htmlspecialchars(dureeLisible((int) $c['restant_s'])) . '</strong>' : '';
    $attrs  = $tourne
        ? ' data-restant-s="' . (int) $c['restant_s'] . '"' . ($c['duree_s'] !== null ? ' data-duree-s="' . (int) $c['duree_s'] . '"' : '')
        : '';

    if ($compact) {
        if (!$c['actif']) {
            return '<div class="sponso-chrono sponso-chrono--compact sponso-chrono--fini">Terminée' . ($fin !== null ? ' le ' . htmlspecialchars($fin) : '') . '</div>';
        }

        return '<div class="sponso-chrono sponso-chrono--compact"' . $attrs . '>'
            . ($debut !== null ? 'Lancée le ' . htmlspecialchars($debut) . ' · ' : '')
            . ($compte !== '' ? 'reste ' . $compte : ($fin !== null ? "jusqu'au " . htmlspecialchars($fin) : 'en cours'))
            . '</div>';
    }

    $html  = '<div class="sponso-chrono' . ($c['actif'] ? '' : ' sponso-chrono--fini') . '"' . $attrs . '>';
    $html .= '<div class="sponso-chrono__titre">' . iconeSite('etoile', 'icon-sm') . ' ' . htmlspecialchars($nom) . ' · '
           . ($c['actif'] ? 'en cours' : 'terminée') . '</div>';
    if ($debut !== null) {
        $html .= '<div class="sponso-chrono__ligne">Lancée le <strong>' . htmlspecialchars($debut) . '</strong></div>';
    }
    if ($fin !== null) {
        $html .= '<div class="sponso-chrono__ligne">' . ($c['actif'] ? 'Se termine le ' : 'Terminée le ') . '<strong>' . htmlspecialchars($fin) . '</strong></div>';
    }
    if ($compte !== '') {
        $html .= '<div class="sponso-chrono__ligne">Temps restant : ' . $compte . '</div>';
    }
    if ($tourne && $c['ecoule_pct'] !== null) {
        $html .= '<div class="jauge" style="margin-top:8px;"><span data-compte-jauge style="width:' . (int) $c['ecoule_pct'] . '%"></span></div>';
    }

    return $html . '</div>';
}

/**
 * La phrase qui dit, au moment du lancement, quand la mise en avant démarre et
 * jusqu'à quand elle court. Vide si l'événement n'en a pas.
 */
function messageLancementMiseEnAvant(PDO $pdo, int $evenementId): string
{
    $stmt = $pdo->prepare('SELECT is_sponsorise, sponsor_formule, sponsor_debut, sponsor_jusqu_au FROM evenements WHERE id = ?');
    $stmt->execute([$evenementId]);
    $c = sponsoringChrono($stmt->fetch() ?: []);
    if ($c === null || $c['fin'] === null) {
        return '';
    }

    $depart = $c['debut'] !== null ? quandSponsoring($c['debut']) : null;

    return ' Mise en avant lancée' . ($depart !== null ? ' le ' . $depart : '') . ", jusqu'au " . quandSponsoring($c['fin'])
        . ($c['duree_s'] !== null ? ' (' . dureeLisible($c['duree_s']) . ').' : '.');
}

// ─── Paiement à la publication ─────────────────────────────────────────────

/**
 * Met de côté les valeurs validées, ouvre la page de paiement Stripe et y
 * envoie l'établissement. Ne revient qu'en cas d'échec, avec le message à
 * afficher.
 *
 * @param array<string, mixed>      $etab
 * @param array<string, mixed>|null $existant
 * @param array<string, mixed>      $v
 * @param array<string, mixed>      $sponso
 */
function ouvrirPaiementEvenement(PDO $pdo, array $etab, ?array $existant, array $v, float $supplement, array $sponso): string
{
    $etabId     = (int) $etab['id'];
    $sponsoPaye = $sponso['nouvelle'] && (float) $sponso['tarif'] > 0 ? (float) $sponso['tarif'] : 0.0;

    $pdo->prepare(
        'INSERT INTO paiements_evenements (etablissement_id, evenement_id, valeurs, montant_supplement, montant_sponsoring)
         VALUES (?,?,?,?,?)'
    )->execute([
        $etabId, $existant ? (int) $existant['id'] : null,
        json_encode(['v' => $v, 'sponso' => $sponso], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        $supplement, $sponsoPaye,
    ]);
    $paiementId = (int) $pdo->lastInsertId();

    $lignes = [];
    if ($supplement > 0) {
        $lignes[] = ['libelle' => 'Événement supplémentaire — ' . $v['titre'], 'montant' => $supplement];
    }
    if ($sponsoPaye > 0) {
        $lignes[] = ['libelle' => libelleSponsoring((string) $sponso['formule']) . ' — ' . $v['titre'], 'montant' => $sponsoPaye];
    }
    $retour = $existant === null
        ? '/partenaire/create_event.php?paiement=annule&p=' . $paiementId
        : '/partenaire/edit_event.php?id=' . (int) $existant['id'] . '&paiement=annule&p=' . $paiementId;

    try {
        $stmt = $pdo->prepare('SELECT email FROM users WHERE id = ?');
        $stmt->execute([(int) $etab['user_id']]);
        $session = stripeOuvrirPaiementUnique(
            abonnementEtablissement($pdo, $etabId), $lignes,
            ['type' => 'evenement', 'paiement_id' => (string) $paiementId, 'etablissement_id' => (string) $etabId],
            $retour, $stmt->fetchColumn() ?: null
        );
        $pdo->prepare('UPDATE paiements_evenements SET stripe_session_id = ? WHERE id = ?')->execute([$session['id'], $paiementId]);
    } catch (StripeErreur $e) {
        logErreur('Stripe : paiement d\'événement impossible', $e, ['etablissement' => $etabId]);
        $pdo->prepare('DELETE FROM paiements_evenements WHERE id = ?')->execute([$paiementId]);

        return 'Le paiement en ligne est momentanément indisponible : rien n\'a été publié. Réessayez dans un instant.';
    }

    header('Location: ' . $session['url'], true, 303);
    exit;
}

/**
 * Publie l'événement payé : appelée par le webhook (checkout.session.completed)
 * et par la page de retour. La première arrivée s'en charge, la seconde ne
 * fait rien : la ligne passe de « en_attente » à « paye » en une seule
 * requête conditionnelle.
 *
 * @param array<string, mixed> $session l'objet Checkout Session
 * @return array{ok:bool, message:string, evenement_id:?int}
 */
function publierEvenementPaye(PDO $pdo, array $session): array
{
    $paiementId = (int) ($session['metadata']['paiement_id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM paiements_evenements WHERE id = ? AND stripe_session_id = ?');
    $stmt->execute([$paiementId, (string) ($session['id'] ?? '')]);
    $paiement = $stmt->fetch();
    if (!$paiement) {
        return ['ok' => false, 'message' => 'Paiement inconnu.', 'evenement_id' => null];
    }
    if ($paiement['statut'] === 'paye') {
        return ['ok' => true, 'message' => 'Paiement reçu : événement publié.', 'evenement_id' => $paiement['evenement_id'] !== null ? (int) $paiement['evenement_id'] : null];
    }
    if (($session['payment_status'] ?? '') !== 'paid') {
        return ['ok' => false, 'message' => 'Le paiement n\'est pas encore confirmé.', 'evenement_id' => null];
    }

    $reference = (string) ($session['payment_intent'] ?? '') ?: (string) $session['id'];
    $prise = $pdo->prepare("UPDATE paiements_evenements SET statut = 'paye', paye_le = NOW(), stripe_paiement_id = ? WHERE id = ? AND statut <> 'paye'");
    $prise->execute([$reference, $paiementId]);
    if ($prise->rowCount() === 0) {
        return ['ok' => true, 'message' => 'Paiement reçu : événement publié.', 'evenement_id' => null];
    }

    $etabId = (int) $paiement['etablissement_id'];
    try {
        $donnees  = json_decode((string) $paiement['valeurs'], true, 512, JSON_THROW_ON_ERROR);
        $existant = null;
        if ($paiement['evenement_id'] !== null) {
            $stmt = $pdo->prepare('SELECT * FROM evenements WHERE id = ? AND etablissement_id = ?');
            $stmt->execute([(int) $paiement['evenement_id'], $etabId]);
            $existant = $stmt->fetch() ?: null;
        }
        $id = ecrireEvenementPro($pdo, $etabId, $existant, $donnees['v'], (float) $paiement['montant_supplement'], $donnees['sponso']);
    } catch (Throwable $e) {
        // Payé mais pas publié : la ligne revient en attente pour que la
        // relance du webhook réessaie, et l'erreur part au journal.
        $pdo->prepare("UPDATE paiements_evenements SET statut = 'en_attente', paye_le = NULL WHERE id = ?")->execute([$paiementId]);
        logErreur('Stripe : événement payé non publié', $e, ['paiement' => $paiementId]);
        throw $e;
    }

    // Les achats de cet événement sont réglés : la facturation mensuelle
    // (cron/stripe.php) ne doit pas les reprendre.
    $pdo->prepare('UPDATE achats_partenaires SET stripe_ligne = ? WHERE evenement_id = ? AND montant > 0 AND stripe_ligne IS NULL')
        ->execute([mb_substr($reference, 0, 64), $id]);
    $pdo->prepare('UPDATE paiements_evenements SET evenement_id = ? WHERE id = ?')->execute([$id, $paiementId]);

    // Recettes encaissées, au registre financier.
    $client = abonnementEtablissement($pdo, $etabId);
    $titre  = (string) ($donnees['v']['titre'] ?? '');
    $ajout  = $pdo->prepare(
        "INSERT INTO finance_mouvements (sens, categorie, client_id, libelle, montant_ht, date_mouvement, statut, moyen, note)
         VALUES ('recette', ?, ?, ?, ?, CURDATE(), 'regle', 'stripe', ?)"
    );
    if ((float) $paiement['montant_supplement'] > 0) {
        $ajout->execute(['autre_recette', $client['id'] ?? null, 'Événement supplémentaire — ' . $titre, $paiement['montant_supplement'], 'Paiement Stripe ' . $reference]);
    }
    if ((float) $paiement['montant_sponsoring'] > 0) {
        $ajout->execute(['sponsoring', $client['id'] ?? null, 'Mise en avant — ' . $titre, $paiement['montant_sponsoring'], 'Paiement Stripe ' . $reference]);
    }

    return [
        'ok'           => true,
        'message'      => ($existant === null ? 'Paiement reçu : événement publié.' : 'Paiement reçu : mise en avant lancée.')
                          . messageLancementMiseEnAvant($pdo, $id),
        'evenement_id' => $id,
    ];
}

/**
 * Le retour de la page de paiement, sur l'espace pro.
 *
 *   ?paiement=ok&session=cs_…  publie (si le webhook n'est pas déjà passé) ;
 *   ?paiement=annule&p=<id>    abandonne, et renvoie les valeurs saisies pour
 *                              re-remplir le formulaire.
 *
 * @param array<string, mixed> $etab
 * @return array<string, mixed>|null les valeurs à remettre dans le formulaire
 */
function traiterRetourPaiementEvenement(PDO $pdo, array $etab): ?array
{
    $etabId = (int) $etab['id'];

    if (($_GET['paiement'] ?? '') === 'ok' && preg_match('/^cs_[A-Za-z0-9_]+$/', (string) ($_GET['session'] ?? ''))) {
        try {
            $session = stripeRequete('GET', 'checkout/sessions/' . $_GET['session']);
            if ((int) ($session['metadata']['etablissement_id'] ?? 0) === $etabId) {
                $res = publierEvenementPaye($pdo, $session);
                flashPro($res['message'], $res['ok'] ? 'succes' : 'alerte');
            }
        } catch (Throwable $e) {
            logErreur('Stripe : retour de paiement d\'événement', $e, ['etablissement' => $etabId]);
            flashPro('Paiement reçu : l\'événement sera en ligne dans quelques instants.', 'alerte');
        }
        header('Location: ' . baseUrl('/partenaire/evenements.php'));
        exit;
    }

    if (($_GET['paiement'] ?? '') === 'annule' && ctype_digit((string) ($_GET['p'] ?? ''))) {
        $stmt = $pdo->prepare("SELECT valeurs FROM paiements_evenements WHERE id = ? AND etablissement_id = ? AND statut = 'en_attente'");
        $stmt->execute([(int) $_GET['p'], $etabId]);
        $valeurs = $stmt->fetchColumn();
        $pdo->prepare("UPDATE paiements_evenements SET statut = 'expire' WHERE id = ? AND etablissement_id = ? AND statut = 'en_attente'")
            ->execute([(int) $_GET['p'], $etabId]);
        flashPro('Paiement annulé : rien n\'a été publié.', 'alerte');
        if (is_string($valeurs)) {
            $donnees = json_decode($valeurs, true);
            if (is_array($donnees) && is_array($donnees['v'] ?? null)) {
                return $donnees['v'] + ['accepte_supplement' => 1];
            }
        }
    }

    return null;
}

/**
 * Le formulaire, prérempli.
 *
 * @param array<string, mixed> $etab
 * @param array<string, mixed> $capacites
 * @param array<string, mixed> $valeurs
 * @param array<string, mixed>|null $existant
 */
function formulaireEvenementHtml(PDO $pdo, array $etab, array $capacites, array $valeurs, ?array $existant): string
{
    $val     = static fn (string $cle, $defaut = '') => $valeurs[$cle] ?? $defaut;
    $h       = static fn ($s): string => htmlspecialchars((string) $s, ENT_QUOTES);
    $estBde  = ($etab['type'] ?? '') === 'bde';
    $usage   = usagePartenaireMois($pdo, (int) $etab['id'], $capacites);
    $suppl   = $existant === null && $usage['evenements']['restants'] <= 0;
    $flashOk = flashDisponible($pdo, (int) $etab['id'], $capacites, $existant ? (int) $existant['id'] : null) || !empty($val('is_flash'));
    $offerte = $usage['mises_en_avant']['offertes_restantes'] > 0;

    ob_start();
    ?>
    <form method="post" class="grille-tdb" novalidate>
      <?= csrfField() ?>
      <div class="pile">
        <section class="carte formulaire" aria-labelledby="f-infos">
          <h2 class="carte__titre" id="f-infos">L'événement</h2>
          <div class="champ">
            <label for="titre">Titre</label>
            <input type="text" id="titre" name="titre" required maxlength="255" value="<?= $h($val('titre')) ?>" placeholder="Soirée étudiante : DJ et happy hour">
          </div>
          <div class="formulaire__ligne">
            <?php if ($estBde): ?>
            <div class="champ">
              <label for="type">Type de soirée</label>
              <select id="type" name="type" required>
                <?php foreach (['bar' => 'Bar', 'boite' => 'Boîte de nuit', 'resto' => 'Restaurant', 'afterwork' => 'Afterwork'] as $code => $lib): ?>
                  <option value="<?= $code ?>"<?= $val('type') === $code ? ' selected' : '' ?>><?= $lib ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
            <div class="champ">
              <label for="style_musique">Style de musique</label>
              <select id="style_musique" name="style_musique">
                <option value="">Non précisé</option>
                <?php foreach (stylesMusique() as $code => $lib): ?>
                  <option value="<?= $h($code) ?>"<?= $val('style_musique') === $code ? ' selected' : '' ?>><?= $h($lib) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="formulaire__ligne">
            <div class="champ">
              <label for="date_heure">Début</label>
              <input type="datetime-local" id="date_heure" name="date_heure" required value="<?= $h(valeurDateLocale($val('date_heure') ?: null)) ?>">
            </div>
            <div class="champ">
              <label for="date_fin">Fin <span class="champ__aide">(facultatif)</span></label>
              <input type="datetime-local" id="date_fin" name="date_fin" value="<?= $h(valeurDateLocale($val('date_fin') ?: null)) ?>">
            </div>
          </div>
          <div class="champ">
            <label for="description">Description</label>
            <textarea id="description" name="description" placeholder="L'ambiance, les animations, ce qui est compris avec la réduction…"><?= $h($val('description')) ?></textarea>
          </div>
          <div class="champ">
            <label for="lieu">Lieu</label>
            <input type="text" id="lieu" name="lieu" maxlength="255" value="<?= $h($val('lieu', $etab['adresse'] ?? '')) ?>" placeholder="<?= $h($etab['adresse'] ?? $etab['nom']) ?>">
            <span class="champ__aide">Laissez l'adresse de l'établissement si la soirée a lieu chez vous.</span>
          </div>
        </section>

        <section class="carte formulaire" aria-labelledby="f-offre">
          <h2 class="carte__titre" id="f-offre">Places et avantage étudiant</h2>
          <div class="formulaire__ligne formulaire__ligne--3">
            <div class="champ">
              <label for="quota">Places</label>
              <input type="number" id="quota" name="quota" min="1" max="10000" required value="<?= (int) $val('quota', 50) ?>">
            </div>
            <div class="champ">
              <label for="prix_normal">Prix habituel (€)</label>
              <input type="number" id="prix_normal" name="prix_normal" min="0" step="0.5" value="<?= $h($val('prix_normal', 0)) ?>">
            </div>
            <div class="champ">
              <label for="reduction">Réduction (%)</label>
              <input type="number" id="reduction" name="reduction" min="0" max="100" value="<?= (int) $val('reduction', 0) ?>">
            </div>
          </div>
          <label class="interrupteur">
            <div><strong>Entrée gratuite</strong><span>Affiché « Gratuit » dans le fil des étudiants.</span></div>
            <input type="checkbox" name="is_gratuit" value="1"<?= !empty($val('is_gratuit')) ? ' checked' : '' ?>>
          </label>
        </section>
      </div>

      <aside class="pile">
        <section class="carte formulaire" aria-labelledby="f-visibilite">
          <h2 class="carte__titre" id="f-visibilite">Visibilité</h2>

          <label class="interrupteur">
            <div><strong><?= iconeSite('eclair', 'icon-sm') ?> Offre flash</strong>
              <span><?= $capacites['flash_par_mois'] === null ? 'Illimitées avec votre formule.' : (int) $usage['flash']['restants'] . ' restante(s) ce mois-ci sur ' . (int) $capacites['flash_par_mois'] . '.' ?></span></div>
            <input type="checkbox" name="is_flash" value="1" data-ouvre="bloc-flash"<?= !empty($val('is_flash')) ? ' checked' : '' ?><?= $flashOk ? '' : ' disabled' ?>>
          </label>
          <div class="champ" id="bloc-flash"<?= empty($val('is_flash')) ? ' hidden' : '' ?>>
            <label for="flash_expiry">L'offre flash court jusqu'au</label>
            <input type="datetime-local" id="flash_expiry" name="flash_expiry" value="<?= $h(valeurDateLocale($val('flash_expiry') ?: null)) ?>">
          </div>

          <label class="interrupteur">
            <div><strong><?= iconeSite('etoile', 'icon-sm') ?> Mise en avant</strong>
              <span><?= $offerte ? 'Une mise en avant offerte disponible ce mois-ci.' : (stripeActif() ? 'En tête du fil des étudiants, payée par carte à la publication.' : 'En tête du fil des étudiants, facturée au mois.') ?></span></div>
            <input type="checkbox" name="is_sponsorise" value="1" data-ouvre="bloc-sponso"<?= !empty($val('is_sponsorise')) ? ' checked' : '' ?>>
          </label>
          <div class="choix choix--liste" id="bloc-sponso" role="radiogroup" aria-label="Formule de mise en avant"<?= empty($val('is_sponsorise')) ? ' hidden' : '' ?>>
            <?php foreach (formulesSponsoring() as $code => $f): ?>
              <label>
                <input type="radio" name="sponsor_formule" value="<?= $h($code) ?>"<?= $val('sponsor_formule') === $code ? ' checked' : '' ?>>
                <?= $h($f['nom']) ?> · <?= $h(eurosSite((float) $f['tarif'])) ?> HT
                <small><?= $h($f['resume']) ?></small>
              </label>
            <?php endforeach; ?>
          </div>
          <?php if ($existant && !empty($existant['is_sponsorise']) && sponsoringActif($existant)): ?>
            <p class="carte__sous">Mise en avant en cours jusqu'au <?= $h(dateFr($existant['sponsor_jusqu_au'], 'D j M')) ?> à <?= date('H\hi', strtotime((string) $existant['sponsor_jusqu_au'])) ?> : enregistrer ne la relance pas.</p>
          <?php endif; ?>
        </section>

        <section class="carte<?= $suppl ? '' : ' carte--accent' ?>">
          <p class="surtitre">Ce mois-ci</p>
          <?php if ($suppl): ?>
            <p style="margin-top:8px;font-weight:600;">Votre quota de <?= (int) $usage['evenements']['quota'] ?> événements est atteint.</p>
            <label class="case" style="margin-top:14px;">
              <input type="checkbox" name="accepte_supplement" value="1"<?= !empty($val('accepte_supplement')) ? ' checked' : '' ?>>
              <?php if (stripeActif()): ?>
                <span>J'accepte le supplément de <strong><?= $h(eurosSite((float) PRIX_EVENEMENT_SUPPLEMENTAIRE)) ?> HT</strong>, payé par carte au moment de publier cet événement.</span>
              <?php else: ?>
                <span>J'accepte le supplément de <strong><?= $h(eurosSite((float) PRIX_EVENEMENT_SUPPLEMENTAIRE)) ?> HT</strong> pour publier cet événement, ajouté à ma facture du mois.</span>
              <?php endif; ?>
            </label>
          <?php elseif ($existant === null): ?>
            <p style="margin-top:8px;font-size:18px;font-weight:700;">Inclus dans votre formule</p>
            <p class="carte__sous" style="margin-top:4px;">Il vous restera <?= max(0, (int) $usage['evenements']['restants'] - 1) ?> événement(s) compris ce mois-ci.</p>
          <?php else: ?>
            <p style="margin-top:8px;font-size:18px;font-weight:700;">Modifier ne coûte rien</p>
            <p class="carte__sous" style="margin-top:4px;">Seule une nouvelle offre flash ou une nouvelle mise en avant est décomptée.</p>
          <?php endif; ?>
        </section>

        <div class="formulaire__pied">
          <a class="bouton bouton--clair" href="<?= $h(baseUrl('/partenaire/evenements.php')) ?>">Annuler</a>
          <button type="submit" class="bouton bouton--plein"><?= $existant === null ? 'Publier l\'événement' : 'Enregistrer' ?></button>
        </div>
      </aside>
    </form>
    <script>
    document.querySelectorAll('[data-ouvre]').forEach(function (c) {
      var bloc = document.getElementById(c.getAttribute('data-ouvre'));
      c.addEventListener('change', function () { bloc.hidden = !c.checked; });
    });
    </script>
    <?php
    return (string) ob_get_clean();
}
