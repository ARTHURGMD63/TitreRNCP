<?php
/**
 * Cache applicatif — un seul point d'entrée, deux implémentations.
 *
 * Le constat qui a motivé ce fichier : chaque affichage du hub relançait les
 * mêmes requêtes pour des données qui ne bougent pas d'une seconde à l'autre —
 * la liste des écoles, la note moyenne des établissements, le catalogue des
 * intérêts. À un utilisateur, c'est invisible. À mille utilisateurs en même
 * temps, c'est plusieurs milliers de balayages de table par seconde pour
 * réafficher exactement le même menu déroulant.
 *
 * Deux magasins, choisis automatiquement :
 *
 *   1. APCu, quand l'extension est là — mémoire partagée entre les processus
 *      PHP du serveur, lecture en microsecondes, rien à nettoyer.
 *   2. Des fichiers, sinon. C'est le cas sous WAMP et sur la plupart des
 *      hébergements mutualisés, où APCu n'est pas installé. Un fichier lu
 *      depuis le cache disque du système reste deux ordres de grandeur moins
 *      cher qu'un aller-retour MySQL.
 *
 * Le même code marche donc en développement et en production, sans condition
 * dans les pages appelantes.
 *
 * Ce qui n'a PAS sa place ici : tout ce qui est propre à un utilisateur et
 * sensible (sessions, jetons, messages privés). Le cache est partagé entre
 * tous les visiteurs ; n'y mettre que des données que n'importe qui pourrait
 * voir de toute façon.
 */

/** Espace de noms du cache : deux installations sur la même machine ne se mélangent pas. */
function cachePrefixe(): string
{
    static $prefixe = null;
    if ($prefixe === null) {
        $prefixe = 'sl_' . substr(hash('sha256', __DIR__), 0, 10) . '_';
    }

    return $prefixe;
}

/** Vrai lorsque APCu est disponible ET actif pour ce SAPI. */
function cacheUtiliseApcu(): bool
{
    static $dispo = null;
    if ($dispo === null) {
        $dispo = function_exists('apcu_enabled') && apcu_enabled();
    }

    return $dispo;
}

/**
 * Dossier du cache fichier.
 *
 * Volontairement hors de l'arborescence web : un fichier de cache n'a pas à
 * être servi par Apache, même par accident. Le dossier temporaire du système
 * convient, il est inscriptible partout, y compris sur un mutualisé.
 */
function cacheDossier(): ?string
{
    static $dossier = null;
    static $verifie = false;

    if ($verifie) {
        return $dossier;
    }
    $verifie = true;

    $candidat = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'studentlink-cache';
    if (!is_dir($candidat) && !@mkdir($candidat, 0700, true) && !is_dir($candidat)) {
        // Sans dossier inscriptible, le cache se désactive silencieusement :
        // une page lente vaut mieux qu'une page en erreur.
        return $dossier = null;
    }

    return $dossier = is_writable($candidat) ? $candidat : null;
}

/** Chemin du fichier portant une clé. */
function cacheChemin(string $cle): ?string
{
    $dossier = cacheDossier();

    return $dossier === null
        ? null
        : $dossier . DIRECTORY_SEPARATOR . cachePrefixe() . hash('sha256', $cle) . '.cache';
}

/**
 * Lit une valeur. Renvoie $defaut si la clé est absente ou périmée.
 *
 * @return mixed
 */
function cacheGet(string $cle, mixed $defaut = null): mixed
{
    if (cacheUtiliseApcu()) {
        $trouve = false;
        /** @var mixed $valeur */
        $valeur = apcu_fetch(cachePrefixe() . $cle, $trouve);

        return $trouve ? $valeur : $defaut;
    }

    $chemin = cacheChemin($cle);
    if ($chemin === null || !is_file($chemin)) {
        return $defaut;
    }

    $brut = @file_get_contents($chemin);
    if ($brut === false) {
        return $defaut;
    }

    // allowed_classes à false : le cache ne doit jamais pouvoir reconstruire
    // un objet, quelle que soit la façon dont son contenu a été écrit.
    /** @var array{e:int,v:mixed}|false $paquet */
    $paquet = @unserialize($brut, ['allowed_classes' => false]);
    if (!is_array($paquet) || !isset($paquet['e'], $paquet['v'])) {
        return $defaut;
    }

    if ($paquet['e'] !== 0 && $paquet['e'] < time()) {
        @unlink($chemin);

        return $defaut;
    }

    return $paquet['v'];
}

/**
 * Écrit une valeur pour $ttl secondes (0 = sans expiration).
 */
function cacheSet(string $cle, mixed $valeur, int $ttl = 300): bool
{
    /*
     * Une duree de vie negative n'a pas de sens, et c'est justement pour ca
     * qu'il faut la traiter : les deux magasins l'interpretaient comme zero,
     * c'est-a-dire « sans expiration ». Un appelant qui calcule un TTL et
     * tombe sous zero — une echeance deja passee, une soustraction de dates
     * inversee — obtenait donc l'exact contraire de ce qu'il demandait : une
     * entree eternelle au lieu d'une entree morte. Et personne ne l'aurait vu
     * avant qu'une valeur figee ne traine des semaines a l'ecran.
     */
    if ($ttl < 0) {
        cacheOublier($cle);

        return false;
    }

    if (cacheUtiliseApcu()) {
        return apcu_store(cachePrefixe() . $cle, $valeur, max(0, $ttl));
    }

    $chemin = cacheChemin($cle);
    if ($chemin === null) {
        return false;
    }

    $contenu = serialize(['e' => $ttl > 0 ? time() + $ttl : 0, 'v' => $valeur]);

    // Écriture en deux temps : un fichier temporaire puis un renommage, qui
    // est atomique. Sans cela, une requête concurrente peut lire un fichier
    // à moitié écrit et le prendre pour une valeur valide.
    $temporaire = $chemin . '.' . bin2hex(random_bytes(6)) . '.tmp';
    if (@file_put_contents($temporaire, $contenu, LOCK_EX) === false) {
        return false;
    }
    if (!@rename($temporaire, $chemin)) {
        @unlink($temporaire);

        return false;
    }

    return true;
}

/** Supprime une clé. */
function cacheOublier(string $cle): void
{
    if (cacheUtiliseApcu()) {
        apcu_delete(cachePrefixe() . $cle);

        return;
    }

    $chemin = cacheChemin($cle);
    if ($chemin !== null && is_file($chemin)) {
        @unlink($chemin);
    }
}

/**
 * Le motif courant : lire, et calculer seulement en cas d'absence.
 *
 * C'est la seule fonction que les pages devraient appeler. Elle évite le
 * couple get/set écrit à la main, où l'on oublie une fois sur deux de
 * remettre la valeur en cache.
 *
 * @template T
 * @param  callable():T $calcul
 * @return T
 */
function cacheRemember(string $cle, int $ttl, callable $calcul): mixed
{
    $sentinelle = "\0absent\0";
    /** @var mixed $valeur */
    $valeur = cacheGet($cle, $sentinelle);
    if ($valeur !== $sentinelle) {
        return $valeur;
    }

    /** @var T $valeur */
    $valeur = $calcul();
    cacheSet($cle, $valeur, $ttl);

    return $valeur;
}

/**
 * Vide tout le cache de cette installation.
 *
 * Utile après une migration ou un déploiement qui change la forme des données
 * mises en cache. Ne touche pas aux clés des autres installations grâce au
 * préfixe.
 */
function cacheVider(): int
{
    if (cacheUtiliseApcu()) {
        $efface = 0;
        /** @var \APCUIterator $iterateur */
        $iterateur = new APCUIterator('/^' . preg_quote(cachePrefixe(), '/') . '/');
        foreach ($iterateur as $entree) {
            if (apcu_delete($entree['key'])) {
                $efface++;
            }
        }

        return $efface;
    }

    $dossier = cacheDossier();
    if ($dossier === null) {
        return 0;
    }

    $efface = 0;
    foreach (glob($dossier . DIRECTORY_SEPARATOR . cachePrefixe() . '*.cache') ?: [] as $fichier) {
        if (@unlink($fichier)) {
            $efface++;
        }
    }

    return $efface;
}

/**
 * Nettoyage des fichiers périmés.
 *
 * APCu se purge tout seul ; les fichiers, non. Sans ce ramassage, le dossier
 * temporaire grossit indéfiniment. Appelé par le cron, pas par les pages :
 * parcourir un dossier à chaque requête coûterait plus cher que ce que le
 * cache fait gagner.
 */
function cachePurger(): int
{
    if (cacheUtiliseApcu()) {
        return 0;
    }

    $dossier = cacheDossier();
    if ($dossier === null) {
        return 0;
    }

    $maintenant = time();
    $efface = 0;
    foreach (glob($dossier . DIRECTORY_SEPARATOR . cachePrefixe() . '*.cache') ?: [] as $fichier) {
        $brut = @file_get_contents($fichier);
        if ($brut === false) {
            continue;
        }
        /** @var array{e:int,v:mixed}|false $paquet */
        $paquet = @unserialize($brut, ['allowed_classes' => false]);
        $perime = !is_array($paquet)
            || !isset($paquet['e'])
            || ($paquet['e'] !== 0 && $paquet['e'] < $maintenant);
        if ($perime && @unlink($fichier)) {
            $efface++;
        }
    }

    return $efface;
}
