<?php
/**
 * Migrations de schéma — découpage, inventaire, application.
 *
 * Pourquoi ce fichier existe. Les migrations se posaient à la main, une par
 * une, dans phpMyAdmin, et rien n'enregistrait celles qui étaient déjà
 * passées. Trois conséquences, toutes vérifiées dans le dépôt :
 *
 *   • On ne pouvait pas savoir où en était une base sans l'ouvrir et
 *     chercher une colonne au hasard.
 *   • Deux dumps complets — db_setup.sql et install_mutualise.sql — devaient
 *     être tenus à jour à la main en plus des migrations. Ils ont divergé :
 *     db_setup.sql a servi pendant des semaines sans les quatre tables du
 *     back-office, et toute installation faite en suivant le README
 *     produisait une administration en erreur.
 *   • Rien n'empêchait de rejouer une migration déjà appliquée.
 *
 * `schema_migrations` répond aux trois : elle dit ce qui est passé, quand,
 * et rend le rejeu impossible.
 *
 * Les fonctions de ce fichier sont volontairement sans effet de bord sauf
 * celles qui prennent un PDO : le découpage SQL, lui, se teste sans base
 * (voir tests/Unit/MigrationsTest.php).
 */

/** Table qui enregistre les migrations appliquées. */
const MIGRATIONS_TABLE = 'schema_migrations';

/**
 * Découpe un script SQL en instructions exécutables une par une.
 *
 * PDO::exec() n'accepte qu'une instruction à la fois dès lors que les
 * requêtes préparées ne sont pas émulées — ce qui est notre cas
 * (includes/db.php). Il faut donc découper, et découper sur « ; » ne suffit
 * pas : un point-virgule dans une chaîne, dans un commentaire ou dans un
 * corps de procédure stockée n'est pas une fin d'instruction.
 *
 * Ce que cette fonction gère, parce que les migrations du dépôt en usent :
 *
 *   • `DELIMITER //` — une directive du client mysql, pas du serveur. Sans
 *     elle, les procédures `ajouter_index` et `ajouter_fk` de la migration
 *     v7 se retrouveraient coupées en deux au premier « ; » de leur corps.
 *   • les chaînes '…', "…" et les identifiants `…`, avec leurs échappements ;
 *   • les commentaires `-- `, `#` et les blocs.
 *
 * @return list<string> les instructions, sans leur délimiteur, vides retirées
 */
function decouperSql(string $sql): array
{
    $instructions = [];
    $courante     = '';
    $delimiteur   = ';';
    $i            = 0;
    $taille       = strlen($sql);

    while ($i < $taille) {
        $c = $sql[$i];

        // ── Chaînes et identifiants quotés : recopiés tels quels ──────────
        if ($c === "'" || $c === '"' || $c === '`') {
            $fin = $c;
            $courante .= $c;
            $i++;
            while ($i < $taille) {
                $d = $sql[$i];
                // L'antislash n'échappe rien dans un identifiant `…`.
                if ($d === '\\' && $fin !== '`' && $i + 1 < $taille) {
                    $courante .= $d . $sql[$i + 1];
                    $i += 2;
                    continue;
                }
                // '' et "" doublés : un quote littéral, pas une fermeture.
                if ($d === $fin && ($sql[$i + 1] ?? '') === $fin) {
                    $courante .= $d . $d;
                    $i += 2;
                    continue;
                }
                $courante .= $d;
                $i++;
                if ($d === $fin) {
                    break;
                }
            }
            continue;
        }

        // ── Commentaire de fin de ligne ───────────────────────────────────
        // « -- » n'ouvre un commentaire que suivi d'un blanc : « 5--3 » est
        // une soustraction. « # » ouvre sans condition, en MySQL.
        // substr() plutôt que $sql[$i + 2] : en fin de chaîne, l'indice
        // n'existe pas, et substr() rend '' là où l'accès par indice
        // déclencherait un avertissement.
        $apresTirets       = substr($sql, $i, 3);
        $tiretsCommentaire = strlen($apresTirets) === 3
            && str_starts_with($apresTirets, '--')
            && in_array($apresTirets[2], [' ', "\t", "\n", "\r"], true);
        if ($tiretsCommentaire || $c === '#') {
            $saut = strpos($sql, "\n", $i);
            if ($saut === false) {
                break;
            }
            // Le saut de ligne est conservé : il sépare deux mots-clés.
            $courante .= "\n";
            $i = $saut + 1;
            continue;
        }

        // ── Commentaire en bloc ───────────────────────────────────────────
        if ($c === '/' && ($sql[$i + 1] ?? '') === '*') {
            $fin = strpos($sql, '*/', $i + 2);
            if ($fin === false) {
                break;
            }
            $courante .= ' ';
            $i = $fin + 2;
            continue;
        }

        // ── DELIMITER : directive du client, jamais envoyée au serveur ────
        // Reconnue en début de ligne seulement, et seulement hors instruction
        // en cours, comme le fait le client mysql.
        if (($c === 'D' || $c === 'd') && ($i === 0 || $sql[$i - 1] === "\n" || $sql[$i - 1] === "\r")
            && trim($courante) === ''
            && strcasecmp(substr($sql, $i, 10), 'DELIMITER ') === 0
        ) {
            $finLigne = strpos($sql, "\n", $i);
            $ligne    = $finLigne === false ? substr($sql, $i) : substr($sql, $i, $finLigne - $i);
            $nouveau  = trim(substr($ligne, 10));
            if ($nouveau !== '') {
                $delimiteur = $nouveau;
            }
            $courante = '';
            $i        = $finLigne === false ? $taille : $finLigne + 1;
            continue;
        }

        // ── Fin d'instruction ─────────────────────────────────────────────
        if (substr($sql, $i, strlen($delimiteur)) === $delimiteur) {
            $instruction = trim($courante);
            if ($instruction !== '') {
                $instructions[] = $instruction;
            }
            $courante = '';
            $i += strlen($delimiteur);
            continue;
        }

        $courante .= $c;
        $i++;
    }

    // Une dernière instruction sans délimiteur final reste valable.
    $reste = trim($courante);
    if ($reste !== '') {
        $instructions[] = $reste;
    }

    return $instructions;
}

/**
 * La version portée par un nom de fichier de migration, ou null.
 *
 * « db_migrations_v12.sql » → « v12 ». Le numéro sert aussi au tri : « v9 »
 * doit passer avant « v10 », ce qu'un tri alphabétique ferait à l'envers.
 */
function versionMigration(string $fichier): ?string
{
    return preg_match('/^db_migrations_(v\d+)\.sql$/i', basename($fichier), $m)
        ? strtolower($m[1])
        : null;
}

/**
 * Inventaire des migrations présentes sur le disque, dans l'ordre d'application.
 *
 * @return array<string,string> version => chemin absolu
 */
function listerMigrations(?string $dossier = null): array
{
    $dossier ??= dirname(__DIR__);
    $trouvees = [];

    foreach (glob($dossier . '/db_migrations_v*.sql') ?: [] as $chemin) {
        $version = versionMigration($chemin);
        if ($version !== null) {
            $trouvees[$version] = $chemin;
        }
    }

    // Tri numérique : v9 avant v10.
    uksort($trouvees, static fn(string $a, string $b): int
        => (int) substr($a, 1) <=> (int) substr($b, 1));

    return $trouvees;
}

/**
 * Crée la table de suivi si elle manque.
 *
 * `applique_le` et non un simple drapeau : savoir qu'une migration est
 * passée ne dit pas quand, et c'est la date qui permet de relier un
 * changement de schéma à une panne apparue le même jour.
 */
function creerTableMigrations(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ' . MIGRATIONS_TABLE . ' (
            version     VARCHAR(20) NOT NULL,
            applique_le DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
}

/**
 * Les versions déjà appliquées sur cette base.
 *
 * @return list<string>
 */
function migrationsAppliquees(PDO $pdo): array
{
    creerTableMigrations($pdo);

    $versions = $pdo->query('SELECT version FROM ' . MIGRATIONS_TABLE)
        ->fetchAll(PDO::FETCH_COLUMN);

    return array_map('strval', $versions ?: []);
}

/**
 * Les migrations qui restent à appliquer, dans l'ordre.
 *
 * @return array<string,string> version => chemin
 */
function migrationsEnAttente(PDO $pdo, ?string $dossier = null): array
{
    $faites = array_flip(migrationsAppliquees($pdo));

    return array_diff_key(listerMigrations($dossier), $faites);
}

/**
 * Exécute une instruction et vide ce qu'elle a produit.
 *
 * PDO::exec() ne suffit pas ici. Plusieurs migrations passent par
 * `PREPARE st FROM @s; EXECUTE st;` — le seul moyen, en SQL pur, de rendre
 * un ALTER TABLE conditionnel. `EXECUTE` renvoie un jeu de résultats, et
 * exec() ne le consomme pas : le curseur reste ouvert et l'instruction
 * suivante échoue sur « Cannot execute queries while other unbuffered
 * queries are active », à une centaine de lignes de la vraie cause.
 *
 * On passe donc par query(), on épuise tous les jeux de résultats, puis on
 * ferme le curseur. Une instruction DDL en renvoie zéro : le surcoût est nul.
 */
function executerInstruction(PDO $pdo, string $sql): void
{
    $stmt = $pdo->query($sql);
    if ($stmt === false) {
        return;
    }

    do {
        // Rien à lire : on avance seulement pour libérer la connexion.
        $suite = false;
        try {
            $suite = $stmt->nextRowset();
        } catch (PDOException $e) {
            // Certaines instructions n'ont aucun jeu de résultats à avancer
            // et le signalent par une exception plutôt que par false.
            break;
        }
    } while ($suite);

    $stmt->closeCursor();
}

/**
 * Applique une migration et l'enregistre.
 *
 * Pas de transaction autour du tout : MySQL valide implicitement à chaque
 * CREATE TABLE ou ALTER TABLE, un BEGIN ne protégerait donc rien et
 * donnerait seulement l'illusion de le faire. Ce qui protège réellement,
 * c'est que chaque migration du dépôt est écrite idempotente (IF NOT EXISTS,
 * INSERT IGNORE, procédures qui vérifient avant d'ajouter) : une reprise
 * après échec au milieu ne casse rien.
 *
 * La ligne de suivi n'est écrite qu'après la dernière instruction. Une
 * migration interrompue reste donc « en attente » et sera reproposée, ce qui
 * est le bon défaut : mieux vaut rejouer de l'idempotent que sauter un pas.
 *
 * @return int le nombre d'instructions exécutées
 */
function appliquerMigration(PDO $pdo, string $version, string $chemin): int
{
    $sql = file_get_contents($chemin);
    if ($sql === false) {
        throw new RuntimeException("Migration illisible : $chemin");
    }

    $instructions = decouperSql($sql);
    foreach ($instructions as $instruction) {
        executerInstruction($pdo, $instruction);
    }

    $pdo->prepare('INSERT IGNORE INTO ' . MIGRATIONS_TABLE . ' (version) VALUES (?)')
        ->execute([$version]);

    return count($instructions);
}

/**
 * Enregistre des migrations comme appliquées sans les exécuter.
 *
 * Sert au raccordement d'une base existante. Une base créée depuis
 * db_setup.sql contient déjà le résultat de toutes les migrations : les
 * rejouer serait sans danger — elles sont idempotentes — mais inutilement
 * long, et la table de suivi doit partir de la vérité, pas de zéro.
 *
 * @param list<string> $versions
 */
function adopterMigrations(PDO $pdo, array $versions): int
{
    creerTableMigrations($pdo);

    $stmt = $pdo->prepare('INSERT IGNORE INTO ' . MIGRATIONS_TABLE . ' (version) VALUES (?)');
    $n = 0;
    foreach ($versions as $version) {
        $stmt->execute([$version]);
        $n += $stmt->rowCount();
    }

    return $n;
}
