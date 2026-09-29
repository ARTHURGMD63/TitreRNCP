<?php
/**
 * GET  /api/v1/partenaire_abonnement.php — la formule en cours (s'il y en a
 *      une), les formules souscriptibles et les places fondateur restantes.
 *      Reprend partenaire/abonnement.php, sans le HTML.
 * POST /api/v1/partenaire_abonnement.php {offre} — souscrire ou changer de
 *      formule. Même logique que le site : offre revérifiée contre la liste
 *      ouverte, place fondateur reverrouillée dans la transaction, ligne
 *      « prévu » posée au registre financier si le tarif n'est pas nul.
 *
 * Écran manquant côté application avant cet ajout : un établissement sans
 * formule restait bloqué au tableau de bord (`abonnement_requis`, voir
 * partenaire_dashboard.php) sans aucun moyen d'en choisir une depuis
 * l'application.
 */

require_once __DIR__ . '/_socle.php';
require_once __DIR__ . '/../../includes/crm.php';

$u   = apiPartenaire($pdo);
$uid = (int) $u['id'];

$etab = apiEtablissementDe($pdo, $uid);
if (!$etab) {
    apiErreur('Aucun établissement associé à ce compte.', 404, 'etablissement_absent');
}

function partenaireAbonnementFormules(PDO $pdo): array
{
    $restantes = placesFondateurRestantes($pdo);
    $formules  = [];
    foreach (formulesSouscriptibles($pdo) as $code => $f) {
        $formules[] = [
            'code'                => $code,
            'libelle'             => $f['libelle'],
            'tarif'               => (float) $f['tarif'],
            'note'                => $f['note'],
            'places_restantes'    => $code === 'fondateur' ? $restantes : null,
            'mois_essai'          => $code === 'fondateur' ? 3 : 1,
        ];
    }
    return $formules;
}

function partenaireAbonnementClient(?array $client): ?array
{
    if ($client === null) {
        return null;
    }
    return [
        'offre'          => (string) $client['offre'],
        'offre_libelle'  => crmLibelleOffre((string) $client['offre']),
        'mrr'            => (float) $client['mrr'],
        'statut'         => (string) $client['statut'],
        'essai_jusquau'  => $client['essai_jusqu_au'] !== null ? (string) $client['essai_jusqu_au'] : null,
    ];
}

$methode = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

if ($methode === 'POST') {
    $corps = apiCorps();
    $offre = (string) ($corps['offre'] ?? '');

    $formulesDispo = formulesSouscriptibles($pdo);
    if (!array_key_exists($offre, $formulesDispo)) {
        apiErreur("Cette formule n'est pas disponible.", 422, 'offre_invalide');
    }

    $client   = abonnementEtablissement($pdo, (int) $etab['id']);
    $tarif    = (float) $formulesDispo[$offre]['tarif'];
    $finEssai = finEssaiPourFormule($offre);

    $stmtEmail = $pdo->prepare('SELECT email FROM users WHERE id = ?');
    $stmtEmail->execute([$uid]);
    $emailContact = $stmtEmail->fetchColumn() ?: null;

    try {
        $pdo->beginTransaction();

        // La place fondateur se revérifie à l'intérieur de la transaction, comme
        // sur le site : deux établissements qui valident en même temps la
        // seizième place ne doivent pas passer tous les deux.
        if ($offre === 'fondateur' && !offreFondateurOuverte($pdo)) {
            $pdo->rollBack();
            apiErreur("La dernière place fondateur vient d'être prise. Choisis une autre formule.", 409, 'fondateur_complet');
        }

        if ($client) {
            $pdo->prepare(
                "UPDATE crm_clients SET
                    offre = ?, mrr = ?, statut = 'essai', essai_jusqu_au = ?,
                    signe_le = COALESCE(signe_le, CURDATE()), perdu_le = NULL
                  WHERE id = ?"
            )->execute([$offre, $tarif, $finEssai, $client['id']]);
            $clientId = (int) $client['id'];
        } else {
            $pdo->prepare(
                "INSERT INTO crm_clients
                    (etablissement_id, nom, categorie, ville, adresse,
                     contact_nom, contact_email, statut, offre, mrr, essai_jusqu_au, signe_le)
                 VALUES (?,?,?,?,?,?,?,'essai',?,?,?,CURDATE())"
            )->execute([
                $etab['id'], $etab['nom'], $etab['type'], $etab['ville'], $etab['adresse'] ?? null,
                trim(((string) ($u['prenom'] ?? '')) . ' ' . ((string) ($u['nom'] ?? ''))) ?: null,
                $emailContact,
                $offre, $tarif, $finEssai,
            ]);
            $clientId = (int) $pdo->lastInsertId();
        }

        if ($tarif > 0) {
            $dejaPrevu = $pdo->prepare(
                "SELECT COUNT(*) FROM finance_mouvements
                  WHERE client_id = ? AND sens = 'recette'
                    AND statut = 'prevu' AND date_mouvement = ?"
            );
            $dejaPrevu->execute([$clientId, $finEssai]);

            if (!$dejaPrevu->fetchColumn()) {
                $pdo->prepare(
                    "INSERT INTO finance_mouvements
                        (sens, categorie, client_id, libelle, montant_ht, date_mouvement, statut, note, cree_par)
                     VALUES ('recette','abonnement',?,?,?,?,'prevu',?,?)"
                )->execute([
                    $clientId,
                    'Abonnement ' . crmLibelleOffre($offre) . ' — ' . $etab['nom'],
                    $tarif,
                    $finEssai,
                    "Souscrit depuis l'application le " . date('d/m/Y') . '.',
                    $uid,
                ]);
            }
        }

        $pdo->commit();
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        logErreur('Souscription impossible', $e, ['etablissement' => $etab['id'], 'offre' => $offre]);
        apiErreur('Enregistrement impossible. Réessaie dans un instant.', 500, 'erreur_serveur');
    }

    $client = abonnementEtablissement($pdo, (int) $etab['id']);
    apiReponse([
        'success' => true,
        'message' => 'Formule ' . crmLibelleOffre($offre) . ' enregistrée.',
        'client'  => partenaireAbonnementClient($client),
    ]);
}

apiExigerMethode('GET');

$client = abonnementEtablissement($pdo, (int) $etab['id']);

apiReponse([
    'success'  => true,
    'client'   => partenaireAbonnementClient($client),
    'formules' => partenaireAbonnementFormules($pdo),
]);
