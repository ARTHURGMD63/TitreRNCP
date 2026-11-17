<?php
/**
 * GET  /api/v1/partenaire_evenement.php[?id=12] — les données du
 *      formulaire : catalogues (styles de musique, formules de sponsoring,
 *      quotas restants) et, si `id` est fourni, l'événement à éditer.
 * POST /api/v1/partenaire_evenement.php {id?, titre, ...} — créer (sans
 *      id) ou modifier (avec id) un événement.
 *
 * Reprend partenaire/create_event.php et partenaire/edit_event.php : mêmes
 * validations, même recalcul serveur du tarif de sponsoring (jamais lu du
 * corps de la requête — un champ modifiable côté client se modifie à la
 * console, et la facturation avec), même garde anti-relance d'une mise en
 * avant déjà en cours quand la formule ne change pas.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/crm.php';
require_once __DIR__ . '/../../includes/sponsoring.php';
require_once __DIR__ . '/../../includes/evenements_temps.php';
require_once __DIR__ . '/../../includes/capacites.php';
require_once __DIR__ . '/../../includes/comptes.php';
require_once __DIR__ . '/../../includes/musique.php';
require_once __DIR__ . '/../../includes/temps_reel.php';
require_once __DIR__ . '/../../includes/sports.php';

/**
 * Refus d'un achat demandé par l'application. Elle ne vend rien (règles Apple
 * 3.1.1 et Google Play Billing) : les mises en avant payantes et les
 * événements au-delà du quota se prennent sur le site. Message neutre, sans
 * lien ni prix, comme l'exigent les stores.
 */
const MESSAGE_ACHAT_HORS_APP = 'Cette mise en avant ne se prend pas dans l’application. Seule celle comprise dans ta formule peut être lancée ici.';

$u   = apiPartenaire($pdo);
$uid = (int) $u['id'];

$etab = apiEtablissementDe($pdo, $uid);
if (!$etab) {
    apiErreur('Aucun établissement associé à ce compte.', 404, 'etablissement_absent');
}
exigerAbonnementValide($pdo, $etab);

$capacites = capacitesEtablissement($pdo, (int) $etab['id']);

// Un club (v28) ne publie que des événements sportifs, un bar jamais : le
// type n'est pas un choix du formulaire, il découle de l'établissement.
$estClub = ($etab['type'] ?? '') === 'club';
$typeCompte = (string) ($etab['type'] ?? '');

/**
 * Catalogues partagés entre GET (préremplissage) et les erreurs de POST.
 *
 * @param array<string, mixed> $capacites
 * @return array<string, mixed>
 */
function catalogues(PDO $pdo, int $etablissementId, array $capacites, bool $estClub): array
{
    global $typeCompte;
    $usage = usagePartenaireMois($pdo, $etablissementId, $capacites);
    $prochainSupplement = $usage['evenements']['restants'] <= 0;

    return [
        'est_club' => $estClub,
        // Le type du compte décide du type d'événement : seul un BDE le choisit.
        'type_compte'     => $typeCompte,
        'type_libre'      => $typeCompte === 'bde',
        'type_evenement'  => typeEvenementPour($typeCompte, ''),
        'sports'   => apiCatalogue(catalogueSports()),
        'formats'  => apiCatalogue(formatsEvenementSport()),
        'styles_musique' => array_map(
            static fn (string $code, string $libelle): array => ['code' => $code, 'libelle' => $libelle],
            array_keys(stylesMusique()),
            array_values(stylesMusique())
        ),
        'formules_sponsoring' => array_map(
            static fn (string $code, array $f): array => [
                'code' => $code, 'nom' => $f['nom'], 'tarif' => (float) $f['tarif'], 'resume' => $f['resume'],
                'heures' => (int) $f['heures'], 'notifications' => (int) $f['notifications'],
                'offrable' => in_array($code, formulesSponsoringOffrables(), true),
            ],
            array_keys(formulesSponsoring()),
            array_values(formulesSponsoring())
        ),
        // Durée par défaut d'un événement sans fin saisie : sert à annoncer la
        // vraie fin d'une mise en avant avant l'achat.
        'duree_defaut_heures' => EVENEMENT_DUREE_PAR_DEFAUT_HEURES,
        'flash_illimite' => $capacites['flash_par_mois'] === null,
        'flash_par_mois' => $capacites['flash_par_mois'],
        'usage'          => $usage,
        // Le prochain événement publié dépasse-t-il le quota ? Le formulaire
        // l'affiche avant la publication, jamais après.
        'supplement'     => [
            'prochain' => $prochainSupplement,
            'tarif'    => (float) PRIX_EVENEMENT_SUPPLEMENTAIRE,
        ],
    ];
}

/**
 * @param array<string, mixed> $e
 * @return array<string, mixed>
 */
function apiEvenementPourEdition(PDO $pdo, array $e): array
{
    $stmt = $pdo->prepare('SELECT envoyer_le FROM sponsor_notifications WHERE evenement_id = ? ORDER BY envoyer_le');
    $stmt->execute([(int) $e['id']]);
    $maintenant = date('Y-m-d H:i:s');
    $envoyees = $notifs = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $h) {
        if ((string) $h <= $maintenant) {
            $envoyees[] = (string) $h;
        } else {
            $notifs[] = (string) $h;
        }
    }

    return [
        'id' => (int) $e['id'],
        'titre' => (string) $e['titre'],
        'description' => (string) ($e['description'] ?? ''),
        'type' => (string) $e['type'],
        'style_musique' => $e['style_musique'] !== null ? (string) $e['style_musique'] : null,
        'sport' => isset($e['sport']) && $e['sport'] !== null ? (string) $e['sport'] : null,
        'format' => isset($e['format']) && $e['format'] !== null ? (string) $e['format'] : null,
        'date_heure' => (string) $e['date_heure'],
        'date_fin' => $e['date_fin'] !== null ? (string) $e['date_fin'] : null,
        'quota' => (int) $e['quota'],
        'reduction' => (int) ($e['reduction'] ?? 0),
        'prix_normal' => (float) ($e['prix_normal'] ?? 0),
        'is_flash' => (bool) $e['is_flash'],
        'flash_expiry' => $e['flash_expiry'] !== null ? (string) $e['flash_expiry'] : null,
        'is_gratuit' => (bool) $e['is_gratuit'],
        'lieu' => (string) ($e['lieu'] ?? ''),
        // « Cochée » seulement tant que la mise en avant court : une mise en
        // avant terminée ne se rachète pas en enregistrant le formulaire.
        'is_sponsorise' => sponsoringActif($e),
        'sponsor_formule' => $e['sponsor_formule'] !== null ? (string) $e['sponsor_formule'] : null,
        'sponsor_actif' => sponsoringActif($e),
        'sponsor_termine' => !empty($e['is_sponsorise']) && !sponsoringActif($e),
        'sponsor_jusqu_au' => $e['sponsor_jusqu_au'] !== null ? (string) $e['sponsor_jusqu_au'] : null,
        // Le déroulé, calculé côté serveur (heures de Paris) : l'application décompte à partir de `sponsor_restant_s`.
        'sponsor_debut' => sponsoringChrono($e)['debut'] ?? null,
        'sponsor_restant_s' => sponsoringChrono($e)['restant_s'] ?? null,
        'sponsor_notifications' => $notifs,
        'sponsor_notifications_envoyees' => $envoyees,
        'supplement_tarif' => (float) ($e['supplement_tarif'] ?? 0),
    ];
}

/**
 * Remplace les notifications programmées d'un événement sponsorisé.
 *
 * Une notification déjà partie reste : elle est consommée, et la retirer
 * rendrait une notification qui a déjà été envoyée. `$toutRemplacer` vaut
 * vrai pour un nouvel achat, qui repart de zéro.
 *
 * @param list<string> $horaires
 */
function enregistrerNotificationsSponsor(PDO $pdo, int $evenementId, array $horaires, bool $toutRemplacer = true): void
{
    if ($toutRemplacer) {
        $pdo->prepare('DELETE FROM sponsor_notifications WHERE evenement_id = ?')->execute([$evenementId]);
    } else {
        $pdo->prepare('DELETE FROM sponsor_notifications WHERE evenement_id = ? AND envoyer_le > ?')
            ->execute([$evenementId, date('Y-m-d H:i:s')]);
    }
    $ins = $pdo->prepare('INSERT INTO sponsor_notifications (evenement_id, envoyer_le) VALUES (?, ?)');
    foreach ($horaires as $h) {
        $ins->execute([$evenementId, $h]);
    }
}

/**
 * Les notifications d'une mise en avant : leur nombre, et leur moment.
 *
 * Elles se choisissent entre maintenant et la fin de la mise en avant, et
 * avant le début de l'événement : une notification qui part pendant la soirée
 * ne ferait venir personne. Celles déjà envoyées comptent dans le total, mais
 * ne se redemandent pas.
 *
 * @param array<string, mixed> $sponso le résultat de resoudreSponsoring()
 * @param list<string> $demandees
 * @return list<string> les erreurs
 */
function validerNotificationsSponsor(PDO $pdo, int $evenementId, array $sponso, array $demandees, string $dateHeure): array
{
    if (!$sponso['is_sponsorise'] || $sponso['formule'] === null) {
        return [];
    }
    $max = notificationsSponsoring($sponso['formule']);

    $deja = 0;
    if (!$sponso['nouvelle'] && $evenementId > 0) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM sponsor_notifications WHERE evenement_id = ? AND envoyer_le <= ?');
        $stmt->execute([$evenementId, date('Y-m-d H:i:s')]);
        $deja = (int) $stmt->fetchColumn();
    }

    $erreurs = [];
    if ($deja + count($demandees) > $max) {
        $erreurs[] = 'Cette formule comprend ' . $max . ' notification' . ($max > 1 ? 's' : '')
            . ($deja > 0 ? " ($deja déjà envoyée" . ($deja > 1 ? 's' : '') . ')' : '') . ' au plus.';
    }

    $limite = min(
        strtotime((string) $sponso['fin']) ?: PHP_INT_MAX,
        strtotime($dateHeure) ?: PHP_INT_MAX
    );
    foreach ($demandees as $n) {
        if (strtotime($n) < time() - 300 || strtotime($n) > $limite) {
            $erreurs[] = "Une notification doit partir pendant la mise en avant et avant le début de l'événement (au plus tard le " . date('d/m à H\hi', $limite) . ').';
            break;
        }
    }

    return $erreurs;
}

$methode = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($methode === 'GET') {
    $eid = (int) ($_GET['id'] ?? 0);
    $evenement = null;

    if ($eid) {
        $stmt = $pdo->prepare('SELECT * FROM evenements WHERE id=? AND etablissement_id=?');
        $stmt->execute([$eid, (int) $etab['id']]);
        $ligne = $stmt->fetch();
        if (!$ligne) {
            apiErreur('Cet événement n’existe plus.', 404, 'introuvable');
        }
        $evenement = apiEvenementPourEdition($pdo, $ligne);
    }

    apiReponse(['success' => true, 'evenement' => $evenement, 'lieu_defaut' => (string) ($etab['adresse'] ?? $etab['nom'])] + catalogues($pdo, (int) $etab['id'], $capacites, $estClub));
}

apiExigerMethode('POST');

$corps = apiCorps();
$eid   = (int) ($corps['id'] ?? 0);

// L'événement en cours d'édition, si édition il y a — chargé tôt : il sert
// à la fois à vérifier l'appartenance et à la garde anti-relance du
// sponsoring plus bas.
$evenementExistant = null;
if ($eid) {
    $stmt = $pdo->prepare('SELECT * FROM evenements WHERE id=? AND etablissement_id=?');
    $stmt->execute([$eid, (int) $etab['id']]);
    $evenementExistant = $stmt->fetch() ?: null;
    if (!$evenementExistant) {
        apiErreur('Cet événement n’existe plus.', 404, 'introuvable');
    }
}

$titre        = trim((string) ($corps['titre'] ?? ''));
$description  = trim((string) ($corps['description'] ?? ''));
// Le type de l'événement découle du compte : un club publie du sport, un bar
// un événement de bar. Seul un BDE, qui n'a pas de lieu à lui, choisit.
$type         = typeEvenementPour($typeCompte, (string) ($corps['type'] ?? ''));
$musique      = trim((string) ($corps['style_musique'] ?? ''));
$sport        = $estClub ? trim((string) ($corps['sport'] ?? '')) : '';
$format       = $estClub ? trim((string) ($corps['format'] ?? '')) : '';
if ($estClub) {
    $musique = '';
}
$dateHeure    = trim((string) ($corps['date_heure'] ?? ''));
$dateFin      = trim((string) ($corps['date_fin'] ?? '')) ?: null;
$quota        = (int) ($corps['quota'] ?? 100);
$reduction    = (int) ($corps['reduction'] ?? 0);
$prixNormal   = (float) ($corps['prix_normal'] ?? 0);
// L'offre flash et la mise en avant visent le fil des soirées : un club n'y
// a pas droit, quoi que dise le corps de la requête.
$isFlash      = !$estClub && !empty($corps['is_flash']) ? 1 : 0;
$flashExpiry  = $isFlash ? trim((string) ($corps['flash_expiry'] ?? '')) : '';
$isGratuit    = !empty($corps['is_gratuit']) ? 1 : 0;
$lieu         = trim((string) ($corps['lieu'] ?? ''));
$isSponsorise = !$estClub && !empty($corps['is_sponsorise']) ? 1 : 0;
$sponsorFormule = $isSponsorise ? (string) ($corps['sponsor_formule'] ?? '') : null;
$notifsDemandees = [];
if ($isSponsorise && is_array($corps['sponsor_notifications'] ?? null)) {
    foreach ($corps['sponsor_notifications'] as $n) {
        $n = trim((string) $n);
        if ($n !== '' && strtotime($n) !== false) {
            $notifsDemandees[] = date('Y-m-d H:i:s', strtotime($n));
        }
    }
    $notifsDemandees = array_values(array_unique($notifsDemandees));
    sort($notifsDemandees);
}

$erreurs = [];
if ($titre === '') $erreurs[] = 'Le titre est obligatoire.';
if ($type === null) $erreurs[] = 'Type invalide.';
if ($estClub && !sportValide($sport)) $erreurs[] = 'Choisis un sport.';
if ($estClub && !formatSportValide($format)) $erreurs[] = 'Choisis un format.';
if ($musique !== '' && !styleMusiqueValide($musique)) $erreurs[] = 'Style de musique invalide.';
if ($dateHeure === '') $erreurs[] = 'La date est obligatoire.';
if ($dateFin && strtotime($dateFin) <= strtotime($dateHeure)) $erreurs[] = 'La fin doit être après le début.';
if ($quota < 1) $erreurs[] = 'Le quota doit être au moins 1.';
if ($isFlash && $flashExpiry === '') $erreurs[] = "Date d'expiration flash requise.";
// Une soirée se publie pour l'avenir : un événement déjà fini n'est ni
// visible dans le fil, ni digne d'un quota ou d'une mise en avant payée.
if (!$evenementExistant && $dateHeure !== '' && strtotime($dateHeure) !== false && evenementFinEffective($dateHeure, $dateFin) <= time()) {
    $erreurs[] = 'Cet événement est déjà terminé : choisis une date à venir.';
}

$refuser = static function (array $erreurs) use ($pdo, $etab, $estClub): never {
    $capacites = capacitesEtablissement($pdo, (int) $etab['id']);
    apiReponse(['success' => false, 'message' => implode(' ', $erreurs), 'erreurs' => $erreurs] + catalogues($pdo, (int) $etab['id'], $capacites, $estClub), 422);
};

if ($erreurs) {
    $refuser($erreurs);
}

// Tout ce qui touche aux quotas se lit et s'écrit dans une seule transaction,
// l'établissement verrouillé : deux requêtes simultanées ne peuvent pas
// consommer ensemble la dernière place du mois. Les quotas se lisent dans le
// registre des achats, qui ne perd rien quand un événement est supprimé.
try {
    $pdo->beginTransaction();
    verrouillerEtablissement($pdo, (int) $etab['id']);

    // La formule est relue sous verrou : elle a pu changer depuis le début de
    // la requête.
    $capacites = capacitesEtablissement($pdo, (int) $etab['id']);
    if ($capacites['offre'] === 'aucune') {
        $pdo->rollBack();
        apiErreur('Choisis une formule d’abonnement pour continuer.', 403, 'abonnement_requis');
    }

    if ($isFlash && !flashDisponible($pdo, (int) $etab['id'], $capacites, $eid ?: null)) {
        $pdo->rollBack();
        $refuser([
            (int) $capacites['flash_par_mois'] === 0
                ? "Ta formule ne comprend pas d'offre flash. Fondateur et Premium en incluent une par mois."
                : "Ton offre flash du mois est déjà utilisée (" . (int) $capacites['flash_par_mois'] . ' par mois).',
        ]);
    }

    if ($evenementExistant) {
        // Ce que devient la mise en avant (voir resoudreSponsoring) : en cours,
        // elle ne bouge pas ; terminée, elle ne se rachète pas sans demande.
        $sponso = resoudreSponsoring($pdo, (int) $etab['id'], $capacites, $evenementExistant, (bool) $isSponsorise, $sponsorFormule, $dateHeure, $dateFin);
        $erreursNotifs = validerNotificationsSponsor($pdo, (int) $eid, $sponso, $notifsDemandees, $dateHeure);
        if ($sponso['erreur'] !== null || $erreursNotifs) {
            $pdo->rollBack();
            $refuser(array_values(array_filter(array_merge([$sponso["erreur"]], $erreursNotifs))));
        }
        if ($sponso['nouvelle'] && (float) $sponso['tarif'] > 0) {
            $pdo->rollBack();
            $refuser([MESSAGE_ACHAT_HORS_APP]);
        }
        $isSponsorise   = $sponso['is_sponsorise'];
        $sponsorFormule = $sponso['formule'];
        $sponsorTarif   = $sponso['tarif'];
        $sponsorFin     = $sponso['fin'];

        $pdo->prepare(
            'UPDATE evenements SET
                titre=?, description=?, type=?, style_musique=?, sport=?, format=?, date_heure=?, date_fin=?,
                quota=?, reduction=?, prix_normal=?,
                is_flash=?, flash_expiry=?, is_gratuit=?, lieu=?,
                is_sponsorise=?, sponsor_formule=?, sponsor_tarif=?, sponsor_jusqu_au=?, sponsor_debut=COALESCE(?, sponsor_debut)
              WHERE id=? AND etablissement_id=?'
        )->execute([
            $titre, $description, $type, $musique ?: null, $sport ?: null, $format ?: null, $dateHeure, $dateFin,
            $quota, $reduction, $prixNormal,
            $isFlash, $flashExpiry ?: null, $isGratuit, $lieu,
            $isSponsorise, $sponsorFormule, $sponsorTarif, $sponsorFin, !empty($sponso['nouvelle']) ? date('Y-m-d H:i:s') : null,
            $eid, (int) $etab['id'],
        ]);

        enregistrerAchatsEvenement($pdo, (int) $etab['id'], $eid, $evenementExistant, [
            'titre' => $titre, 'is_flash' => $isFlash, 'is_sponsorise' => $isSponsorise,
            'sponsor_formule' => $sponsorFormule, 'sponsor_tarif' => $sponsorTarif, 'sponsor_nouvelle' => $sponso['nouvelle'],
        ]);
        if ($sponso['nouvelle']) {
            enregistrerNotificationsSponsor($pdo, $eid, $notifsDemandees, true);
        } elseif ($isSponsorise && sponsoringActif($evenementExistant)) {
            enregistrerNotificationsSponsor($pdo, $eid, $notifsDemandees, false);
        }
        $pdo->commit();

        apiReponse(['success' => true, 'message' => 'Événement mis à jour.', 'id' => $eid]);
    }

    // Création. Au-delà du quota mensuel, l'événement serait un supplément
    // payant : l'application ne vend rien (règles Apple 3.1.1 et Google Play
    // Billing), le supplément ne se prend que sur le site. L'API refuse donc,
    // quoi que dise `accepte_supplement` (que d'anciennes versions envoient).
    $supplementTarif = 0.0;
    if (evenementSeraSupplementaire($pdo, (int) $etab['id'], $capacites)) {
        $pdo->rollBack();
        apiReponse([
            'success' => false,
            'code'    => 'quota_atteint',
            'message' => 'Ton quota de ' . (int) $capacites['evenements_quota_actuel'] . ' événements du mois est atteint.',
        ], 409);
    }

    $sponso = resoudreSponsoring($pdo, (int) $etab['id'], $capacites, null, (bool) $isSponsorise, $sponsorFormule, $dateHeure, $dateFin);
    $erreursNotifs = validerNotificationsSponsor($pdo, 0, $sponso, $notifsDemandees, $dateHeure);
    if ($sponso['erreur'] !== null || $erreursNotifs) {
        $pdo->rollBack();
        $refuser(array_values(array_filter(array_merge([$sponso["erreur"]], $erreursNotifs))));
    }
    if ($sponso['nouvelle'] && (float) $sponso['tarif'] > 0) {
        $pdo->rollBack();
        $refuser([MESSAGE_ACHAT_HORS_APP]);
    }
    $isSponsorise   = $sponso['is_sponsorise'];
    $sponsorFormule = $sponso['formule'];
    $sponsorTarif   = $sponso['tarif'];
    $sponsorFin     = $sponso['fin'];

    $pdo->prepare(
        'INSERT INTO evenements
            (etablissement_id, titre, description, type, style_musique, sport, format, date_heure, date_fin, quota, reduction, prix_normal, is_flash, flash_expiry, is_gratuit, lieu,
             is_sponsorise, sponsor_formule, sponsor_tarif, sponsor_jusqu_au, sponsor_debut, supplement_tarif)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    )->execute([
        (int) $etab['id'], $titre, $description, $type, $musique ?: null, $sport ?: null, $format ?: null, $dateHeure, $dateFin,
        $quota, $reduction, $prixNormal, $isFlash,
        $flashExpiry ?: null, $isGratuit, $lieu,
        $isSponsorise, $sponsorFormule, $sponsorTarif, $sponsorFin, !empty($sponso['nouvelle']) ? date('Y-m-d H:i:s') : null, $supplementTarif,
    ]);
    $nouvelId = (int) $pdo->lastInsertId();

    enregistrerAchatsEvenement($pdo, (int) $etab['id'], $nouvelId, null, [
        'titre' => $titre, 'supplement_tarif' => $supplementTarif, 'is_flash' => $isFlash,
        'is_sponsorise' => $isSponsorise, 'sponsor_formule' => $sponsorFormule, 'sponsor_tarif' => $sponsorTarif,
        'sponsor_nouvelle' => $sponso['nouvelle'],
    ]);
    if ($sponso['nouvelle']) {
        enregistrerNotificationsSponsor($pdo, $nouvelId, $notifsDemandees, true);
    }
    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logErreur('Publication d\'événement impossible', $e, ['etablissement' => $etab['id']]);
    apiErreur('Enregistrement impossible. Réessaie dans un instant.', 500, 'erreur_serveur');
}

// Le canal global ne bouge que pour ça : une nouvelle soirée publiée.
fluxToucher($pdo, CANAL_GLOBAL);

apiReponse([
    'success' => true,
    'message' => 'Événement créé.',
    'id'      => $nouvelId,
]);
