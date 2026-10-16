# Deploy produksi — Persuratan & Layanan FKIP

Pola docker-apps: satu aplikasi di satu mesin, di belakang reverse proxy fakultas pada
`https://supportfkip.unsil.ac.id/surat`. Berkas yang dipakai: `Dockerfile`, `docker-compose.prod.yml`,
`.env.production.example`, `docker/` (php, nginx, backup).

> **Profil Ringan** (STANDAR-TEKNIS §1b dan §7, keputusan 15–16 Oktober 2026): antrean, cache, sesi, dan lock di MariaDB
> (driver `database`); tidak ada container `queue`, `redis`, `gotenberg`, maupun `db`. Satu container `scheduler` menjalankan jadwal
> sekaligus worker antrean (`--stop-when-empty` tiap menit). MariaDB berjalan native di host (`host.docker.internal`,
> `extra_hosts: host-gateway`). PDF memakai dompdf (Gotenberg hanya cadangan lewat ADR). Pemantauan lewat `/api/health` dan
> `php artisan queue:failed` / `queue:retry`, tanpa `/horizon`. Peralihan dari v1.0.0 (Horizon/Redis): lihat §11.

## 1. Arsitektur

```
internet ─TLS─▶ reverse proxy fakultas (/surat/ → 127.0.0.1:8028, awalan dipotong)
                    └▶ nginx (container `nginx`, aset statis + FastCGI)
                          └▶ app (php-fpm 8.5)        ─▶ MariaDB native di host (host.docker.internal:3306)
                    scheduler (schedule:work → queue:work tiap menit)  ─▶ MariaDB (antrean, cache, sesi, lock)
                    backup (profil `backup`, klien mariadb-dump, dijalankan cron host)
```

Port yang dibuka ke host hanya `127.0.0.1:${HTTP_PORT}`. Tidak ada container database, Redis, atau Gotenberg.

## 2. Prasyarat

| Hal | Ketentuan |
|---|---|
| Server | Linux, Docker Engine ≥ 26 + plugin compose, 2 vCPU / 4 GB RAM / 20 GB disk minimal |
| Basis data | MariaDB 10.11 LTS atau lebih baru, native; DB `db_surat` dan pengguna `app` khusus (hanya hak atas DB itu) |
| DNS dan TLS | `supportfkip.unsil.ac.id` sudah ada; sertifikat TLS dikelola di reverse proxy (bukan di aplikasi). Aktifkan HTTP→HTTPS dan HSTS di proxy; aplikasi juga mengirim HSTS pada produksi |
| Surel keluar | Akun SMTP fakultas (host, port, pengguna, kata sandi) |
| Jaringan keluar | Aplikasi memerlukan akses keluar ke `fonts.bunny.net` hanya saat **build** aset; saat berjalan hanya SMTP, API Aset, gerbang WhatsApp (bila dipakai), dan pemeriksaan tautan Drive |

Buat basis data (sekali):

```sql
CREATE DATABASE db_surat CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'app'@'%' IDENTIFIED BY '<kata-sandi-kuat>';
GRANT ALL PRIVILEGES ON db_surat.* TO 'app'@'%';
FLUSH PRIVILEGES;
```

Batasi `bind-address`/firewall MariaDB agar hanya dapat dijangkau dari host/Docker bridge.

## 3. Reverse proxy dan subpath

Subpath **persis** STANDAR-TEKNIS §2.4. Di `.env`:

```dotenv
APP_URL=https://supportfkip.unsil.ac.id/surat
ASSET_URL=https://supportfkip.unsil.ac.id/surat
SESSION_PATH=/surat
SESSION_COOKIE=surat_session
SESSION_SECURE_COOKIE=true
TRUSTED_PROXIES=<IP reverse proxy>          # bukan "*"
```

Blok pada reverse proxy fakultas:

```nginx
location = /surat { return 301 /surat/; }
location /surat/ {
    proxy_pass http://127.0.0.1:8028/;          # garis miring akhir = awalan dipotong
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Prefix /surat;
    client_max_body_size 20m;
}
```

**Awalan QR** (`/surat/verifikasi/…`) tercetak pada naskah: jangan mengubah awalan setelah produksi.

## 4. Build dan jalankan pertama kali

```bash
git clone <repo> /opt/surat && cd /opt/surat && git checkout v1.0.0
cp .env.production.example .env && chmod 600 .env     # isi DB_PASSWORD, SMTP, TRUSTED_PROXIES
docker compose -f docker-compose.prod.yml build        # ASSET_URL dibaca dari .env/lingkungan; build gagal bila kosong
docker compose -f docker-compose.prod.yml run --rm app php artisan key:generate --show   # salin ke APP_KEY, SIMPAN cadangan kunci
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
docker compose -f docker-compose.prod.yml exec app php artisan db:seed --force      # master: peran, jabatan, register, jenis, rubrik (tanpa akun)
docker compose -f docker-compose.prod.yml exec app php artisan surat:buat-superadmin   # interaktif; MFA wajib saat masuk pertama ke /surat/admin
docker compose -f docker-compose.prod.yml exec app php artisan optimize
```

Build memanggil `composer install --no-dev`, `npm run build` dengan `ASSET_URL` terisi, `filament:upgrade`; entrypoint menghangatkan
`optimize` dan `filament:optimize` dari lingkungan **runtime**. Migrasi tidak pernah berjalan otomatis.

Verifikasi: `curl -fsS http://127.0.0.1:8028/up`, `…/api/health` (db, job_tertunda, job_gagal_24j, detak_penjadwal; detak `ok` setelah ±1 menit), dan `docker compose … exec app php artisan schedule:list`.

Lengkapi master sebelum go-live (05-UJI §5): jabatan dan pemangku, kode klasifikasi arsip (`KlasifikasiArsipResource` → impor CSV), pola register, kop surat, kebijakan LPJ.

## 5. Pembaruan dan pembatalan rilis

```bash
cd /opt/surat && git fetch --tags && git checkout vX.Y.Z
SURAT_VERSI=X.Y.Z docker compose -f docker-compose.prod.yml build
docker compose -f docker-compose.prod.yml run --rm backup            # cadangan sebelum migrasi (profil backup)
SURAT_VERSI=X.Y.Z docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
docker compose -f docker-compose.prod.yml exec scheduler php artisan queue:restart      # muat ulang worker (yang berjalan selesai dulu)
```

Pembatalan: `git checkout vSEBELUMNYA`, `SURAT_VERSI=… up -d`; bila migrasi sudah berjalan dan merusak, pulihkan cadangan §6 (migrasi tidak pernah diubah setelah ditandai tag, jadi mundur skema = pulihkan cadangan).

## 6. Cadangan harian dan uji pulih

`docker/backup/backup.sh` membuat `mariadb-dump` konsisten (`--single-transaction`), mengompres, memverifikasi (gzip utuh dan memuat tabel `users`), menulis `.sha256`, lalu menghapus cadangan **> 30 hari** (`BACKUP_RETENSI_HARI`).

Jadwalkan di host (`crontab -e`, 02:15 setiap hari):

```cron
15 2 * * * cd /opt/surat && docker compose -f docker-compose.prod.yml --profile backup run --rm backup >> /var/log/surat-backup.log 2>&1
```

- Berkas dump berisi data pribadi: `BACKUP_DIR` hanya dapat dibaca root; salin harian ke penyimpanan terpisah (server lain/Shared Drive fakultas terbatas).
- **Uji pulih wajib tiap semester dan sebelum cutover**:

```bash
docker compose -f docker-compose.prod.yml --profile backup run --rm --entrypoint /bin/sh backup /skrip/uji-pulih.sh
```

  Skrip memulihkan cadangan terbaru ke basis data sementara `db_surat_uji_pulih`, membandingkan jumlah tabel dan baris `users`/`naskah`/`permohonan`/`nomor_terpakai`, lalu menghapusnya. Catat tanggal dan hasil uji pada log operasional.
- Selain basis data: simpan `.env` dan `APP_KEY` (terenkripsi, di luar server). Tanpa `APP_KEY` data terenkripsi pada cadangan tidak dapat dibaca.
- Volume `storage` hanya berisi log dan berkas sementara (≤ 24 jam); tidak ada unggahan pengguna (berkas = tautan).

## 7. Rotasi rahasia

| Rahasia | Cara |
|---|---|
| Token gerbang WhatsApp (`WA_GATEWAY_TOKEN`) | Terbitkan token baru di gerbang → ubah `.env` → `docker compose … up -d` (scheduler dan app dimuat ulang) → cabut token lama. Tiap 90 hari atau saat petugas berganti |
| Token API Aset (`ASET_API_TOKEN`) | Sama; koordinasi dengan pengelola sistem Aset |
| `DB_PASSWORD`, SMTP | Ubah di sumber → `.env` → `up -d` (app dan scheduler dimuat ulang) |
| `APP_KEY` | **Jangan diganti sembarangan**: data terenkripsi (telepon pengurus, penanggung jawab, rahasia MFA) tak terbaca. Bila terpaksa, lakukan migrasi enkripsi terencana dan MFA diatur ulang |
| Kata sandi pengguna | Pengguna sendiri; admin dapat memicu atur ulang. Tidak ada kata sandi bawaan |

Rahasia hanya di `.env` server (chmod 600), tidak pernah di repo, citra, atau log.

## 8. Cutover dari OrmawaHub (07-MIGRASI §9)

1. Umumkan jadwal; bekukan OrmawaHub (pesan pemeliharaan).
2. Ekspor final XLSX dan siapkan pemetaan CSV → `php artisan ormawahub:impor … --dry-run` → perbaiki pemetaan → impor sungguhan → `php artisan ormawahub:verifikasi` harus hijau.
3. `php artisan ormawahub:undang-pengguna` (surel atur kata sandi; antrean `default`).
4. **Hapus deployment Web App Google Apps Script OrmawaHub** (bukan hanya membatasi) agar `doGet`/`doPost` tidak dapat dipanggil; ganti kata sandi akun lama.
5. Ubah berbagi spreadsheet dan folder Drive menjadi terbatas; arsipkan baca-saja ≥ 1 tahun.
6. Alihkan alamat lama (alias) ke sistem baru; ormawa membuat akun pribadi dan ditautkan lewat NIM.

Bukti (tangkapan layar penghapusan deployment, hasil `ormawahub:verifikasi`, log uji pulih) disimpan sebagai arsip cutover.

## 9. Pemantauan dan perawatan

| Hal | Cara |
|---|---|
| Kesehatan | `/surat/up` (proses hidup), `/surat/api/health` (db → 503 bila gagal; `job_tertunda` > 10 menit, `job_gagal_24j`, dan `detak_penjadwal` `terlambat`/`belum-ada` adalah peringatan, tidak membuat 503); pantau dari alat monitoring fakultas |
| Antrean | `/api/health` (job tertunda dan gagal); `exec app php artisan queue:failed` dan `queue:retry <id>`; `docker compose … logs -f scheduler`. Super-admin menerima notifikasi harian bila ada job gagal |
| Penjadwal | `exec app php artisan schedule:list`; tugas berjalan oleh container `scheduler` |
| Log | `docker compose … exec app tail -f storage/logs/laravel.log` (level `warning`); log aktivitas audit di DB (retensi 1825 hari) |
| Kebijakan fakultas | Panel → Pengaturan sistem (min hari pengajuan, batas LPJ, blokir, sesi, WhatsApp, pengingat) |
| Pembaruan keamanan | `composer audit` dan `npm audit` tiap rilis (docs/KEAMANAN.md); perbarui citra dasar (`php`, `nginx`, klien `mariadb`) dan OS host berkala |

## 10. Pemecahan masalah

| Gejala | Periksa |
|---|---|
| Aset 404 / halaman tanpa gaya | `ASSET_URL` saat **build** harus sama dengan `APP_URL` (`docker compose … build --no-cache`), blok proxy memotong `/surat/` |
| Tautan/redirect menuju `http://` atau tanpa `/surat` | `TRUSTED_PROXIES` salah, header `X-Forwarded-Proto/Prefix` tidak diteruskan proxy |
| Logout otomatis / cookie tidak tersimpan | `SESSION_PATH=/surat`, `SESSION_SECURE_COOKIE=true` hanya bila diakses lewat HTTPS |
| Notifikasi/PDF/ekspor tidak jalan | Container `scheduler` hidup, `exec app php artisan schedule:list` memuat dua `queue:work`, `/api/health` (`detak_penjadwal` harus `ok`), `queue:failed`; ekspor besar memakai antrean `berat` (jeda ±1 menit) |
| `/api/health` `detak_penjadwal` = `belum-ada` | Scheduler belum berjalan ≥ 1 menit atau `CACHE_STORE` bukan `database`; periksa `.env` |
| Memori server menipis | `docker stats`; bila perlu ganti container `scheduler` dengan crontab host (STANDAR-TEKNIS §1b) |
| Galat enkripsi setelah pulih | `APP_KEY` berbeda dari saat data ditulis |

## 11. Peralihan dari v1.0.0 (Horizon/Redis) ke Profil Ringan

**Jangan merge ke `main` sebelum langkah 1–4 selesai.** `.github/workflows/deploy.yml` men-deploy otomatis setiap push ke `main`
(`~/docker-apps/deploy.sh surat`); compose baru tidak punya Redis, sehingga `.env` lama (`SESSION_DRIVER=redis`) membuat aplikasi mati.

| # | Langkah | Bukti |
|---|---|---|
| 1 | Jadwalkan di luar jam kerja dan umumkan: **semua sesi berakhir**, pengguna masuk ulang | Pengumuman |
| 2 | Cadangan penuh: `run --rm backup`, `uji-pulih.sh`, serta salinan `.env` dan `APP_KEY` di luar server. Simpan juga `.env` lama sebagai `.env.v1.0.0` | Log uji pulih |
| 3 | **Kuras antrean lama**: di `/surat/horizon` tunggu job tertunda dan berjalan = 0, lalu `exec queue php artisan horizon:terminate`. Job yang tersisa di Redis **tidak** berpindah dan akan hilang | Tangkapan layar Horizon kosong |
| 4 | Ubah `.env` di server: `SESSION_DRIVER=database`, `QUEUE_CONNECTION=database`, `CACHE_STORE=database`, tambah `APP_KODE=surat`, `PDF_DRIVER=dompdf`; hapus `REDIS_*`, `HORIZON_*`, `GOTENBERG_URL` (lihat `.env.production.example`) | `diff` terhadap `.env.production.example` |
| 5 | Merge ke `main` (atau `git checkout` rilis baru), lalu `up -d --remove-orphans` agar container `queue`, `redis`, `gotenberg`, dan `db` lama dihapus; `exec app php artisan migrate --force` (tabel `jobs`, `cache`, `cache_locks`, `sessions` sudah ada dari migrasi dasar) | `docker compose ps` |
| 6 | Verifikasi: `/api/health` → `db: ok`, `detak_penjadwal: ok` (≤ 2 menit); `schedule:list` memuat dua `queue:work`; kirim notifikasi uji dan terbitkan satu naskah uji (PDF dompdf); `docker stats` | Keluaran perintah |
| 7 | Ukur memori `docker stats` selama jam sibuk; sesuaikan `mem_limit` (nilai awal: app 512m, nginx 64m, scheduler 768m) | Catatan ukur |
| 8 | Setelah 7 hari tanpa masalah: hapus volume `redis-data` dan `db-data` lama (`docker volume ls`) | — |

**Pembatalan:** `git checkout v1.0.0`, kembalikan `.env.v1.0.0` ke `.env`, `docker compose -f docker-compose.prod.yml up -d --remove-orphans`.
Data basis data tidak berubah oleh peralihan ini (hanya tabel `jobs`/`cache`/`sessions` yang mulai terpakai), jadi tidak ada pemulihan DB; job yang
sempat dikirim ke antrean `database` selama percobaan dapat hilang saat mundur.
