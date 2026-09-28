<?php
/**
 * Position d'une adresse, via Nominatim (OpenStreetMap) — gratuit, sans clé.
 *
 * Sert de filet quand un partenaire n'a pas encore réglé la position de son
 * établissement à la main sur la carte (api/v1/partenaire_profil.php) :
 * autant lui donner un point de départ approximatif à partir de son adresse
 * plutôt que de le laisser absent de la carte tant qu'il n'a rien touché.
 *
 * Nominatim impose un en-tête User-Agent identifiable et limite à une
 * requête par seconde par IP côté client — ce point n'est appelé qu'à
 * l'enregistrement du profil établissement, jamais en boucle.
 */

/**
 * @return array{lat: float, lon: float}|null
 */
function geocoderAdresse(string $adresse, string $ville): ?array
{
    $requete = trim($adresse . ', ' . $ville . ', France');
    if (trim($adresse) === '' && trim($ville) === '') {
        return null;
    }

    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'q'              => $requete,
        'format'         => 'json',
        'limit'          => 1,
        'countrycodes'   => 'fr',
    ]);

    // cURL d'abord : plus fiable que allow_url_fopen sur un hébergement
    // mutualisé, où cette option est parfois coupée par l'hébergeur.
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['User-Agent: Linkee/1.0 (contact@linkee.fr)'],
            CURLOPT_TIMEOUT        => 4,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $reponse = curl_exec($ch);
        curl_close($ch);
        if ($reponse === false) {
            return null;
        }
    } else {
        $contexte = stream_context_create([
            'http' => [
                'method'  => 'GET',
                // Nominatim refuse les requêtes sans User-Agent identifiable.
                'header'  => "User-Agent: Linkee/1.0 (contact@linkee.fr)\r\n",
                'timeout' => 4,
            ],
        ]);
        $reponse = @file_get_contents($url, false, $contexte);
        if ($reponse === false) {
            return null;
        }
    }

    $donnees = json_decode($reponse, true);
    if (!is_array($donnees) || empty($donnees[0]['lat']) || empty($donnees[0]['lon'])) {
        return null;
    }

    return ['lat' => (float) $donnees[0]['lat'], 'lon' => (float) $donnees[0]['lon']];
}
