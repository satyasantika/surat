#!/bin/sh
# Cadangan harian basis data (mysqldump) dengan retensi 30 hari. Dijalankan dalam container `backup`:
#   docker compose -f docker-compose.prod.yml --profile backup run --rm backup
# Kredensial dari .env (DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD). Berkas dump berisi data pribadi: simpan terbatas.
set -eu

: "${DB_HOST:?}" "${DB_DATABASE:?}" "${DB_USERNAME:?}" "${DB_PASSWORD:?}"
TUJUAN="${BACKUP_PATH:-/backups}"
RETENSI_HARI="${BACKUP_RETENSI_HARI:-30}"
CAP="$(date +%Y%m%d-%H%M%S)"
BERKAS="$TUJUAN/${DB_DATABASE}-$CAP.sql.gz"

umask 077
mkdir -p "$TUJUAN"

MYSQL_PWD="$DB_PASSWORD" mariadb-dump --host="$DB_HOST" --port="${DB_PORT:-3306}" --user="$DB_USERNAME" \
    --single-transaction --quick --routines --events --triggers --default-character-set=utf8mb4 "$DB_DATABASE" | gzip -9 > "$BERKAS"

# verifikasi: arsip utuh dan memuat skema
gzip -t "$BERKAS"
zcat "$BERKAS" | grep -q 'CREATE TABLE `users`' || { echo "Cadangan tidak memuat tabel users: $BERKAS" >&2; rm -f "$BERKAS"; exit 1; }
sha256sum "$BERKAS" > "$BERKAS.sha256"

# retensi
find "$TUJUAN" -maxdepth 1 -name "${DB_DATABASE}-*.sql.gz*" -type f -mtime +"$RETENSI_HARI" -delete

echo "Cadangan selesai: $BERKAS ($(du -h "$BERKAS" | cut -f1)); retensi ${RETENSI_HARI} hari."
