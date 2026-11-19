<?php
/**
 * Suppression d'un événement — une seule règle pour l'application
 * (api/v1/partenaire_evenements.php), l'espace pro du site
 * (partenaire/evenements.php) et l'administration
 * (api/admin_supprimer_evenement.php).
 *
 * La suppression était physique et sans condition (PAY-10, DB-01) : les
 * inscriptions, les avis et les photos partaient en cascade, les étudiants
 * inscrits n'en savaient rien et se présentaient à une soirée effacée de
 * leur portefeuille, et une mise en avant payée était perdue.
 *
 * Choix retenu : refuser la suppression dès que l'événement engage quelqu'un
 * — un pass non annulé (à venir comme passé : l'historique de présence des
 * étudiants et les statistiques de l'établissement en dépendent) ou un
 * paiement encaissé. Un événement sans inscrit ni paiement se supprime comme
 * avant. Plutôt qu'une suppression logique : elle aurait demandé un filtre
 * dans la cinquantaine de requêtes qui lisent `evenements`, et chaque oubli
 * aurait remis un événement « supprimé » sous les yeux des étudiants ; ici,
 * trois points d'écriture changent et aucune lecture.
 *
 * Un événement qui engage quelqu'un s'annule à la place
 * (evenementAnnuler()) : il reste en base, ses pass sont annulés, les
 * inscrits sont prévenus, et il sort du fil (sqlEvenementNonAnnule()).
 */

require_once __DIR__ . '/log.php';
require_once __DIR__ . '/evenements_temps.php';

/** Longueur maximale du motif d'annulation (colonne VARCHAR(255)). */
const EVENEMENT_ANNULATION_MOTIF_MAX = 255;

/**
 * Le motif du refus, ou null si l'événement peut être supprimé.
 *
 * @return array{code:string, message:string}|null
 */
function evenementSuppressionRefus(int $inscritsActifs, int $paiementsPayes): ?array
{
    if ($inscritsActifs > 0) {
        return [
            'code'    => 'inscrits',
            'message' => $inscritsActifs === 1
                ? 'Une personne a un pass pour cet événement : il ne peut plus être supprimé. Annule-le plutôt : elle sera prévenue.'
                : $inscritsActifs . ' personnes ont un pass pour cet événement : il ne peut plus être supprimé. Annule-le plutôt : elles seront prévenues.',
        ];
    }
    if ($paiementsPayes > 0) {
        return [
            'code'    => 'paiement',
            'message' => 'Cet événement a fait l’objet d’un paiement : il ne peut plus être supprimé. Annule-le plutôt.',
        ];
    }

    return null;
}

/**
 * Supprime l'événement $id s'il ne bloque rien (voir plus haut).
 *
 * $etablissementId borne la suppression à un établissement (partenaire) ;
 * null pour l'administrateur. Le contrôle et la suppression se font sous le
 * verrou de la ligne de l'événement, celui que prend api/inscrire.php : une
 * inscription ne peut pas se glisser entre les deux.
 *
 * @return array{ok:bool, code:string, message:string}
 */
function evenementSupprimer(PDO $pdo, int $id, ?int $etablissementId = null): array
{
    // Notre propre indicateur plutôt que inTransaction(), peu fiable derrière
    // la passerelle TiDB (voir api/v1/inscription.php).
    $enTransaction = false;
    try {
        $pdo->beginTransaction();
        $enTransaction = true;

        // SQLite (tests) ne connaît pas FOR UPDATE : il verrouille toute la
        // base à l'écriture.
        $verrou = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
        $sql    = 'SELECT id FROM evenements WHERE id = ?' . ($etablissementId !== null ? ' AND etablissement_id = ?' : '') . $verrou;
        $params = $etablissementId !== null ? [$id, $etablissementId] : [$id];
        $stmt   = $pdo->prepare($sql);
        $stmt->execute($params);
        if ($stmt->fetchColumn() === false) {
            $pdo->rollBack();

            return ['ok' => false, 'code' => 'introuvable', 'message' => 'Cet événement n’existe plus.'];
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscriptions WHERE evenement_id = ? AND statut <> 'annule'");
        $stmt->execute([$id]);
        $inscrits = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM paiements_evenements WHERE evenement_id = ? AND statut = 'paye'");
        $stmt->execute([$id]);
        $payes = (int) $stmt->fetchColumn();

        $refus = evenementSuppressionRefus($inscrits, $payes);
        if ($refus !== null) {
            $pdo->rollBack();

            return ['ok' => false] + $refus;
        }

        $pdo->prepare('DELETE FROM evenements WHERE id = ?')->execute([$id]);
        $pdo->commit();
        $enTransaction = false;
    } catch (PDOException $e) {
        if ($enTransaction) {
            try {
                $pdo->rollBack();
            } catch (PDOException) {
                // Déjà annulée côté serveur.
            }
        }
        logErreur('Suppression d’événement impossible', $e, ['evenement' => $id, 'etablissement' => $etablissementId]);

        return ['ok' => false, 'code' => 'erreur_serveur', 'message' => 'Suppression impossible. Réessaie dans un instant.'];
    }

    return ['ok' => true, 'code' => 'supprime', 'message' => 'Événement supprimé.'];
}

/**
 * Condition SQL : l'événement n'est pas annulé. À ajouter partout où un
 * étudiant découvre un événement ou peut s'y inscrire.
 */
function sqlEvenementNonAnnule(string $alias = 'e'): string
{
    return "$alias.annule_le IS NULL";
}

/**
 * Le motif nettoyé (espaces, longueur), ou null s'il est vide.
 */
function evenementMotifAnnulation(?string $motif): ?string
{
    $motif = trim(preg_replace('/\s+/u', ' ', (string) $motif) ?? '');
    if ($motif === '') {
        return null;
    }

    return mb_substr($motif, 0, EVENEMENT_ANNULATION_MOTIF_MAX);
}

/**
 * Annule l'événement $id : il reste en base pour l'historique et les
 * statistiques, mais sort du fil, n'accepte plus d'inscription, et chaque
 * pass encore valable (statut « inscrit ») est annulé. Les entrées déjà
 * scannées (« checkin ») restent : elles ont eu lieu.
 *
 * $etablissementId borne l'annulation à un établissement (partenaire), qui
 * ne peut annuler qu'un événement pas encore terminé ; null pour
 * l'administrateur, qui peut aussi retirer un événement passé (contenu
 * illicite). Contrôle et écriture se font sous le verrou de la ligne de
 * l'événement, celui que prend api/inscrire.php : aucune inscription ne se
 * glisse entre la lecture des pass et leur annulation.
 *
 * Les inscrits sont prévenus après la validation, en catégorie « compte »
 * (non désactivable) : un pass annulé ne doit pas passer inaperçu parce
 * qu'on a coupé les rappels.
 *
 * @return array{ok:bool, code:string, message:string, pass_annules?:int}
 */
function evenementAnnuler(PDO $pdo, int $id, ?int $etablissementId, int $auteurId, ?string $motif = null): array
{
    $motif         = evenementMotifAnnulation($motif);
    $enTransaction = false;
    try {
        $pdo->beginTransaction();
        $enTransaction = true;

        $verrou = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite' ? '' : ' FOR UPDATE';
        $sql    = 'SELECT id, titre, date_heure, date_fin, annule_le FROM evenements WHERE id = ?'
                . ($etablissementId !== null ? ' AND etablissement_id = ?' : '') . $verrou;
        $stmt   = $pdo->prepare($sql);
        $stmt->execute($etablissementId !== null ? [$id, $etablissementId] : [$id]);
        $ev = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($ev === false) {
            $pdo->rollBack();

            return ['ok' => false, 'code' => 'introuvable', 'message' => 'Cet événement n’existe plus.'];
        }
        if ($ev['annule_le'] !== null) {
            $pdo->rollBack();

            return ['ok' => false, 'code' => 'deja_annule', 'message' => 'Cet événement est déjà annulé.'];
        }
        $dateFin = $ev['date_fin'] !== null ? (string) $ev['date_fin'] : null;
        if ($etablissementId !== null && evenementTermine((string) $ev['date_heure'], $dateFin)) {
            $pdo->rollBack();

            return ['ok' => false, 'code' => 'termine', 'message' => 'Cet événement est terminé : il ne peut plus être annulé.'];
        }

        $stmt = $pdo->prepare("SELECT user_id FROM inscriptions WHERE evenement_id = ? AND statut = 'inscrit'");
        $stmt->execute([$id]);
        $inscrits = array_values(array_unique(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN))));

        $pdo->prepare("UPDATE inscriptions SET statut = 'annule' WHERE evenement_id = ? AND statut = 'inscrit'")
            ->execute([$id]);
        $pdo->prepare('UPDATE evenements SET annule_le = ?, annule_motif = ?, annule_par = ? WHERE id = ?')
            ->execute([date('Y-m-d H:i:s'), $motif, $auteurId > 0 ? $auteurId : null, $id]);

        $pdo->commit();
        $enTransaction = false;
    } catch (PDOException $e) {
        if ($enTransaction) {
            try {
                $pdo->rollBack();
            } catch (PDOException) {
                // Déjà annulée côté serveur.
            }
        }
        logErreur('Annulation d’événement impossible', $e, ['evenement' => $id, 'etablissement' => $etablissementId]);

        return ['ok' => false, 'code' => 'erreur_serveur', 'message' => 'Annulation impossible. Réessaie dans un instant.'];
    }

    // pushNotifier() ne lève jamais : l'annulation est faite, une
    // notification qui échoue ne doit pas la défaire.
    if ($inscrits !== []) {
        require_once __DIR__ . '/push.php';
        [$titre, $corps] = pushTexteEvenementAnnuleParOrganisateur((string) $ev['titre'], $motif);
        foreach ($inscrits as $uid) {
            pushNotifier($pdo, $uid, 'compte', 'annulation:' . $id, $titre, $corps, ['url' => '/evenement/' . $id]);
        }
    }

    $n = count($inscrits);

    return [
        'ok'           => true,
        'code'         => 'annule',
        'message'      => match (true) {
            $n === 0 => 'Événement annulé.',
            $n === 1 => 'Événement annulé. La personne inscrite est prévenue.',
            default  => "Événement annulé. Les $n personnes inscrites sont prévenues.",
        },
        'pass_annules' => $n,
    ];
}
