<?php
/**
 * GET /api/v1/admin_suivi.php — le suivi de l'application, réservé aux
 * administrateurs : inscrits et actifs, heures de pointe, événements et pass,
 * abonnements, options, revenus, santé (includes/suivi.php).
 *
 * Lecture seule. apiAdmin() refuse tout autre compte (403) ; les jetons
 * administrateur ne vivent qu'un jour. Aucune donnée nominative n'est
 * renvoyée : seulement des totaux.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/suivi.php';

apiExigerMethode('GET');
apiAdmin($pdo);

apiReponse(['success' => true] + suiviTableauDeBord($pdo));
