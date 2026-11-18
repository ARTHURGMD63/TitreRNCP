<?php
/**
 * Gabarits du site : vitrine, connexion et espace établissement.
 *
 * Le site ne sert plus que deux publics :
 *   - les visiteurs, à qui la vitrine présente l'application ;
 *   - les établissements, qui gèrent leur compte et paient leur formule ici
 *     (le paiement ne se fait jamais dans l'application : règles Apple 3.1.1
 *     et Google Play Billing).
 * Un étudiant qui arrive sur le site est renvoyé vers l'application (voir
 * application.php).
 *
 * Feuille de style : assets/css/site.css. L'ancien style.css reste celui du
 * back-office.
 */

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/config.php';

/**
 * Pictogrammes du site : tracés 24 × 24, trait de 2, repris des icônes
 * dessinées de l'application (mobile/src/composants/Icone.tsx). Un même nom
 * désigne le même dessin des deux côtés. Aucun emoji.
 */
const ICONES_SITE = [
    'tableau'      => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
    'calendrier'   => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
    'plus'         => '<path d="M12 5v14M5 12h14"/>',
    'plus-rond'    => '<circle cx="12" cy="12" r="10"/><path d="M12 8v8M8 12h8"/>',
    'qr'           => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="3" height="3"/>',
    'photo'        => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/>',
    'carte'        => '<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>',
    'graphique'    => '<path d="M3 3v18h18"/><path d="m7 15 4-4 3 3 5-6"/>',
    'personnes'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    'personne'     => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
    'sortie'       => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
    'menu'         => '<path d="M3 6h18M3 12h18M3 18h18"/>',
    'croix'        => '<path d="M18 6 6 18M6 6l12 12"/>',
    'fleche-d'     => '<path d="M5 12h14M12 5l7 7-7 7"/>',
    'fleche-g'     => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
    'check'        => '<path d="M20 6 9 17l-5-5"/>',
    'crayon'       => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
    'poubelle'     => '<path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>',
    'oeil'         => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>',
    'telephone'    => '<rect x="6" y="2" width="12" height="20" rx="3"/><path d="M11 18h2"/>',
    'verrou'       => '<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
    'info'         => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
    'alerte'       => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4M12 17h.01"/>',
    'horloge'      => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
    'lieu'         => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
    'eclair'       => '<path d="M13 2 3 14h9l-1 8 10-12h-9l1-8Z"/>',
    'etoile'       => '<path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1Z"/>',
    'musique'      => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
    'verre'        => '<path d="M8 22h8"/><path d="M12 15v7"/><path d="M5 3h14l-7 12z"/>',
    'ballon'       => '<circle cx="12" cy="12" r="10"/><path d="M12 2a15 15 0 0 0 0 20M2 12h20M4.9 5.6c4 2.2 10.2 2.2 14.2 0M4.9 18.4c4-2.2 10.2-2.2 14.2 0"/>',
    'palette'      => '<circle cx="13.5" cy="6.5" r="1"/><circle cx="17.5" cy="10.5" r="1"/><circle cx="8.5" cy="7.5" r="1"/><circle cx="6.5" cy="12.5" r="1"/><path d="M12 2a10 10 0 0 0 0 20c.9 0 1.6-.7 1.6-1.6 0-.4-.2-.8-.4-1.1-.3-.3-.4-.7-.4-1.1 0-.9.7-1.6 1.6-1.6H16a6 6 0 0 0 6-6c0-4.9-4.5-8.6-10-8.6Z"/>',
    'ticket'       => '<path d="M2 9a3 3 0 0 0 0 6v3a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-3a3 3 0 0 0 0-6V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2M13 17v2M13 11v2"/>',
    'megaphone'    => '<path d="m3 11 18-5v12L3 14Z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/>',
    'cible'        => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
    'facture'      => '<path d="M4 2v20l3-2 3 2 3-2 3 2 3-2 1 .7V2l-1 .7L16 2l-3 2-3-2-3 2Z"/><path d="M8 8h8M8 12h8M8 16h5"/>',
    'engrenage'    => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>',
    'telecharger'  => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
];

/** Un pictogramme du site, décoratif sauf si $titre est donné. */
function iconeSite(string $nom, string $classes = '', string $titre = ''): string
{
    if (!isset(ICONES_SITE[$nom])) {
        return '';
    }
    $acc = $titre !== ''
        ? 'role="img" aria-label="' . htmlspecialchars($titre, ENT_QUOTES) . '"'
        : 'aria-hidden="true" focusable="false"';

    return '<svg class="icon' . ($classes !== '' ? ' ' . htmlspecialchars($classes, ENT_QUOTES) : '') . '" viewBox="0 0 24 24" ' . $acc . '>' . ICONES_SITE[$nom] . '</svg>';
}

/**
 * Le logo de la refonte : tuile colorée aux anneaux entrelacés + « Outsee ».
 * Même dessin que mobile/src/composants/Marque.tsx (la charte interdit de le
 * recomposer ailleurs).
 */
function marqueSite(string $suffixe = '', string $href = '', string $couleur = '#1D55F0', string $classes = ''): string
{
    static $n = 0;
    $n++;
    $clip = 'moitieBasse' . $n;

    $tuile = '<svg class="marque__tuile" viewBox="0 0 512 512" fill="none" aria-hidden="true" focusable="false">'
           . '<defs><clipPath id="' . $clip . '"><rect x="0" y="255.5" width="512" height="256.5"/></clipPath></defs>'
           . '<rect width="512" height="512" rx="112" fill="' . htmlspecialchars($couleur, ENT_QUOTES) . '"/>'
           . '<circle cx="196.5" cy="255.5" r="88.5" stroke="#111116" stroke-width="43"/>'
           . '<circle cx="314.5" cy="255.5" r="88.5" stroke="#F2F2F5" stroke-width="43"/>'
           . '<circle cx="196.5" cy="255.5" r="88.5" stroke="#111116" stroke-width="43" clip-path="url(#' . $clip . ')"/>'
           . '</svg>';

    $mot = '<span class="marque__mot" aria-hidden="true">Outs<span class="marque__ee" style="color:' . htmlspecialchars($couleur, ENT_QUOTES) . '">ee</span>'
         . ($suffixe !== '' ? '<span class="marque__suffixe">' . htmlspecialchars($suffixe) . '</span>' : '')
         . '</span>';

    $sr     = '<span class="sr-only">Outsee' . ($suffixe !== '' ? ' ' . htmlspecialchars($suffixe) : '') . '</span>';
    $classe = 'marque' . ($classes !== '' ? ' ' . $classes : '');

    return $href !== ''
        ? '<a class="' . $classe . '" href="' . htmlspecialchars($href, ENT_QUOTES) . '">' . $sr . $tuile . $mot . '</a>'
        : '<span class="' . $classe . '">' . $sr . $tuile . $mot . '</span>';
}

/**
 * Les liens des stores, réglables sans toucher au code (APP_STORE_URL,
 * PLAY_STORE_URL). Vides tant que l'application n'est pas publiée : le
 * bouton affiche alors « Bientôt ».
 *
 * @return array{ios:string, android:string}
 */
function liensStores(): array
{
    return [
        'ios'     => reglage('APP_STORE_URL', 'app_store_url', ''),
        'android' => reglage('PLAY_STORE_URL', 'play_store_url', ''),
    ];
}

/** Les deux boutons de téléchargement. */
function boutonsStores(): string
{
    $liens   = liensStores();
    $boutons = [
        'ios'     => ['Télécharger sur', "l'App Store"],
        'android' => ['Disponible sur', 'Google Play'],
    ];

    $html = '';
    foreach ($boutons as $os => [$haut, $bas]) {
        $url = $liens[$os];
        if ($url !== '') {
            $html .= '<a class="bouton-store" href="' . htmlspecialchars($url, ENT_QUOTES) . '" rel="noopener">'
                   . iconeSite('telephone', 'icon-lg') . '<span><small>' . $haut . '</small><strong>' . $bas . '</strong></span></a>';
        } else {
            $html .= '<span class="bouton-store bouton-store--bientot">'
                   . iconeSite('telephone', 'icon-lg') . '<span><small>Bientôt sur</small><strong>' . $bas . '</strong></span></span>';
        }
    }

    return $html;
}

/**
 * Début d'une page du site.
 *
 * @param array{description?:string, classe?:string, scripts?:list<string>, styles?:list<string>} $options
 */
function siteDebut(string $titre, array $options = []): void
{
    setSecurityHeaders();
    $description = (string) ($options['description'] ?? "Outsee, l'application des sorties étudiantes : soirées, sport et culture, au même endroit.");
    $classe      = (string) ($options['classe'] ?? '');

    echo "<!DOCTYPE html>\n<html lang=\"fr\">\n<head>\n";
    echo "<meta charset=\"UTF-8\">\n";
    echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
    echo '<title>' . htmlspecialchars($titre) . "</title>\n";
    echo '<meta name="description" content="' . htmlspecialchars($description, ENT_QUOTES) . "\">\n";
    echo '<meta name="theme-color" content="#111116">' . "\n";
    echo metaCsrf() . "\n" . metaBase() . "\n";
    echo '<link rel="preload" href="' . htmlspecialchars(baseUrl('/assets/fonts/Onest_400Regular.ttf'), ENT_QUOTES) . '" as="font" type="font/ttf" crossorigin>' . "\n";
    echo '<link rel="stylesheet" href="' . htmlspecialchars(asset('/assets/css/site.css'), ENT_QUOTES) . "\">\n";
    foreach ((array) ($options['styles'] ?? []) as $feuille) {
        echo '<link rel="stylesheet" href="' . htmlspecialchars(asset((string) $feuille), ENT_QUOTES) . "\">\n";
    }
    echo '<link rel="icon" type="image/png" href="' . htmlspecialchars(asset('/Logo.png'), ENT_QUOTES) . "\">\n";
    foreach ((array) ($options['scripts'] ?? []) as $script) {
        echo '<script src="' . htmlspecialchars(asset((string) $script), ENT_QUOTES) . "\" defer></script>\n";
    }
    echo '<script src="' . htmlspecialchars(asset('/assets/js/site.js'), ENT_QUOTES) . "\" defer></script>\n";
    echo "</head>\n<body" . ($classe !== '' ? ' class="' . htmlspecialchars($classe, ENT_QUOTES) . '"' : '') . ">\n";
}

function siteFin(): void
{
    echo "\n</body>\n</html>\n";
}

/** Le pied de page public : liens légaux. */
function piedSite(): string
{
    $liens = [
        '/mentions-legales.php' => 'Mentions légales',
        '/confidentialite.php'  => 'Confidentialité',
        '/cgu.php'              => 'Conditions d\'utilisation',
        '/cgv.php'              => 'Conditions de vente',
        '/auth/login.php'       => 'Espace établissement',
    ];
    $html = '';
    foreach ($liens as $chemin => $libelle) {
        $html .= '<a href="' . htmlspecialchars(baseUrl($chemin), ENT_QUOTES) . '">' . htmlspecialchars($libelle) . '</a>';
    }

    return '<footer class="lp-pied">'
         . '<div class="lp-pied__haut">' . marqueSite('', baseUrl('/'))
         . '<nav aria-label="Liens du site">' . $html . '</nav></div>'
         . '<p class="lp-pied__bas">&copy; ' . date('Y') . ' Outsee. Les sorties étudiantes, au même endroit.</p>'
         . '</footer>';
}

// ─── Espace établissement ─────────────────────────────────────────────────

/**
 * L'établissement du compte connecté (relation 1 compte ↔ 1 établissement).
 *
 * @return array<string, mixed>|null
 */
function etablissementConnecte(PDO $pdo): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM etablissements WHERE user_id = ? LIMIT 1');
    $stmt->execute([(int) ($_SESSION['user_id'] ?? 0)]);

    return $stmt->fetch() ?: null;
}

/**
 * Ouvre une page de l'espace pro : compte partenaire, établissement, formule
 * valable. Équivalent web de exigerAbonnementValide() (api/v1) : sans
 * formule, ou BDE pas encore validé, on est renvoyé vers la page Formule,
 * qui explique la situation.
 *
 * @return array<string, mixed> l'établissement
 */
function ouvrirEspacePro(PDO $pdo, bool $formuleRequise = true): array
{
    requirePartner();
    $etab = etablissementConnecte($pdo);
    if (!$etab) {
        // Compte partenaire sans établissement (ancien compte incomplet).
        header('Location: ' . baseUrl('/partenaire/abonnement.php'));
        exit;
    }
    if ($formuleRequise) {
        require_once __DIR__ . '/comptes.php';
        if (etatAbonnement($pdo, $etab) !== '') {
            header('Location: ' . baseUrl('/partenaire/abonnement.php'));
            exit;
        }
    }

    return $etab;
}

/**
 * Message à afficher une fois, posé avant une redirection.
 *
 * @return array{texte: string, type: string}|null
 */
function flashPro(?string $texte = null, string $type = 'succes'): ?array
{
    if ($texte !== null) {
        $_SESSION['flash_pro'] = ['texte' => $texte, 'type' => $type];

        return null;
    }
    $f = $_SESSION['flash_pro'] ?? null;
    unset($_SESSION['flash_pro']);

    return is_array($f) ? $f : null;
}

/** Le message flash éventuel, mis en forme. */
function messageFlashPro(): string
{
    // Refus CSRF posé par csrfVerify() (formulaire resté ouvert trop longtemps).
    if (!empty($_SESSION['csrf_error'])) {
        flashPro((string) $_SESSION['csrf_error'], 'erreur');
        unset($_SESSION['csrf_error']);
    }
    $f = flashPro();
    if (!$f) {
        return '';
    }
    $classe = $f['type'] === 'erreur' ? 'message--erreur' : ($f['type'] === 'alerte' ? 'message--alerte' : 'message--succes');
    $ico    = $f['type'] === 'erreur' || $f['type'] === 'alerte' ? 'alerte' : 'check';

    return '<div class="message ' . $classe . '" role="status" style="margin-bottom:20px;">' . iconeSite($ico) . '<span>' . htmlspecialchars((string) $f['texte']) . '</span></div>';
}

/**
 * Les entrées du menu de l'espace pro : clé => [chemin, libellé, icône].
 *
 * @return array<string, array{0: string, 1: string, 2: string}>
 */
function menuPro(): array
{
    return [
        'tableau'    => ['/partenaire/dashboard.php', 'Tableau de bord', 'tableau'],
        'evenements' => ['/partenaire/evenements.php', 'Mes événements', 'calendrier'],
        'creer'      => ['/partenaire/create_event.php', 'Créer un événement', 'plus-rond'],
        'photos'     => ['/partenaire/photos.php', 'Photos', 'photo'],
        'abonnement' => ['/partenaire/abonnement.php', 'Formule et achats', 'carte'],
    ];
}

/**
 * Début d'une page de l'espace établissement : menu latéral, barre mobile.
 *
 * @param array<string, mixed>|null $etab
 * @param array{scripts?:list<string>} $options
 */
function proDebut(string $titre, string $actif, ?array $etab, array $options = []): void
{
    siteDebut($titre . ' — Outsee pro', ['classe' => 'corps-pro', 'scripts' => $options['scripts'] ?? []]);

    $nom    = (string) ($etab['nom'] ?? 'Mon établissement');
    $ville  = (string) ($etab['ville'] ?? '');
    $init   = mb_strtoupper(mb_substr($nom, 0, 1));

    echo '<div class="pro" id="pro">';
    echo '<header class="pro-haut">' . marqueSite('pro', baseUrl('/partenaire/dashboard.php'))
       . '<button type="button" class="bouton-icone" data-menu-pro aria-controls="pro-cote" aria-expanded="false">'
       . iconeSite('menu', '', 'Ouvrir le menu') . '</button></header>';
    echo '<div class="pro-voile" data-menu-pro></div>';

    echo '<aside class="pro-cote" id="pro-cote">';
    echo marqueSite('pro', baseUrl('/partenaire/dashboard.php'));
    echo '<div class="pro-etab"><div class="pro-etab__initiale">' . htmlspecialchars($init) . '</div>'
       . '<div><div class="pro-etab__nom">' . htmlspecialchars($nom) . '</div>'
       . ($ville !== '' ? '<div class="pro-etab__ville">' . htmlspecialchars($ville) . '</div>' : '')
       . '</div></div>';

    echo '<nav class="pro-nav" aria-label="Espace établissement"><div class="pro-nav__titre">Gérer</div>';
    foreach (menuPro() as $cle => [$chemin, $libelle, $ico]) {
        $estActif = $cle === $actif;
        echo '<a href="' . htmlspecialchars(baseUrl($chemin), ENT_QUOTES) . '"' . ($estActif ? ' class="actif" aria-current="page"' : '') . '>'
           . iconeSite($ico) . htmlspecialchars($libelle) . '</a>';
    }
    echo '</nav>';

    echo '<div class="pro-cote__bas">'
       . '<a href="' . htmlspecialchars(baseUrl('/'), ENT_QUOTES) . '">' . iconeSite('oeil') . 'Voir le site</a>'
       . '<a href="' . htmlspecialchars(baseUrl('/auth/logout.php'), ENT_QUOTES) . '">' . iconeSite('sortie') . 'Se déconnecter</a>'
       . '</div>';
    echo '</aside>';

    echo '<main class="pro-main" id="contenu"><div class="pro-contenu">';
}

function proFin(): void
{
    echo '</div></main></div>';
    siteFin();
}

/**
 * La modale de scan des pass, partagée par le tableau de bord et la liste
 * des événements. Elle s'ouvre depuis tout bouton [data-scanner]. Charger
 * avec les scripts html5-qrcode et scan-pro.js (voir SCRIPTS_SCAN_PRO).
 */
function modaleScanPro(): string
{
    return '<div class="modale" id="modale-scan" role="dialog" aria-modal="true" aria-labelledby="t-scan">'
         . '<div class="modale__boite">'
         . '<div class="modale__tete"><div><h2 id="t-scan">Scanner un pass</h2><p id="scan-titre" style="color:var(--gris-pale);font-size:14px;"></p></div>'
         . '<button type="button" class="bouton-icone" id="fermer-scan" style="background:transparent;color:#fff;border-color:rgba(255,255,255,.2);">' . iconeSite('croix', '', 'Fermer') . '</button></div>'
         . '<div class="lecteur" id="lecteur"></div>'
         . '<div class="scan-resultat" id="scan-resultat" role="status" aria-live="polite"></div>'
         . '<div style="margin-top:20px;"><label for="scan-recherche" style="display:block;font-size:14px;font-weight:600;margin-bottom:8px;">Le scan ne passe pas ? Validez à la main</label>'
         . '<input type="search" id="scan-recherche" class="saisie" style="background:var(--basalte-3);border-color:var(--basalte-3);color:#fff;" placeholder="Prénom ou nom" autocomplete="off">'
         . '<div id="scan-liste" style="margin-top:10px;display:grid;gap:8px;"></div></div>'
         . '</div></div>';
}

/** Les scripts dont la modale de scan a besoin. */
const SCRIPTS_SCAN_PRO = ['/assets/vendor/html5-qrcode.min.js', '/assets/js/scan-pro.js'];

/** Les initiales d'une personne, pour l'avatar sans photo. */
function initialesSite(string $prenom, string $nom = ''): string
{
    return mb_strtoupper(mb_substr($prenom, 0, 1) . mb_substr($nom, 0, 1));
}

/** Un montant à la française : « 49,99 € », « 70 € ». */
function eurosSite(float $montant): string
{
    $arrondi = round($montant, 2);

    return (floor($arrondi) == $arrondi ? number_format($arrondi, 0, ',', ' ') : number_format($arrondi, 2, ',', ' ')) . ' €';
}
