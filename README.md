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
