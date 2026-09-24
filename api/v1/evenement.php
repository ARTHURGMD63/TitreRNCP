<?php
/**
 * GET /api/v1/evenement.php?id=12 — la fiche complete d'une soiree.
 *
 * Reprend les trois lectures de view_event.php : la soiree et son
 * etablissement, les photos du lieu, et les amis qui y vont — avec leur nom et
 * leur avatar cette fois, la ou le fil ne rend que les prenoms.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/uploads.php';
require_once __DIR__ . '/../../includes/agregats.php';
require_once __DIR__ . '/../../includes/sponsoring.php';

apiExigerMethode('GET');

$u   = apiEtudiant($pdo);
$uid = (int) $u['id'];
$id  = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    apiErreur('Identifiant manquant', 422, 'id_manquant');
}

$stmt = $pdo->prepare("
    SELECT e.*, et.nom AS etablissement_nom, et.ville, et.adresse,
           et.type AS etab_type,
           (SELECT COUNT(*) FROM inscriptions
             WHERE evenement_id = e.id AND statut != 'annule') AS nb_inscrits,
           (SELECT COUNT(*) FROM inscriptions
             WHERE evenement_id = e.id AND user_id = ? AND statut != 'annule') AS deja_inscrit
      FROM evenements e
      JOIN etablissements et ON et.id = e.etablissement_id
     WHERE e.id = ?
");
$stmt->execute([$uid, $id]);
$e = $stmt->fetch();

if (!$e) {
    apiErreur('Cette soirée n’existe plus', 404, 'introuvable');
}

// Photos du lieu.
$stmt = $pdo->prepare(
    'SELECT fichier, legende FROM etablissement_photos
      WHERE etablissement_id = ? ORDER BY position ASC, id ASC LIMIT 8'
);
$stmt->execute([(int) $e['etablissement_id']]);
$photos = $stmt->fetchAll();

// Les amis qui y vont, avec de quoi afficher leur avatar.
$stmt = $pdo->prepare("
    SELECT u.id, u.prenom, u.nom, u.photo
      FROM follows_users fu
      JOIN inscriptions i ON i.user_id = fu.followed_id AND i.statut = 'inscrit'
      JOIN users u        ON u.id = fu.followed_id
     WHERE fu.follower_id = ? AND fu.statut = 'accepted' AND i.evenement_id = ?
");
$stmt->execute([$uid, $id]);
$amis = $stmt->fetchAll();

$notes    = notesEtablissements($pdo);
$note     = $notes[(int) $e['etablissement_id']] ?? null;
$quota    = (int) ($e['quota'] ?? 0);
$inscrits = (int) $e['nb_inscrits'];

// Une soiree flash dont l'echeance est passee n'est plus flash : le web fait
// le meme calcul avant d'afficher son compte a rebours.
$flashEncours = (bool) $e['is_flash']
    && $e['flash_expiry'] !== null
    && strtotime((string) $e['flash_expiry']) > time();

apiReponse([
    'success'   => true,
    'evenement' => [
        'id'            => (int) $e['id'],
        'titre'         => (string) $e['titre'],
        'description'   => (string) ($e['description'] ?? ''),
        'type'          => (string) ($e['type'] ?? ''),
        'style_musique' => $e['style_musique'] !== null ? (string) $e['style_musique'] : null,
        'date_heure'    => (string) $e['date_heure'],
        'lieu'          => (string) ($e['lieu'] ?? ''),

        'etablissement' => [
            'id'      => (int) $e['etablissement_id'],
            'nom'     => (string) $e['etablissement_nom'],
            'type'    => (string) ($e['etab_type'] ?? ''),
            'ville'   => (string) ($e['ville'] ?? ''),
            'adresse' => (string) ($e['adresse'] ?? ''),
            'note'    => $note !== null ? round((float) $note['note'], 1) : null,
            'nb_avis' => (int) ($note['nb'] ?? 0),
        ],

        'places' => [
            'quota'       => $quota,
            'inscrits'    => $inscrits,
            'restantes'   => $quota > 0 ? max(0, $quota - $inscrits) : null,
            'complet'     => $quota > 0 && $inscrits >= $quota,
            'pourcentage' => $quota > 0 ? (int) round($inscrits / $quota * 100) : null,
        ],

        'reduction'    => $e['reduction'] !== null ? (int) $e['reduction'] : null,
        'prix_normal'  => $e['prix_normal'] !== null ? (float) $e['prix_normal'] : null,
        'is_gratuit'   => (bool) ($e['is_gratuit'] ?? false),
        'is_flash'     => $flashEncours,
        'flash_expiry' => $e['flash_expiry'] !== null ? (string) $e['flash_expiry'] : null,
        'deja_inscrit' => ((int) $e['deja_inscrit']) > 0,
        // La mention reste sur la fiche comme sur le web : un contenu payé
        // s'annonce partout où il est lu.
        'sponsorise'   => sponsoringActif($e),

        'photos' => array_map(
            static fn (array $p): array => [
                'url'     => apiUrlAbsolue(venuePhotoUrl((string) $p['fichier'])),
                'legende' => $p['legende'] !== null ? (string) $p['legende'] : null,
            ],
            $photos
        ),

        'amis' => array_map(
            static fn (array $a): array => [
                'id'        => (int) $a['id'],
                'prenom'    => (string) $a['prenom'],
                'nom'       => (string) $a['nom'],
                'photo_url' => !empty($a['photo']) ? avatarUrlAbsolue((string) $a['photo']) : null,
            ],
            $amis
        ),
    ],
]);
