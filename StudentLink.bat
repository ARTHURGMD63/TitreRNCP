@echo off
rem ============================================================
rem  StudentLink — lanceur
rem
rem  Double-cliquer sur ce fichier, ou sur le raccourci du Bureau.
rem  Il ouvre une fenetre, verifie que tout est en place, affiche les
rem  adresses, puis le QR code a scanner avec l'appareil photo.
rem
rem  ── DEUX PRECAUTIONS, ET ELLES SERVENT ──────────────────────────────
rem
rem  1. PowerShell est appele par son CHEMIN COMPLET. « powershell » tout
rem     court depend du PATH, et sur cette machine il n'y est pas toujours :
rem     la commande echoue alors sur « le terme powershell n'est pas
rem     reconnu », ce qui ne dit rien du vrai probleme. %SystemRoot% est
rem     defini par Windows lui-meme, il ne peut pas manquer.
rem
rem  2. -ExecutionPolicy Bypass ne vaut QUE pour ce lancement. La politique
rem     de la machine n'est pas modifiee — c'est ce qui evite de la
rem     desactiver globalement pour un seul script local.
rem ============================================================

title StudentLink
cd /d "%~dp0mobile"

"%SystemRoot%\System32\WindowsPowerShell\v1.0\powershell.exe" -NoProfile -ExecutionPolicy Bypass -File "%~dp0mobile\demarrer.ps1"

rem Si le script s'est arrete sur une erreur, la fenetre reste ouverte le
rem temps de lire le message plutot que de se fermer instantanement.
if errorlevel 1 pause
