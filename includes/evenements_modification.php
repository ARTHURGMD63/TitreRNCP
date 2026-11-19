<?php
/**
 * Modification d'un événement déjà en ligne — règles communes à
 * l'application (api/v1/partenaire_evenement.php) et à l'espace pro du site
 * (includes/evenement_pro.php, partenaire/edit_event.php).
 *
 * Un événement publié se modifie, inscrits ou pas. Trois garde-fous :
 *  - un événement terminé ne se modifie plus : le redater dans le futur le
 *    republiait sans passer par le quota du mois ;
 *  - la fin ne peut pas tomber dans le passé : les pass des inscrits
 *    disparaîtraient du portefeuille, sans annulation ni message ;
 *  - le nombre de places ne descend pas sous le nombre de pass déjà pris
 *    (l'application le refusait, le site non).
 * Et quand la date, l'heure ou le lieu changent, les inscrits sont prévenus.
 */

require_once __DIR__ . '/evenements_temps.php';
require_once __DIR__ . '/log.php';

/**
 * Les raisons de refuser la modification (vide : acceptée).
 *
 * @param array<string, mixed> $existant l'événement tel qu'il est en base
 * @return list<string>
 */
function evenementModificationRefus(array $existant, string $dateHeure, ?string $dateFin, int $quota, int $passPris): array
{
    $erreurs = [];
    $finAvant = $existant['date_fin'] ?? null;
    if (evenementTermine((string) $existant['date_heure'], $finAvant !== null ? (string) $finAvant : null)) {
        // Rien d'autre à dire : le reste du formulaire n'a plus d'objet.
        return ['Cet événement est terminé : il ne se modifie plus. Publie un nouvel événement.'];
    }
    if ($dateHeure !== '' && strtotime($dateHeure) !== false) {
        $fin = evenementFinEffective($dateHeure, $dateFin);
        if ($fin !== null && $fin <= time()) {
            $erreurs[] = 'Avec ces horaires, l’événement serait déjà terminé : choisis une date à venir.';
        }
    }
    if ($quota < $passPris) {
        $erreurs[] = $passPris === 1
            ? 'Une personne est déjà inscrite : il faut au moins 1 place.'
            : "Il y a déjà $passPris inscrits : le nombre de places ne peut pas descendre en dessous.";
    }

    return $erreurs;
}

/** Pass encore valables ou déjà scannés : les places réellement prises. */
function evenementPassPris(PDO $pdo, int $evenementId): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM inscriptions WHERE evenement_id = ? AND statut <> 'annule'");
    $stmt->execute([$evenementId]);

    return (int) $stmt->fetchColumn();
}

/** « sam. 28 nov. à 20h00 », sans dépendre des fonctions du site. */
function evenementQuandCourt(string $horodatage): string
{
    $t     = (int) strtotime($horodatage);
    $jours = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];
    $mois  = ['janv.', 'févr.', 'mars', 'avr.', 'mai', 'juin', 'juil.', 'août', 'sept.', 'oct.', 'nov.', 'déc.'];

    return $jours[(int) date('w', $t)] . ' ' . date('j', $t) . ' ' . $mois[(int) date('n', $t) - 1] . ' à ' . date('G\hi', $t);
}

/**
 * Ce qui a changé et que les inscrits doivent savoir : l'heure de début
 * (à la minute près) et le lieu. Le titre, la description, les places ne
 * changent rien à leur venue.
 *
 * @param array<string, mixed> $avant
 * @param array<string, mixed> $apres titre, date_heure, lieu
 * @return list<string> des phrases prêtes à envoyer
 */
function evenementChangementsPourInscrits(array $avant, array $apres): array
{
    $changements = [];
    $minute = static fn ($h): string => $h ? date('Y-m-d H:i', (int) strtotime((string) $h)) : '';
    if ($minute($avant['date_heure'] ?? '') !== $minute($apres['date_heure'] ?? '')) {
        $changements[] = 'Nouvelle date : ' . evenementQuandCourt((string) $apres['date_heure']) . '.';
    }
    $lieu = static fn ($l): string => mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $l) ?? ''));
    if ($lieu($avant['lieu'] ?? '') !== $lieu($apres['lieu'] ?? '') && trim((string) ($apres['lieu'] ?? '')) !== '') {
        $changements[] = 'Nouveau lieu : ' . trim((string) $apres['lieu']) . '.';
    }

    return $changements;
}

/**
 * Prévient chaque inscrit (pass valable) d'un changement de date ou de lieu,
 * en catégorie « compte » : on ne laisse pas quelqu'un venir au mauvais
 * endroit parce qu'il a coupé les rappels. La clé contient les nouveaux
 * horaires et lieu : deux enregistrements identiques n'envoient qu'un message.
 * Ne lève jamais : la modification est déjà enregistrée.
 *
 * @param list<string> $changements
 */
function evenementPrevenirInscrits(PDO $pdo, int $evenementId, string $titre, array $changements): int
{
    if ($changements === []) {
        return 0;
    }
    try {
        require_once __DIR__ . '/push.php';
        $stmt = $pdo->prepare("SELECT DISTINCT user_id FROM inscriptions WHERE evenement_id = ? AND statut = 'inscrit'");
        $stmt->execute([$evenementId]);
        $inscrits = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $cle      = 'modification:' . $evenementId . ':' . substr(hash('sha256', implode('|', $changements)), 0, 16);
        foreach ($inscrits as $uid) {
            pushNotifier($pdo, $uid, 'compte', $cle, 'Changement pour ' . $titre, implode(' ', $changements), ['url' => '/evenement/' . $evenementId]);
        }

        return count($inscrits);
    } catch (Throwable $e) {
        logErreur('Inscrits non prévenus d’une modification', $e, ['evenement' => $evenementId]);

        return 0;
    }
}
