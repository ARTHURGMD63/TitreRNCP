<?php
/**
 * Statut d'une inscription à la liste d'attente : son rang et ses filleuls.
 *
 * Sert à ce qu'un visiteur qui revient (code retrouvé dans localStorage,
 * ou lien ?r= qu'il a lui-même partagé et rouvert) retrouve son état sans
 * se réinscrire — le rang bouge à mesure que ses filleuls rejoignent.
 *
 * Lecture seule, pas de jeton exigé (voir ApiProtectionTest::LECTURE_SEULE) :
 * le code n'expose ni adresse ni nom, seulement un rang. Depuis la v41 il
 * est aléatoire (ETU-12) : on ne lit plus le rang des autres en essayant
 * les ids un à un.
 */
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/liste_attente.php';
header('Content-Type: application/json');

try {
    $id = idListeAttenteDepuisCode($pdo, (string) ($_GET['code'] ?? ''));
    if ($id === null) {
        echo json_encode(['success' => false, 'message' => 'Code invalide.']);
        exit;
    }

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
