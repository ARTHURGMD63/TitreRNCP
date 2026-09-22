# ============================================================
#  StudentLink — demarrer l'application pour Expo Go
#
#  Lancer depuis PowerShell, dans le dossier mobile :
#
#      .\demarrer.ps1
#
#  Le QR code s'affiche : le scanner avec l'appareil photo de l'iPhone
#  (Expo Go doit etre installe depuis l'App Store).
#
#  ── POURQUOI CE SCRIPT PLUTOT QUE « npx expo start » ────────────────────
#
#  Cette machine a sept interfaces reseau : Wi-Fi, deux cartes VMware, une
#  VirtualBox, deux ponts Hyper-V/WSL, plus le point d'acces mobile quand il
#  est actif. Expo en choisit une — et rien ne garantit que ce soit celle que
#  le telephone peut joindre. Un QR code pointant vers 192.168.56.1
#  (VirtualBox) donne « impossible de se connecter au serveur de
#  developpement », sans rien dire de la raison.
#
#  Ce script choisit l'interface explicitement, verifie que l'API repond
#  dessus, et la passe a Expo.
# ============================================================

$ErrorActionPreference = 'Stop'

# ─── 1. Trouver l'adresse que le telephone pourra joindre ───────────────────
#
# Par ordre de preference : le point d'acces mobile d'abord — c'est le seul
# reseau ou les appareils se voient a coup sur —, le Wi-Fi ensuite.
#
# Les interfaces virtuelles sont ecartees par leur nom : aucune n'est
# joignable depuis un telephone, et ce sont precisement celles qu'Expo a
# tendance a choisir.
$virtuelles = 'vEthernet|VMware|VirtualBox|Loopback|Hyper-V|Bluetooth'

$candidates = Get-NetIPAddress -AddressFamily IPv4 |
    Where-Object {
        $_.IPAddress -notlike '127.*' -and
        $_.IPAddress -notlike '169.254.*' -and
        $_.InterfaceAlias -notmatch $virtuelles
    }

# Le point d'acces mobile de Windows sert toujours en 192.168.137.x.
$hotspot = $candidates | Where-Object { $_.IPAddress -like '192.168.137.*' } | Select-Object -First 1
$wifi    = $candidates | Where-Object { $_.InterfaceAlias -like '*Wi-Fi*' }   | Select-Object -First 1

$choisie = if ($hotspot) { $hotspot } elseif ($wifi) { $wifi } else { $candidates | Select-Object -First 1 }

if (-not $choisie) {
    Write-Host "Aucune interface reseau utilisable." -ForegroundColor Red
    Write-Host "Activer le point d'acces mobile (Win+A -> Point d'acces sans fil) puis relancer."
    exit 1
}

$ip = $choisie.IPAddress

if ($hotspot) {
    Write-Host "Point d'acces mobile detecte : $ip" -ForegroundColor Green
} else {
    Write-Host "Adresse retenue : $ip ($($choisie.InterfaceAlias))" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "  ATTENTION : ce n'est pas un point d'acces mobile." -ForegroundColor Yellow
    Write-Host "  Si le reseau isole les appareils entre eux — ce qui est le cas de"
    Write-Host "  WIFI_Bonjour_World — le telephone ne joindra pas cette machine."
    Write-Host "  Dans ce cas : Win+A, activer « Point d'acces sans fil », y connecter"
    Write-Host "  le telephone, puis relancer ce script."
    Write-Host ""
}

# ─── 2. L'API repond-elle sur cette adresse ? ───────────────────────────────
#
# Verifie AVANT d'afficher le QR code. Sans cela, l'application se charge,
# affiche son ecran de connexion, et echoue au premier appel — un symptome
# qui ressemble a un bug de l'application alors que le serveur web est
# simplement arrete.
$urlApi = "http://${ip}:8080/TitreRNCP/auth/login.php"
try {
    $reponse = Invoke-WebRequest -Uri $urlApi -TimeoutSec 5 -UseBasicParsing
    Write-Host "API joignable sur $ip`:8080 (HTTP $($reponse.StatusCode))" -ForegroundColor Green
} catch {
    Write-Host "L'API ne repond pas sur $urlApi" -ForegroundColor Red
    Write-Host "  Demarrer Apache depuis WAMP (icone verte), puis relancer."
    Write-Host "  L'application se chargerait, mais ne pourrait pas se connecter."
    exit 1
}

# ─── 3. Lancer Expo sur cette adresse ───────────────────────────────────────
#
# REACT_NATIVE_PACKAGER_HOSTNAME impose l'adresse ecrite dans le QR code et
# dans l'URL exp://. C'est aussi elle que src/api.ts relit pour deduire ou
# joindre l'API : une seule adresse a poser, et tout suit.
$env:REACT_NATIVE_PACKAGER_HOSTNAME = $ip

Write-Host ""
Write-Host "Scanner le QR code ci-dessous avec l'appareil photo de l'iPhone." -ForegroundColor Cyan
Write-Host "Expo Go doit etre installe depuis l'App Store."
Write-Host ""

npx expo start --lan
