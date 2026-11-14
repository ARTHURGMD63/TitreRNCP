<?php
/**
 * L'administration de l'espace Culture — réservée à l'administrateur.
 *
 *   GET  /api/v1/admin_culture.php
 *        les événements à venir et en cours, puis les trente derniers terminés
 *   POST { "action": "enregistrer", "id"?: 3, …champs }   créer ou modifier
 *   POST { "action": "supprimer", "id": 3 }
 *
 * C'est l'équipe qui publie ce que la ville met en avant : il n'existe pas de
 * compte « ville », et en créer un pour quelques événements par mois
 * ajouterait un rôle entier à garder.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/culture.php';
require_once __DIR__ . '/../../includes/temps_reel.php';

apiExigerMethode(($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' ? 'POST' : 'GET');

$admin = apiAdmin($pdo);

/**
 * @param array<string,mixed> $c
 * @return array<string,mixed>
 */
function adminCultureLigne(array $c): array
{
    $f = cultureFormater($c);
    unset($f['amis'], $f['deja_inscrit']);
    $f['termine'] = (bool) $c['termine'];

    return $f;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $fin  = cultureSqlFin('c');
    $base = "SELECT c.*, ($fin < NOW()) AS termine,
                    (c.date_heure <= NOW() AND $fin >= NOW()) AS en_cours_calc,
                    (SELECT COUNT(*) FROM culture_inscriptions ci WHERE ci.evenement_id = c.id) AS nb_inscrits
               FROM culture_evenements c";

    $aVenir  = $pdo->query("$base WHERE $fin >= NOW() ORDER BY c.date_heure ASC, c.id ASC")->fetchAll();
    $passes  = $pdo->query("$base WHERE $fin < NOW() ORDER BY c.date_heure DESC, c.id DESC LIMIT 30")->fetchAll();

    apiReponse([
        'success'    => true,
        'a_venir'    => array_map('adminCultureLigne', $aVenir),
        'termines'   => array_map('adminCultureLigne', $passes),
        'categories' => array_map(
            static fn (string $code, string $libelle): array => ['code' => $code, 'libelle' => $libelle],
            array_keys(CULTURE_CATEGORIES),
            array_values(CULTURE_CATEGORIES)
        ),
    ]);
}

$corps  = apiCorps();
$action = (string) ($corps['action'] ?? '');
$id     = (int) ($corps['id'] ?? 0);

if ($action === 'supprimer') {
    if ($id <= 0) {
        apiErreur('Événement invalide', 422, 'id_manquant');
    }
    // Les inscriptions partent avec (ON DELETE CASCADE) ; les invitations en
    // attente vers cet événement n'ont plus d'objet et sont refermées.
    $pdo->prepare("UPDATE invitations SET statut = 'declined' WHERE type = 'culture' AND target_id = ? AND statut = 'pending'")
        ->execute([$id]);
    $pdo->prepare('DELETE FROM culture_evenements WHERE id = ?')->execute([$id]);
    fluxToucher($pdo, canalCulture($id), CANAL_GLOBAL);
    apiReponse(['success' => true, 'message' => 'Événement supprimé']);
}

if ($action !== 'enregistrer') {
    apiErreur('Action inconnue', 422, 'action');
}

[$v, $erreurs] = cultureValiderSaisie($corps);
if ($erreurs) {
    apiErreur(implode(' ', $erreurs), 422, 'saisie');
}

$colonnes = ['titre', 'description', 'categorie', 'organisateur', 'lieu', 'ville', 'date_heure', 'date_fin', 'quota', 'prix', 'lien', 'mis_en_avant'];
$valeurs  = array_map(static fn (string $col) => $v[$col], $colonnes);

if ($id > 0) {
    $stmt = $pdo->prepare('UPDATE culture_evenements SET ' . implode(', ', array_map(static fn (string $col): string => "$col = ?", $colonnes)) . ' WHERE id = ?');
    $stmt->execute([...$valeurs, $id]);
    if ($stmt->rowCount() === 0) {
        $existe = $pdo->prepare('SELECT COUNT(*) FROM culture_evenements WHERE id = ?');
        $existe->execute([$id]);
        if (!$existe->fetchColumn()) {
            apiErreur('Cet événement n’existe plus', 404, 'introuvable');
        }
    }
} else {
    $pdo->prepare('INSERT INTO culture_evenements (' . implode(', ', $colonnes) . ', cree_par) VALUES (' . implode(', ', array_fill(0, count($colonnes) + 1, '?')) . ')')
        ->execute([...$valeurs, (int) $admin['id']]);
    $id = (int) $pdo->lastInsertId();
}

fluxToucher($pdo, canalCulture($id), CANAL_GLOBAL);

apiReponse(['success' => true, 'id' => $id, 'message' => 'Événement enregistré']);
