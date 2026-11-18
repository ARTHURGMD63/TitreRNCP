#!/usr/bin/env bash
# ============================================================
#  Outsee — sauvegarde nocturne (cron, root) : base + photos envoyées.
#
#  Garde 14 jours sur le VPS, dans /var/backups/outsee. Une sauvegarde qui
#  reste sur la même machine ne protège pas d'une panne du VPS : régler
#  aussi une copie ailleurs (OVH Backup Storage ou Object Storage, voir
#  docs/DEPLOIEMENT_VPS_OVH.md, étape 9) — variable DESTINATION_DISTANTE.
# ============================================================
set -euo pipefail

DOSSIER=/var/backups/outsee
DATE="$(date +%Y-%m-%d_%H%M)"
mkdir -p "$DOSSIER"

# --single-transaction : une copie cohérente sans bloquer les écritures.
mariadb-dump --single-transaction --routines --triggers outsee | gzip -9 > "$DOSSIER/outsee_$DATE.sql.gz"
tar -czf "$DOSSIER/uploads_$DATE.tar.gz" -C /var/www/outsee/shared uploads
chmod 600 "$DOSSIER"/*_"$DATE".*

find "$DOSSIER" -type f -mtime +14 -delete

if [ -n "${DESTINATION_DISTANTE:-}" ]; then
    rsync -a "$DOSSIER/" "$DESTINATION_DISTANTE"
fi

echo "$(date '+%d/%m/%Y %H:%M') sauvegarde $DATE terminée"
