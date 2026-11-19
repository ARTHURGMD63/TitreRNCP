<?php
/**
 * GET /api/v1/mes_donnees.php — toutes les données personnelles du compte
 * connecté, en JSON (droit d'accès et de portabilité, RGPD art. 15 et 20).
 *
 * Ouvert à tout compte (étudiant, établissement, administrateur). Le contenu
 * et ce qui en est exclu (secrets, données des autres) sont décrits dans
 * includes/rgpd.php. Les fichiers (photo de profil, photos de soirée et du
 * lieu) sont rendus par leur adresse, à télécharger tant que le compte
 * existe.
 *
 * L'export lit une trentaine de tables : limité à 3 par heure et par compte
 * (et 10 par adresse IP), avec la limite propre à cette action
 * (limiteAtteinte(), motif « export »). 429 au-delà.
 *
 * Créé après l'audit (ETU-13) : les demandes se traitaient à la main.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/uploads.php';
require_once __DIR__ . '/../../includes/rgpd.php';

apiExigerMethode('GET');

$u   = apiUtilisateur($pdo);
$uid = (int) $u['id'];
$ip  = ipClient();

if (limiteAtteinte($pdo, 'export', $ip, 10, 3600, (string) $uid, 3)) {
    apiErreur('Tu as déjà demandé tes données plusieurs fois : réessaie dans une heure.', 429, 'trop_de_demandes');
}
enregistrerTentative($pdo, 'export', $ip, (string) $uid);

$donnees = rgpdExport($pdo, $uid);

// Les fichiers, par leur adresse plutôt que par leur nom sur le disque.
if (is_array($donnees['compte'] ?? null)) {
    $donnees['compte']['photo_url'] = apiPhotoUrl($donnees['compte']['photo'] ?? null);
}
if (is_array($donnees['photos_de_soiree'] ?? null)) {
    $donnees['photos_de_soiree'] = array_map(
        static fn (array $p): array => $p + ['url' => apiUrlAbsolue(evenementPhotoUrl((string) $p['fichier']))],
        $donnees['photos_de_soiree']
    );
}
if (is_array($donnees['photos_etablissement'] ?? null)) {
    $donnees['photos_etablissement'] = array_map(
        static fn (array $p): array => $p + ['url' => apiUrlAbsolue(venuePhotoUrl((string) $p['fichier']))],
        $donnees['photos_etablissement']
    );
}

header('Content-Disposition: inline; filename="outsee-mes-donnees.json"');
apiReponse(['success' => true, 'donnees' => $donnees]);
