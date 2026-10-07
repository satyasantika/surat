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
