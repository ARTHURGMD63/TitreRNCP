<?php
/**
 * Injecteur de charge.
 *
 * Mesurer une page seul sur sa machine ne dit rien de ce qui se passe à mille
 * utilisateurs : la latence d'une requête isolée est le meilleur des cas, et
 * ce qui casse en production, c'est la file d'attente, pas la requête.
 *
 * Ce script lance N requêtes réellement simultanées et rend la distribution
 * des temps de réponse — médiane, 95e et 99e centiles — plus le débit
 * soutenu. Ce sont ces trois chiffres qui répondent à « combien d'utilisateurs
 * en même temps », et non la moyenne, qui cache précisément les cas lents.
 *
 * Dépend uniquement de curl, déjà présent partout où PHP tourne : pas d'outil
 * à installer, donc utilisable le jour où la question se pose vraiment.
 *
 * Exemples :
 *     php outils/charge.php --url=http://localhost/TitreRNCP/explore.php \
 *                           --cookie=PHPSESSID=xxxx --concurrence=50 --total=1000
 *
 *     php outils/charge.php --url=http://localhost/TitreRNCP/api/live.php?ev=1,2,3 \
 *                           --cookie=PHPSESSID=xxxx --concurrence=200 --total=4000
 *
 * Options :
 *     --url=…          obligatoire
 *     --cookie=…       en-tête Cookie complet, pour tester une page connectée
 *     --cookies=…      fichier d'en-têtes Cookie, un par ligne, distribués en
 *                      rotation entre les requêtes. Indispensable dès qu'on
 *                      mesure une page connectée : PHP verrouille le fichier de
 *                      session pendant toute la requête, donc mille requêtes
 *                      partageant un cookie se suivent au lieu de se chevaucher,
 *                      et l'on mesure ce verrou plutôt que le serveur. De vrais
 *                      utilisateurs ont des sessions distinctes : c'est cela
 *                      qu'il faut reproduire.
 *     --concurrence=N  requêtes en vol simultanément (défaut 20)
 *     --total=N        requêtes au total (défaut 200)
 *     --etag=…         valeur If-None-Match, pour mesurer le chemin 304
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (!function_exists('curl_multi_init')) {
    fwrite(STDERR, "L'extension curl est nécessaire.\n");
    exit(1);
}

$opt = static function (string $nom, ?string $defaut = null) use ($argv): ?string {
    foreach ($argv as $a) {
        if (str_starts_with($a, "--$nom=")) {
            return substr($a, strlen($nom) + 3);
        }
    }

    return $defaut;
};

$url = $opt('url');
if (!$url) {
    fwrite(STDERR, "Usage : php outils/charge.php --url=… [--cookie=…] [--concurrence=20] [--total=200]\n");
    exit(1);
}

$concurrence = max(1, (int) $opt('concurrence', '20'));
$total       = max($concurrence, (int) $opt('total', '200'));
$cookie      = $opt('cookie');
$etag        = $opt('etag');

/** @var list<string> $cookies */
$cookies = [];
if (($fichier = $opt('cookies')) !== null) {
    if (!is_file($fichier)) {
        fwrite(STDERR, "Fichier de cookies introuvable : $fichier\n");
        exit(1);
    }
    $cookies = array_values(array_filter(array_map('trim', file($fichier) ?: [])));
}
if (!$cookies && $cookie !== null && $cookie !== '') {
    $cookies = [$cookie];
}

$entetes = [];
if ($etag !== null) {
    $entetes[] = 'If-None-Match: ' . $etag;
}

printf("Cible       : %s\n", $url);
printf("Charge      : %d requêtes, %d en parallèle\n\n", $total, $concurrence);
printf("Sessions    : %s\n", $cookies ? count($cookies) . ' distincte(s)' : 'aucune (anonyme)');

$tour = 0;
$fabriquer = static function () use ($url, $cookies, $entetes, &$tour) {
    // Rotation : chaque requête prend la session suivante, pour qu'aucune
    // n'attende le verrou d'une autre.
    $cookie = $cookies ? $cookies[$tour++ % count($cookies)] : '';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        // Pas de réutilisation de connexion entre les requêtes : on veut
        // mesurer ce que vit un visiteur qui arrive, pas un client
        // artificiellement avantagé par une connexion déjà ouverte.
        CURLOPT_FORBID_REUSE   => true,
        CURLOPT_HTTPHEADER     => $entetes,
        CURLOPT_COOKIE         => $cookie,
        CURLOPT_ENCODING       => '',   // accepte gzip : mesure ce que fait un navigateur
    ]);

    return $ch;
};

$multi    = curl_multi_init();
$enVol    = [];
$lances   = 0;
$temps    = [];
$codes    = [];
$octets   = 0;
$debut    = microtime(true);

$lancer = static function () use ($multi, $fabriquer, &$enVol, &$lances, $total): bool {
    if ($lances >= $total) {
        return false;
    }
    $ch = $fabriquer();
    curl_multi_add_handle($multi, $ch);
    $enVol[(int) $ch] = $ch;
    $lances++;

    return true;
};

for ($i = 0; $i < $concurrence; $i++) {
    $lancer();
}

do {
    curl_multi_exec($multi, $actives);
    // Bloque jusqu'à ce qu'il se passe quelque chose, au lieu de tourner à
    // vide : sans cela, l'injecteur consomme un cœur entier et fausse la
    // mesure de la machine qu'il est censé observer.
    if ($actives) {
        curl_multi_select($multi, 1.0);
    }

    while (($info = curl_multi_info_read($multi)) !== false) {
        $ch = $info['handle'];
        $temps[]  = curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000;
        $code     = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $codes[$code] = ($codes[$code] ?? 0) + 1;
        $octets  += (int) curl_getinfo($ch, CURLINFO_SIZE_DOWNLOAD);

        curl_multi_remove_handle($multi, $ch);
        curl_close($ch);
        unset($enVol[(int) $ch]);

        $lancer();
    }
} while ($actives || $enVol);

curl_multi_close($multi);

$duree = microtime(true) - $debut;
sort($temps);

$centile = static function (array $tries, float $p): float {
    if (!$tries) {
        return 0.0;
    }
    $rang = (int) ceil($p * count($tries)) - 1;

    return $tries[max(0, min($rang, count($tries) - 1))];
};

printf("Durée totale : %.1f s\n", $duree);
printf("Débit        : %.0f requêtes/s\n\n", count($temps) / max($duree, 0.001));

printf("Latence      médiane %6.0f ms\n", $centile($temps, 0.50));
printf("             p95     %6.0f ms\n", $centile($temps, 0.95));
printf("             p99     %6.0f ms\n", $centile($temps, 0.99));
printf("             max     %6.0f ms\n\n", end($temps) ?: 0);

printf("Transfert    %.1f Mo au total, %.1f Ko par réponse\n\n",
    $octets / 1048576, $octets / max(count($temps), 1) / 1024);

ksort($codes);
foreach ($codes as $code => $n) {
    // Tout ce qui n'est pas 2xx ou 304 signale que le serveur a lâché sous la
    // charge : c'est le chiffre à regarder en premier, avant les latences.
    $marque = ($code >= 200 && $code < 300) || $code === 304 ? ' ' : '!';
    printf("  %s HTTP %d : %d\n", $marque, $code, $n);
}
