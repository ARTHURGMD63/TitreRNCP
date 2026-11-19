<?php
/**
 * Fabrique includes/config.local.php à partir des secrets du dépôt.
 *
 * Appelé par le workflow de déploiement, juste avant l'envoi FTP.
 *
 * Pourquoi un fichier plutôt qu'un « php -r » dans le YAML : un mot de
 * passe peut contenir des apostrophes, des dollars, des accents. Passé
 * en ligne de commande, il traverse le shell puis le YAML, et chaque
 * couche a ses propres règles d'échappement. Ici, les valeurs viennent
 * de l'environnement et var_export produit du PHP correct quoi qu'elles
 * contiennent.
 *
 * Usage :  php .github/scripts/ecrire_config.php <chemin de sortie>
 */

declare(strict_types=1);

$destination = $argv[1] ?? null;
if ($destination === null) {
    fwrite(STDERR, "Usage : php ecrire_config.php <chemin de sortie>\n");
    exit(1);
}

$correspondances = [
    'host' => 'DB_HOST',
    'name' => 'DB_NAME',
    'user' => 'DB_USER',
    'pass' => 'DB_PASSWORD',
];

$config = [];
$manquants = [];

foreach ($correspondances as $cle => $variable) {
    $valeur = getenv($variable);
    if ($valeur === false || $valeur === '') {
        $manquants[] = $variable;
        continue;
    }
    $config[$cle] = $valeur;
}

if ($manquants !== []) {
    // On nomme les secrets absents : sans ça, l'erreur n'arriverait qu'en
    // ligne, sous la forme d'une page « Service momentanément indisponible ».
    fwrite(STDERR, "Secrets manquants dans les paramètres du dépôt : "
        . implode(', ', $manquants) . "\n");
    exit(1);
}

$config['port'] = getenv('DB_PORT') ?: '3306';

/// Environnement : OBLIGATOIRE. Sans app_env, le site se croyait hors
// production : traces d'erreur affichées, codes de test renvoyés, et
// surtout les comptes de démonstration au mot de passe public « password »
// s'ouvraient, hachageInterdit() ne les refusant qu'en production (audit
// SEC-01). Une valeur de poste de développement est refusée pour la même
// raison : ce workflow publie un site joignable par tous.
$appEnv = strtolower(trim((string) getenv('APP_ENV')));
if ($appEnv === '') {
    fwrite(STDERR, "::error::Variable APP_ENV absente (FTP_APP_ENV dans les variables du dépôt) : "
        . "le déploiement est arrêté. Valeur attendue : production.\n");
    exit(1);
}
if (in_array($appEnv, ['local', 'dev', 'developpement', 'development', 'test'], true)) {
    fwrite(STDERR, "::error::APP_ENV vaut « $appEnv » : un site public ne tourne jamais en mode développement.\n");
    exit(1);
}
if (!in_array($appEnv, ['production', 'prod'], true)) {
    fwrite(STDERR, "::warning::APP_ENV vaut « $appEnv » : hors production, les comptes de démonstration restent ouverts.\n");
}
$config['app_env'] = $appEnv;

// Facultative : l'adresse publique. Sans elle, les liens des e-mails ne se
// construisent pas en production (urlPublique() échoue et le journalise).
// Elle vient des variables du dépôt, jamais d'une valeur écrite ici.
$urlPublique = getenv('URL_PUBLIQUE');
if ($urlPublique === false || $urlPublique === '') {
    fwrite(STDERR, "::warning::Variable URL_PUBLIQUE absente : 'url_publique' n'est pas écrit dans la configuration.\n");
} else {
    $config['url_publique'] = $urlPublique;
}

$contenu = "<?php\n"
    . "// Fichier généré par le workflow de déploiement.\n"
    . "// Ne pas modifier à la main : il est réécrit à chaque déploiement.\n"
    . 'return ' . var_export($config, true) . ";\n";

$dossier = dirname($destination);
if (!is_dir($dossier) && !mkdir($dossier, 0775, true) && !is_dir($dossier)) {
    fwrite(STDERR, "Dossier inaccessible : $dossier\n");
    exit(1);
}

if (file_put_contents($destination, $contenu) === false) {
    fwrite(STDERR, "Écriture impossible : $destination\n");
    exit(1);
}

// Le mot de passe ne doit apparaître nulle part dans le journal du
// workflow : on confirme l'écriture sans rien afficher de son contenu.
printf("Configuration écrite (%d octets) vers %s%s", strlen($contenu), $destination, PHP_EOL);
