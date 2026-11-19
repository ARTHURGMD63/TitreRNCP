#!/usr/bin/env bash
# ============================================================
#  Outsee — réapplique la configuration système d'une version (root).
#
#  installer_serveur.sh ne tourne qu'une fois ; deployer.sh, lui, ne copie
#  que le code. Sans ce script, une ligne ajoutée à deploy/cron/outsee ou
#  un réglage nginx modifié n'arriverait jamais sur le serveur.
#
#  À lancer EN ROOT, à la main, après un déploiement qui change deploy/ :
#      sudo bash /var/www/outsee/current/deploy/appliquer_config.sh <domaine>
#  (deployer.sh signale à la fin les fichiers qui diffèrent du serveur.)
#
#  Volontairement absent du sudoers du compte « deploy » : ce script écrit
#  /etc/cron.d et /usr/local/sbin, donc qui peut le lancer et modifier les
#  fichiers qu'il copie obtient root. Il ne doit être lancé que par une
#  personne, sur une version dont le contenu de deploy/ a été relu.
#
#  Ce qu'il réinstalle, depuis le dossier deploy/ où il se trouve :
#    - la sauvegarde (/usr/local/sbin/outsee-sauvegarde, root:root 755) ;
#    - les tâches planifiées (/etc/cron.d/outsee) ;
#    - la rotation des journaux (/etc/logrotate.d/outsee) ;
#    - PHP-FPM (99-outsee.ini, pool outsee) ;
#    - nginx (site, snippets, réglages globaux), contrôlé par nginx -t.
#  Rien d'autre : ni la base, ni config.local.php, ni le certificat.
# ============================================================
set -euo pipefail

DOMAINE="${1:?Usage : appliquer_config.sh <domaine>}"
ICI="$(cd "$(dirname "$0")" && pwd)"

[ "$(id -u)" -eq 0 ] || { echo "À lancer en root (sudo)." >&2; exit 1; }
[ -f "/etc/letsencrypt/live/$DOMAINE/fullchain.pem" ] || {
    echo "Certificat introuvable pour $DOMAINE : lancer d'abord installer_serveur.sh." >&2
    exit 1
}

echo "== Dossiers"
install -d -o www-data -g www-data -m 750 /var/log/outsee
# Journal de la sauvegarde : root l'écrit, donc dans un dossier à root. Dans
# /var/log/outsee (www-data), le compte web pouvait remplacer le fichier par
# un lien symbolique et faire écrire root où il voulait (audit DEP-02).
install -d -o root -g root -m 750 /var/log/outsee-sauvegarde
install -d -o root -g root -m 700 /var/backups/outsee
install -d -o root -g root -m 755 /var/lib/outsee
install -d -o root -g root -m 700 /etc/outsee

echo "== Sauvegarde"
# Copie root:root hors de /var/www/outsee : le cron root ne lit jamais un
# fichier que le compte deploy peut réécrire.
install -o root -g root -m 755 "$ICI/sauvegarder.sh" /usr/local/sbin/outsee-sauvegarde

echo "== Tâches planifiées"
install -o root -g root -m 644 "$ICI/cron/outsee" /etc/cron.d/outsee

echo "== Rotation des journaux"
install -o root -g root -m 644 "$ICI/logrotate/outsee" /etc/logrotate.d/outsee

echo "== PHP"
install -o root -g root -m 644 "$ICI/php/99-outsee.ini" /etc/php/8.3/fpm/conf.d/99-outsee.ini
install -o root -g root -m 644 "$ICI/php/outsee-fpm.conf" /etc/php/8.3/fpm/pool.d/outsee.conf
rm -f /etc/php/8.3/fpm/pool.d/www.conf
php-fpm8.3 -t
systemctl reload php8.3-fpm

echo "== nginx"
install -o root -g root -m 644 "$ICI/nginx/outsee-php.conf" /etc/nginx/snippets/outsee-php.conf
install -o root -g root -m 644 "$ICI/nginx/outsee-entetes.conf" /etc/nginx/snippets/outsee-entetes.conf
sed "s|__DOMAINE__|${DOMAINE}|g" "$ICI/nginx/outsee.conf" > /etc/nginx/sites-available/outsee.conf
chmod 644 /etc/nginx/sites-available/outsee.conf
ln -sf /etc/nginx/sites-available/outsee.conf /etc/nginx/sites-enabled/outsee.conf
rm -f /etc/nginx/sites-enabled/default
sed -i 's/worker_connections [0-9]*;/worker_connections 8192;/' /etc/nginx/nginx.conf
grep -q 'worker_rlimit_nofile' /etc/nginx/nginx.conf || sed -i '1i worker_rlimit_nofile 65535;' /etc/nginx/nginx.conf
cat > /etc/nginx/conf.d/outsee-gzip.conf <<'EOF'
gzip on;
gzip_comp_level 5;
gzip_min_length 1024;
gzip_vary on;
gzip_proxied any;
gzip_types application/json text/css application/javascript image/svg+xml text/plain;
keepalive_timeout 30s;
keepalive_requests 1000;
open_file_cache max=10000 inactive=60s;
EOF
nginx -t
systemctl reload nginx

echo "Configuration de $ICI appliquée."
