<?php
/**
 * L'espace Culture — les événements mis en avant par la ville.
 *
 * Même découpage que le hub (includes/hub.php) : les critères et la
 * validation de saisie sont du calcul pur, testable sans base
 * (tests/Unit/CultureTest.php) ; les lectures et écritures rendent des
 * tableaux complets, que les points d'API mettent en forme.
 *
 * Ce qui diffère des soirées, et pourquoi :
 *   • pas d'établissement : l'organisateur (la ville, un musée) en tient lieu ;
 *   • pas de pass ni de QR : s'inscrire dit « j'y vais » et réserve une place
 *     quand il y en a un nombre limité ;
 *   • une durée réelle : une exposition reste « en cours » jusqu'à sa date de
 *     fin, là où une soirée se termine quelques heures après son début.
 */

require_once __DIR__ . '/places.php';

/** Le catalogue des catégories : la colonne ENUM de la migration v29, dans le même ordre. */
const CULTURE_CATEGORIES = [
    'expo'       => 'Expo',
    'concert'    => 'Concert',
    'spectacle'  => 'Spectacle',
    'festival'   => 'Festival',
    'patrimoine' => 'Patrimoine',
    'cinema'     => 'Cinéma',
    'atelier'    => 'Atelier',
    'conference' => 'Conférence',
    'autre'      => 'Autre',
];

/** Événements affichés par palier de « voir plus ». */
const CULTURE_PAR_PAGE = 20;

/** Durée prêtée à un événement sans date de fin. */
const CULTURE_DUREE_DEFAUT_HEURES = 3;

/**
 * L'instant où un événement se termine, en SQL.
 *
 * Une seule définition, partagée par le fil, la fiche, l'inscription et
 * l'invitation : sinon une exposition pourrait s'afficher « en cours » et
 * refuser l'inscription au même moment.
 */
function cultureSqlFin(string $alias = 'c'): string
{
    return "COALESCE($alias.date_fin, $alias.date_heure + INTERVAL " . CULTURE_DUREE_DEFAUT_HEURES . " HOUR)";
}

/**
 * Traduit les paramètres d'URL en critères validés.
 *
 * Une catégorie inconnue ne filtre rien plutôt que de rendre une liste vide
 * qu'on prendrait pour « rien de prévu ».
 *
 * @param array<string,mixed> $get
 * @return array{categorie:string, page:int}
 */
function cultureCriteres(array $get): array
{
    $categorie = is_string($get['categorie'] ?? null) ? $get['categorie'] : '';

    return [
        'categorie' => isset(CULTURE_CATEGORIES[$categorie]) ? $categorie : '',
        'page'      => max(1, (int) ($get['pe'] ?? 1)),
    ];
}

/**
 * Valide la saisie de l'administrateur.
 *
 * Calcul pur : rend les valeurs prêtes pour la base, ou la liste de ce qui ne
 * va pas — dite comme le formulaire l'affichera.
 *
 * @param array<string,mixed> $d le corps JSON reçu
 * @return array{0: array<string,mixed>, 1: list<string>}
 */
function cultureValiderSaisie(array $d): array
{
    $erreurs = [];
    $texte   = static fn (string $cle, int $max): string => mb_substr(trim(is_scalar($d[$cle] ?? null) ? (string) $d[$cle] : ''), 0, $max);

    $titre        = $texte('titre', 255);
    $description  = $texte('description', 5000);
    $organisateur = $texte('organisateur', 150);
    $lieu         = $texte('lieu', 255);
    $ville        = $texte('ville', 100);
    $lien         = $texte('lien', 500);
    $categorie    = is_string($d['categorie'] ?? null) ? $d['categorie'] : '';

    if ($titre === '') {
        $erreurs[] = 'Le titre est obligatoire.';
    }
    if ($lieu === '') {
        $erreurs[] = 'Le lieu est obligatoire.';
    }
    if (!isset(CULTURE_CATEGORIES[$categorie])) {
        $erreurs[] = 'Choisis une catégorie.';
    }

    $debut = cultureLireDate($d['date_heure'] ?? null);
    $fin   = cultureLireDate($d['date_fin'] ?? null);
    if ($debut === null) {
        $erreurs[] = 'La date de début est invalide.';
    }
    if (($d['date_fin'] ?? '') !== '' && ($d['date_fin'] ?? null) !== null && $fin === null) {
        $erreurs[] = 'La date de fin est invalide.';
    }
    if ($debut !== null && $fin !== null && $fin <= $debut) {
        $erreurs[] = 'La fin doit venir après le début.';
    }

    $quota = (int) ($d['quota'] ?? 0);
    if ($quota < 0 || $quota > 100000) {
        $erreurs[] = 'Le nombre de places doit être compris entre 0 (entrée libre) et 100 000.';
    }
    $prix = round((float) ($d['prix'] ?? 0), 2);
    if ($prix < 0 || $prix > 10000) {
        $erreurs[] = 'Le prix est invalide.';
    }
    // Un lien qu'on ouvre depuis l'application : http(s) seulement, pour
    // qu'aucun « javascript: » ni schéma d'application ne s'y glisse.
    if ($lien !== '' && !preg_match('#^https?://#i', $lien)) {
        $erreurs[] = 'Le lien doit commencer par https://.';
    }

    return [[
        'titre'        => $titre,
        'description'  => $description,
        'categorie'    => $categorie,
        'organisateur' => $organisateur !== '' ? $organisateur : 'Ville de Clermont-Ferrand',
        'lieu'         => $lieu,
        'ville'        => $ville !== '' ? $ville : 'Clermont-Ferrand',
        'date_heure'   => $debut?->format('Y-m-d H:i:s'),
        'date_fin'     => $fin?->format('Y-m-d H:i:s'),
        'quota'        => max(0, $quota),
        'prix'         => max(0, $prix),
        'lien'         => $lien !== '' ? $lien : null,
        'mis_en_avant' => !empty($d['mis_en_avant']) ? 1 : 0,
    ], $erreurs];
}

/** « 2026-11-20 19:30 », « 2026-11-20T19:30:00 » → date, ou null. */
function cultureLireDate(mixed $valeur): ?DateTimeImmutable
{
    if (!is_string($valeur) || trim($valeur) === '') {
        return null;
    }
    $valeur = str_replace('T', ' ', trim($valeur));
    foreach (['Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
        $d = DateTimeImmutable::createFromFormat('!' . $format, $valeur);
        if ($d !== false && $d->format($format) === $valeur) {
            return $d;
        }
    }

    return null;
}

/**
 * Qui, parmi les personnes suivies, va à quel événement culturel.
 *
 * Une requête groupée pour toute la liste, comme hubAmisParEvenement().
 *
 * @return array<int,array{prenoms:list<string>,nb:int}>
 */
function cultureAmisParEvenement(PDO $pdo, int $uid): array
{
    $stmt = $pdo->prepare("
        SELECT ci.evenement_id,
               GROUP_CONCAT(u.prenom ORDER BY ci.created_at SEPARATOR ',') AS prenoms,
               COUNT(*) AS nb
          FROM follows_users fu
          JOIN culture_inscriptions ci ON ci.user_id = fu.followed_id
          JOIN users u ON u.id = fu.followed_id
         WHERE fu.follower_id = ? AND fu.statut = 'accepted'
         GROUP BY ci.evenement_id
    ");
    $stmt->execute([$uid]);

    $par = [];
    foreach ($stmt->fetchAll() as $ligne) {
        $par[(int) $ligne['evenement_id']] = [
            'prenoms' => explode(',', (string) $ligne['prenoms']),
            'nb'      => (int) $ligne['nb'],
        ];
    }

    return $par;
}

/** Les colonnes calculées communes au fil et à la fiche. */
function cultureColonnes(): string
{
    $fin = cultureSqlFin('c');

    return "c.*,
            (c.date_heure <= NOW() AND $fin >= NOW()) AS en_cours_calc,
            (SELECT COUNT(*) FROM culture_inscriptions ci WHERE ci.evenement_id = c.id) AS nb_inscrits,
            EXISTS (SELECT 1 FROM culture_inscriptions cm
                     WHERE cm.evenement_id = c.id AND cm.user_id = ?) AS deja_inscrit";
}

/**
 * Le fil de l'espace Culture : tout ce qui n'est pas terminé.
 *
 * Ordre : ce qui se passe maintenant, puis « à la une », puis par date. La
 * pagination est cumulative, comme celle du hub : la page 2 rend les pages 1
 * et 2, et l'application remplace sa liste au lieu d'y concaténer.
 *
 * @param array{categorie:string, page:int} $criteres
 * @return array{evenements:list<array<string,mixed>>, reste:bool}
 */
function cultureEvenements(PDO $pdo, int $uid, array $criteres): array
{
    $fin    = cultureSqlFin('c');
    $where  = "$fin >= NOW()";
    $params = [$uid];

    if ($criteres['categorie'] !== '') {
        $where   .= ' AND c.categorie = ?';
        $params[] = $criteres['categorie'];
    }

    $limite = CULTURE_PAR_PAGE * $criteres['page'];
    $stmt   = $pdo->prepare(
        "SELECT " . cultureColonnes() . "
           FROM culture_evenements c
          WHERE $where
          ORDER BY (c.date_heure <= NOW()) DESC, c.mis_en_avant DESC, c.date_heure ASC, c.id ASC
          LIMIT " . ($limite + 1)
    );
    $stmt->execute($params);
    $lignes = $stmt->fetchAll();

    return [
        'evenements' => array_slice($lignes, 0, $limite),
        'reste'      => count($lignes) > $limite,
    ];
}

/**
 * Un événement, terminé ou non — la fiche reste lisible après coup.
 *
 * @return array<string,mixed>|null
 */
function cultureEvenement(PDO $pdo, int $uid, int $id): ?array
{
    $stmt = $pdo->prepare("SELECT " . cultureColonnes() . ", (" . cultureSqlFin('c') . " < NOW()) AS termine
                             FROM culture_evenements c WHERE c.id = ?");
    $stmt->execute([$uid, $id]);
    $ligne = $stmt->fetch();

    return $ligne ?: null;
}

/**
 * Met une ligne en forme pour l'application : types explicites, comme
 * apiEvenement() pour les soirées.
 *
 * @param array<string,mixed> $c
 * @param array{prenoms:list<string>,nb:int}|null $amis
 * @return array<string,mixed>
 */
function cultureFormater(array $c, ?array $amis = null): array
{
    $quota    = (int) $c['quota'];
    $inscrits = (int) ($c['nb_inscrits'] ?? 0);
    $places   = remplissage($quota, $inscrits);
    $prix     = (float) $c['prix'];

    return [
        'id'           => (int) $c['id'],
        'titre'        => (string) $c['titre'],
        'description'  => (string) ($c['description'] ?? ''),
        'categorie'    => (string) $c['categorie'],
        'organisateur' => (string) $c['organisateur'],
        'lieu'         => (string) $c['lieu'],
        'ville'        => (string) $c['ville'],
        'date_heure'   => (string) $c['date_heure'],
        'date_fin'     => $c['date_fin'] !== null ? (string) $c['date_fin'] : null,
        'places'       => [
            'quota'     => $quota,
            'inscrits'  => $inscrits,
            'restantes' => $places['restantes'],
            'complet'   => $places['complet'],
        ],
        'prix'         => $prix,
        'is_gratuit'   => $prix <= 0,
        'lien'         => $c['lien'] !== null && $c['lien'] !== '' ? (string) $c['lien'] : null,
        'mis_en_avant' => (bool) $c['mis_en_avant'],
        'en_cours'     => (bool) ($c['en_cours_calc'] ?? false),
        'deja_inscrit' => (bool) ($c['deja_inscrit'] ?? false),
        'amis'         => [
            'prenoms' => array_values($amis['prenoms'] ?? []),
            'nb'      => (int) ($amis['nb'] ?? 0),
        ],
    ];
}

/**
 * Inscrit un étudiant. Rend null en cas de succès, sinon le message à afficher.
 *
 * Le contrôle du quota et l'insertion sont atomiques, comme api/inscrire.php :
 * FOR UPDATE verrouille l'événement, donc deux personnes qui prennent la
 * dernière place en même temps passent l'une après l'autre.
 */
function cultureInscrire(PDO $pdo, int $uid, int $id): ?string
{
    $fin = cultureSqlFin('c');
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT c.quota FROM culture_evenements c WHERE c.id = ? AND $fin >= NOW() FOR UPDATE");
        $stmt->execute([$id]);
        $ev = $stmt->fetch();
        if (!$ev) {
            $pdo->rollBack();
            return 'Événement introuvable ou terminé';
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM culture_inscriptions WHERE evenement_id = ?');
        $stmt->execute([$id]);
        if (remplissage((int) $ev['quota'], (int) $stmt->fetchColumn())['complet']) {
            $pdo->rollBack();
            return 'Complet';
        }

        $pdo->prepare('INSERT INTO culture_inscriptions (user_id, evenement_id) VALUES (?, ?)')->execute([$uid, $id]);
        $pdo->commit();

        return null;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($e->getCode() === '23000') {
            return 'Tu es déjà inscrit';
        }
        throw $e;
    }
}

/** Retire l'inscription. Sans effet si elle n'existait pas. */
function cultureDesinscrire(PDO $pdo, int $uid, int $id): void
{
    $pdo->prepare('DELETE FROM culture_inscriptions WHERE user_id = ? AND evenement_id = ?')->execute([$uid, $id]);
}
