<?php
/**
 * Modèle de configuration pour un hébergement sans variables
 * d'environnement — mutualisés type InfinityFree, o2switch, OVH.
 *
 * Copier ce fichier en « config.local.php » dans le même dossier, puis
 * reporter les identifiants affichés par le panneau de l'hébergeur.
 *
 * config.local.php n'est pas versionné : le mot de passe de la base ne
 * part jamais sur GitHub. C'est la seule raison d'être de ce modèle.
 *
 * Ordre de priorité appliqué par db.php :
 *   1. variables d'environnement (Railway, Docker) ;
 *   2. ce fichier ;
 *   3. valeurs WAMP par défaut.
 */

return [
    // InfinityFree donne un hôte de la forme « sqlXXX.infinityfree.com ».
    // Ce n'est jamais « localhost » : la base est sur une autre machine.
    'host' => 'sqlXXX.infinityfree.com',

    // Nom imposé par l'hébergeur, préfixé par l'identifiant du compte.
    'name' => 'if0_00000000_studentlink',

    'user' => 'if0_00000000',
    'pass' => 'a-remplacer',
    'port' => '3306',
];
