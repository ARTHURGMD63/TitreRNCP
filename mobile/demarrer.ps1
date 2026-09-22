# ============================================================
#  StudentLink — lanceur de l'environnement de developpement
#
#  Double-cliquer sur « StudentLink.bat » (racine du projet) ou sur le
#  raccourci du Bureau. Sinon, depuis PowerShell :
#
#      cd C:\wamp64\www\TitreRNCP\mobile; .\demarrer.ps1
#
#  Ce que le script fait, dans l'ordre :
#
#    1. verifie qu'Apache et MySQL tournent ;
#    2. choisit l'adresse reseau que le telephone pourra joindre ;
#    3. verifie que l'API repond reellement dessus ;
#    4. affiche les adresses — site web ET application ;
#    5. lance Expo, QR code a l'appui.
#
#  ── POURQUOI IL EXISTE ──────────────────────────────────────────────────
#
#  Cette machine a sept interfaces reseau : Wi-Fi, deux cartes VMware, une
#  VirtualBox, deux ponts Hyper-V/WSL, plus le point d'acces mobile quand il
#  est actif. « npx expo start » en choisit une — mesure, il annonce
#  « localhost » — et rien ne garantit que ce soit celle que le telephone
#  peut joindre. Un QR code pointant vers 192.168.56.1 donne « impossible de
#  se connecter au serveur de developpement », sans dire pourquoi.
#
#  Le script choisit explicitement, verifie, et le dit.
# ============================================================

$ErrorActionPreference = 'Stop'
Set-Location -Path $PSScriptRoot

function Titre($texte) {
    Write-Host ""
    Write-Host "  $texte" -ForegroundColor Cyan
    Write-Host "  $('-' * $texte.Length)" -ForegroundColor DarkGray
}

Write-Host ""
Write-Host "  StudentLink " -NoNewline -ForegroundColor White
Write-Host "/ environnement de developpement" -ForegroundColor Red

# ─── 1. Les services WAMP ───────────────────────────────────────────────────
#
# Apache sert le site et l'API, MySQL porte les donnees. Sans eux,
# l'application se charge, affiche sa connexion, et echoue au premier appel —
# un symptome qui ressemble a un bug de l'application.

Titre "Services"

$apache = Get-Process httpd  -ErrorAction SilentlyContinue
$mysql  = Get-Process mysqld -ErrorAction SilentlyContinue

if ($apache) {
    Write-Host "    Apache  " -NoNewline; Write-Host "en marche" -ForegroundColor Green
} else {
    Write-Host "    Apache  " -NoNewline; Write-Host "ARRETE" -ForegroundColor Red
}
if ($mysql) {
    Write-Host "    MySQL   " -NoNewline; Write-Host "en marche" -ForegroundColor Green
} else {
    Write-Host "    MySQL   " -NoNewline; Write-Host "ARRETE" -ForegroundColor Red
}

if (-not $apache -or -not $mysql) {
    Write-Host ""
    Write-Host "    Demarrer WAMP (icone verte dans la barre des taches)," -ForegroundColor Yellow
    Write-Host "    puis relancer ce script." -ForegroundColor Yellow
    Write-Host ""
    Read-Host "    Entree pour fermer"
    exit 1
}

# ─── 2. L'adresse que le telephone pourra joindre ───────────────────────────
#
# Par ordre de preference : le point d'acces mobile — le seul reseau ou les
# appareils se voient a coup sur —, le Wi-Fi ensuite. Les interfaces
# virtuelles sont ecartees par leur nom : aucune n'est joignable depuis un
# telephone, et ce sont precisement celles qu'Expo a tendance a choisir.

Titre "Reseau"

$virtuelles = 'vEthernet|VMware|VirtualBox|Loopback|Hyper-V|Bluetooth'

$candidates = Get-NetIPAddress -AddressFamily IPv4 |
    Where-Object {
        $_.IPAddress -notlike '127.*' -and
        $_.IPAddress -notlike '169.254.*' -and
        $_.InterfaceAlias -notmatch $virtuelles
    }

$hotspot = $candidates | Where-Object { $_.IPAddress -like '192.168.137.*' } | Select-Object -First 1
$wifi    = $candidates | Where-Object { $_.InterfaceAlias -like '*Wi-Fi*' }   | Select-Object -First 1
$choisie = if ($hotspot) { $hotspot } elseif ($wifi) { $wifi } else { $candidates | Select-Object -First 1 }

if (-not $choisie) {
    Write-Host "    Aucune interface reseau utilisable." -ForegroundColor Red
    Write-Host "    Activer le partage de connexion (Win+A -> Point d'acces sans fil)."
    Read-Host "    Entree pour fermer"
    exit 1
}

$ip = $choisie.IPAddress

if ($hotspot) {
    Write-Host "    Partage de connexion " -NoNewline
    Write-Host "ACTIF" -ForegroundColor Green -NoNewline
    Write-Host " — $ip"
    Write-Host "    Les telephones connectes dessus pourront ouvrir l'application."
} else {
    Write-Host "    Partage de connexion " -NoNewline
    Write-Host "ETEINT" -ForegroundColor Yellow
    Write-Host "    Adresse retenue : $ip ($($choisie.InterfaceAlias))"
    Write-Host ""
    Write-Host "    Si ce reseau isole les appareils entre eux — c'est le cas de" -ForegroundColor Yellow
    Write-Host "    WIFI_Bonjour_World — le telephone ne joindra pas cette machine." -ForegroundColor Yellow
    Write-Host "    Activer le partage de connexion : Win+A, « Point d'acces sans fil »." -ForegroundColor Yellow
}

# ─── 3. L'API repond-elle sur cette adresse ? ───────────────────────────────
#
# Verifie AVANT d'afficher le QR code, pour que l'echec soit nomme ici plutot
# que decouvert sur le telephone.

Titre "API"

$urlSite = "http://${ip}:8080/TitreRNCP/"
try {
    $r = Invoke-WebRequest -Uri "${urlSite}auth/login.php" -TimeoutSec 5 -UseBasicParsing
    Write-Host "    Joignable sur $ip`:8080 " -NoNewline
    Write-Host "(HTTP $($r.StatusCode))" -ForegroundColor Green
} catch {
    Write-Host "    INJOIGNABLE sur ${ip}:8080" -ForegroundColor Red
    Write-Host ""
    Write-Host "    Apache tourne, mais refuse cette adresse. Verifier que le vhost" -ForegroundColor Yellow
    Write-Host "    autorise ce reseau (Require ip ...) dans :" -ForegroundColor Yellow
    Write-Host "      C:\wamp64\bin\apache\apache2.4.62.1\conf\extra\httpd-vhosts.conf" -ForegroundColor DarkGray
    Write-Host ""
    Read-Host "    Entree pour fermer"
    exit 1
}

# ─── 4. Les adresses ────────────────────────────────────────────────────────

Titre "Adresses"
Write-Host "    Site web      " -NoNewline; Write-Host $urlSite -ForegroundColor White
Write-Host "    Application   " -NoNewline; Write-Host "scanner le QR ci-dessous avec l'appareil photo" -ForegroundColor White
Write-Host ""
Write-Host "    Comptes de demonstration :" -ForegroundColor DarkGray
Write-Host "      etudiant    arthur@uca.fr / password" -ForegroundColor DarkGray
Write-Host "      partenaire  jean@lebecquipique.fr / password" -ForegroundColor DarkGray

# ─── 5. Expo ────────────────────────────────────────────────────────────────
#
# REACT_NATIVE_PACKAGER_HOSTNAME impose l'adresse ecrite dans le QR code et
# dans l'URL exp://. C'est aussi elle que src/api.ts relit pour deduire ou
# joindre l'API : une seule adresse a poser, et tout suit.

$env:REACT_NATIVE_PACKAGER_HOSTNAME = $ip

Titre "Expo"
Write-Host "    Expo Go doit etre installe sur le telephone (App Store)."
Write-Host "    Ctrl+C pour arreter."
Write-Host ""

npx expo start --lan
