# Keputusan & Pengecualian

## K-01 — Tabel impor Filament memakai id auto-increment

`imports` dan `failed_import_rows` (Filament Import Action) tetap memakai `id` bigint auto-increment karena Filament 5 tidak menyediakan pengaturan model kustom untuk `Import`/`FailedImportRow`. Hanya `imports.user_id` yang diubah menjadi `foreignUuid`. Tabel ini infrastruktur, tidak tampil di URL, dan dikecualikan di `tests/Arch/UuidTest.php` (STANDAR-TEKNIS §4a butir 5).

## K-02 — Nilai "perlu verifikasi" pada dokumen paket

Pengguna mengonfirmasi (7 Oktober 2026) bahwa nilai bawaan dokumen (domain surel mahasiswa, kode unit `UN58.10`, struktur jabatan FKIP, pola nomor, kode klasifikasi) sudah benar dan dipakai apa adanya.

## K-03 — MariaDB sebagai pengganti MySQL

Basis data memakai MariaDB 10.11 di host; kolom `uuid()` Laravel dipetakan ke tipe native `uuid` MariaDB.

## K-04 — Halaman publik: /organisasi dan formulir /verifikasi

Profil publik ormawa berada di `/organisasi` (bukan `/ormawa`) karena `/ormawa` sudah dipakai beranda pengurus yang memerlukan login. `/verifikasi` kini formulir kode/tautan yang mengalihkan ke `/verifikasi/{id}`; ia tidak pernah menampilkan daftar naskah (uji di `VerifikasiTest`).

## K-05 — Asumsi impor OrmawaHub

Format kolom sheet mengikuti 07-MIGRASI-DATA §4–6; nama kolom CSV pemetaan ditetapkan sebagai `pengguna.csv` (`id_lama,email,peran,jabatan`), `ormawa.csv` (`id_lama,nama,slug,tingkat,buang`), `ruangan.csv` (`id_lama,kode_ruangan`), `wd.csv` (`kode_lama,kode_jabatan`). SK kepengurusan tidak dikarang karena sumber tak memuat nomor/periode: hanya tautan `sk`, pengurus belum aktif (BR-02) sampai admin mengisi SK. Pemangku jabatan hasil impor bermasa jabatan mulai hari impor; data historis memakai pemangku terbaru bila tanggalnya mendahului. Permission `naskah.susun`/`naskah.terbitkan` pada §4a dipetakan ke `naskah.draf`/`nomor.terbitkan` (nama izin yang ada). Nama tahap di `steps` dikenali lewat kata kunci (validasi, disposisi, wd/wakil, kasubag/rekomendasi, terbit/surat, ajuk) dengan cadangan `currentStep`; kecocokan format riil perlu diuji dry-run pada ekspor asli.

## K-06 — Profil Ringan (15 Oktober 2026)

Semua sistem aktif, termasuk Surat, memakai Profil Ringan (STANDAR-TEKNIS §1b): tanpa Horizon, Redis, dan Octane; antrean, cache, sesi, dan lock di MySQL; satu container `scheduler` menjalankan jadwal dan worker; `/api/health` untuk pemantauan; dompdf bawaan, Gotenberg hanya cadangan (ADR). Berlaku setelah Fase B; kode v1.0.0 masih memakai Horizon/Redis/Gotenberg. Catatan K-01 sampai K-05 dan riwayat CHANGELOG tidak diubah. Alasan: lihat PERUBAHAN-PROFIL-RINGAN-00-README.md (Horizon, Redis, dan Octane dirancang untuk beban jauh di atas kebutuhan sistem ini).

## K-07 — PHP 8.5, MariaDB native, tanpa SQLite (16 Oktober 2026)

Keputusan pemilik, berlaku untuk semua sistem: (1) PHP 8.5; (2) basis data MariaDB 10.11 LTS atau lebih baru, native di host (bukan MySQL, bukan container); (3) SQLite tidak dipakai untuk pengembangan, uji, maupun produksi (uji memakai MariaDB database `<app>_testing`); (4) kolasi baku `utf8mb4_unicode_ci` (bukan `utf8mb4_0900_ai_ci`). Rujukan baku: STANDAR-TEKNIS. Dokumen `vibecoding/` diselaraskan; `DB_CONNECTION=mariadb`. Catatan K-03 dan K-06 serta riwayat CHANGELOG tidak diubah (K-06 menyebut MySQL sebelum keputusan ini; baca sebagai MariaDB).

## K-08 — Nama DB seragam, UUID native, produksi tanpa container DB, PHP 8.5 (16 Oktober 2026, lanjutan)

Keputusan pemilik, berlaku untuk semua sistem; rujukan baku: STANDAR-TEKNIS §2.1, §2.2, §4a, §7. (1) Nama basis data seragam `db_<app>` dan `db_<app>_testing` (Surat: `db_surat`, `db_surat_testing`); nama pengguna DB tidak diubah. Ini menggantikan penamaan `surat`/`surat_testing` pada dokumen `vibecoding/` (sebelumnya bertentangan dengan kode yang sudah memakai `db_surat`) dan frasa "database `<app>_testing`" pada K-07. (2) UUID mengikuti Laravel 13: `uuid()`/`foreignUuid()`/`uuidMorphs()` menghasilkan tipe `UUID` native MariaDB; UUIDv7 dibuat aplikasi lewat `HasUuids`; klaim tipe `CHAR(36)` dicabut, `char('id', 36)`/`binary(16)` eksplisit dilarang; gerbang uji: kolom `id` bertipe `uuid`/`char(36)`, 100 id UUIDv7 terurut sesuai `ORDER BY id`, FK dan morph berfungsi. (3) Produksi memakai MariaDB native di host lewat `host.docker.internal` (`extra_hosts: host-gateway`), sama dengan aplikasi produksi lain; compose produksi tidak boleh memuat container database; CI boleh memakai service container sementara; alat dump `mariadb-dump`; variabel dan healthcheck CI `MARIADB_*` dan `healthcheck.sh --connect --innodb_initialized`. (4) PHP 8.5: `ARG PHP_VERSION=8.5` pada Dockerfile produksi (sudah) dan `"php": "^8.5"` pada `composer.json` (kondisi saat ini `^8.3`) menjadi butir Fase B. Berlaku setelah Fase B untuk kode: compose produksi saat ini masih memuat service `db` (profil `db-lokal`) dan `migrations/..._create_impor_ormawahub_log_table.php` masih memakai `char('id_baru', 36)`.
