<?php
/**
 * La coquille HTML commune à toutes les pages.
 *
 * Elle était recopiée à l'identique — en théorie — dans vingt-quatre
 * fichiers : doctype, jeu de caractères, viewport, titre, amorce de thème,
 * jeton CSRF, feuille de style, icône, métas PWA. Douze lignes par page,
 * dont onze ne changent jamais.
 *
 * Ce que la copie a réellement produit, avant ce fichier :
 *
 *   - les métas PWA n'étaient que sur sept pages. Un étudiant arrivé par
 *     explore.php pouvait installer l'application, le même arrivé par
 *     avis.php ou par la page de connexion, non ;
 *   - l'icône manquait sur treize pages, dont les quatre pages
 *     d'authentification — la toute première chose que voit un visiteur ;
 *   - deux pages seulement portaient « viewport-fit=cover », alors que la
 *     question — un écran à encoche — ne dépend pas de la page.
 *
 * Une duplication ne reste pas identique : elle diverge, silencieusement, et
 * personne ne relit douze lignes de <head> à chaque modification. C'est le
 * même mécanisme qui avait fait diverger db_setup.sql de install_mutualise.sql
 * de quatre tables, et sendResetEmail() du reste de l'application.
 *
 * Ce fichier ne traite que le <head> et l'ouverture du <body>. La fin des
 * pages n'est pas recopiée : elles n'ont ni la même navigation ni les mêmes
 * scripts, et un pageFin() n'y factoriserait que deux balises fermantes.
 */

// baseUrl(), asset() et themeBootScript() viennent d'auth_check.php, qui
// charge lui-même security.php pour metaCsrf(). Toutes les pages le
// requièrent déjà avant d'afficher quoi que ce soit : ce require_once ne
// change donc aucun ordre de chargement, il rend seulement la dépendance
// explicite au lieu de la supposer.
require_once __DIR__ . '/auth_check.php';

/**
 * Ouvre la page : du doctype jusqu'au <body> inclus.
 *
 * @param string               $titre   Titre brut, échappé ici. Ne jamais
 *                                      passer une valeur déjà passée par
 *                                      htmlspecialchars() : elle serait
 *                                      échappée deux fois.
 * @param array<string,mixed>  $options
 *     'pwa'      => bool   métas d'installation (manifeste, icône d'accueil,
 *                          barre d'état iOS). Faux par défaut : le manifeste
 *                          déclare « start_url: explore.php », donc proposer
 *                          l'installation depuis l'espace partenaire
 *                          enverrait le partenaire sur le hub étudiant.
 *     'description' => string méta description, pour les pages publiques que
 *                          les moteurs affichent en résultat. Vide sur les
 *                          écrans connectés, qu'ils n'indexent pas.
 *     'viewport' => string contenu de la méta viewport.
 *     'scripts'  => list<string> chemins de scripts chargés dans le <head>,
 *                          pour ceux qui doivent être là avant le corps.
 *     'tete'     => string HTML supplémentaire en fin de <head> : c'est là
 *                          que passe le <style> propre à une page, capturé
 *                          par ob_start()/ob_get_clean() pour que le PHP
 *                          qu'il contient parfois soit évalué normalement.
 */
function pageDebut(string $titre, array $options = []): void
{
    $pwa         = (bool) ($options['pwa'] ?? false);
    $viewport    = (string) ($options['viewport'] ?? 'width=device-width, initial-scale=1.0');
    $scripts     = (array) ($options['scripts'] ?? []);
    $tete        = (string) ($options['tete'] ?? '');
    $description = (string) ($options['description'] ?? '');

    echo "<!DOCTYPE html>\n";
    echo "<html lang=\"fr\">\n";
    echo "<head>\n";
    echo "<meta charset=\"UTF-8\">\n";
    echo '<meta name="viewport" content="' . htmlspecialchars($viewport, ENT_QUOTES) . "\">\n";
    echo '<title>' . htmlspecialchars($titre) . "</title>\n";

    if ($description !== '') {
        echo '<meta name="description" content="' . htmlspecialchars($description, ENT_QUOTES) . "\">\n";
    }

    // Avant la feuille de style : le thème est posé sur <html> par ce script
    // synchrone, sinon la page s'affiche en clair puis bascule en sombre.
    echo themeBootScript() . "\n";
    echo metaCsrf() . "\n";

    // Le préfixe d'installation, pour que le JavaScript n'ait pas à le
    // deviner : app.js le refaisait avec sa propre règle sur le nom d'hôte,
    // et se trompait donc exactement dans les mêmes cas.
    echo metaBase() . "\n";

    echo '<link rel="stylesheet" href="' . htmlspecialchars(asset('/assets/css/style.css'), ENT_QUOTES) . "\">\n";

    // L'icône, sur toutes les pages. Elle manquait sur treize d'entre elles,
    // sans qu'aucune raison ne le justifie : un onglet sans icône se retrouve
    // mal dans une barre qui en compte vingt.
    echo '<link rel="icon" type="image/png" href="' . htmlspecialchars(baseUrl('/Logo.png'), ENT_QUOTES) . "\">\n";

    if ($pwa) {
        echo '<link rel="apple-touch-icon" href="' . htmlspecialchars(baseUrl('/Logo.png'), ENT_QUOTES) . "\">\n";
        echo '<link rel="manifest" href="' . htmlspecialchars(baseUrl('/manifest.json'), ENT_QUOTES) . "\">\n";
        echo "<meta name=\"apple-mobile-web-app-capable\" content=\"yes\">\n";
        echo "<meta name=\"apple-mobile-web-app-status-bar-style\" content=\"black-translucent\">\n";
        echo "<meta name=\"apple-mobile-web-app-title\" content=\"StudentLink\">\n";
    }

    foreach ($scripts as $script) {
        echo '<script src="' . htmlspecialchars(asset((string) $script), ENT_QUOTES) . "\"></script>\n";
    }

    if (trim($tete) !== '') {
        echo rtrim($tete, "\n") . "\n";
    }

    echo "</head>\n";
    echo "<body>\n";
}
