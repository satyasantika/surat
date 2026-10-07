# Persuratan & Layanan FKIP Unsil

Sistem persuratan fakultas berbasis tata naskah dinas (surat masuk & disposisi, naskah keluar dengan nomor otomatis dan QR verifikasi, register & arsip) dengan **Layanan Ormawa** di atasnya. Pengganti OrmawaHub / SipOrmawa-FKIP.

Stack: Laravel 13, MariaDB, Redis, Filament 5, Livewire 4, Tailwind 4, Gotenberg.

## Dokumentasi

Lihat `docs/`: analisis sistem berjalan, PRD, arsitektur, skema database, prompt bertahap, uji penerimaan, rekomendasi regulasi, migrasi data, standar teknis & git. Konteks agen AI ada di `CLAUDE.md`.

## Menjalankan lokal

Aplikasi memakai container yang dikelola compose pusat `~/code/docker-compose.yml` (`surat-php` PHP 8.5, `surat-nginx`, jaringan `laranet`), **MariaDB** native di host (DB `db_surat`, `db_surat_testing`), dan Redis bersama (`redis`, DB 52/53/54). Tidak ada container milik repo ini.

```bash
alias srp='docker exec -w /var/www/html surat-php'
cp .env.example .env && srp php artisan key:generate   # isi DB_PASSWORD di .env
srp composer install
srp php artisan migrate
```

Aplikasi: http://localhost:8028 (port dari compose pusat). Perintah PHP/Composer selalu lewat `srp`; npm dijalankan dari host (container belum berisi Node).

## Subpath produksi `/surat`

Produksi: `https://supportfkip.unsil.ac.id/surat` (reverse proxy memotong awalan). Atur di `.env`: `APP_URL` dan `ASSET_URL` = URL tersebut, `SESSION_PATH=/surat`, `SESSION_COOKIE=surat_session`, `SESSION_SECURE_COOKIE=true`, `TRUSTED_PROXIES=<ip proxy>`. Bila `APP_URL` berpath, `AppServiceProvider` memaksa root URL; semua URL harus lewat `route()`/`url()`/`asset()` (diuji `tests/Arch/SubpathTest.php`).

Build aset dengan awalan: `ASSET_URL=https://supportfkip.unsil.ac.id/surat npm run build` (di host).

```nginx
location = /surat { return 301 /surat/; }
location /surat/ {
    proxy_pass http://127.0.0.1:<port>/;          # garis miring akhir = awalan dipotong
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Prefix /surat;
    client_max_body_size 20m;
}
```

## Layanan tambahan di compose pusat

Compose pusat (`~/code/docker-compose.yml`) berada di luar repo ini. Tambahkan service berikut bila ingin Gotenberg (PDF presisi) dan Horizon berjalan; tanpa itu set `PDF_DRIVER=dompdf` dan jalankan antrean manual (`srp php artisan horizon`).

```yaml
  surat-gotenberg:
    image: gotenberg/gotenberg:8
    restart: unless-stopped
    networks: [laranet]

  surat-horizon:
    build: { context: ., dockerfile: php/laravel8.5.Dockerfile }
    restart: unless-stopped
    working_dir: /var/www/html
    volumes: ["./surat:/var/www/html"]
    extra_hosts: ["host.docker.internal:host-gateway"]
    command: php artisan horizon
    networks: [laranet]
```

## Uji penerimaan (staging)

`php artisan surat:siapkan-uat` menyiapkan data **rekaan** (ditolak di produksi): akun per peran (`uat.<peran>@contoh.test`, kata sandi acak dicetak sekali),
3 ormawa, surat masuk, permohonan di setiap tahap, LPJ (terlambat/diajukan/dinilai), kabar, dan galeri; idempoten. Skenario uji per peran: **docs/05-UJI-PENERIMAAN.md**.
Panduan pengguna: **docs/PANDUAN-ADMIN.md**, **docs/PANDUAN-PIMPINAN.md**, **docs/PANDUAN-ORMAWA.md** (versi HTML dengan tangkapan layar di `public/panduan/`).
