#!/usr/bin/env bash
# ============================================================
#  Outsee — mise en service d'une version déjà copiée sur le VPS.
#
#  Lancé par le workflow GitHub « Déploiement VPS », en tant que « deploy » :
#      bash /var/www/outsee/releases/<version>/deploy/deployer.sh <version>
#
#  1. relie la version aux données partagées (photos envoyées, réglages) ;
#  2. applique les migrations de base en attente (outils/migrer.php) ;
#  3. bascule le lien `current` d'un coup (aucune requête ne voit un
#     mélange de deux versions) ;
#  4. recharge PHP-FPM (OPcache ne relit pas les fichiers de lui-même) ;
#  5. garde les cinq dernières versions pour revenir en arrière.
#
#  Revenir à la version précédente :
#      ln -sfn /var/www/outsee/releases/<ancienne> /var/www/outsee/current
#      sudo systemctl reload php8.3-fpm
#  (une migration déjà appliquée, elle, reste appliquée.)
# ============================================================
set -euo pipefail

VERSION="${1:?Usage : deployer.sh <version>}"
RACINE=/var/www/outsee
REL="$RACINE/releases/$VERSION"

[ -d "$REL" ] || { echo "Version introuvable : $REL" >&2; exit 1; }
[ -f "$RACINE/shared/config.local.php" ] || { echo "Réglages absents : $RACINE/shared/config.local.php" >&2; exit 1; }

echo "== Liens vers les données partagées"
rm -rf "$REL/uploads"
ln -sfn "$RACINE/shared/uploads" "$REL/uploads"
ln -sfn "$RACINE/shared/config.local.php" "$REL/includes/config.local.php"

echo "== Migrations"
APP_ENV=production php "$REL/outils/migrer.php"

echo "== Bascule"
ln -sfn "$REL" "$RACINE/current.nouveau"
mv -Tf "$RACINE/current.nouveau" "$RACINE/current"
sudo /usr/bin/systemctl reload php8.3-fpm

echo "== Contrôle"
DOMAINE="$(php -r 'echo parse_url((require "'"$RACINE"'/shared/config.local.php")["url_publique"] ?? "", PHP_URL_HOST);')"
if [ -n "$DOMAINE" ]; then
    CODE="$(curl -s -o /dev/null -w '%{http_code}' --resolve "$DOMAINE:443:127.0.0.1" "https://$DOMAINE/api/v1/legal.php?page=cgu")"
    echo "   https://$DOMAINE/api/v1/legal.php → HTTP $CODE"
    if [ "$CODE" != "200" ]; then
        echo "La nouvelle version ne répond pas : retour à la précédente." >&2
        PRECEDENTE="$(ls -1dt "$RACINE"/releases/*/ | sed -n 2p)"
        if [ -n "$PRECEDENTE" ]; then
            ln -sfn "${PRECEDENTE%/}" "$RACINE/current"
            sudo /usr/bin/systemctl reload php8.3-fpm
        fi
        exit 1
    fi
fi

echo "== Ménage (5 versions gardées)"
ls -1dt "$RACINE"/releases/*/ | tail -n +6 | xargs -r rm -rf

echo "Version $VERSION en service."
