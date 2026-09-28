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
require_once __DIR__ . '/../../includes/capacites.php';
require_once __DIR__ . '/../../includes/musique.php';
require_once __DIR__ . '/../../includes/temps_reel.php';

$u   = apiPartenaire($pdo);
$uid = (int) $u['id'];

$etab = apiEtablissementDe($pdo, $uid);
if (!$etab) {
    apiErreur('Aucun établissement associé à ce compte.', 404, 'etablissement_absent');
}
if (!abonnementChoisi(abonnementEtablissement($pdo, (int) $etab['id']))) {
    apiErreur('Choisis une formule d’abonnement pour continuer.', 403, 'abonnement_requis');
}

$capacites = capacitesEtablissement($pdo, (int) $etab['id']);

/** Catalogues partagés entre GET (préremplissage) et les erreurs de POST. */
function catalogues(array $capacites): array
{
    return [
        'styles_musique' => array_map(
            static fn (string $code, string $libelle): array => ['code' => $code, 'libelle' => $libelle],
            array_keys(stylesMusique()),
            array_values(stylesMusique())
        ),
        'formules_sponsoring' => array_map(
            static fn (string $code, array $f): array => [
                'code' => $code, 'nom' => $f['nom'], 'tarif' => (float) $f['tarif'], 'resume' => $f['resume'],
            ],
            array_keys(formulesSponsoring()),
            array_values(formulesSponsoring())
        ),
        'flash_illimite' => $capacites['flash_par_mois'] === null,
        'flash_par_mois' => $capacites['flash_par_mois'],
    ];
}

function apiEvenementPourEdition(array $e): array
{
    return [
        'id' => (int) $e['id'],
        'titre' => (string) $e['titre'],
        'description' => (string) ($e['description'] ?? ''),
        'type' => (string) $e['type'],
        'style_musique' => $e['style_musique'] !== null ? (string) $e['style_musique'] : null,
        'date_heure' => (string) $e['date_heure'],
        'date_fin' => $e['date_fin'] !== null ? (string) $e['date_fin'] : null,
        'quota' => (int) $e['quota'],
        'reduction' => (int) ($e['reduction'] ?? 0),
        'prix_normal' => (float) ($e['prix_normal'] ?? 0),
        'is_flash' => (bool) $e['is_flash'],
        'flash_expiry' => $e['flash_expiry'] !== null ? (string) $e['flash_expiry'] : null,
        'is_gratuit' => (bool) $e['is_gratuit'],
        'lieu' => (string) ($e['lieu'] ?? ''),
        'is_sponsorise' => (bool) $e['is_sponsorise'],
        'sponsor_formule' => $e['sponsor_formule'] !== null ? (string) $e['sponsor_formule'] : null,
    ];
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
        $evenement = apiEvenementPourEdition($ligne);
    }

    apiReponse(['success' => true, 'evenement' => $evenement, 'lieu_defaut' => (string) ($etab['adresse'] ?? $etab['nom'])] + catalogues($capacites));
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
$type         = (string) ($corps['type'] ?? '');
$musique      = trim((string) ($corps['style_musique'] ?? ''));
$dateHeure    = trim((string) ($corps['date_heure'] ?? ''));
$dateFin      = trim((string) ($corps['date_fin'] ?? '')) ?: null;
$quota        = (int) ($corps['quota'] ?? 100);
$reduction    = (int) ($corps['reduction'] ?? 0);
$prixNormal   = (float) ($corps['prix_normal'] ?? 0);
$isFlash      = !empty($corps['is_flash']) ? 1 : 0;
$flashExpiry  = $isFlash ? trim((string) ($corps['flash_expiry'] ?? '')) : '';
$isGratuit    = !empty($corps['is_gratuit']) ? 1 : 0;
$lieu         = trim((string) ($corps['lieu'] ?? ''));
$isSponsorise = !empty($corps['is_sponsorise']) ? 1 : 0;
$sponsorFormule = $isSponsorise ? (string) ($corps['sponsor_formule'] ?? '') : null;

$erreurs = [];
if ($titre === '') $erreurs[] = 'Le titre est obligatoire.';
if (!in_array($type, ['bar', 'boite', 'resto', 'afterwork'], true)) $erreurs[] = 'Type invalide.';
if ($musique !== '' && !styleMusiqueValide($musique)) $erreurs[] = 'Style de musique invalide.';
if ($dateHeure === '') $erreurs[] = 'La date est obligatoire.';
if ($dateFin && strtotime($dateFin) <= strtotime($dateHeure)) $erreurs[] = 'La fin doit être après le début.';
if ($quota < 1) $erreurs[] = 'Le quota doit être au moins 1.';
if ($isFlash && $flashExpiry === '') $erreurs[] = "Date d'expiration flash requise.";
if ($isSponsorise && !formuleSponsoringValide($sponsorFormule)) $erreurs[] = 'Choisis une formule de sponsoring.';
if ($isFlash && !flashDisponible($pdo, (int) $etab['id'], $capacites, $eid ?: null)) {
    $erreurs[] = "Ton quota d'offres flash du mois est atteint (" . (int) $capacites['flash_par_mois'] . ' par mois). Le Premium les rend illimitées.';
}

if ($erreurs) {
    apiReponse(['success' => false, 'message' => implode(' ', $erreurs), 'erreurs' => $erreurs] + catalogues($capacites), 422);
}

if ($evenementExistant) {
    // Le tarif/la fenêtre de mise en avant ne se recalculent que si la
    // formule change réellement : corriger une faute de frappe dans le
    // titre ne doit pas redémarrer sept jours de mise en avant offerts.
    $formuleAvant = $evenementExistant['sponsor_formule'] ?? null;
    $etaitSponso  = !empty($evenementExistant['is_sponsorise']);

    if (!$isSponsorise) {
        $sponsorTarif = 0.0;
        $sponsorFin   = null;
    } elseif ($etaitSponso && $formuleAvant === $sponsorFormule) {
        $sponsorTarif = (float) ($evenementExistant['sponsor_tarif'] ?? 0);
        $sponsorFin   = $evenementExistant['sponsor_jusqu_au'] ?? null;
    } else {
        $sponsorTarif = misesEnAvantOffertesRestantes($pdo, (int) $etab['id'], $capacites) > 0 ? 0.0 : tarifSponsoring((string) $sponsorFormule);
        $sponsorFin   = finSponsoring((string) $sponsorFormule, $dateHeure);
    }

    $pdo->prepare(
        'UPDATE evenements SET
            titre=?, description=?, type=?, style_musique=?, date_heure=?, date_fin=?,
            quota=?, reduction=?, prix_normal=?,
            is_flash=?, flash_expiry=?, is_gratuit=?, lieu=?,
            is_sponsorise=?, sponsor_formule=?, sponsor_tarif=?, sponsor_jusqu_au=?
          WHERE id=? AND etablissement_id=?'
    )->execute([
        $titre, $description, $type, $musique ?: null, $dateHeure, $dateFin,
        $quota, $reduction, $prixNormal,
        $isFlash, $flashExpiry ?: null, $isGratuit, $lieu,
        $isSponsorise, $sponsorFormule, $sponsorTarif, $sponsorFin,
        $eid, (int) $etab['id'],
    ]);

    apiReponse(['success' => true, 'message' => 'Événement mis à jour.', 'id' => $eid]);
}

$sponsorTarif = $isSponsorise ? tarifSponsoring((string) $sponsorFormule) : 0.0;
if ($isSponsorise && misesEnAvantOffertesRestantes($pdo, (int) $etab['id'], $capacites) > 0) {
    $sponsorTarif = 0.0;
}
$sponsorFin = $isSponsorise ? finSponsoring((string) $sponsorFormule, $dateHeure) : null;

$pdo->prepare(
    'INSERT INTO evenements
        (etablissement_id, titre, description, type, style_musique, date_heure, date_fin, quota, reduction, prix_normal, is_flash, flash_expiry, is_gratuit, lieu,
         is_sponsorise, sponsor_formule, sponsor_tarif, sponsor_jusqu_au)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
)->execute([
    (int) $etab['id'], $titre, $description, $type, $musique ?: null, $dateHeure, $dateFin,
    $quota, $reduction, $prixNormal, $isFlash,
    $flashExpiry ?: null, $isGratuit, $lieu,
    $isSponsorise, $sponsorFormule, $sponsorTarif, $sponsorFin,
]);
$nouvelId = (int) $pdo->lastInsertId();

// Le canal global ne bouge que pour ça : une nouvelle soirée publiée.
fluxToucher($pdo, CANAL_GLOBAL);

apiReponse(['success' => true, 'message' => 'Événement créé.', 'id' => $nouvelId]);
