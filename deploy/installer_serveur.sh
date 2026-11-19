#!/usr/bin/env bash
# ============================================================
#  Outsee — préparation du VPS OVH (Ubuntu 24.04 LTS).
#
#  À lancer en root sur le VPS neuf, depuis une copie de ce dossier deploy/ :
#      sudo bash installer_serveur.sh outsee.fr arthur@exemple.fr
#
#  Peut être relancé sans danger : le mot de passe de la base n'est pas
#  changé si config.local.php existe déjà, et le certificat n'est pas
#  redemandé s'il existe. Pour seulement réappliquer cron, nginx, PHP,
#  logrotate et la sauvegarde après un déploiement : appliquer_config.sh,
#  que ce script appelle aussi.
#
#  Ce que fait le script, et rien d'autre :
#    - pare-feu (SSH, HTTP, HTTPS), fail2ban, mises à jour de sécurité auto ;
#    - nginx, PHP 8.3-FPM (+ APCu, GD, cURL…), MariaDB, certbot ;
#    - un compte « deploy » pour GitHub Actions, sans mot de passe ;
#    - l'arborescence /var/www/outsee/{releases,shared,current} ;
#    - la base « outsee » et son utilisateur aux droits limités ;
#    - le certificat HTTPS Let's Encrypt ;
#    - les fichiers de configuration de deploy/ (MariaDB ici ; nginx, PHP,
#      cron, logrotate et la sauvegarde par appliquer_config.sh).
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
    ufw fail2ban unattended-upgrades rsync acl logrotate gnupg

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
# Le déploiement recharge PHP-FPM, et rien d'autre. Ne JAMAIS ajouter ici
# appliquer_config.sh ni un script de /var/www/outsee : le compte deploy peut
# réécrire ces fichiers, ce serait lui donner root.
echo 'deploy ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.3-fpm' > /etc/sudoers.d/outsee-deploy
chmod 440 /etc/sudoers.d/outsee-deploy
visudo -cf /etc/sudoers.d/outsee-deploy

echo "== Arborescence"
install -d -o deploy -g www-data -m 2775 "$RACINE" "$RACINE/releases" "$RACINE/shared"
install -d -o www-data -g www-data -m 2775 \
    "$RACINE/shared/uploads" "$RACINE/shared/uploads/avatars" \
    "$RACINE/shared/uploads/etablissements" "$RACINE/shared/uploads/evenements"
install -d -o www-data -g www-data -m 750 /var/log/outsee
# Journal de la sauvegarde : root l'écrit, donc dans un dossier à root. Dans
# /var/log/outsee (www-data), le compte web pouvait remplacer le fichier par
# un lien symbolique et faire écrire root où il voulait (audit DEP-02).
install -d -o root -g root -m 750 /var/log/outsee-sauvegarde
# Sauvegardes : root seul. Leur date, elle, va dans un témoin lisible par
# PHP (/var/lib/outsee/derniere_sauvegarde) pour la sonde de santé.
install -d -o root -g root -m 700 /var/backups/outsee
install -d -o root -g root -m 755 /var/lib/outsee
# Réglages de la copie distante des sauvegardes (sauvegarde.conf).
install -d -o root -g root -m 700 /etc/outsee
install -d /var/www/certbot

echo "== Base de données"
cp "$ICI/mariadb/99-outsee.cnf" /etc/mysql/mariadb.conf.d/99-outsee.cnf
systemctl restart mariadb
CONFIG="$RACINE/shared/config.local.php"
mariadb -e "CREATE DATABASE IF NOT EXISTS outsee CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
if [ -f "$CONFIG" ]; then
    # Relance : le mot de passe en service est gardé tel quel. Le changer
    # couperait l'application jusqu'à ce que quelqu'un le reporte à la main.
    MDP_BASE="$(php -r '$c = require $argv[1]; echo is_array($c) ? (string) ($c["pass"] ?? "") : "";' "$CONFIG")"
    [ -n "$MDP_BASE" ] || { echo "Aucun mot de passe ('pass') dans $CONFIG." >&2; exit 1; }
    echo "   $CONFIG existe : mot de passe de la base inchangé."
else
    MDP_BASE="$(openssl rand -base64 30 | tr -d '/+=' | cut -c1-32)"
fi
# Barres obliques inverses et apostrophes doublées pour la chaîne SQL (un
# mot de passe saisi à la main dans config.local.php peut en contenir).
MDP_SQL="$(printf '%s' "$MDP_BASE" | sed -e 's/\\/\\\\/g' -e "s/'/''/g")"
mariadb <<SQL
CREATE USER IF NOT EXISTS 'outsee'@'localhost' IDENTIFIED BY '${MDP_SQL}';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES,
      CREATE TEMPORARY TABLES, LOCK TABLES, CREATE ROUTINE, ALTER ROUTINE, EXECUTE
   ON outsee.* TO 'outsee'@'localhost';
FLUSH PRIVILEGES;
SQL

echo "== Configuration de l'application"
if [ ! -f "$CONFIG" ]; then
    # Le compte peut venir d'une installation précédente sans
    # config.local.php : il reçoit le mot de passe qu'on va y écrire.
    mariadb -e "ALTER USER 'outsee'@'localhost' IDENTIFIED BY '${MDP_SQL}';"
    sed -e "s|__DOMAINE__|${DOMAINE}|g" -e "s|__MOT_DE_PASSE_BASE__|${MDP_BASE}|g" \
        "$ICI/config.local.production.example.php" > "$CONFIG"
    chown deploy:www-data "$CONFIG"
    chmod 640 "$CONFIG"
    echo "   $CONFIG créé : compléter Stripe, SMTP et alerte_email avant l'ouverture."
else
    echo "   $CONFIG existe déjà : laissé tel quel."
fi

echo "== Certificat HTTPS"
if [ ! -f "/etc/letsencrypt/live/$DOMAINE/fullchain.pem" ]; then
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
else
    echo "   certificat déjà présent pour $DOMAINE."
fi
touch /etc/letsencrypt/cli.ini
grep -q '^deploy-hook' /etc/letsencrypt/cli.ini || echo 'deploy-hook = systemctl reload nginx' >> /etc/letsencrypt/cli.ini

echo "== PHP, nginx, tâches planifiées, journaux, sauvegarde"
bash "$ICI/appliquer_config.sh" "$DOMAINE"

echo
echo "Serveur prêt. Il reste :"
echo "  1. coller la clé publique de déploiement GitHub dans /home/deploy/.ssh/authorized_keys ;"
echo "  2. compléter $CONFIG (Stripe, SMTP, alerte_email) ;"
echo "  3. importer la base (docs/DEPLOIEMENT_VPS_OVH.md, étape 5) ;"
echo "  4. régler la copie distante des sauvegardes (/etc/outsee/sauvegarde.conf,"
echo "     docs/DEPLOIEMENT_VPS_OVH.md, étape 9) ;"
echo "  5. lancer le workflow « Déploiement VPS » depuis GitHub."
