# Standar Teknis Bersama — Support System FKIP Unsil

> Berkas ini identik di setiap folder sistem (`akreditasi` — folder `lamdik2026`, `alias`, `aset`, `kerjasama`, `keuangan`, `lms`, `puspresma`, `regulasi`, `sdm`, `surat`). Bila diubah, ubah di semua folder agar seluruh sistem tetap seragam.
> Disusun 6 Oktober 2026. Versi paket di bawah adalah versi stabil terbaru saat penyusunan; saat instalasi, biarkan Composer/NPM mengambil rilis stabil terbaru pada major yang sama.

## 1. Tumpukan teknologi (wajib)

| Lapisan | Pilihan | Catatan |
|---|---|---|
| Bahasa | **PHP 8.4** (minimal 8.3) | Laravel 13 mensyaratkan PHP ≥ 8.3 |
| Framework | **Laravel 13.x** (rilis 17 Maret 2026) | Bug fix s.d. Q3 2027, security fix s.d. 17 Maret 2028. Constraint `^13.0` |
| Basis data | **MySQL 8.4 LTS** (native di host saat pengembangan, §2) | `utf8mb4` / `utf8mb4_0900_ai_ci`, engine InnoDB, zona waktu aplikasi `Asia/Jakarta` |
| Cache, sesi, antrean, lock | **Redis 7.x** | Satu container bersama; setiap aplikasi memakai blok DB index & prefiks sendiri (default, cache, queue — §2.1) |
| Monitor antrean | **Laravel Horizon** | Dashboard `/horizon`, hanya role `super-admin` |
| Panel back-office | **Filament 5** (di atas Livewire 4) | CRUD, tabel, filter, impor/ekspor bawaan, notifikasi database, MFA |
| Halaman publik / swalayan | Blade + **Livewire 4** + **Tailwind CSS 4** | Dibangun dengan Vite |
| Hak akses | `spatie/laravel-permission` | Role & permission; policy Laravel untuk aturan per-record |
| Jejak audit | `spatie/laravel-activitylog` | Wajib untuk tabel transaksi & status |
| Berkas/dokumen | **Tautan publik (Google Drive, dsb.) — TIDAK ada unggah berkas ke server** | Lihat §1a. Server hanya menyimpan URL + metadata. Penyimpanan fisik (disk/MinIO) disiapkan lewat antarmuka, belum diaktifkan |
| PDF | `barryvdh/laravel-dompdf` | Opsional **Gotenberg** (container) bila butuh tata letak presisi (surat resmi) |
| Excel/CSV | Filament Import/Export Action (berbasis antrean) + `spatie/simple-excel` | Impor besar wajib lewat queue |
| QR code | `endroid/qr-code` | Isi QR = URL verifikasi publik ber-UUID (`id` UUIDv7, §4a), bukan ID berurutan |
| Pencarian | MySQL FULLTEXT (default) | Opsional **Meilisearch** + Laravel Scout untuk pencarian dokumen besar |
| Surel | SMTP kampus; **Mailpit** di lokal | Semua surel lewat antrean (`ShouldQueue`) |
| WhatsApp | Gateway HTTP (mis. Fonnte, sudah dipakai OrmawaHub) | Bungkus sebagai *Notification Channel* kustom, dikirim via antrean |
| Uji | **Pest 4** + plugin Laravel | Feature test per alur bisnis, minimal jalur sukses + gagal otorisasi |
| Kualitas kode | **Laravel Pint** (PSR-12 preset laravel), **Larastan** level 6 | Dijalankan sebelum setiap commit |

## 1a. Kebijakan berkas: tautan, bukan unggahan (keputusan 6 Oktober 2026)

Karena kapasitas storage server terbatas, **untuk sementara seluruh sistem tidak menerima unggahan berkas**. Setiap kebutuhan "unggah dokumen/foto/bukti" diganti dengan **isian tautan** ke berkas yang disimpan pengguna di Google Drive (akun `@unsil.ac.id`) atau layanan sejenis.

Aturan wajib:

1. **Tabel tautan polimorfik** dipakai semua modul, bukan kolom URL tersebar:
   `tautan_berkas` (`id` UUIDv7, `pemilik_type`, `pemilik_id` UUID (`uuidMorphs`), `jenis` (mis. `sk`, `bukti`, `foto`, `sertifikat`), `label`, `url` VARCHAR(2048), `penyedia` ENUM(`google_drive`,`google_docs`,`onedrive`,`unsil`,`lainnya`), `drive_file_id` nullable, `status_cek` ENUM(`belum`,`dapat_diakses`,`tidak_dapat_diakses`), `dicek_pada` nullable, `ditambahkan_oleh`, timestamps, soft delete). Model memakai relasi `morphMany`.
2. **Validasi** (Form Request/Filament rule kustom `TautanBerkasValid`): hanya `https://`; domain dalam daftar putih di `config/berkas.php` (`drive.google.com`, `docs.google.com`, `*.unsil.ac.id`, opsional `onedrive.live.com`/`*.sharepoint.com`); tolak pemendek URL (bit.ly, s.id) dan tautan folder bila yang diminta satu berkas. Ekstrak `drive_file_id` dari pola URL Drive.
3. **Pemeriksaan keteraksesan** lewat job antrean `PeriksaTautanBerkas` (HTTP HEAD/GET tanpa mengunduh isi, timeout 10 detik) saat disimpan dan ulang terjadwal mingguan; tautan yang mati ditandai dan pemiliknya diberi notifikasi. Status ini informatif — jangan memblokir simpan karena Drive kadang meminta login.
4. **Privasi**: tampilkan petunjuk di formulir — dokumen umum boleh "Siapa saja yang memiliki link"; dokumen berisi data pribadi (SK kepegawaian, ijazah, KTP, nilai) wajib dibagikan **terbatas ke domain unsil.ac.id** atau ke akun tertentu, bukan publik. Sistem tidak pernah menampilkan tautan ke peran yang tidak berhak (otorisasi tetap di Policy).
5. **Tampilan**: tombol "Buka berkas" (`target="_blank" rel="noopener noreferrer"`); pratinjau sematan Drive (`https://drive.google.com/file/d/<id>/preview`) di iframe untuk PDF; foto ditampilkan via `https://lh3.googleusercontent.com/d/<id>` (pola yang sudah dipakai SIMAN). Sediakan placeholder bila gagal dimuat.
6. **Keluaran yang dihasilkan sistem** (PDF surat, ekspor Excel, label QR) **di-stream langsung ke browser**, tidak disimpan permanen. Bila harus lewat antrean, simpan sementara di `storage/app/tmp` dan hapus otomatis ≤ 24 jam (`schedule` harian). QR dibangkitkan on-the-fly.
7. **Siap dialihkan**: seluruh akses berkas lewat satu antarmuka `App\Contracts\PenyimpananBerkas` dengan implementasi `TautanEksternal` (aktif). Kelak bila storage tersedia, cukup menambah implementasi `DiskLokal`/`S3` tanpa mengubah modul. Nama konfigurasi: `BERKAS_MODE=tautan`.
8. **Jejak**: perubahan tautan dicatat activitylog (URL lama → baru), karena isi berkas di Drive bisa berubah di luar sistem; untuk dokumen final (SK, perjanjian, surat terbit) minta pengguna memakai berkas PDF final yang tidak diedit lagi.

### Kenapa Redis (dan layanan tambahan lain)
- **Antrean**: ekspor Excel/PDF, impor data, pemeriksaan tautan berkas, surel & WhatsApp tidak boleh membuat pengguna menunggu.
- **Cache**: master data (prodi, ruangan, kategori, konfigurasi) dibaca sangat sering.
- **Atomic lock**: penomoran surat/dokumen dan peminjaman barang wajib bebas tabrakan (`Cache::lock()`), dikombinasikan dengan transaksi DB + `lockForUpdate()`.
- **Rate limit**: login & endpoint publik (lookup QR, verifikasi surat).
- **Sesi**: siap *scale-out* bila kelak aplikasi dijalankan lebih dari satu container.

Layanan opsional (pakai bila memang dibutuhkan, bukan default): Meilisearch (pencarian), Gotenberg (PDF presisi), Laravel Reverb (notifikasi real-time). MinIO (penyimpanan objek) baru relevan bila kelak kebijakan §1a dicabut.

## 2. Lingkungan pengembangan: Docker FKIP (tanpa Sail)

Keputusan 6 Oktober 2026: semua sistem berjalan di **Docker yang sudah ada di mesin pengembang** mengikuti pola repo `alias` (`~/code/<app>`, container `<app>-php` & `<app>-nginx`), **bukan** Laravel Sail dan bukan PHP di host. Perintah PHP/Composer/NPM selalu dijalankan **di dalam container** lewat alias shell per aplikasi.

| Komponen | Letak | Catatan |
|---|---|---|
| MySQL 8.4 | **Native di host** (bukan container) | Dijangkau container lewat `host.docker.internal`; satu database + satu database uji + satu pengguna per aplikasi |
| Redis 7 | **Satu container bersama** (sudah ada) | Setiap aplikasi memakai blok DB index & prefiks sendiri (§2.1) |
| Mailpit | **Satu container bersama** (dibuat sekali, §2.2) | SMTP `mailpit:1025`, UI `http://localhost:8025` |
| Jaringan | `fkip-net` (external) | Menghubungkan container aplikasi dengan Redis & Mailpit bersama |
| PHP-FPM 8.4, Nginx, Horizon, scheduler | Per aplikasi, di `docker-compose.yml` repo | Templat §2.3 |
| Gotenberg (Surat), Meilisearch (Regulasi) | Per aplikasi, internal (tanpa port host) | Ditambahkan pada langkah sistem terkait |

### 2.1 Alokasi per aplikasi

| Aplikasi | Folder | Container PHP | Alias shell | Port Nginx | Database MySQL | Redis DB (default / cache / queue) | Prefiks Redis |
|---|---|---|---|---|---|---|---|
| alias | `~/code/alias` | `alias-php` | `ap` | 8018 (sudah ada) | `alias`, `alias_testing` | 16 / 17 / 18 | `alias_` |
| akreditasi | `~/code/akreditasi` | `akreditasi-php` | `akp` | 8011 | `akreditasi`, `akreditasi_testing` | 20 / 21 / 22 | `akreditasi_` |
| aset | `~/code/aset` | `aset-php` | `asp` | 8012 | `aset`, `aset_testing` | 24 / 25 / 26 | `aset_` |
| kerjasama | `~/code/kerjasama` | `kerjasama-php` | `ksp` | 8013 | `kerjasama`, `kerjasama_testing` | 28 / 29 / 30 | `kerjasama_` |
| keuangan | `~/code/keuangan` | `keuangan-php` | `kup` | 8014 | `keuangan`, `keuangan_testing` | 32 / 33 / 34 | `keuangan_` |
| lms | `~/code/lms` | `lms-php` | `lmp` | 8015 | `lms`, `lms_testing` | 36 / 37 / 38 (+ `kuis` 39) | `lms_` |
| puspresma | `~/code/puspresma` | `puspresma-php` | `psp` | 8016 | `puspresma`, `puspresma_testing` | 40 / 41 / 42 | `puspresma_` |
| regulasi | `~/code/regulasi` | `regulasi-php` | `rgp` | 8017 | `regulasi`, `regulasi_testing` | 44 / 45 / 46 | `regulasi_` |
| sdm | `~/code/sdm` | `sdm-php` | `sdp` | 8019 | `sdm`, `sdm_testing` | 48 / 49 / 50 | `sdm_` |
| surat | `~/code/surat` | `surat-php` | `srp` | 8020 | `surat`, `surat_testing` | 52 / 53 / 54 | `surat_` |

- DB index 0–15 dibiarkan untuk aplikasi lama yang sudah memakai Redis bersama (fkipapp, plp, dll.). Karena `cache:clear` menjalankan `FLUSHDB`, **cache setiap aplikasi wajib di DB index sendiri** — jangan pernah berbagi DB cache antaraplikasi.
- Port dan nama container boleh disesuaikan bila bentrok dengan layanan yang sudah ada; ubah tabel ini di semua folder.
- Nama repositori di GitHub boleh berbeda (mis. `siman-fkip`, `persuratan-fkip`); folder kerja lokal tetap `~/code/<app>` agar hook dan alias seragam.

Alias shell (tambahkan sekali ke `~/.bashrc`, dijalankan dari root repo masing-masing):
```bash
alias ap='docker compose exec alias-php'
alias akp='docker compose exec akreditasi-php'
alias asp='docker compose exec aset-php'
alias ksp='docker compose exec kerjasama-php'
alias kup='docker compose exec keuangan-php'
alias lmp='docker compose exec lms-php'
alias psp='docker compose exec puspresma-php'
alias rgp='docker compose exec regulasi-php'
alias sdp='docker compose exec sdm-php'
alias srp='docker compose exec surat-php'
```
Contoh: `asp php artisan test`, `asp ./vendor/bin/pint`, `asp composer require …`, `asp npm run build`.

### 2.2 Infrastruktur bersama (sekali per mesin)

```bash
# 1) Jaringan bersama, lalu sambungkan container Redis yang sudah ada dengan nama jaringan "redis"
docker network create fkip-net
docker network connect --alias redis fkip-net <nama-container-redis>

# 2) Redis harus menyediakan ≥ 64 DB index dan sebaiknya persisten (antrean & jawaban kuis LMS).
#    Jalankan ulang container Redis dengan perintah (atau ubah redis.conf/compose-nya):
#    redis-server --databases 64 --appendonly yes

# 3) Mailpit bersama
docker run -d --name mailpit --restart unless-stopped --network fkip-net \
  -p 127.0.0.1:8025:8025 axllent/mailpit
```

MySQL native (sekali per mesin, lalu sekali per aplikasi):
```ini
# my.cnf / mysqld.cnf — dengarkan juga di gateway Docker agar container dapat terhubung (MySQL ≥ 8.0.13)
[mysqld]
bind-address = 127.0.0.1,172.17.0.1
```
```sql
-- ganti <app> dan <rahasia>; '172.%' = subnet jaringan Docker di host Linux/WSL
CREATE DATABASE `<app>` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE DATABASE `<app>_testing` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE USER '<app>'@'172.%' IDENTIFIED BY '<rahasia>';
GRANT ALL PRIVILEGES ON `<app>`.* TO '<app>'@'172.%';
GRANT ALL PRIVILEGES ON `<app>\_testing%`.* TO '<app>'@'172.%';   -- termasuk DB uji paralel <app>_testing_test_N
```
Bila MySQL native berada di Windows sementara Docker berjalan di WSL2 (Docker Desktop), `host.docker.internal` menunjuk ke Windows: buat pengguna dengan host yang sesuai dan batasi lewat firewall **(cek di mesin Anda)**. Uji koneksi: `docker compose exec <app>-php php -r "new PDO('mysql:host=host.docker.internal;dbname=<app>', '<app>', '<rahasia>'); echo 'ok';"`.

### 2.3 Templat berkas Docker per aplikasi

`docker/php/Dockerfile`:
```dockerfile
FROM php:8.4-fpm
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql redis intl gd bcmath pcntl zip exif opcache
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
# Node 22 untuk Vite/Tailwind di dalam container
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
 && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx
# paket sistem tambahan per aplikasi (mis. Regulasi: poppler-utils) ditambahkan di sini
ARG UID=1000
ARG GID=1000
RUN groupmod -o -g ${GID} www-data && usermod -o -u ${UID} www-data
USER www-data
WORKDIR /var/www/html
```

`docker-compose.yml` (ganti `<app>` dan `<port>` sesuai §2.1):
```yaml
name: <app>

x-php: &php
  build:
    context: ./docker/php
    args: { UID: "${UID:-1000}", GID: "${GID:-1000}" }
  image: <app>-php:dev
  volumes: [".:/var/www/html"]
  extra_hosts: ["host.docker.internal:host-gateway"]
  networks: [default, fkip-net]
  restart: unless-stopped

services:
  <app>-php:
    <<: *php
  <app>-nginx:
    image: nginx:1.27-alpine
    ports: ["<port>:80"]
    volumes:
      - .:/var/www/html:ro
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on: [<app>-php]
    networks: [default, fkip-net]   # agar dapat dijangkau gateway (§2.4)
    restart: unless-stopped
  <app>-scheduler:
    <<: *php
    command: php artisan schedule:work
  # <app>-horizon ditambahkan pada langkah pemasangan Horizon:
  # <app>-horizon:
  #   <<: *php
  #   command: php artisan horizon

networks:
  fkip-net:
    external: true
```

`docker/nginx/default.conf`:
```nginx
server {
    listen 80;
    root /var/www/html/public;
    index index.php index.html;   # index.html untuk public/panduan/
    client_max_body_size 20m;   # hanya impor Excel/CSV (§1a)
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        fastcgi_pass <app>-php:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
    location ~ /\.(?!well-known) { deny all; }
}
```

Membuat proyek Laravel baru tanpa PHP di host (folder repo sudah berisi `docs/`):
```bash
cd ~/code/<app>
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD":/app -w /app composer:2 \
  create-project laravel/laravel _laravel "^13.0" --ignore-platform-reqs
# lalu pindahkan isi _laravel/ ke root tanpa menimpa berkas milik kita (dikerjakan agen di F1.1)
```

Sehari-hari: `docker compose up -d --build` · `<alias> php artisan migrate` · `<alias> npm run dev` · setelah mengubah kode antrean: `docker compose restart <app>-horizon`.

### 2.4 Subpath produksi `https://supportfkip.unsil.ac.id/<app>`

Semua sistem dipasang di **satu domain** dengan awalan path per aplikasi (pola yang sudah dipakai landing page `supportfkip.unsil.ac.id`: `dbsmatematika/`, `plp/`, dst.). Reverse proxy di depan memotong awalan lalu meneruskan ke Nginx aplikasi; aplikasi membangkitkan semua URL **dengan** awalan.

| Hal | Ketentuan |
|---|---|
| URL | `APP_URL=https://supportfkip.unsil.ac.id/<app>` dan `ASSET_URL` = nilai yang sama. Lokal lewat gateway: `http://localhost:8080/<app>` |
| Root URL | `AppServiceProvider::boot()`: bila path `APP_URL` tidak kosong → `URL::forceRootUrl(config('app.url'))`; bila skema https → `URL::forceScheme('https')` |
| Proxy tepercaya | `bootstrap/app.php` → `trustProxies(at: env('TRUSTED_PROXIES'), headers: X-Forwarded-For/Host/Port/Proto/Prefix)`; produksi isi IP reverse proxy, lokal `*` |
| Cookie | Satu domain dipakai bersama ⇒ wajib `SESSION_PATH=/<app>` dan `SESSION_COOKIE=<app>_session` (cookie `XSRF-TOKEN` & remember ikut path sesi), `SESSION_SECURE_COOKIE=true` di produksi |
| Aset Vite | `npm run build` dijalankan dengan `ASSET_URL` terisi agar `laravel-vite-plugin` menulis base `/<app>/build/` (termasuk `url()` di CSS) |
| Livewire 4, Filament 5, Horizon | URL update & skrip Livewire, aset Filament, path panel (`/<app>/admin`), dan Horizon (`/<app>/horizon`) mengikuti root URL; periksa via Laravel Boost, sesuaikan `Livewire::setUpdateRoute()`/`setScriptRoute()` bila perlu |
| Kode | **Dilarang** URL absolut berawalan `/` di Blade/JS (`href="/…"`, `src="/…"`, `action="/…"`, `fetch('/…')`); selalu `route()`, `url()`, `asset()` atau path relatif. Diuji `tests/Arch/SubpathTest.php` |
| QR & tautan tercetak | QR (aset, verifikasi surat/keuangan/puspresma) memuat awalan `/<app>` — **tetapkan awalan final sebelum mencetak label/naskah**, jangan diubah setelah produksi |
| Uji | `tests/Feature/SubpathTest.php`: dengan `app.url` berawalan, `route()`/`asset()`/redirect login berawalan `/<app>` dan cookie sesi ber-path `/<app>` |

Blok reverse proxy produksi (per aplikasi, di server depan):
```nginx
location = /<app> { return 301 /<app>/; }
location /<app>/ {
    proxy_pass http://127.0.0.1:<port>/;          # garis miring akhir = awalan dipotong
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Prefix /<app>;
    client_max_body_size 20m;
}
```

**Gateway lokal** (meniru produksi; sekali per mesin): satu container Nginx `gateway` di jaringan `fkip-net`, port 8080, yang menyajikan landing page `supportfkip/index.html` di `/` dan meneruskan `/<app>/` ke `<app>-nginx`:
```nginx
# ~/code/gateway/default.conf — tambah satu blok location per aplikasi
server {
    listen 80;
    resolver 127.0.0.11 valid=10s;                 # DNS Docker; aplikasi yang belum jalan tidak membuat gateway gagal
    location = / { root /usr/share/nginx/html; try_files /index.html =404; }
    location = /aset { return 301 /aset/; }
    location /aset/ {
        set $up aset-nginx;
        rewrite ^/aset/(.*)$ /$1 break;
        proxy_pass http://$up;
        proxy_set_header Host $host:8080;
        proxy_set_header X-Forwarded-Proto http;
        proxy_set_header X-Forwarded-Prefix /aset;
    }
}
```
```bash
docker run -d --name gateway --restart unless-stopped --network fkip-net -p 8080:80   -v ~/code/gateway/default.conf:/etc/nginx/conf.d/default.conf:ro   -v ~/code/landing:/usr/share/nginx/html:ro nginx:1.27-alpine      # ~/code/landing = salinan index.html supportfkip
```
Sejak langkah "Siap subpath" di fase F1, akses lokal sehari-hari melalui `http://localhost:8080/<app>/`; port langsung (`<port>`) tetap untuk pemeriksaan kesehatan.

**Catatan Alias:** di bawah subpath, tautan pendek menjadi `https://supportfkip.unsil.ac.id/alias/<kode>`. Bila kelak tersedia domain pendek khusus, cukup ubah `APP_URL`/`SHORT_URL` tanpa mengubah kode.

### 2.5 Panduan pengguna per peran (HTML + tangkapan layar)

Setelah semua fitur selesai (langkah terakhir sebelum rilis `v1.0.0`), setiap aplikasi membuat panduan per peran:

- Lokasi: `public/panduan/index.html` (daftar peran) dan `public/panduan/<peran>.html`, gambar di `public/panduan/img/<peran>/NN-<langkah>.png`. Tersaji di `https://supportfkip.unsil.ac.id/<app>/panduan/` dan **ditautkan dari landing page aplikasi**; kartu aplikasi di landing page `supportfkip` (root `index.html`) juga diberi tautan "Panduan".
- Format: **HTML statis saja** (bukan PDF/Markdown), CSS di dalam berkas, tanpa CDN, tautan & gambar **relatif** (aman untuk subpath), bahasa Indonesia, isi: tujuan peran, cara masuk, langkah bernomor + tangkapan layar + keterangan, tanya-jawab, kontak admin.
- Tangkapan layar dibuat otomatis dengan **Playwright** (service `<app>-panduan`, profile `panduan`, image `mcr.microsoft.com/playwright`) melalui gateway (`http://gateway/<app>`), memakai `PanduanSeeder` (akun demo per peran + **data rekaan**, hanya `APP_ENV=local`). Dilarang memakai data pribadi asli.
- Gambar panduan adalah aset statis buatan tim (bukan unggahan pengguna, §1a); kompres PNG, lebar maks 1366 px, total `public/panduan` ≤ 15 MB.
- Diuji `tests/Feature/PanduanTest.php` (setiap peran punya halaman, setiap gambar ada, tidak ada path absolut). Perbarui panduan setiap kali alur atau tampilan berubah signifikan.

## 3. Konfigurasi dasar `.env`

```dotenv
APP_NAME="<Nama Sistem> FKIP Unsil"
APP_URL=http://localhost:8080/<app>   # produksi: https://supportfkip.unsil.ac.id/<app> (§2.4)
APP_LOCALE=id
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=id_ID
APP_TIMEZONE=Asia/Jakarta          # set juga di config/app.php

DB_CONNECTION=mysql
DB_HOST=host.docker.internal       # MySQL native di host
DB_PORT=3306
DB_DATABASE=<app>
DB_USERNAME=<app>
DB_PASSWORD=<rahasia>

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=phpredis
REDIS_HOST=redis                   # container Redis bersama di jaringan fkip-net
REDIS_PREFIX=<app>_
REDIS_DB=<blok>                    # §2.1
REDIS_CACHE_DB=<blok+1>
REDIS_QUEUE_DB=<blok+2>
CACHE_PREFIX=<app>_cache_
HORIZON_PREFIX=<app>_horizon:

ASSET_URL=${APP_URL}               # §2.4 subpath
SESSION_PATH=/<app>
SESSION_COOKIE=<app>_session
TRUSTED_PROXIES=*                  # produksi: IP reverse proxy

FILESYSTEM_DISK=local
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
```

Uji (`phpunit.xml` / `.env.testing`): `DB_HOST=host.docker.internal`, `DB_DATABASE=<app>_testing`, `CACHE_STORE=array`, `QUEUE_CONNECTION=sync`, `SESSION_DRIVER=array` — uji tidak menyentuh Redis bersama.

## 4. Konvensi kode

- **Bahasa**: nama tabel/kolom/model memakai istilah domain berbahasa Indonesia (`inventaris`, `peminjaman`, `tanggal_mulai`) agar sesuai dokumen regulasi; kata kerja teknis Laravel tetap bahasa Inggris (`store`, `update`). Konsisten dalam satu proyek.
- **Struktur**: `app/Models`, `app/Enums` (status sebagai PHP Enum yang di-cast), `app/Actions` (satu kelas per aksi bisnis, mis. `SetujuiMutasi`), `app/Policies`, `app/Filament/Resources`, `app/Notifications`, `app/Jobs`.
- **Status alur kerja** selalu PHP Enum + tabel riwayat status (siapa, kapan, dari→ke, catatan). Jangan menyimpan riwayat sebagai JSON di satu kolom.
- **Kunci**: semua primary key memakai **UUIDv7** (`id` CHAR(36)); FK & morph ikut UUID. Tidak ada kolom `ulid` terpisah dan tidak ada `id` auto-increment. Rincian wajib §4a.
- **Uang**: `DECIMAL(15,2)` — jangan float.
- **Tanggal**: kolom `date`/`datetime`; tampilan format Indonesia (`d F Y`) via Carbon locale `id`.
- **Soft delete** untuk master data dan dokumen; transaksi yang sudah final tidak boleh dihapus, hanya dibatalkan dengan status.
- **Berkas**: tidak ada unggahan; gunakan tabel `tautan_berkas` dan aturan §1a. Jangan menambah `<input type="file">` / `FileUpload` Filament kecuali untuk **impor data Excel/CSV** yang diproses langsung lalu dihapus (bukan disimpan).
- **Otorisasi** di Policy, bukan di view. Setiap Resource Filament wajib memakai policy.
- **N+1**: aktifkan `Model::preventLazyLoading(! app()->isProduction())`.
- **Data pribadi** (NIK, HP, alamat) hanya tampil ke peran berwenang; log aktivitas tidak boleh menyimpan kata sandi/token.

## 4a. Kunci primer UUIDv7 (keputusan 6 Oktober 2026)

Semua sistem memakai **UUID versi 7** (berurut waktu, RFC 9562) sebagai primary key. Di Laravel 13 trait `HasUuids` sudah menghasilkan UUIDv7 (`Str::uuid7()`), jadi tidak perlu paket tambahan.

1. **Migrasi**: `$table->uuid('id')->primary();` — jangan `$table->id()`. FK: `$table->foreignUuid('prodi_id')->constrained('prodi')`. Polimorfik: `uuidMorphs('pemilik')` / `nullableUuidMorphs(...)` — jangan `morphs()`. Pivot tanpa entitas sendiri memakai PK komposit dua kolom UUID. Tipe MySQL `CHAR(36)` bawaan Laravel (bukan `BINARY(16)`), collation `utf8mb4_0900_ai_ci` agar sederhana dibaca di alat DB.
2. **Model**: setiap model memakai `use HasUuids;` (langsung di setiap model atau lewat kelas dasar `App\Models\ModelDasar`). Jangan menimpa `newUniqueId()` ke versi lain; jangan memakai `HasVersion4Uuids`/`HasUlids`.
3. **URL & QR**: rute publik/panel memakai `id` langsung (`/verifikasi/{naskah}`, route model binding bawaan, `->whereUuid('naskah')`). Kolom `ulid` tidak dibuat. UUIDv7 memuat stempel waktu pembuatan (milidetik) dan 74 bit acak: tidak dapat ditebak berurutan, tetapi **otorisasi Policy tetap wajib** — UUID bukan pengganti izin.
4. **Urutan**: UUIDv7 kira-kira berurut waktu sehingga indeks InnoDB tetap efisien; namun untuk tampilan urutkan dengan `created_at`, bukan `id`.
5. **Tabel paket** — sesuaikan migrasi yang di-publish **sebelum** `migrate` pertama:
   - `users`: `uuid('id')->primary()`; `sessions.user_id` → `foreignUuid('user_id')->nullable()->index()`.
   - `spatie/laravel-permission`: `roles.id` & `permissions.id` → `uuid`; pivot `model_has_roles`/`model_has_permissions` kolom `model_id` → `uuid`, `role_id`/`permission_id` → `uuid`; `role_has_permissions` → `uuid`. Model kustom `App\Models\Role` & `App\Models\Permission` (extends model spatie + `HasUuids`) didaftarkan di `config/permission.php` (`models.role`, `models.permission`); `column_names.model_morph_key` tetap `model_id`.
   - `spatie/laravel-activitylog`: `id` → `uuid`; `nullableUuidMorphs('subject')`, `nullableUuidMorphs('causer')`; model kustom `App\Models\Aktivitas` (extends `Spatie\Activitylog\Models\Activity` + `HasUuids`) di `config/activitylog.php` `activity_model`.
   - `notifications`: `id` sudah UUID; ganti `morphs('notifiable')` → `uuidMorphs('notifiable')`.
   - Sanctum `personal_access_tokens`: `id` → `uuid`, `uuidMorphs('tokenable')`; model kustom `App\Models\TokenAkses` (extends `Laravel\Sanctum\PersonalAccessToken` + `HasUuids`) via `Sanctum::usePersonalAccessTokenModel()`.
   - Filament Import/Export (`imports`, `exports`, `failed_import_rows`): `user_id` → `foreignUuid`; `id` ke UUID **bila** model kustom didukung versi Filament terpasang (cek via Boost); bila tidak, catat sebagai pengecualian di `docs/KEPUTUSAN.md`.
6. **Pengecualian yang diizinkan** (tabel infrastruktur kerangka kerja, bukan data domain, tidak pernah tampil di URL): `migrations`, `jobs`, `job_batches` (id sudah string), `failed_jobs` (punya kolom `uuid`), `cache`, `cache_locks`, `sessions` (id sudah string), `password_reset_tokens`. Tabel lain **tanpa kecuali** memakai UUIDv7.
7. **Integrasi antarsistem**: rujuk entitas sistem lain dengan **kode alami** (mis. `kode_ruangan`, `nip`) atau UUID-nya sebagai `VARCHAR(36)` tanpa FK lintas basis data.
8. **Uji arsitektur** (Pest, wajib sejak F1): semua kelas di `app/Models` memakai `HasUuids`; tidak ada berkas di `database/migrations` (selain tabel infrastruktur butir 6 dan pengecualian Filament yang tercatat menurut butir 5) yang memuat `->id()`, `foreignId(`, `morphs(` tanpa awalan `uuid`/`nullableUuid`, `bigIncrements(`, atau `increments(`.

## 5. Peran dasar lintas sistem

Setiap sistem minimal memiliki `super-admin` (TI fakultas) dan peran domain sesuai PRD masing-masing. Nama peran memakai kebab-case (`admin-fakultas`, `admin-prodi`, `pimpinan`, `dosen`, `mahasiswa`).

## 6. Kesiapan integrasi (tanpa membangun FKIP Edu)

FKIP Edu (portal & SSO) dikembangkan terpisah. Agar nanti mudah disambungkan:
- Tabel `users` memuat `email` unik (akun `@unsil.ac.id`), `nip`/`nidn` atau `npm` (nullable), `prodi_id` (nullable).
- Autentikasi dibungkus di satu tempat (panel Filament + guard `web`) sehingga kelak dapat diganti/ditambah login OIDC/Socialite tanpa mengubah modul lain.
- Data master lintas sistem (prodi, pegawai, mahasiswa) diberi kolom `kode_eksternal` untuk pemetaan ke sumber data pusat.
- Sediakan endpoint `GET /api/health` dan versi aplikasi di footer.

## 7. Deployment

- Mengikuti pola `docker-apps` (fkipapp, plp): image PHP-FPM 8.4 + Nginx (atau FrankenPHP), container `queue` (`php artisan horizon`), container `scheduler` (`php artisan schedule:work`), MySQL 8.4, Redis 7.
- `php artisan optimize`, `filament:optimize`, `icons:cache` saat build.
- Backup harian MySQL (retensi minimal 30 hari), uji pulihkan tiap semester. Berkas pengguna berada di Google Drive masing-masing unit; sarankan unit memakai Shared Drive fakultas agar berkas tidak hilang saat pegawai pindah/purnatugas.
- HTTPS wajib; cookie `secure`, `SESSION_SECURE_COOKIE=true`.
