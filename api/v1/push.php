<?php
/**
 * Notifications push : l'appareil et les préférences.
 *
 * GET  /api/v1/push.php
 *      → { categories: [{ code, libelle, detail, modifiable, actif }] }
 *        les catégories de CE compte (un étudiant en règle trois, un
 *        établissement n'en a aucune à régler).
 *
 * POST /api/v1/push.php
 *      { action: "enregistrer", jeton, plateforme: "ios"|"android", appareil? }
 *          rattache le téléphone à ce compte (à chaque ouverture : le jeton
 *          peut changer, et le téléphone peut changer de propriétaire) ;
 *      { action: "retirer", jeton }
 *          déconnexion : ce téléphone ne reçoit plus rien pour ce compte ;
 *      { action: "preference", categorie, actif: bool }
 *          coupe ou rétablit une catégorie.
 *
 * Les envois eux-mêmes ne passent pas ici : voir includes/push.php.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/push.php';

$u    = apiUtilisateur($pdo);
$uid  = (int) $u['id'];
$type = (string) $u['type'];

$etat = static function () use ($pdo, $uid, $type): array {
    $prefs = pushPreferences($pdo, $uid);
    $defs  = pushCategories();
    $liste = [];
    foreach (pushCategoriesPour($type) as $code) {
        $liste[] = [
            'code'       => $code,
            'libelle'    => $defs[$code]['libelle'],
            'detail'     => $defs[$code]['detail'],
            'modifiable' => $defs[$code]['modifiable'],
            'actif'      => $prefs[$code] ?? true,
        ];
    }

    return $liste;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    apiExigerMethode('GET');
    apiReponse(['success' => true, 'categories' => $etat()]);
}

$corps  = apiCorps();
$action = (string) ($corps['action'] ?? '');

switch ($action) {
    case 'enregistrer':
        $ok = pushEnregistrerAppareil(
            $pdo, $uid,
            (string) ($corps['jeton'] ?? ''), (string) ($corps['plateforme'] ?? ''),
            isset($corps['appareil']) ? (string) $corps['appareil'] : null
        );
        if (!$ok) {
            apiErreur('Jeton de notification invalide.', 422, 'jeton_invalide');
        }
        apiReponse(['success' => true]);

    case 'retirer':
        pushRetirerAppareil($pdo, $uid, (string) ($corps['jeton'] ?? ''));
        apiReponse(['success' => true]);

    case 'preference':
        $categorie = (string) ($corps['categorie'] ?? '');
        if (!in_array($categorie, pushCategoriesPour($type), true)
            || !pushDefinirPreference($pdo, $uid, $categorie, (bool) ($corps['actif'] ?? false))) {
            apiErreur('Catégorie inconnue ou non modifiable.', 422, 'categorie_invalide');
        }
        apiReponse(['success' => true, 'categories' => $etat()]);

    default:
        apiErreur('Action inconnue.', 422, 'action_inconnue');
}
