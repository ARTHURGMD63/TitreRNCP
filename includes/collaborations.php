<?php
/**
 * Les événements en collaboration (migration v39).
 *
 * Un établissement qui publie un événement peut inviter un autre
 * établissement — un bar, un club, un BDE — à le co-organiser. L'invité
 * accepte ou refuse depuis son espace ; tant qu'il n'a pas accepté, les
 * étudiants ne voient rien. Acceptée, la collaboration s'affiche sur la carte
 * et la fiche de l'événement (« avec … »).
 *
 * L'événement reste celui de l'hôte : quotas, mise en avant, inscrits et scan
 * ne changent pas de main. Un seul collaborateur par événement pour l'instant
 * — la table en accepterait plusieurs, l'interface n'en propose qu'un.
 */

/** Les statuts d'une collaboration, tels qu'en base. */
const COLLABORATION_EN_ATTENTE = 'en_attente';
const COLLABORATION_ACCEPTEE   = 'acceptee';
const COLLABORATION_REFUSEE    = 'refusee';

/**
 * Condition SQL d'un établissement qu'on peut inviter : tout le monde sauf un
 * BDE que l'administrateur n'a pas (encore) validé — rien ne prouve qu'il en
 * soit un, et son nom s'afficherait sur la carte d'un autre.
 */
function collaborationConditionInvitable(string $alias = 'et'): string
{
    return "$alias.verification IN ('non_requise','validee')";
}

/**
 * Les établissements qu'un hôte peut inviter, filtrés par nom ou ville.
 *
 * @return list<array{id:int, nom:string, type:string, ville:string}>
 */
function collaborationRechercher(PDO $pdo, int $hoteId, string $q, int $limite = 15): array
{
    $sql = 'SELECT et.id, et.nom, et.type, et.ville
              FROM etablissements et
             WHERE et.id <> ? AND ' . collaborationConditionInvitable();
    $params = [$hoteId];
    $q = trim($q);
    if ($q !== '') {
        $sql .= ' AND (et.nom LIKE ? OR et.ville LIKE ?)';
        $terme = '%' . addcslashes($q, '%_\\') . '%';
        array_push($params, $terme, $terme);
    }
    $sql .= ' ORDER BY et.nom LIMIT ' . max(1, min(50, $limite));
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return array_map(
        static fn (array $r): array => [
            'id'    => (int) $r['id'],
            'nom'   => (string) $r['nom'],
            'type'  => (string) $r['type'],
            'ville' => (string) ($r['ville'] ?? ''),
        ],
        $stmt->fetchAll()
    );
}

/**
 * Règle le collaborateur d'un événement de l'hôte.
 *
 * `$inviteId` à 0 retire toute collaboration. Un autre établissement que
 * l'actuel remplace l'invitation précédente (acceptée ou non) par une
 * nouvelle, en attente. Le même établissement ne bouge pas : une invitation
 * refusée ne se renvoie pas en réenregistrant le formulaire.
 *
 * À appeler dans la transaction qui écrit l'événement : l'appartenance de
 * l'événement à l'hôte est vérifiée par l'appelant.
 *
 * @return string|null le message d'erreur, null si tout va bien
 */
function collaborationRegler(PDO $pdo, int $evenementId, int $hoteId, int $inviteId): ?string
{
    if ($inviteId === 0) {
        $pdo->prepare('DELETE FROM evenement_collaborations WHERE evenement_id = ?')->execute([$evenementId]);

        return null;
    }
    if ($inviteId === $hoteId) {
        return 'Tu ne peux pas t’inviter toi-même à collaborer.';
    }

    $stmt = $pdo->prepare('SELECT et.id FROM etablissements et WHERE et.id = ? AND ' . collaborationConditionInvitable());
    $stmt->execute([$inviteId]);
    if (!$stmt->fetchColumn()) {
        return 'Cet établissement ne peut pas être invité à collaborer.';
    }

    $pdo->prepare('DELETE FROM evenement_collaborations WHERE evenement_id = ? AND etablissement_id <> ?')
        ->execute([$evenementId, $inviteId]);
    $pdo->prepare(
        'INSERT IGNORE INTO evenement_collaborations (evenement_id, etablissement_id, statut, invite_le)
         VALUES (?, ?, ?, ?)'
    )->execute([$evenementId, $inviteId, COLLABORATION_EN_ATTENTE, date('Y-m-d H:i:s')]);

    return null;
}

/**
 * Les collaborations de plusieurs événements, par événement.
 *
 * @param list<int> $evenementIds
 * @return array<int, list<array{id:int, nom:string, type:string, statut:string}>>
 */
function collaborationsDe(PDO $pdo, array $evenementIds, bool $accepteesSeules = true): array
{
    $evenementIds = array_values(array_unique(array_map('intval', $evenementIds)));
    if (!$evenementIds) {
        return [];
    }
    $marques = implode(',', array_fill(0, count($evenementIds), '?'));
    $sql = "SELECT c.evenement_id, c.statut, et.id, et.nom, et.type
              FROM evenement_collaborations c
              JOIN etablissements et ON et.id = c.etablissement_id
             WHERE c.evenement_id IN ($marques)";
    if ($accepteesSeules) {
        $sql .= " AND c.statut = '" . COLLABORATION_ACCEPTEE . "'";
    }
    $sql .= ' ORDER BY c.invite_le, c.id';

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($evenementIds);
        $lignes = $stmt->fetchAll();
    } catch (PDOException) {
        // Base pas encore migrée en v39 : pas de collaboration, rien ne casse.
        return [];
    }

    $parEvenement = [];
    foreach ($lignes as $r) {
        $parEvenement[(int) $r['evenement_id']][] = [
            'id'     => (int) $r['id'],
            'nom'    => (string) $r['nom'],
            'type'   => (string) $r['type'],
            'statut' => (string) $r['statut'],
        ];
    }

    return $parEvenement;
}

/**
 * Les collaborateurs acceptés d'un événement, au format des API étudiantes.
 *
 * @param array<int, list<array<string, mixed>>> $parEvenement le résultat de collaborationsDe()
 * @return list<array{id:int, nom:string, type:string}>
 */
function collaborationsPourApi(array $parEvenement, int $evenementId): array
{
    return array_map(
        static fn (array $c): array => ['id' => $c['id'], 'nom' => $c['nom'], 'type' => $c['type']],
        array_values(array_filter(
            $parEvenement[$evenementId] ?? [],
            static fn (array $c): bool => $c['statut'] === COLLABORATION_ACCEPTEE
        ))
    );
}

/**
 * Les invitations reçues par un établissement : celles qui attendent une
 * réponse, puis les collaborations acceptées d'événements pas encore passés.
 *
 * @return list<array<string, mixed>>
 */
function collaborationInvitationsRecues(PDO $pdo, int $etabId): array
{
    $stmt = $pdo->prepare(
        "SELECT c.id, c.statut, c.invite_le, e.id AS evenement_id, e.titre, e.date_heure, e.date_fin, e.lieu,
                h.id AS hote_id, h.nom AS hote_nom, h.type AS hote_type
           FROM evenement_collaborations c
           JOIN evenements e ON e.id = c.evenement_id
           JOIN etablissements h ON h.id = e.etablissement_id
          WHERE c.etablissement_id = ?
            AND c.statut IN ('" . COLLABORATION_EN_ATTENTE . "', '" . COLLABORATION_ACCEPTEE . "')
            AND COALESCE(e.date_fin, e.date_heure) >= ?
          ORDER BY c.statut = '" . COLLABORATION_EN_ATTENTE . "' DESC, e.date_heure"
    );
    $stmt->execute([$etabId, date('Y-m-d H:i:s', time() - 6 * 3600)]);

    return array_map(
        static fn (array $r): array => [
            'id'         => (int) $r['id'],
            'statut'     => (string) $r['statut'],
            'invite_le'  => (string) $r['invite_le'],
            'evenement'  => [
                'id'         => (int) $r['evenement_id'],
                'titre'      => (string) $r['titre'],
                'date_heure' => (string) $r['date_heure'],
                'date_fin'   => $r['date_fin'] !== null ? (string) $r['date_fin'] : null,
                'lieu'       => (string) ($r['lieu'] ?? ''),
            ],
            'hote' => [
                'id'   => (int) $r['hote_id'],
                'nom'  => (string) $r['hote_nom'],
                'type' => (string) $r['hote_type'],
            ],
        ],
        $stmt->fetchAll()
    );
}

/**
 * L'invité répond : `accepter`, `refuser`, ou `quitter` une collaboration
 * déjà acceptée (elle passe alors en refusée, l'hôte le voit).
 *
 * @return string|null le message d'erreur, null si tout va bien
 */
function collaborationRepondre(PDO $pdo, int $etabId, int $collaborationId, string $action): ?string
{
    $depuis = match ($action) {
        'accepter' => [COLLABORATION_EN_ATTENTE],
        'refuser'  => [COLLABORATION_EN_ATTENTE],
        'quitter'  => [COLLABORATION_ACCEPTEE],
        default    => null,
    };
    if ($depuis === null) {
        return 'Action inconnue.';
    }
    $vers = $action === 'accepter' ? COLLABORATION_ACCEPTEE : COLLABORATION_REFUSEE;

    $marques = implode(',', array_fill(0, count($depuis), '?'));
    $stmt = $pdo->prepare(
        "UPDATE evenement_collaborations SET statut = ?, repondu_le = ?
          WHERE id = ? AND etablissement_id = ? AND statut IN ($marques)"
    );
    $stmt->execute(array_merge([$vers, date('Y-m-d H:i:s'), $collaborationId, $etabId], $depuis));

    return $stmt->rowCount() > 0 ? null : 'Cette invitation n’est plus valable.';
}
