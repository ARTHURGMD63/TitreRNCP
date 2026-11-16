<?php
/**
 * Où range-t-on un compte ? — les familles d'établissements.
 *
 * Trois mondes ne se mélangent pas :
 *   - la sortie : bars, restaurants, boîtes, afterworks, et les BDE qui
 *     organisent des soirées. Leurs événements vont dans le fil Explorer ;
 *   - le sport : clubs et établissements sportifs. Leurs événements vont dans
 *     l'onglet Sport, avec leurs terrains et créneaux ;
 *   - la culture : l'espace Culture est alimenté par la ville, depuis
 *     l'administration. Il n'y a pas de compte partenaire « culture », et
 *     aucun partenaire ne peut y publier.
 *
 * Ce fichier est la seule source de ces règles : le type du compte, les
 * formules qu'il peut prendre et les types d'événements qu'il peut publier
 * en découlent, et chaque point d'entrée les relit ici plutôt que de se fier
 * à ce que l'écran propose.
 */

require_once __DIR__ . '/crm.php';

/** Les types d'établissements, avec leur famille. @return array<string, array{libelle:string, famille:string}> */
function typesEtablissement(): array
{
    return [
        'bar'       => ['libelle' => 'Bar',                 'famille' => 'sortie'],
        'resto'     => ['libelle' => 'Restaurant',          'famille' => 'sortie'],
        'boite'     => ['libelle' => 'Boîte de nuit',       'famille' => 'sortie'],
        'afterwork' => ['libelle' => 'Afterwork',           'famille' => 'sortie'],
        'bde'       => ['libelle' => 'BDE / association',   'famille' => 'sortie'],
        'club'      => ['libelle' => 'Club sportif',        'famille' => 'sport'],
    ];
}

function typeEtablissementValide(?string $type): bool
{
    return $type !== null && array_key_exists($type, typesEtablissement());
}

/** 'sortie' ou 'sport' ; une valeur inconnue est traitée comme la sortie. */
function familleEtablissement(?string $type): string
{
    return typesEtablissement()[(string) $type]['famille'] ?? 'sortie';
}

/**
 * Les formules qu'un type de compte peut prendre.
 *
 *   - un club n'a que la formule Club, et la formule Club n'existe que pour lui ;
 *   - un BDE n'a que la formule BDE, réservée aux BDE et associations ;
 *   - l'Essentiel s'adresse aux bars, restaurants et afterworks ;
 *   - le Premium aux discothèques et groupes, et reste ouvert à un bar qui
 *     veut plus d'événements ;
 *   - le Fondateur, l'offre de lancement, aux établissements de sortie.
 *
 * @return list<string>
 */
function formulesPourType(?string $type): array
{
    return match ($type) {
        'club'      => ['club'],
        'bde'       => ['bde'],
        'boite'     => ['fondateur', 'premium'],
        'bar', 'resto', 'afterwork' => ['fondateur', 'essentiel', 'premium'],
        default     => ['fondateur', 'essentiel', 'premium'],
    };
}

function formuleCompatible(?string $type, string $offre): bool
{
    return in_array($offre, formulesPourType($type), true);
}

/**
 * Le type d'événement qu'un compte publie réellement.
 *
 * Un club publie du sport, un bar un événement de bar : le type n'est pas un
 * choix du formulaire pour eux, il découle du compte. Un BDE, qui n'a pas de
 * lieu à lui, choisit parmi les types de soirée.
 */
function typeEvenementPour(string $typeEtablissement, string $demande): ?string
{
    if ($typeEtablissement === 'club') {
        return 'sport';
    }
    if ($typeEtablissement === 'bde') {
        return in_array($demande, ['bar', 'boite', 'resto', 'afterwork'], true) ? $demande : null;
    }
    return in_array($typeEtablissement, ['bar', 'boite', 'resto', 'afterwork'], true) ? $typeEtablissement : null;
}

/**
 * Le type du compte est-il encore modifiable ?
 *
 * Il se règle à l'inscription et se corrige tant que rien n'a été engagé :
 * dès qu'une formule est choisie ou qu'un achat est inscrit au registre, le
 * type est figé. Sans cela, un bar changerait son type en « club » ou en
 * « BDE » pour prendre une formule moins chère, puis le remettrait.
 */
function typeEtablissementModifiable(PDO $pdo, int $etablissementId): bool
{
    if (abonnementChoisi(abonnementEtablissement($pdo, $etablissementId))) {
        return false;
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM achats_partenaires WHERE etablissement_id = ?');
    $stmt->execute([$etablissementId]);

    return (int) $stmt->fetchColumn() === 0;
}

/**
 * Ce type de compte doit-il être validé par un administrateur ?
 *
 * Seul le BDE : rien ne prouve à l'inscription qu'un « BDE » en est un, et sa
 * formule coûte moins cher que celles des bars.
 */
function verificationRequise(?string $type): bool
{
    return $type === 'bde';
}

/**
 * Le compte est-il en règle côté validation ? Tout type qui n'en demande pas
 * l'est d'office ; un BDE ne l'est qu'une fois validé.
 *
 * @param array<string, mixed> $etab
 */
function compteValide(array $etab): bool
{
    return !verificationRequise((string) ($etab['type'] ?? ''))
        || ($etab['verification'] ?? '') === 'validee';
}

/** L'état de validation à poser pour un type de compte qui vient d'être choisi. */
function verificationInitiale(?string $type): string
{
    return verificationRequise($type) ? 'en_attente' : 'non_requise';
}

/**
 * Le compte a-t-il une formule valable pour son type ?
 *
 * @param array<string, mixed> $etab
 * @return ''|'validation_requise'|'validation_refusee'|'abonnement_requis'|'formule_incompatible' le code d'erreur, ou '' si tout va bien
 */
function etatAbonnement(PDO $pdo, array $etab): string
{
    // Un BDE non validé n'a droit à rien : ni formule, ni publication.
    if (!compteValide($etab)) {
        return ($etab['verification'] ?? '') === 'refusee' ? 'validation_refusee' : 'validation_requise';
    }
    $client = abonnementEtablissement($pdo, (int) $etab['id']);
    if (!abonnementChoisi($client)) {
        return 'abonnement_requis';
    }
    if (!formuleCompatible((string) ($etab['type'] ?? ''), (string) $client['offre'])) {
        return 'formule_incompatible';
    }

    return '';
}

/**
 * Refuse la requête d'API tant que le compte n'a pas de formule valable pour
 * son type. À appeler en tête de chaque point d'entrée partenaire.
 *
 * @param array<string, mixed> $etab
 */
function exigerAbonnementValide(PDO $pdo, array $etab): void
{
    $etat = etatAbonnement($pdo, $etab);
    if ($etat === 'abonnement_requis') {
        apiErreur('Choisis une formule d’abonnement pour continuer.', 403, 'abonnement_requis');
    }
    if ($etat === 'validation_requise') {
        apiErreur('Ton compte BDE est en attente de validation par l’équipe Outsee. Tu pourras choisir ta formule dès qu’il sera validé.', 403, 'validation_requise');
    }
    if ($etat === 'validation_refusee') {
        apiErreur('Ton compte BDE n’a pas été validé. Écris-nous si c’est une erreur.', 403, 'validation_refusee');
    }
    if ($etat === 'formule_incompatible') {
        apiErreur('Ta formule ne correspond pas à ton type de compte. Choisis-en une qui convient.', 403, 'abonnement_requis');
    }
}
