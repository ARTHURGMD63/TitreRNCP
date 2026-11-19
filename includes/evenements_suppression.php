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
 */

require_once __DIR__ . '/log.php';

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
                ? 'Une personne a un pass pour cet événement : il ne peut plus être supprimé. Modifie-le, ou écris-nous pour l’annuler.'
                : $inscritsActifs . ' personnes ont un pass pour cet événement : il ne peut plus être supprimé. Modifie-le, ou écris-nous pour l’annuler.',
        ];
    }
    if ($paiementsPayes > 0) {
        return [
            'code'    => 'paiement',
            'message' => 'Cet événement a fait l’objet d’un paiement : il ne peut plus être supprimé. Écris-nous pour l’annuler.',
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
