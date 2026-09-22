<?php
/**
 * GET /api/v1/squads.php — les squads a venir.
 *
 * Meme requete que squads.php, a une difference pres : le web agrege les
 * INITIALES des membres (GROUP_CONCAT de LEFT(prenom, 1)) parce qu'il n'en
 * dessine que des pastilles. Ici on rend les prenoms entiers et les avatars :
 * l'application peut faire les deux, alors que l'inverse est impossible — on
 * ne remonte pas un prenom depuis sa premiere lettre.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/uploads.php';

apiExigerMethode('GET');

$u   = apiEtudiant($pdo);
$uid = (int) $u['id'];

$stmt = $pdo->prepare("
    SELECT s.*,
           u.prenom AS createur_prenom, u.nom AS createur_nom, u.photo AS createur_photo,
           (SELECT COUNT(*) FROM squad_membres sm WHERE sm.squad_id = s.id) AS nb_membres,
           (SELECT COUNT(*) FROM squad_membres sm
             WHERE sm.squad_id = s.id AND sm.user_id = ?) AS deja_membre
      FROM squads s
      JOIN users u ON u.id = s.createur_id
     WHERE s.date_heure >= NOW()
     ORDER BY s.date_heure ASC
");
$stmt->execute([$uid]);
$squads = $stmt->fetchAll();

// Les membres de toutes les squads en une requete, plutot qu'une par squad.
// A vingt squads affichees, la seconde forme ferait vingt allers-retours pour
// afficher des pastilles.
$membres = [];
if ($squads) {
    $ids   = array_map(static fn (array $s): int => (int) $s['id'], $squads);
    $trous = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare("
        SELECT sm.squad_id, u.id, u.prenom, u.photo
          FROM squad_membres sm
          JOIN users u ON u.id = sm.user_id
         WHERE sm.squad_id IN ($trous)
         ORDER BY sm.joined_at ASC
    ");
    $stmt->execute($ids);

    foreach ($stmt->fetchAll() as $m) {
        $membres[(int) $m['squad_id']][] = [
            'id'        => (int) $m['id'],
            'prenom'    => (string) $m['prenom'],
            'photo_url' => !empty($m['photo']) ? avatarUrlAbsolue((string) $m['photo']) : null,
        ];
    }
}

apiReponse([
    'success' => true,
    'squads'  => array_map(
        static function (array $s) use ($membres, $uid): array {
            $id    = (int) $s['id'];
            $quota = (int) ($s['quota'] ?? 0);
            $nb    = (int) $s['nb_membres'];

            return [
                'id'          => $id,
                'titre'       => (string) $s['titre'],
                'description' => (string) ($s['description'] ?? ''),
                'type'        => (string) ($s['type'] ?? ''),
                'niveau'      => (string) ($s['niveau'] ?? ''),
                'date_heure'  => (string) $s['date_heure'],
                'lieu'        => (string) ($s['lieu'] ?? ''),

                'createur' => [
                    'id'        => (int) $s['createur_id'],
                    'prenom'    => (string) $s['createur_prenom'],
                    'nom'       => (string) $s['createur_nom'],
                    'photo_url' => !empty($s['createur_photo'])
                        ? avatarUrlAbsolue((string) $s['createur_photo'])
                        : null,
                ],

                'places' => [
                    'quota'     => $quota,
                    'membres'   => $nb,
                    'restantes' => $quota > 0 ? max(0, $quota - $nb) : null,
                    'complet'   => $quota > 0 && $nb >= $quota,
                ],

                'deja_membre' => ((int) $s['deja_membre']) > 0,
                // Le createur voit « Supprimer » la ou les autres voient
                // « Rejoindre » : l'application a besoin de le savoir sans
                // avoir a comparer elle-meme des identifiants.
                'est_createur' => (int) $s['createur_id'] === $uid,
                'membres'     => $membres[$id] ?? [],
            ];
        },
        $squads
    ),
]);
