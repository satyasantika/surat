# Catatan Perubahan

Format mengikuti [Keep a Changelog](https://keepachangelog.com/id-ID/1.1.0/) dan [Semantic Versioning](https://semver.org/lang/id/).

## [Belum dirilis]

## [0.1.0] - 2026-10-07

Fase 1: fondasi.

### Ditambahkan
- Laravel 13 di container `surat-php`/`surat-nginx` (compose pusat), MariaDB host, Redis bersama (DB 52/53/54).
- Pest 4, Larastan level 6, Pint, hook git `commit-msg` dan `pre-commit`, CI GitHub Actions (MariaDB 10.11, Redis 7).
- Filament 5 (panel `admin`), Horizon, spatie permission & activitylog (retensi 1825 hari), simple-excel, endroid/qr-code.
- Kunci primer UUIDv7 di semua tabel aplikasi beserta uji arsitektur.
- Dukungan subpath `/surat` (root URL, trusted proxy, cookie `surat_session`, aset Vite) beserta uji.
- Kontrak `PembangkitPdf` dengan driver Gotenberg dan cadangan dompdf.
- Laravel Boost dan endpoint `GET /api/health`; versi aplikasi di footer panel.

### Catatan
- Container Gotenberg dan Horizon belum ada di compose pusat (di luar repo); lihat README.
- Dokumen `docs/` ditulis untuk MySQL 8.4; implementasi memakai MariaDB 10.11.
