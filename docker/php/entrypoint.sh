#!/bin/sh
# Entrypoint produksi: menghangatkan cache konfigurasi dari lingkungan RUNTIME (bukan saat build) lalu menjalankan perintah.
# Migrasi TIDAK dijalankan otomatis: lakukan manual saat rilis (docs/DEPLOY.md).
set -e

cd /var/www/html

if [ "$1" = "php-fpm" ] || [ "$1" = "php" ]; then
    php artisan optimize --no-interaction >/dev/null 2>&1 || echo "peringatan: artisan optimize gagal (periksa .env)" >&2
    php artisan filament:optimize --no-interaction >/dev/null 2>&1 || true
fi

exec "$@"
