<?php
/**
 * GET /api/v1/squad_membres.php?id=5 — « Gérer mon Squad ».
 *
 * La lecture d'api/squad_members.php, réservée comme elle au créateur du
 * squad. Ce point-là s'authentifie par la session du navigateur : il ne
 * pouvait pas servir l'application, qui présente un jeton. Le retrait d'un
 * membre et la suppression passent, eux, par les points d'écriture communs
 * (remove_squad_member.php, delete_squad.php), qui acceptent déjà le jeton.
 */

require_once __DIR__ . '/_socle.php';

apiExigerMethode('GET');

$moi = apiEtudiant($pdo);
$uid = (int) $moi['id'];
$id  = (int) ($_GET['id'] ?? 0);

$stmt = $pdo->prepare('SELECT createur_id FROM squads WHERE id = ?');
$stmt->execute([$id]);
$squad = $stmt->fetch();

if (!$squad || (int) $squad['createur_id'] !== $uid) {
    apiErreur('Non autorisé', 403, 'non_createur');
}

$stmt = $pdo->prepare('
    SELECT u.id, u.prenom, u.nom, u.ecole
    FROM squad_membres sm
    JOIN users u ON u.id = sm.user_id
    WHERE sm.squad_id = ?
    ORDER BY sm.joined_at ASC
');
$stmt->execute([$id]);

apiReponse([
    'success' => true,
    'my_id'   => $uid,
    'membres' => array_map(static fn (array $m): array => [
        'id'     => (int) $m['id'],
        'prenom' => (string) $m['prenom'],
        'nom'    => (string) $m['nom'],
        'ecole'  => $m['ecole'] !== null ? (string) $m['ecole'] : null,
    ], $stmt->fetchAll()),
]);
