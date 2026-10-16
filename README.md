# Persuratan & Layanan FKIP Unsil

Sistem persuratan fakultas berbasis tata naskah dinas (surat masuk & disposisi, naskah keluar dengan nomor otomatis dan QR verifikasi, register & arsip) dengan **Layanan Ormawa** di atasnya. Pengganti OrmawaHub / SipOrmawa-FKIP.

Stack: Laravel 13, MariaDB, Filament 5, Livewire 4, Tailwind 4, dompdf.

> **Profil Ringan** (STANDAR-TEKNIS §1b): antrean, cache, sesi, dan lock di MariaDB (driver `database`); tanpa Redis, Horizon, dan Gotenberg. Satu container `surat-scheduler` menjalankan jadwal sekaligus worker antrean; PDF memakai dompdf; pemantauan lewat `/api/health`. PHP 8.5 dan UUID native MariaDB (STANDAR-TEKNIS §4a). Peralihan produksi dari v1.0.0: `docs/DEPLOY.md` §11.

## Dokumentasi

Lihat `docs/`: keputusan teknis (`KEPUTUSAN.md`), panduan pengguna, keamanan, dan deploy.

## Menjalankan lokal

Aplikasi memakai container yang dikelola compose pusat `~/code/docker-compose.yml` (`surat-php` PHP 8.5, `surat-nginx`, jaringan `laranet`) dan **MariaDB** native di host (DB `db_surat` dan `db_surat_testing`, dijangkau lewat `host.docker.internal`). Tidak ada Redis.

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

## Worker antrean di lokal

Antrean memakai driver `database`. Compose pusat (`~/code/docker-compose.yml`, di luar repo ini) cukup ditambah satu service penjadwal yang sekaligus
menyalakan worker (`routes/console.php`); tidak perlu Redis, Horizon, maupun Gotenberg:

```yaml
  surat-scheduler:
    build: { context: ., dockerfile: php/laravel8.5.Dockerfile }
    restart: unless-stopped
    working_dir: /var/www/html
    volumes: ["./surat:/var/www/html"]
    extra_hosts: ["host.docker.internal:host-gateway"]
    command: php artisan schedule:work
    networks: [laranet]
```

Tanpa service itu, proses antrean secara manual: `srp php artisan queue:work database --queue=default --stop-when-empty`
(job berat: `queue:work database-berat --queue=berat --stop-when-empty`). Pantau dengan `curl http://localhost:8028/api/health`
dan `srp php artisan queue:failed`.

## Uji penerimaan (staging)

`php artisan surat:siapkan-uat` menyiapkan data **rekaan** (ditolak di produksi): akun per peran (`uat.<peran>@contoh.test`, kata sandi acak dicetak sekali),
3 ormawa, surat masuk, permohonan di setiap tahap, LPJ (terlambat/diajukan/dinilai), kabar, dan galeri; idempoten.
Panduan pengguna: **docs/PANDUAN-ADMIN.md**, **docs/PANDUAN-PIMPINAN.md**, **docs/PANDUAN-ORMAWA.md** (versi HTML dengan tangkapan layar di `public/panduan/`).

## Memperbarui panduan

Panduan pengguna HTML per peran ada di `public/panduan/` (tersaji di `/panduan/`, ditautkan dari landing page, halaman masuk, aplikasi, dan panel).
Alurnya didefinisikan di `tests/panduan/alur.json`; `tangkap.mjs` menangkap layar (Playwright) dan `bangun.mjs` menulis HTML statis.

```bash
npm install            # sekali: playwright-core (browser berasal dari image Playwright)
composer panduan       # = bash tests/panduan/jalankan.sh (dijalankan dari host)
```

Skrip menyiapkan basis data **sementara** (`db_surat_testing`), mengisinya dengan `PanduanSeeder` (akun `panduan.<peran>@contoh.test`, data rekaan,
hanya `APP_ENV=local`), menjalankan server artisan sementara di container `surat-php`, menangkap layar lewat container
`mcr.microsoft.com/playwright`, lalu membangun HTML. `PANDUAN_PASSWORD` dibuat acak per proses (atau set sendiri). Akun demo hanya dikecualikan dari MFA di
lokal (`PANDUAN_TANPA_MFA`). Panduan **super-admin bersifat internal**: dibangun ke `docs/panduan-internal/` (bukan `public/`), tidak ditautkan di indeks maupun landing page, dan tidak ikut citra produksi. Untuk satu peran saja: `PANDUAN_PERAN=dekan,kasubag composer panduan`.

Bila ingin menjalankannya sebagai service compose pusat (`~/code/docker-compose.yml`, di luar repo) seperti STANDAR-TEKNIS §2.5:

```yaml
  surat-panduan:
    image: mcr.microsoft.com/playwright:v1.63.0-noble
    profiles: ["panduan"]
    working_dir: /work
    volumes: ["./surat:/work"]
    ipc: host
    environment:
      PANDUAN_BASE_URL: http://gateway/surat
      PANDUAN_PASSWORD: ${PANDUAN_PASSWORD}
    command: node tests/panduan/tangkap.mjs
    networks: [laranet]
```

## Produksi

Citra dan compose produksi (pola docker-apps), cadangan harian + uji pulih, rotasi token, dan cutover: **docs/DEPLOY.md**. Pengerasan keamanan dan audit dependensi: **docs/KEAMANAN.md**.
