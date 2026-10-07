#!/usr/bin/env bash
# Memperbarui panduan: seed data rekaan → tangkap layar (Playwright) → bangun HTML. Dijalankan dari HOST (butuh docker + node).
#   bash tests/panduan/jalankan.sh            (atau: composer panduan)
# Memakai basis data SEMENTARA (bawaan db_surat_testing) dan server artisan sementara di container aplikasi; basis data
# pengembangan db_surat tidak disentuh (migrate:fresh akan menghapusnya, jadi skrip menolaknya).
set -euo pipefail
cd "$(dirname "$0")/../.."

APP_CONTAINER="${APP_CONTAINER:-surat-php}"
DB="${PANDUAN_DB:-db_surat_testing}"
JARINGAN="${PANDUAN_NETWORK:-$(docker inspect "$APP_CONTAINER" --format '{{range $k,$v := .NetworkSettings.Networks}}{{$k}} {{end}}' | awk '{print $1}')}"
GAMBAR="${PANDUAN_IMAGE:-mcr.microsoft.com/playwright:v1.63.0-noble}"
PORT="${PANDUAN_PORT:-8099}"
SANDI="${PANDUAN_PASSWORD:-$(head -c 12 /dev/urandom | od -An -tx1 | tr -d ' \n')Aa1}"

if [ "$DB" = "db_surat" ]; then
    echo "Menolak: PANDUAN_DB=db_surat adalah basis data pengembangan (migrate:fresh menghapusnya)." >&2
    exit 1
fi

[ -d node_modules/playwright-core ] || npm install --ignore-scripts >/dev/null

LINGKUNGAN=(-e "DB_DATABASE=$DB" -e APP_ENV=local -e APP_DEBUG=false -e "APP_URL=http://$APP_CONTAINER:$PORT" -e "ASSET_URL=http://$APP_CONTAINER:$PORT"
    -e SESSION_DRIVER=file -e SESSION_PATH=/ -e SESSION_SECURE_COOKIE=false -e CACHE_STORE=array -e QUEUE_CONNECTION=sync -e PANDUAN_TANPA_MFA=true -e "PANDUAN_PASSWORD=$SANDI")

[ -d public/build ] || npm run build

docker exec "${LINGKUNGAN[@]}" -w /var/www/html "$APP_CONTAINER" php artisan migrate:fresh --seeder=PanduanSeeder --force

# --no-reload: tanpa ini artisan membuang variabel yang ada di .env sehingga override lingkungan di atas hilang
docker exec -d "${LINGKUNGAN[@]}" -e PHP_CLI_SERVER_WORKERS=4 -w /var/www/html "$APP_CONTAINER" php artisan serve --host=0.0.0.0 --port="$PORT" --no-reload
trap 'docker exec "$APP_CONTAINER" sh -c "pkill -f \"artisan serve\"; pkill -f \"php -S 0.0.0.0:$PORT\"" >/dev/null 2>&1 || true' EXIT
sleep 4

docker run --rm --ipc=host --network "$JARINGAN" --user "$(id -u):$(id -g)" -v "$PWD":/work -w /work \
    -e "PANDUAN_BASE_URL=http://$APP_CONTAINER:$PORT" -e "PANDUAN_PASSWORD=$SANDI" -e "PANDUAN_PERAN=${PANDUAN_PERAN:-}" \
    "$GAMBAR" node tests/panduan/tangkap.mjs

node tests/panduan/bangun.mjs
echo "Selesai. Periksa public/panduan/ lalu commit."
