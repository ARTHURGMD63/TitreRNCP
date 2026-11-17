#!/usr/bin/env bash
# ============================================================
#  Outsee — préparation du VPS OVH (Ubuntu 24.04 LTS), une seule fois.
#
#  À lancer en root sur le VPS neuf, depuis une copie de ce dossier deploy/ :
#      sudo bash installer_serveur.sh outsee.fr arthur@exemple.fr
#
#  Ce que fait le script, et rien d'autre :
#    - pare-feu (SSH, HTTP, HTTPS), fail2ban, mises à jour de sécurité auto ;
#    - nginx, PHP 8.3-FPM (+ APCu, GD, cURL…), MariaDB, certbot ;
#    - un compte « deploy » pour GitHub Actions, sans mot de passe ;
#    - l'arborescence /var/www/outsee/{releases,shared,current} ;
#    - la base « outsee » et son utilisateur aux droits limités ;
#    - les fichiers de configuration de deploy/ (nginx, PHP, MariaDB, cron) ;
#    - le certificat HTTPS Let's Encrypt.
#
#  Le DNS du domaine (A et AAAA vers le VPS) doit déjà pointer ici, sinon
#  Let's Encrypt échoue : le script s'arrête alors avant nginx en HTTPS.
# ============================================================
set -euo pipefail

DOMAINE="${1:?Usage : installer_serveur.sh <domaine> <email-letsencrypt>}"
EMAIL_CERT="${2:?Usage : installer_serveur.sh <domaine> <email-letsencrypt>}"
ICI="$(cd "$(dirname "$0")" && pwd)"
RACINE=/var/www/outsee

echo "== Système"
timedatectl set-timezone Europe/Paris
apt-get update
DEBIAN_FRONTEND=noninteractive apt-get -y upgrade
DEBIAN_FRONTEND=noninteractive apt-get -y install \
    nginx mariadb-server certbot \
    php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-gd php8.3-curl \
    php8.3-intl php8.3-xml php8.3-zip php8.3-apcu php8.3-opcache \
    ufw fail2ban unattended-upgrades rsync acl

echo "== Pare-feu"
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable
systemctl enable --now fail2ban
dpkg-reconfigure -f noninteractive unattended-upgrades

echo "== Réglages système (files d'attente réseau)"
cat > /etc/sysctl.d/99-outsee.conf <<'EOF'
net.core.somaxconn = 4096
net.ipv4.tcp_max_syn_backlog = 4096
EOF
sysctl --system >/dev/null

echo "== Compte de déploiement"
if ! id deploy >/dev/null 2>&1; then
    adduser --disabled-password --gecos "" deploy
fi
usermod -aG www-data deploy
install -d -m 700 -o deploy -g deploy /home/deploy/.ssh
touch /home/deploy/.ssh/authorized_keys
chown deploy:deploy /home/deploy/.ssh/authorized_keys
chmod 600 /home/deploy/.ssh/authorized_keys
# Le déploiement recharge PHP-FPM, et rien d'autre.
echo 'deploy ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.3-fpm' > /etc/sudoers.d/outsee-deploy
chmod 440 /etc/sudoers.d/outsee-deploy

echo "== Arborescence"
install -d -o deploy -g www-data -m 2775 "$RACINE" "$RACINE/releases" "$RACINE/shared"
install -d -o www-data -g www-data -m 2775 \
    "$RACINE/shared/uploads" "$RACINE/shared/uploads/avatars" \
    "$RACINE/shared/uploads/etablissements" "$RACINE/shared/uploads/evenements"
install -d -o www-data -g www-data -m 750 /var/log/outsee
install -d -m 700 /var/backups/outsee
install -d /var/www/certbot

echo "== Base de données"
cp "$ICI/mariadb/99-outsee.cnf" /etc/mysql/mariadb.conf.d/99-outsee.cnf
systemctl restart mariadb
MDP_BASE="$(openssl rand -base64 30 | tr -d '/+=' | cut -c1-32)"
mariadb <<SQL
CREATE DATABASE IF NOT EXISTS outsee CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'outsee'@'localhost' IDENTIFIED BY '${MDP_BASE}';
ALTER USER 'outsee'@'localhost' IDENTIFIED BY '${MDP_BASE}';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES,
      CREATE TEMPORARY TABLES, LOCK TABLES, CREATE ROUTINE, ALTER ROUTINE, EXECUTE
   ON outsee.* TO 'outsee'@'localhost';
FLUSH PRIVILEGES;
SQL

echo "== Configuration de l'application"
CONFIG="$RACINE/shared/config.local.php"
if [ ! -f "$CONFIG" ]; then
    sed -e "s|__DOMAINE__|${DOMAINE}|g" -e "s|__MOT_DE_PASSE_BASE__|${MDP_BASE}|g" \
        "$ICI/config.local.production.example.php" > "$CONFIG"
    chown deploy:www-data "$CONFIG"
    chmod 640 "$CONFIG"
    echo "   $CONFIG créé : compléter Stripe et SMTP avant l'ouverture."
else
    echo "   $CONFIG existe déjà : le mot de passe de la base vient d'être changé en"
    echo "   ${MDP_BASE} — le reporter dans 'pass'."
fi

echo "== PHP"
cp "$ICI/php/99-outsee.ini" /etc/php/8.3/fpm/conf.d/99-outsee.ini
cp "$ICI/php/outsee-fpm.conf" /etc/php/8.3/fpm/pool.d/outsee.conf
rm -f /etc/php/8.3/fpm/pool.d/www.conf
systemctl restart php8.3-fpm

echo "== Tâches planifiées"
cp "$ICI/cron/outsee" /etc/cron.d/outsee
chmod 644 /etc/cron.d/outsee

echo "== Certificat HTTPS"
rm -f /etc/nginx/sites-enabled/default
cat > /etc/nginx/sites-available/outsee-acme.conf <<EOF
server { listen 80; listen [::]:80; server_name ${DOMAINE} www.${DOMAINE};
         location ^~ /.well-known/acme-challenge/ { root /var/www/certbot; }
         location / { return 404; } }
EOF
ln -sf /etc/nginx/sites-available/outsee-acme.conf /etc/nginx/sites-enabled/outsee-acme.conf
systemctl reload nginx
certbot certonly --webroot -w /var/www/certbot -d "$DOMAINE" -d "www.$DOMAINE" \
    --email "$EMAIL_CERT" --agree-tos --non-interactive
rm -f /etc/nginx/sites-enabled/outsee-acme.conf
echo 'deploy-hook = systemctl reload nginx' >> /etc/letsencrypt/cli.ini

echo "== nginx"
cp "$ICI/nginx/outsee-php.conf" /etc/nginx/snippets/outsee-php.conf
sed "s|__DOMAINE__|${DOMAINE}|g" "$ICI/nginx/outsee.conf" > /etc/nginx/sites-available/outsee.conf
ln -sf /etc/nginx/sites-available/outsee.conf /etc/nginx/sites-enabled/outsee.conf
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

echo
echo "Serveur prêt. Il reste :"
echo "  1. coller la clé publique de déploiement GitHub dans /home/deploy/.ssh/authorized_keys ;"
echo "  2. compléter $CONFIG (Stripe, SMTP) ;"
echo "  3. importer la base (docs/DEPLOIEMENT_VPS_OVH.md, étape 5) ;"
echo "  4. lancer le workflow « Déploiement VPS » depuis GitHub."
