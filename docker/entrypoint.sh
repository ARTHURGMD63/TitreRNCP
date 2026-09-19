#!/bin/sh
# ============================================================
#  StudentLink — amorçage du conteneur
#
#  Railway, Render et les plateformes du même genre imposent le port
#  d'écoute par la variable PORT, décidée au démarrage. Apache, lui, lit
#  son port dans un fichier de configuration. Ce script fait le pont.
#
#  Sans lui, le conteneur écoute obstinément sur le 80 et la plateforme
#  conclut que l'application ne démarre pas.
# ============================================================
set -e

PORT="${PORT:-8080}"

sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-enabled/000-default.conf

# Les photos envoyées par les utilisateurs sont écrites par le processus
# Apache. Sur une image reconstruite, le dossier peut revenir avec les droits
# de root et les envois échouent silencieusement.
# Rappel, et ce n'est pas un détail : ce dossier vit dans le conteneur. Un
# redéploiement l'efface. Le stockage objet externe reste à faire (SL-12).
mkdir -p /var/www/html/uploads/avatars /var/www/html/uploads/etablissements
chown -R www-data:www-data /var/www/html/uploads

exec "$@"
