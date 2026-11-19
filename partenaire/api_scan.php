<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/evenements_temps.php';

header('Content-Type: application/json');

// Ecriture : POST obligatoire, jeton CSRF et origine verifies.
// Voir protegerEcritureApi() dans includes/security.php.
protegerEcritureApi();

if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'partenaire') {
    echo json_encode(['success' => false, 'message' => 'Non autorisé']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$qr_code = trim((string) ($input['qr_code'] ?? ''));
$inscription_id = (int) ($input['inscription_id'] ?? 0);
$event_id = $input['event_id'] ?? 0;

/*
 * Le pass est encodé « outsee:<code> » : le préfixe identifie
 * l'application quand un lecteur généraliste tombe sur le code. La
 * recherche, elle, se fait sur le code seul — c'est ce que contient la
 * colonne qr_code. Sans ce retrait, la comparaison portait sur
 * « outsee:abc… » face à « abc… » : aucun scan ne pouvait aboutir,
 * tous les pass valides étaient refusés.
 * Le préfixe reste optionnel pour accepter un code saisi à la main.
 *
 * « linkee: » et « studentlink: » sont les anciens préfixes, d'avant le changement de nom : un
 * pass affiché par une version en cache de l'application, ou photographié
 * avant la mise à jour, le porte encore. Il reste accepté.
 */
foreach (['outsee:', 'linkee:', 'studentlink:'] as $prefixe) {
    if (stripos($qr_code, $prefixe) === 0) {
        $qr_code = substr($qr_code, strlen($prefixe));
        break;
    }
}

if ((!$qr_code && !$inscription_id) || !$event_id) {
    echo json_encode(['success' => false, 'message' => 'Données manquantes']);
    exit;
}

// Verify the event belongs to this partner
$stmt = $pdo->prepare("
    SELECT e.id, e.date_heure, e.date_fin FROM evenements e
    JOIN etablissements et ON et.id = e.etablissement_id
    WHERE e.id = ? AND et.user_id = ?
");
$stmt->execute([$event_id, $_SESSION['user_id']]);
$evenement = $stmt->fetch();
if (!$evenement) {
    echo json_encode(['success' => false, 'message' => 'Événement non autorisé']);
    exit;
}

// Même fenêtre que api/v1/partenaire_scan.php (PAY-17) : pas d'entrée
// validée la veille, ni un mois après.
$dateFin = $evenement['date_fin'] !== null ? (string) $evenement['date_fin'] : null;
if (!evenementScanOuvert((string) $evenement['date_heure'], $dateFin)) {
    echo json_encode(['success' => false, 'message' => evenementScanMessageFerme((string) $evenement['date_heure'], $dateFin)]);
    exit;
}

// Find the inscription — par QR, ou par id pour une validation manuelle
// (voir partenaire/api_invites.php, quand le scan ne fonctionne pas).
if ($inscription_id) {
    $stmt = $pdo->prepare("
        SELECT i.id, i.statut, u.prenom, u.nom
        FROM inscriptions i
        JOIN users u ON u.id = i.user_id
        WHERE i.id = ? AND i.evenement_id = ?
    ");
    $stmt->execute([$inscription_id, $event_id]);
} else {
    $stmt = $pdo->prepare("
        SELECT i.id, i.statut, u.prenom, u.nom
        FROM inscriptions i
        JOIN users u ON u.id = i.user_id
        WHERE i.qr_code = ? AND i.evenement_id = ?
    ");
    $stmt->execute([$qr_code, $event_id]);
}
$inscription = $stmt->fetch();

if (!$inscription) {
    echo json_encode(['success' => false, 'message' => 'Pass invalide pour cet événement']);
    exit;
}

if ($inscription['statut'] === 'checkin') {
    echo json_encode(['success' => false, 'message' => 'Ce pass a déjà été scanné']);
    exit;
}

if ($inscription['statut'] === 'annule') {
    echo json_encode(['success' => false, 'message' => 'Inscription annulée']);
    exit;
}

// Même règle que api/v1/partenaire_scan.php : la condition est dans
// l'UPDATE, sinon deux scans simultanés du même pass validaient tous deux.
// checkin_le n'était pas renseigné ici : l'heure d'entrée manquait aux
// statistiques de présence pour tout scan fait depuis le navigateur.
// Qui a validé, et comment (v41) : une validation manuelle se distingue
// d'un scan dans le journal des entrées.
$stmt = $pdo->prepare(
    "UPDATE inscriptions SET statut = 'checkin', checkin_le = NOW(), checkin_mode = ?, checkin_par = ?
      WHERE id = ? AND statut NOT IN ('checkin', 'annule')"
);
$stmt->execute([$inscription_id ? 'manuel' : 'scan', (int) $_SESSION['user_id'], $inscription['id']]);
if ($stmt->rowCount() === 0) {
    echo json_encode(['success' => false, 'message' => 'Ce pass a déjà été scanné']);
    exit;
}
// Texte brut : c'est le client qui l'insère (textContent), l'échapper ici
// afficherait « &amp; » à l'écran.
echo json_encode([
    'success' => true,
    'message' => 'Entrée validée pour ' . $inscription['prenom'] . ' ' . $inscription['nom'],
]);
