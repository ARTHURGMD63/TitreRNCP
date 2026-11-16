<?php
/**
 * Souscrire ou changer de formule — une seule écriture pour l'application et
 * pour le site, pour que les garde-fous ne dépendent pas de l'écran utilisé.
 *
 *   - la formule est revérifiée contre le type du compte et contre la liste
 *     ouverte (une requête forgée ne prend ni la formule Club sur un bar, ni
 *     la BDE sur un restaurant, ni une place fondateur fermée) ;
 *   - redemander la formule déjà en cours ne change rien : la période offerte
 *     ne se relance pas ;
 *   - la période offerte ne s'accorde qu'à la première souscription. Un
 *     établissement déjà signé qui change de formule garde sa date d'essai ;
 *   - une formule se change au plus une fois par mois ;
 *   - le fondateur est réservé à la première souscription : en repartir ne
 *     redonne ni les trois mois offerts, ni le tarif gelé.
 */

require_once __DIR__ . '/crm.php';
require_once __DIR__ . '/comptes.php';

/**
 * @param array<string, mixed> $etab
 * @param array<string, mixed> $utilisateur id, prenom, nom
 * @return array{ok:bool, code:string, message:string}
 */
function souscrireFormule(PDO $pdo, array $etab, array $utilisateur, string $offre, ?string $emailContact = null): array
{
    if (!compteValide($etab)) {
        return ['ok' => false, 'code' => 'validation_requise', 'message' => 'Ton compte BDE doit d’abord être validé par l’équipe Outsee.'];
    }

    $client = abonnementEtablissement($pdo, (int) $etab['id']);
    $dispo  = formulesSouscriptibles($pdo, (string) ($etab['type'] ?? ''), $client);

    if (!array_key_exists($offre, $dispo)) {
        return ['ok' => false, 'code' => 'offre_invalide', 'message' => "Cette formule n'est pas disponible."];
    }

    $dejaChoisie = abonnementChoisi($client);
    if ($dejaChoisie && (string) $client['offre'] === $offre) {
        return ['ok' => true, 'code' => 'inchangee', 'message' => 'Formule ' . crmLibelleOffre($offre) . ' déjà en cours.'];
    }
    if ($dejaChoisie && substr((string) ($client['offre_changee_le'] ?? ''), 0, 7) === date('Y-m')) {
        $prochain = date('d/m/Y', (int) strtotime('first day of next month'));
        return ['ok' => false, 'code' => 'changement_limite', 'message' => "Tu as déjà changé de formule ce mois-ci. Prochain changement possible à partir du $prochain."];
    }

    $tarif    = (float) $dispo[$offre]['tarif'];
    $premiere = $client === null || empty($client['signe_le']);
    $finEssai = $premiere ? finEssaiPourFormule($offre) : ($client['essai_jusqu_au'] ?? null);
    $statut   = $premiere
        ? 'essai'
        : (in_array((string) $client['statut'], ['essai', 'actif'], true) ? (string) $client['statut'] : 'actif');

    try {
        $pdo->beginTransaction();
        $pdo->prepare('SELECT id FROM etablissements WHERE id = ? FOR UPDATE')->execute([(int) $etab['id']]);

        // La place fondateur se revérifie à l'intérieur de la transaction :
        // deux établissements qui valident en même temps la seizième place ne
        // doivent pas passer tous les deux.
        if ($offre === 'fondateur' && !offreFondateurOuverte($pdo)) {
            $pdo->rollBack();
            return ['ok' => false, 'code' => 'fondateur_complet', 'message' => "La dernière place fondateur vient d'être prise. Choisis une autre formule."];
        }

        if ($client) {
            $pdo->prepare(
                "UPDATE crm_clients SET
                    offre = ?, mrr = ?, statut = ?, essai_jusqu_au = ?,
                    signe_le = COALESCE(signe_le, CURDATE()), perdu_le = NULL, offre_changee_le = CURDATE()
                  WHERE id = ?"
            )->execute([$offre, $tarif, $statut, $finEssai, $client['id']]);
            $clientId = (int) $client['id'];
        } else {
            $pdo->prepare(
                "INSERT INTO crm_clients
                    (etablissement_id, nom, categorie, ville, adresse,
                     contact_nom, contact_email, statut, offre, mrr, essai_jusqu_au, signe_le, offre_changee_le)
                 VALUES (?,?,?,?,?,?,?,'essai',?,?,?,CURDATE(),CURDATE())"
            )->execute([
                $etab['id'], $etab['nom'], $etab['type'], $etab['ville'], $etab['adresse'] ?? null,
                trim(((string) ($utilisateur['prenom'] ?? '')) . ' ' . ((string) ($utilisateur['nom'] ?? ''))) ?: null,
                $emailContact,
                $offre, $tarif, $finEssai,
            ]);
            $clientId = (int) $pdo->lastInsertId();
        }

        // La première échéance entre au registre en « prévu » : seulement à la
        // première souscription, puisque c'est là que commence la période
        // offerte dont elle marque la fin.
        if ($premiere && $tarif > 0 && $finEssai !== null) {
            $dejaPrevu = $pdo->prepare(
                "SELECT COUNT(*) FROM finance_mouvements
                  WHERE client_id = ? AND sens = 'recette' AND statut = 'prevu' AND date_mouvement = ?"
            );
            $dejaPrevu->execute([$clientId, $finEssai]);
            if (!$dejaPrevu->fetchColumn()) {
                $pdo->prepare(
                    "INSERT INTO finance_mouvements
                        (sens, categorie, client_id, libelle, montant_ht, date_mouvement, statut, note, cree_par)
                     VALUES ('recette','abonnement',?,?,?,?,'prevu',?,?)"
                )->execute([
                    $clientId, 'Abonnement ' . crmLibelleOffre($offre) . ' — ' . $etab['nom'], $tarif, $finEssai,
                    'Souscrit le ' . date('d/m/Y') . '.', (int) ($utilisateur['id'] ?? 0) ?: null,
                ]);
            }
        }

        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logErreur('Souscription impossible', $e, ['etablissement' => $etab['id'], 'offre' => $offre]);
        return ['ok' => false, 'code' => 'erreur_serveur', 'message' => 'Enregistrement impossible. Réessaie dans un instant.'];
    }

    return ['ok' => true, 'code' => $premiere ? 'souscrite' : 'changee', 'message' => 'Formule ' . crmLibelleOffre($offre) . ' enregistrée.'];
}
