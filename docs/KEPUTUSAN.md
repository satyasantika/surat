# Keputusan & Pengecualian

## K-01 — Tabel impor Filament memakai id auto-increment

`imports` dan `failed_import_rows` (Filament Import Action) tetap memakai `id` bigint auto-increment karena Filament 5 tidak menyediakan pengaturan model kustom untuk `Import`/`FailedImportRow`. Hanya `imports.user_id` yang diubah menjadi `foreignUuid`. Tabel ini infrastruktur, tidak tampil di URL, dan dikecualikan di `tests/Arch/UuidTest.php` (STANDAR-TEKNIS §4a butir 5).

## K-02 — Nilai "perlu verifikasi" pada dokumen paket

Pengguna mengonfirmasi (7 Oktober 2026) bahwa nilai bawaan dokumen (domain surel mahasiswa, kode unit `UN58.10`, struktur jabatan FKIP, pola nomor, kode klasifikasi) sudah benar dan dipakai apa adanya.

## K-03 — MariaDB sebagai pengganti MySQL

Basis data memakai MariaDB 10.11 di host; kolom `uuid()` Laravel dipetakan ke tipe native `uuid` MariaDB.
