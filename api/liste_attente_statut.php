<?php
/**
 * Statut d'une inscription à la liste d'attente : son rang et ses filleuls.
 *
 * Sert à ce qu'un visiteur qui revient (code retrouvé dans localStorage,
 * ou lien ?r= qu'il a lui-même partagé et rouvert) retrouve son état sans
 * se réinscrire — le rang bouge à mesure que ses filleuls rejoignent.
 *
 * Lecture seule, pas de jeton exigé (voir ApiProtectionTest::LECTURE_SEULE) :
 * le code n'est pas un secret, juste un id habillé (codeDepuisId()), pas
 * plus sensible qu'un numéro de dossard.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/liste_attente.php';
header('Content-Type: application/json');

$id = idDepuisCode((string) ($_GET['code'] ?? ''));
if ($id === null) {
    echo json_encode(['success' => false, 'message' => 'Code invalide.']);
    exit;
}

try {
    $statut = statutListeAttente($pdo, $id);
    if ($statut === null) {
        echo json_encode(['success' => false, 'message' => 'Inscription introuvable.']);
        exit;
    }

    $count = (int) $pdo->query('SELECT COUNT(*) FROM liste_attente')->fetchColumn();

    echo json_encode(['success' => true, 'count' => $count] + $statut);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erreur serveur.']);
}
