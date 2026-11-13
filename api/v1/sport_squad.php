<?php
/**
 * POST /api/v1/sport_squad.php {titre, type, niveau, date_heure, quota, lieu,
 *      description, cree_par_association} — créer un événement sportif
 *      d'étudiant (un squad).
 *
 * Remplace, pour l'application, api/create_squad.php : celui-ci reste celui
 * du site, limité aux quatre sports d'avant v28. Ici le sport vient du
 * catalogue (includes/sports.php), et la visibilité est posée par le
 * serveur : réservé aux abonnés du créateur, sauf pour une association.
 * Gratuit pour l'étudiant.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/sports.php';

apiExigerMethode('POST');

$u   = apiEtudiant($pdo);
$uid = (int) $u['id'];

$corps = apiCorps();
$texte = static fn (string $cle, int $max): string => mb_substr(trim(is_scalar($corps[$cle] ?? null) ? (string) $corps[$cle] : ''), 0, $max);

$titre       = $texte('titre', 255);
$type        = (string) ($corps['type'] ?? '');
$niveau      = (string) ($corps['niveau'] ?? 'tous');
$dateHeure   = $texte('date_heure', 19);
$quota       = (int) ($corps['quota'] ?? 10);
$lieu        = $texte('lieu', 255);
$description = $texte('description', 1000);
$association = !empty($corps['cree_par_association']) ? 1 : 0;

$erreurs = [];
if ($titre === '') $erreurs[] = 'Le titre est obligatoire.';
if (!sportValide($type)) $erreurs[] = 'Choisis un sport.';
if (!in_array($niveau, niveauxSport(), true)) $erreurs[] = 'Niveau invalide.';
$ts = $dateHeure !== '' ? strtotime($dateHeure) : false;
if ($ts === false) {
    $erreurs[] = 'La date est obligatoire.';
} elseif ($ts <= time()) {
    $erreurs[] = 'La date doit être à venir.';
}
if ($quota < 2 || $quota > 500) $erreurs[] = 'Entre 2 et 500 participants.';

if ($erreurs) {
    apiReponse(['success' => false, 'code' => 'invalide', 'message' => implode(' ', $erreurs), 'erreurs' => $erreurs], 422);
}

try {
    $pdo->beginTransaction();
    $pdo->prepare(
        'INSERT INTO squads (createur_id, type, cree_par_association, visibilite, titre, description, niveau, date_heure, lieu, quota)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        $uid, $type, $association, $association ? 'public' : 'abonnes',
        $titre, $description, $niveau, date('Y-m-d H:i:s', (int) $ts), $lieu, $quota,
    ]);
    $id = (int) $pdo->lastInsertId();
    $pdo->prepare('INSERT INTO squad_membres (squad_id, user_id) VALUES (?, ?)')->execute([$id, $uid]);
    $pdo->commit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    logErreur('Création de squad impossible', $e, ['user' => $uid]);
    apiErreur('Création impossible. Réessaie dans un instant.', 500, 'erreur_serveur');
}

apiReponse([
    'success' => true,
    'id'      => $id,
    'message' => $association ? 'Événement publié pour tout le monde.' : 'Événement publié pour tes abonnés.',
]);
