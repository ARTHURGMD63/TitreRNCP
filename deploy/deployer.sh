#!/usr/bin/env bash
# ============================================================
#  Outsee — mise en service d'une version déjà copiée sur le VPS.
#
#  Lancé par le workflow GitHub « Déploiement VPS », en tant que « deploy » :
#      bash /var/www/outsee/releases/<version>/deploy/deployer.sh <version>
#
#  1. relie la version aux données partagées (photos envoyées, réglages) ;
#  2. sauvegarde la base dans shared/sauvegardes_deploiement/ (point de
#     restauration si une migration casse quelque chose) ;
#  3. applique les migrations de base en attente (outils/migrer.php) ;
#  4. bascule le lien `current` d'un coup (aucune requête ne voit un
#     mélange de deux versions) ;
#  5. recharge PHP-FPM (OPcache ne relit pas les fichiers de lui-même) ;
#  6. contrôle la réponse, et revient sinon à la version qui était en
#     service juste avant (lue avec readlink avant la bascule) ;
#  7. garde les cinq dernières versions pour revenir en arrière ;
#  8. signale les fichiers de deploy/ (cron, nginx, PHP, sauvegarde) qui
#     diffèrent de ceux installés : appliquer_config.sh, en root, à la main.
#
#  Revenir à la version précédente :
#      ln -sfn /var/www/outsee/releases/<ancienne> /var/www/outsee/current
#      sudo systemctl reload php8.3-fpm
#  Une migration déjà appliquée, elle, reste appliquée : pour revenir aussi
#  sur la base, restaurer shared/sauvegardes_deploiement/avant_<version>.sql.gz
#  (docs/DEPLOIEMENT_VPS_OVH.md, étape 9).
# ============================================================
set -euo pipefail

VERSION="${1:?Usage : deployer.sh <version>}"
RACINE=/var/www/outsee
REL="$RACINE/releases/$VERSION"
CONFIG="$RACINE/shared/config.local.php"

[ -d "$REL" ] || { echo "Version introuvable : $REL" >&2; exit 1; }
[ -f "$CONFIG" ] || { echo "Réglages absents : $CONFIG" >&2; exit 1; }

# La version en service AVANT la bascule : c'est elle, et pas « l'avant-
# dernier dossier par date », qu'il faut remettre si la nouvelle ne répond
# pas (un dossier plus récent peut être un déploiement raté).
PRECEDENTE="$(readlink -f "$RACINE/current" 2>/dev/null || true)"

echo "== Liens vers les données partagées"
rm -rf "$REL/uploads"
ln -sfn "$RACINE/shared/uploads" "$REL/uploads"
ln -sfn "$CONFIG" "$REL/includes/config.local.php"

echo "== Sauvegarde de la base avant migrations"
SAUVEGARDES="$RACINE/shared/sauvegardes_deploiement"
install -d -m 700 "$SAUVEGARDES"
OPTIONS_BASE="$(mktemp)"
trap 'rm -f "$OPTIONS_BASE"' EXIT
chmod 600 "$OPTIONS_BASE"
# Identifiants lus dans config.local.php, jamais passés en ligne de commande
# (ils seraient visibles de tous dans la liste des processus). mktemp crée
# le fichier en 600 : le mot de passe n'est jamais lisible par un autre.
php -r '
    $c = require $argv[1];
    $q = static fn ($v) => "\"" . addcslashes((string) $v, "\\\"") . "\"";
    echo "[client]\n",
         "user=", $q($c["user"] ?? ""), "\n",
         "password=", $q($c["pass"] ?? ""), "\n",
         "host=", $q($c["host"] ?? "localhost"), "\n",
         "port=", $q($c["port"] ?? "3306"), "\n";
' "$CONFIG" > "$OPTIONS_BASE"
NOM_BASE="$(php -r 'echo (require $argv[1])["name"] ?? "outsee";' "$CONFIG")"
DUMP="$SAUVEGARDES/avant_$VERSION.sql.gz"
mariadb-dump --defaults-extra-file="$OPTIONS_BASE" --single-transaction --no-tablespaces \
    "$NOM_BASE" | gzip -6 > "$DUMP"
chmod 600 "$DUMP"
echo "   $DUMP"
# Dix points de restauration suffisent : les sauvegardes nocturnes prennent
# le relais au-delà.
ls -1t "$SAUVEGARDES"/avant_*.sql.gz 2>/dev/null | tail -n +11 | xargs -r rm -f

echo "== Migrations"
APP_ENV=production php "$REL/outils/migrer.php"

echo "== Bascule"
ln -sfn "$REL" "$RACINE/current.nouveau"
mv -Tf "$RACINE/current.nouveau" "$RACINE/current"
sudo /usr/bin/systemctl reload php8.3-fpm

echo "== Contrôle"
DOMAINE="$(php -r 'echo parse_url((require $argv[1])["url_publique"] ?? "", PHP_URL_HOST);' "$CONFIG")"
if [ -n "$DOMAINE" ]; then
    CODE="$(curl -s -o /dev/null -w '%{http_code}' --resolve "$DOMAINE:443:127.0.0.1" "https://$DOMAINE/sante.php")"
    echo "   https://$DOMAINE/sante.php → HTTP $CODE"
    if [ "$CODE" != "200" ]; then
        echo "La nouvelle version ne répond pas : retour à celle qui était en service." >&2
        if [ -n "$PRECEDENTE" ] && [ -d "$PRECEDENTE" ] && [ "$PRECEDENTE" != "$REL" ]; then
            ln -sfn "$PRECEDENTE" "$RACINE/current.nouveau"
            mv -Tf "$RACINE/current.nouveau" "$RACINE/current"
            sudo /usr/bin/systemctl reload php8.3-fpm
            echo "   current → $PRECEDENTE" >&2
        else
            echo "   aucune version précédente valide : rien n'a été changé." >&2
        fi
        echo "   Les migrations éventuelles restent appliquées ; point de restauration : $DUMP" >&2
        exit 1
    fi
fi

echo "== Ménage (5 versions gardées)"
# La version en service et la précédente ne sont jamais supprimées, même
# si des déploiements ratés plus récents les font sortir des cinq.
ls -1dt "$RACINE"/releases/*/ | tail -n +6 | while read -r ancien; do
    ancien="${ancien%/}"
    if [ "$ancien" != "$REL" ] && [ "$ancien" != "$PRECEDENTE" ]; then
        rm -rf "$ancien"
    fi
done

echo "== Configuration système"
# Le compte deploy ne peut pas installer ces fichiers (ce serait lui donner
# root) : on signale seulement l'écart. Fichiers installés lisibles par tous.
ECARTS=0
comparer() {
    if ! cmp -s "$1" "$2"; then
        echo "   diffère : $2"
        ECARTS=1
    fi
}
comparer "$REL/deploy/sauvegarder.sh" /usr/local/sbin/outsee-sauvegarde
comparer "$REL/deploy/cron/outsee" /etc/cron.d/outsee
comparer "$REL/deploy/logrotate/outsee" /etc/logrotate.d/outsee
comparer "$REL/deploy/php/99-outsee.ini" /etc/php/8.3/fpm/conf.d/99-outsee.ini
comparer "$REL/deploy/php/outsee-fpm.conf" /etc/php/8.3/fpm/pool.d/outsee.conf
comparer "$REL/deploy/nginx/outsee-php.conf" /etc/nginx/snippets/outsee-php.conf
comparer "$REL/deploy/nginx/outsee-entetes.conf" /etc/nginx/snippets/outsee-entetes.conf
if [ -n "$DOMAINE" ]; then
    comparer <(sed "s|__DOMAINE__|${DOMAINE}|g" "$REL/deploy/nginx/outsee.conf") /etc/nginx/sites-available/outsee.conf
fi
if [ "$ECARTS" -eq 1 ]; then
    echo "   À appliquer en root, après relecture de deploy/ :"
    echo "      sudo bash $RACINE/current/deploy/appliquer_config.sh ${DOMAINE:-<domaine>}"
else
    echo "   à jour."
fi

echo "Version $VERSION en service."
