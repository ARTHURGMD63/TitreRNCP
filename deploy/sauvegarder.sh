#!/usr/bin/env bash
# ============================================================
#  Outsee — sauvegarde nocturne (cron, root) : base + photos envoyées.
#
#  Ce fichier n'est JAMAIS exécuté depuis /var/www/outsee : le dossier des
#  versions appartient au compte « deploy », piloté par une clé SSH stockée
#  dans GitHub. installer_serveur.sh et appliquer_config.sh le copient en
#  root:root 755 dans /usr/local/sbin/outsee-sauvegarde, et c'est cette
#  copie que lance /etc/cron.d/outsee.
#
#  Garde 14 jours sur le VPS, dans /var/backups/outsee (700, root). Après
#  chaque sauvegarde réussie, écrit la date dans un témoin lisible par PHP
#  (/var/lib/outsee/derniere_sauvegarde) : la sonde de santé, qui tourne en
#  www-data, ne peut pas lire le dossier des sauvegardes lui-même.
#
#  Copie distante, chiffrée, facultative. Réglages dans
#  /etc/outsee/sauvegarde.conf (root, 600), lu s'il existe :
#
#      DESTINATION_DISTANTE=utilisateur@hote:/chemin/   (rsync par SSH)
#      DESTINATION_DISTANTE=rclone:<remote>:<seau>/outsee (rclone, Object Storage)
#      AGE_DESTINATAIRE=age1…            clé PUBLIQUE age (chiffrement par age)
#      PHRASE_FICHIER=/etc/outsee/sauvegarde.phrase  (gpg symétrique, à défaut)
#
#  age est préféré s'il est installé et qu'une clé publique est donnée : la
#  clé privée qui déchiffre n'est alors jamais sur le VPS. Sinon gpg, avec la
#  phrase secrète du fichier indiqué. Rien ne part en clair : sans moyen de
#  chiffrer, la copie distante est refusée et le journal le dit.
#
#  Restauration : docs/DEPLOIEMENT_VPS_OVH.md, étape 9.
# ============================================================
set -euo pipefail
umask 077

DOSSIER=/var/backups/outsee
TEMOIN=/var/lib/outsee/derniere_sauvegarde
CONF=/etc/outsee/sauvegarde.conf
DATE="$(date +%Y-%m-%d_%H%M)"

DESTINATION_DISTANTE="${DESTINATION_DISTANTE:-}"
AGE_DESTINATAIRE="${AGE_DESTINATAIRE:-}"
PHRASE_FICHIER="${PHRASE_FICHIER:-/etc/outsee/sauvegarde.phrase}"
if [ -f "$CONF" ]; then
    # shellcheck source=/dev/null
    . "$CONF"
fi

journal() { echo "$(date '+%d/%m/%Y %H:%M') $*"; }

mkdir -p "$DOSSIER"
chmod 700 "$DOSSIER"

# --single-transaction : une copie cohérente sans bloquer les écritures.
mariadb-dump --single-transaction --routines --triggers outsee | gzip -9 > "$DOSSIER/outsee_$DATE.sql.gz"
tar -czf "$DOSSIER/uploads_$DATE.tar.gz" -C /var/www/outsee/shared uploads
chmod 600 "$DOSSIER"/*_"$DATE".*

find "$DOSSIER" -maxdepth 1 -type f -mtime +14 -delete

# Témoin pour la sonde (includes/sante.php) : écrit seulement ici, une fois
# la base et les photos copiées. Une sauvegarde qui échoue plus haut
# s'arrête (set -e) et laisse vieillir le témoin, donc l'alerte part.
install -d -m 755 "$(dirname "$TEMOIN")"
date +%s > "$TEMOIN.tmp"
chmod 644 "$TEMOIN.tmp"
mv -f "$TEMOIN.tmp" "$TEMOIN"
journal "sauvegarde locale $DATE terminée"

# ─── Copie distante chiffrée ────────────────────────────────────────────
if [ -z "$DESTINATION_DISTANTE" ]; then
    journal "ATTENTION : aucune copie distante (DESTINATION_DISTANTE vide dans $CONF) ; la sauvegarde ne protège pas d'une perte du VPS"
    exit 0
fi

CHIFFRE="$(mktemp -d "$DOSSIER/distant.XXXXXX")"
trap 'rm -rf "$CHIFFRE"' EXIT

if command -v age >/dev/null 2>&1 && [ -n "$AGE_DESTINATAIRE" ]; then
    for f in "$DOSSIER/outsee_$DATE.sql.gz" "$DOSSIER/uploads_$DATE.tar.gz"; do
        age -r "$AGE_DESTINATAIRE" -o "$CHIFFRE/$(basename "$f").age" "$f"
    done
    MOYEN=age
elif command -v gpg >/dev/null 2>&1 && [ -r "$PHRASE_FICHIER" ]; then
    for f in "$DOSSIER/outsee_$DATE.sql.gz" "$DOSSIER/uploads_$DATE.tar.gz"; do
        gpg --batch --yes --quiet --pinentry-mode loopback --passphrase-file "$PHRASE_FICHIER" \
            --symmetric --cipher-algo AES256 -o "$CHIFFRE/$(basename "$f").gpg" "$f"
    done
    MOYEN=gpg
else
    journal "ÉCHEC copie distante : ni age (AGE_DESTINATAIRE) ni gpg ($PHRASE_FICHIER lisible) ; rien n'est envoyé en clair"
    exit 2
fi

case "$DESTINATION_DISTANTE" in
    rclone:*)
        rclone copy "$CHIFFRE/" "${DESTINATION_DISTANTE#rclone:}"
        ;;
    *)
        rsync -a "$CHIFFRE/" "$DESTINATION_DISTANTE"
        ;;
esac

journal "copie distante $DATE envoyée ($MOYEN) vers $DESTINATION_DISTANTE"
