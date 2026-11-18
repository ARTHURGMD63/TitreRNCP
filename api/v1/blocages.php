<?php
/**
 * GET /api/v1/blocages.php — les personnes que j'ai bloquées.
 *
 * Une fois bloquée, une personne disparaît de partout, sa fiche comprise
 * (etudiant.php répond « introuvable ») : le bouton « Débloquer » de la fiche
 * n'était donc plus jamais atteignable, et un blocage devenait définitif.
 * Cette liste, ouverte depuis l'écran Moi, est le seul chemin de retour.
 * Le déblocage lui-même passe par api/moderation.php (action « unblock »).
 *
 * Seuls les blocages que J'AI posés figurent ici : ceux qu'une autre personne
 * a posés sur moi ne me regardent pas.
 */

require_once __DIR__ . '/_socle.php';

apiExigerMethode('GET');

$moi = apiEtudiant($pdo);

$stmt = $pdo->prepare(
    "SELECT u.id, u.prenom, u.nom, u.ecole, u.promo, u.photo, b.created_at
       FROM user_blocks b
       JOIN users u ON u.id = b.blocked_id
      WHERE b.blocker_id = ? AND u.type = 'etudiant'
      ORDER BY b.created_at DESC
      LIMIT 500"
);
$stmt->execute([(int) $moi['id']]);

apiReponse([
    'success'   => true,
    'personnes' => array_map(static fn (array $p): array => apiPersonne($p) + [
        'bloque_le' => (string) $p['created_at'],
    ], $stmt->fetchAll()),
]);
