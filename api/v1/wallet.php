<?php
/**
 * GET /api/v1/wallet.php — les pass de l'etudiant et ses economies.
 *
 * Parametre optionnel : ?h=2 pour allonger l'historique (cumulatif, comme sur
 * le web).
 *
 * LE CODE DU PASS N'EST PAS UNE IMAGE.
 *
 * L'API renvoie `code_qr`, la chaine a encoder — « linkee:<code> » —, pas
 * un QR code dessine. Trois raisons : un PNG en base64 pese dix fois le texte
 * qu'il represente ; une image rendue cote serveur ne s'adapte ni a la densite
 * de l'ecran ni au mode sombre ; et surtout, l'application iOS devra de toute
 * facon disposer de la chaine brute pour construire un vrai pass Apple Wallet,
 * ou c'est le systeme qui dessine le code.
 *
 * C'est aussi ce qui evite de refaire l'erreur du web, ou le code prenait ses
 * couleurs dans le theme et devenait illisible en mode sombre : le rendu
 * appartient au client, la donnee au serveur.
 */

require_once __DIR__ . '/_socle.php';

apiExigerMethode('GET');

$u   = apiEtudiant($pdo);
$uid = (int) $u['id'];

// ── Economies ───────────────────────────────────────────────────────────────

$stmt = $pdo->prepare(
    'SELECT COALESCE(SUM(montant), 0) FROM economies
      WHERE user_id = ? AND MONTH(date_economie) = MONTH(NOW()) AND YEAR(date_economie) = YEAR(NOW())'
);
$stmt->execute([$uid]);
$economiesMois = (float) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    'SELECT COALESCE(SUM(montant), 0) FROM economies
      WHERE user_id = ? AND YEAR(date_economie) = YEAR(NOW())'
);
$stmt->execute([$uid]);
$economiesAnnee = (float) $stmt->fetchColumn();

// ── Pass actifs ─────────────────────────────────────────────────────────────

$stmt = $pdo->prepare("
    SELECT i.id, i.qr_code, i.statut, i.created_at,
           e.id AS evenement_id, e.titre, e.date_heure, e.reduction, e.prix_normal, e.lieu,
           et.nom AS etablissement_nom, et.type AS etab_type,
           CASE WHEN e.id IS NULL THEN 1 ELSE 0 END AS evenement_supprime
      FROM inscriptions i
      LEFT JOIN evenements e     ON e.id = i.evenement_id
      LEFT JOIN etablissements et ON et.id = e.etablissement_id
     WHERE i.user_id = ? AND i.statut = 'inscrit'
       AND (e.date_heure >= NOW() OR e.id IS NULL)
     ORDER BY e.date_heure ASC
");
$stmt->execute([$uid]);
$actifs = $stmt->fetchAll();

// ── Historique ──────────────────────────────────────────────────────────────

$parPage = 10;
$page    = max(1, (int) ($_GET['h'] ?? 1));
$limite  = $parPage * $page;

$stmt = $pdo->prepare("
    SELECT i.id, i.statut, i.created_at,
           e.id AS evenement_id, e.titre, e.date_heure, e.reduction, e.prix_normal,
           et.nom AS etablissement_nom
      FROM inscriptions i
      JOIN evenements e      ON e.id = i.evenement_id
      JOIN etablissements et ON et.id = e.etablissement_id
     WHERE i.user_id = :uid AND (i.statut != 'inscrit' OR e.date_heure < NOW())
     ORDER BY e.date_heure DESC LIMIT :limite
");
// Une ligne de plus que la tranche, pour savoir s'il en reste sans compter
// toute la table — meme mecanique que le web.
$stmt->bindValue(':uid', $uid, PDO::PARAM_INT);
$stmt->bindValue(':limite', $limite + 1, PDO::PARAM_INT);
$stmt->execute();
$historique = $stmt->fetchAll();

$resteHistorique = count($historique) > $limite;
if ($resteHistorique) {
    array_pop($historique);
}

/**
 * Met un pass en forme.
 *
 * @param array<string,mixed> $p
 * @return array<string,mixed>
 */
function apiPass(array $p, bool $avecCode): array
{
    $reduction = $p['reduction'] !== null ? (int) $p['reduction'] : null;
    $prix      = $p['prix_normal'] !== null ? (float) $p['prix_normal'] : null;

    return [
        'id'            => (int) $p['id'],
        'evenement_id'  => $p['evenement_id'] !== null ? (int) $p['evenement_id'] : null,
        'titre'         => (string) ($p['titre'] ?? 'Événement supprimé'),
        'etablissement' => (string) ($p['etablissement_nom'] ?? ''),
        'lieu'          => (string) ($p['lieu'] ?? ''),
        'date_heure'    => $p['date_heure'] !== null ? (string) $p['date_heure'] : null,
        'statut'        => (string) $p['statut'],
        'reduction'     => $reduction,
        'prix_normal'   => $prix,
        // L'economie que ce pass represente, calculee ici : l'application ne
        // doit pas avoir a connaitre la regle commerciale pour afficher un
        // montant, sinon elle finira par en afficher une autre que le web.
        'economie'      => ($reduction !== null && $prix !== null)
            ? round($prix * $reduction / 100, 2)
            : null,
        'evenement_supprime' => (bool) ($p['evenement_supprime'] ?? false),
        // La pastille de la liste « passes actifs » : lave pour les soirées,
        // moutarde pour les restos, comme sur le site.
        'etab_type'     => isset($p['etab_type']) && $p['etab_type'] !== null ? (string) $p['etab_type'] : null,

        // Uniquement sur les pass actifs : un pass passe ou annule n'a plus de
        // code a presenter, et l'envoyer quand meme serait donner un secret
        // qui ne sert plus a rien.
        'code_qr' => $avecCode && !empty($p['qr_code'])
            ? 'linkee:' . (string) $p['qr_code']
            : null,
    ];
}

// Les squads à venir dont l'étudiant est membre : le second carrousel de
// wallet.php (« N squads prévus »).
$stmt = $pdo->prepare("
    SELECT s.id, s.titre, s.type, s.niveau, s.date_heure, s.lieu, u.prenom AS createur_prenom
      FROM squad_membres sm
      JOIN squads s ON s.id = sm.squad_id
      JOIN users u  ON u.id = s.createur_id
     WHERE sm.user_id = ? AND s.date_heure >= NOW()
     ORDER BY s.date_heure ASC
");
$stmt->execute([$uid]);
$mesSquads = $stmt->fetchAll();

apiReponse([
    'success' => true,
    'squads' => array_map(static fn (array $s): array => [
        'id'              => (int) $s['id'],
        'titre'           => (string) $s['titre'],
        'type'            => (string) ($s['type'] ?? ''),
        'niveau'          => (string) ($s['niveau'] ?? ''),
        'date_heure'      => (string) $s['date_heure'],
        'lieu'            => (string) ($s['lieu'] ?? ''),
        'createur_prenom' => (string) $s['createur_prenom'],
    ], $mesSquads),
    'economies' => [
        'mois'  => round($economiesMois, 2),
        'annee' => round($economiesAnnee, 2),
    ],
    'pass_actifs' => array_map(
        static fn (array $p): array => apiPass($p, true),
        $actifs
    ),
    'historique' => array_map(
        static fn (array $p): array => apiPass($p, false),
        $historique
    ),
    'pagination' => [
        'page'       => $page,
        'a_suivre'   => $resteHistorique,
        'cumulative' => true,
    ],
]);
