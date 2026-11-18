<?php
/**
 * GET  /api/v1/partenaire_profil.php — les infos de l'établissement et du
 *      compte connecté (écran « Moi » de l'espace partenaire).
 * POST /api/v1/partenaire_profil.php {nom, type, ville, adresse} — les
 *      modifier.
 *
 * Rien d'équivalent sur le site aujourd'hui (le nom/type/adresse de
 * l'établissement ne se règlent qu'à l'inscription, auth/register.php) :
 * c'est un ajout propre à l'application, pas un portage — mais il reste
 * scopé à l'établissement du compte connecté, comme partout ailleurs dans
 * cet espace.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/geocodage.php';
require_once __DIR__ . '/../../includes/comptes.php';

$u   = apiPartenaire($pdo);
$uid = (int) $u['id'];

$etab = apiEtablissementDe($pdo, $uid);
if (!$etab) {
    apiErreur('Aucun établissement associé à ce compte.', 404, 'etablissement_absent');
}

$methode = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($methode === 'POST') {
    $corps = apiCorps();
    $nom     = trim((string) ($corps['nom'] ?? ''));
    $type    = (string) ($corps['type'] ?? '');
    $ville   = trim((string) ($corps['ville'] ?? ''));
    $adresse = trim((string) ($corps['adresse'] ?? ''));
    // Position choisie à la main sur la carte (on fait glisser l'épingle) :
    // si le partenaire ne l'a pas touchée, null ici veut dire « laisser la
    // valeur déjà en base », pas « effacer la position ».
    $latitude  = array_key_exists('latitude', $corps) && $corps['latitude'] !== null ? (float) $corps['latitude'] : null;
    $longitude = array_key_exists('longitude', $corps) && $corps['longitude'] !== null ? (float) $corps['longitude'] : null;
    $positionFournie = $latitude !== null && $longitude !== null;

    $erreurs = [];
    if ($nom === '') $erreurs[] = "Le nom de l'établissement est obligatoire.";
    if (!typeEtablissementValide($type)) $erreurs[] = 'Type invalide.';
    // Le type range le compte dans son monde (sortie, sport) et décide des
    // formules qu'il peut prendre : il se fige dès qu'une formule est choisie
    // ou qu'un achat est inscrit. Sinon un bar se déclarerait club ou BDE le
    // temps de prendre une formule moins chère.
    if ($type !== (string) $etab['type'] && !typeEtablissementModifiable($pdo, (int) $etab['id'])) {
        $erreurs[] = 'Le type de ton compte est lié à ta formule et ne peut plus changer. Écris-nous si ton activité a changé.';
    }
    if ($ville === '') $erreurs[] = 'La ville est obligatoire.';

    if ($erreurs) {
        apiReponse(['success' => false, 'message' => implode(' ', $erreurs), 'erreurs' => $erreurs], 422);
    }

    // Un compte qui devient BDE attend la validation ; qui cesse de l'être
    // n'en a plus besoin. Un type inchangé garde son état.
    $verification = $type !== (string) $etab['type']
        ? verificationInitiale($type)
        : (string) $etab['verification'];

    if ($positionFournie) {
        $pdo->prepare('UPDATE etablissements SET nom=?, type=?, verification=?, ville=?, adresse=?, latitude=?, longitude=? WHERE id=? AND user_id=?')
            ->execute([$nom, $type, $verification, $ville, $adresse ?: null, $latitude, $longitude, (int) $etab['id'], $uid]);
    } else {
        $pdo->prepare('UPDATE etablissements SET nom=?, type=?, verification=?, ville=?, adresse=? WHERE id=? AND user_id=?')
            ->execute([$nom, $type, $verification, $ville, $adresse ?: null, (int) $etab['id'], $uid]);

        // Filet : si l'adresse ou la ville change et qu'aucune position n'est
        // encore connue, on tente de la déduire — mieux qu'un établissement
        // absent de la carte tant que personne n'a touché à l'épingle.
        $aDejaUnePosition = $etab['latitude'] !== null && $etab['longitude'] !== null;
        if (!$aDejaUnePosition) {
            $position = geocoderAdresse($adresse, $ville);
            if ($position) {
                $pdo->prepare('UPDATE etablissements SET latitude=?, longitude=? WHERE id=?')
                    ->execute([$position['lat'], $position['lon'], (int) $etab['id']]);
            }
        }
    }

    apiReponse(['success' => true, 'message' => 'Établissement mis à jour.']);
}

apiExigerMethode('GET');

apiReponse([
    'success' => true,
    'compte' => ['prenom' => (string) $u['prenom'], 'nom' => (string) $u['nom'], 'email' => (string) $u['email']],
    'etablissement' => [
        'nom'       => (string) $etab['nom'],
        'type'      => (string) $etab['type'],
        'type_modifiable' => typeEtablissementModifiable($pdo, (int) $etab['id']),
        'verification'    => (string) $etab['verification'],
        'ville'     => (string) ($etab['ville'] ?? ''),
        'adresse'   => (string) ($etab['adresse'] ?? ''),
        'latitude'  => $etab['latitude'] !== null ? (float) $etab['latitude'] : null,
        'longitude' => $etab['longitude'] !== null ? (float) $etab['longitude'] : null,
    ],
]);
