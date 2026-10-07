#!/bin/sh
# Uji pulih: memulihkan cadangan terbaru ke basis data sementara lalu membandingkan jumlah tabel dan baris kunci.
# Wajib dijalankan berkala (mis. tiap semester) dan sebelum cutover. Tidak menyentuh basis data produksi.
#   docker compose -f docker-compose.prod.yml --profile backup run --rm --entrypoint /bin/sh backup /skrip/uji-pulih.sh
set -eu

: "${DB_HOST:?}" "${DB_DATABASE:?}" "${DB_USERNAME:?}" "${DB_PASSWORD:?}"
TUJUAN="${BACKUP_PATH:-/backups}"
SEMENTARA="${DB_DATABASE}_uji_pulih"
TERBARU="$(ls -1t "$TUJUAN/${DB_DATABASE}"-*.sql.gz 2>/dev/null | head -n1 || true)"

[ -n "$TERBARU" ] || { echo "Tidak ada cadangan di $TUJUAN" >&2; exit 1; }
sha256sum -c "$TERBARU.sha256"

export MYSQL_PWD="$DB_PASSWORD"
KLIEN="mariadb --host=$DB_HOST --port=${DB_PORT:-3306} --user=$DB_USERNAME"

$KLIEN -e "DROP DATABASE IF EXISTS \`$SEMENTARA\`; CREATE DATABASE \`$SEMENTARA\` CHARACTER SET utf8mb4;"
zcat "$TERBARU" | $KLIEN "$SEMENTARA"

hitung() { $KLIEN -N -e "SELECT COUNT(*) FROM \`$1\`.\`$2\`" ; }
tabel() { $KLIEN -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$1'"; }

GALAT=0
[ "$(tabel "$DB_DATABASE")" = "$(tabel "$SEMENTARA")" ] || { echo "Jumlah tabel berbeda" >&2; GALAT=1; }

for T in users naskah permohonan nomor_terpakai; do
    ASAL="$(hitung "$DB_DATABASE" "$T")"
    PULIH="$(hitung "$SEMENTARA" "$T")"
    echo "$T: produksi=$ASAL pulih=$PULIH"
    # cadangan boleh tertinggal beberapa baris dari produksi, tidak boleh lebih banyak
    [ "$PULIH" -le "$ASAL" ] || { echo "Jumlah baris $T hasil pulih melebihi produksi" >&2; GALAT=1; }
done

$KLIEN -e "DROP DATABASE \`$SEMENTARA\`;"
[ "$GALAT" = 0 ] && echo "UJI PULIH LULUS dari $TERBARU" || { echo "UJI PULIH GAGAL" >&2; exit 1; }
