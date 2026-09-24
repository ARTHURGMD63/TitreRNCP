# ============================================================
#  Linkee — installer la base sur un serveur distant (TiDB Cloud…)
#
#  Depuis PowerShell, a la racine du projet :
#
#    .\outils\installer_distant.ps1 -Hote gateway01.eu-central-1.prod.aws.tidbcloud.com -Utilisateur xxxxxxxx.root
#
#  Le mot de passe est demande de facon masquee : il n'apparait ni a
#  l'ecran, ni dans l'historique de PowerShell, ni dans aucun fichier.
#
#  Etapes : creation de la base si elle manque, installation du schema et
#  du jeu de demonstration (outils/installer.php), puis etat des migrations.
# ============================================================

param(
    [Parameter(Mandatory = $true)] [string] $Hote,
    [Parameter(Mandatory = $true)] [string] $Utilisateur,
    [string] $Port = '4000',
    [string] $Base = 'linkee',
    [string] $Certificat = 'C:\Program Files\Git\mingw64\etc\ssl\certs\ca-bundle.crt'
)

$ErrorActionPreference = 'Stop'
$racine = Split-Path -Parent $PSScriptRoot

$php = Get-ChildItem 'C:\wamp64\bin\php\php8*\php.exe' -ErrorAction SilentlyContinue |
    Sort-Object FullName -Descending | Select-Object -First 1
if (-not $php) { throw "PHP introuvable dans C:\wamp64\bin\php." }

if (-not (Test-Path $Certificat)) {
    throw "Certificat introuvable : $Certificat. Donner le chemin d'isrgrootx1.pem avec -Certificat."
}

$secret = Read-Host "Mot de passe de la base" -AsSecureString
$bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secret)
try {
    $env:MYSQLPASSWORD = [Runtime.InteropServices.Marshal]::PtrToStringAuto($bstr)
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($bstr)
}

$env:MYSQLHOST     = $Hote
$env:MYSQLPORT     = $Port
$env:MYSQLUSER     = $Utilisateur
$env:MYSQLDATABASE = $Base
$env:MYSQL_SSL_CA  = $Certificat

try {
    Write-Host "`n1. La base" -ForegroundColor Cyan
    & $php.FullName "$racine\outils\creer_base.php"
    if ($LASTEXITCODE -ne 0) { throw "Arret : la base n'a pas pu etre creee." }

    Write-Host "`n2. Le schema et les donnees de demonstration" -ForegroundColor Cyan
    & $php.FullName "$racine\outils\installer.php"
    if ($LASTEXITCODE -ne 0) { throw "Arret : l'installation a echoue (message ci-dessus)." }

    Write-Host "`n3. Verification" -ForegroundColor Cyan
    & $php.FullName "$racine\outils\migrer.php" --etat
} finally {
    # Le mot de passe ne survit pas au script.
    Remove-Item Env:MYSQLPASSWORD -ErrorAction SilentlyContinue
}
