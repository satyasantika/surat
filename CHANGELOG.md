# Catatan Perubahan

Format mengikuti [Keep a Changelog](https://keepachangelog.com/id-ID/1.1.0/) dan [Semantic Versioning](https://semver.org/lang/id/).

## [Belum dirilis]

## [1.0.0] - 2026-10-07

Fase 12: pengerasan, produksi, uji penerimaan, dan panduan. Rilis pertama yang siap produksi.

### Ditambahkan
- Keamanan (`docs/KEAMANAN.md`): header keamanan global (`HeaderKeamanan`: nosniff, X-Frame-Options, Referrer-Policy, Permissions-Policy, HSTS produksi) dan CSP bernonce untuk halaman aplikasi tanpa `unsafe-inline` pada skrip; uji lintas-permukaan `MatriksAksesTest` (seluruh izin × peran PRD §3.1), `KlasifikasiRahasiaTest`, `IdorTest`, `VerifikasiPublikTest`, `RateLimitTest`, `XssTest`, `Arch/TanpaHtmlMentahTest`, `TanpaUnggahTest`, `TanpaKredensialBawaanTest`, `KonkurensiTest`, `HeaderKeamananTest`.
- Produksi (`docs/DEPLOY.md`): `Dockerfile` bertahap (php-fpm 8.5, nginx + aset berawalan `/surat/`), `docker-compose.prod.yml` (app, nginx, queue Horizon, scheduler, redis, gotenberg internal, cadangan, profil basis data lokal), `.env.production.example`, cadangan harian `mariadb-dump` retensi 30 hari dengan verifikasi, uji pulih, rotasi token, dan langkah cutover.
- Uji penerimaan: `php artisan surat:siapkan-uat` (data rekaan: akun per peran, 3 ormawa, surat masuk, permohonan di tiap tahap, LPJ, kabar, galeri; idempoten; ditolak di produksi).
- Panduan pengguna HTML per peran dengan 59 tangkapan layar otomatis (Playwright) di `public/panduan/` (`/panduan/`), ditautkan dari halaman publik, masuk, aplikasi, dan panel; `PanduanSeeder` (hanya lokal), `composer panduan`, `PanduanTest`.

### Diubah
- Dependensi dev `concurrently` dihapus (kerentanan kritis `shell-quote`); `npm audit` dan `composer audit` bersih. Tambahan dev: `playwright-core` (hanya untuk panduan).
- Panduan Markdown lama (`docs/PANDUAN-*.md`) menjadi tautan ke versi HTML.

### Langkah manusia sebelum go-live
Cutover OrmawaHub (impor final, `ormawahub:verifikasi`, hapus deployment Web App GAS), uji pulih cadangan, verifikasi nilai bawaan terhadap Permendiktisaintek 42/2025 (05-UJI §5), dan MFA seluruh pejabat/admin.

## [0.11.0] - 2026-10-07

Fase 11: notifikasi, penjadwal, laporan, dan arsip.

### Ditambahkan
- Notifikasi tahap (BR-21) lewat satu pintu `PengirimNotifikasi`: database (juga lonceng panel), surel, dan WhatsApp opsional (kontrak `GerbangWhatsapp`, implementasi HTTP ke `WA_GATEWAY_URL`/token `.env`, aktif bila pengaturan `wa_aktif`; isi hanya ringkasan + tautan). Peristiwa: surat masuk perlu disposisi, disposisi diterima/diteruskan/dilaporkan, naskah perlu paraf/tanda tangan/dikembalikan/terbit, setiap transisi permohonan ke pihak berikutnya dan ormawa, LPJ diajukan/dinilai, kabar diajukan/diputuskan. Perihal surat/naskah non-biasa tidak pernah disertakan. Preferensi per kategori dan nomor WhatsApp di `/profil`, daftar notifikasi di `/notifikasi`. Kegagalan notifikasi tidak menggagalkan alur bisnis.
- Penjadwal (02-ARSITEKTUR §8, tanpa tumpang tindih dan satu server): `surat:pengingat-disposisi` (tiap jam; menandai terlambat), `surat:pengingat-permohonan` (07:00; tertahan > N hari), `surat:pengingat-lpj` (07:00; H-3, H, terlambat mingguan), `surat:tandai-blokir-lpj` (01:00; blokir dan pencabutan, juga langsung saat LPJ diajukan), `surat:periksa-tautan` (Senin 06:00; admin diberi tahu saat tautan mati), `surat:bersihkan-tmp`, `surat:laporan-retensi` (bulanan). Pengingat tepat sekali per kunci (`pengingat_terkirim`).
- Dasbor panel per peran (surat masuk belum didisposisikan, disposisi terlambat per pejabat, naskah menunggu tanda tangan, permohonan per tahap, LPJ terlambat, ormawa terblokir) dan halaman Laporan LAP-01–LAP-06 dengan filter periode serta ekspor XLSX lewat antrean (tmp ≤ 24 jam, unduhan bertanda tangan milik pemohon); izin `laporan.lihat`; laporan tanpa data pribadi dan perihal rahasia dirahasiakan.
- Arsip: aksi admin "Arsipkan" untuk surat masuk selesai, peninjauan retensi aktif/inaktif per klasifikasi (hanya laporan, tidak ada penghapusan otomatis), dan halaman Arsip untuk mencari nomor, perihal, asal/tujuan, klasifikasi yang menghormati klasifikasi keamanan.

### Diperbaiki
- Ekspor register XLSX tidak lagi menulis baris header ganda.

## [0.10.0] - 2026-10-07

Fase 10: migrasi data OrmawaHub.

### Ditambahkan
- `ormawahub:impor {xlsx} --pemetaan= [--pelaksana=] [--dry-run]`: impor XLSX ekspor OrmawaHub dengan pemetaan CSV (pengguna, ormawa, ruangan, wd) yang divalidasi lengkap sebelum menulis; satu transaksi per sheet; dry-run = rollback; idempoten (kunci `sumber_id_lama`/`nomor_lama` dan log); XLSX dan CSV dihapus setelah impor sungguhan tanpa galat. Jejak tiap baris di `impor_ormawahub_log`.
- Sheet yang diimpor: Users (peran, pemangku jabatan; kata sandi dan urlTte tidak dibaca), Ormawa_Profiles (logo/SK sebagai tautan; SK "perlu dilengkapi"), Pengurus (telepon terenkripsi, tanpa akun), Rooms/RektoratRooms (sesuai mode ruangan), Requests (permohonan bernomor baru berurut tanggal pengajuan, ruangan, riwayat dengan `pelaku_lama`, disposisi, persetujuan WD, tautan, naskah arsip dan klaim nomor register, booking manual), Laporan (LPJ dan nilai per penilai), Blogs (kabar disanitasi), Galleries (galeri tervalidasi). Data contoh templat (S-13) dibuang; pengurai tanggal campuran (id/ISO/serial Excel).
- `ormawahub:verifikasi`: 8 cek §8 (jumlah per sheet, rekap status, ruangan, register nomor, sampel LPJ, pengguna, data contoh, privasi halaman publik) dengan kode keluar ≠ 0 bila gagal.
- `ormawahub:undang-pengguna`: undangan atur kata sandi lewat antrean, sekali per akun (`users.diundang_pada`), `--surel` dan `--dry-run`.
- Halaman panel "Log migrasi" (hanya super-admin) dengan filter status, sheet, dan batch.

## [0.9.0] - 2026-10-07

Fase 9: kabar, galeri, dan halaman publik.

### Ditambahkan
- Kabar: pengurus mengusulkan dari `/ormawa/{id}/kabar` (draf → diajukan), admin/operator (`kabar.kelola`) menerbitkan atau menolak dengan catatan di panel; isi disanitasi saat simpan dan saat render; foto sampul berupa tautan (Drive lewat lh3).
- Galeri berbasis tautan (foto/video/instagram) dengan validasi per tipe, panel `GaleriResource`, dan saran nonaktif otomatis dari LPJ yang sudah dinilai.
- Halaman publik tanpa login: beranda, `/kabar`, `/kabar/{slug}`, `/galeri`, `/organisasi`, `/organisasi/{slug}`, formulir `/verifikasi`. Hanya kabar terbit dan galeri aktif; pengurus hanya nama dan jabatan (tanpa NIM/telepon/surel); video YouTube tanpa cookie, Instagram hanya tautan; CSP ketat; HTML jadi di-cache maksimal 5 menit dan disegarkan saat konten berubah.

### Diubah
- `/` kini beranda publik; `/verifikasi` menjadi formulir kode (K-04).

## [0.8.0] - 2026-10-07

Fase 8: LPJ dan penilaian.

### Ditambahkan
- LPJ dibuat otomatis (draf, batas waktu) saat permohonan selesai; formulir `Ormawa\IsiLpj` dengan tautan berkas LPJ wajib dan tautan media tervalidasi; `Ormawa::lpjTerlambat()` memblokir pengajuan baru bila lewat toleransi (dapat dimatikan di pengaturan, BR-16).
- Rubrik LPJ sebagai master (`RubrikLpjResource`, izin `master.kelola`) dengan seeder rubrik lama (total 100), penilaian oleh pemangku jabatan yang tercantum (`NilaiLpj`), nilai 0..maks, revisi sebelum final, `HitungNilaiAkhir` (setelah semua penilai lengkap; rumus jumlah atau persen), `LpjResource` dengan aksi nilai dan matriks nilai; ormawa melihat nilai akhir dan catatan hanya setelah final.

## [0.7.0] - 2026-10-07

Fase 7: permohonan ormawa dan ruangan.

### Ditambahkan
- Jenis permohonan (4 jenis), layanan ruangan `LayananRuangan` dengan implementasi lokal dan klien Aset API (cache 60 detik, 409 → bentrok, 5xx/jaringan → `LayananRuanganTidakTersedia`, tanpa data palsu), sesi dari pengaturan ("seharian" bentrok dengan semua sesi), katalog ruangan lokal dan pemakaian manual.
- `AjukanPermohonan`: hanya pengurus aktif ber-SK berlaku, blokir LPJ, nomor `PMH-{tahun}-{urut}`, BR-15 (alasan mendesak), tautan berkas wajib, penahanan ruangan atomik per ruangan+tanggal (GET_LOCK MariaDB, teruji 8 pengajuan paralel → satu pemenang), batas laju 10/jam, data pribadi penanggung jawab terenkripsi.
- Peta transisi `AlurPermohonan` dan aksi: persetujuan pembina, validasi, pengembalian dan ajukan ulang, penolakan (melepas ruangan), pembatalan oleh ormawa; disposisi dekan ke satu/lebih WD, putusan WD (semua setuju → rekomendasi, satu tolak → ditolak, Plt dapat memutus), rekomendasi kasubag.
- Penerbitan surat izin: draf naskah otomatis (cek bentrok ulang di dalam kunci), surat pengantar rektorat terpisah, listener `NaskahTerbit` menyelesaikan permohonan dan mengonfirmasi ruangan, job `CatatPemakaianRuangan` idempoten dengan retry dan notifikasi admin.
- UI: `/ormawa/{id}/permohonan/baru` (formulir bertahap dengan ketersediaan langsung), progres dengan linimasa, tab "Permohonan ormawa" di `/disposisi`, resource panel dengan tab per status dan riwayat, halaman "Plot ruangan".

### Diubah
- Kunci asing `pemakaian_ruangan_lokal.permohonan_id` dilepas dan kolomnya menjadi string (referensi buram).
- Pola register permohonan menjadi `PMH-{tahun}-{urut:4}`.

## [0.6.0] - 2026-10-07

Fase 6: ormawa.

### Ditambahkan
- Profil ormawa (slug unik otomatis, pembina berperan pembina-ormawa, logo sebagai tautan) dan SK kepengurusan dengan `Ormawa::skBerlaku()` dihitung dari periode.
- Pengurus per orang (`PengurusOrmawa`): telepon terenkripsi, NIM dan telepon disembunyikan dari serialisasi dan jejak audit (BR-18), `PengurusOrmawa::aktifPada()`, `User::ormawaAktif()`, `User::dapatMengelolaOrmawa()` (BR-02).
- `TautkanAkunPengurus`: penautan akun lewat NIM oleh admin atau ketua/sekretaris, hanya akun aktif bersurel terverifikasi; tidak otomatis saat pendaftaran.
- Kebijakan `OrmawaPolicy`, `PengurusOrmawaPolicy` (hak data pribadi), `SkKepengurusanPolicy`; pembina hanya melihat binaannya.
- Ruang ormawa `/ormawa` dan `/ormawa/{id}/profil` (Livewire): beranda dengan pilihan ormawa, ubah profil dan pengurus oleh ketua/sekretaris, penjaga agar ormawa tidak kehilangan pengelola.

### Diubah
- Peran pembina-ormawa tidak lagi memiliki `ormawa.lihat` (hanya `ormawa.kelola-binaan`).

## [0.5.0] - 2026-10-07

Fase 5: naskah keluar dan verifikasi QR.

### Ditambahkan
- Naskah keluar: layout A4 dan templat per jenis, isian dinamis dari definisi variabel, isi kaya disanitasi (`symfony/html-sanitizer`, daftar tag putih) saat simpan dan saat render, satu komponen terpusat untuk keluaran HTML mentah, pratinjau ber-CSP.
- Paraf berurutan, pengembalian dengan catatan, `riwayat_naskah` yang tidak dapat diubah; semua transisi lewat `TransisiNaskah` (kunci per naskah, FOR UPDATE).
- Tanda tangan (`TandaTangani`): nomor dibentuk saat tanda tangan, snapshot beku, pembekuan kolom di model, mode basah dan visual (gambar diambil server, anti-SSRF, disimpan di snapshot), a.n./u.b./Plt, mode TTE belum didukung.
- Render PDF dari snapshot dengan QR, hash SHA-256 deterministik (dompdf, `PDF_DRIVER_NASKAH`), job `TerbitkanNaskah`, unduh PDF, pencatatan pindaian tanda tangan basah.
- Verifikasi publik `/verifikasi/{uuid}` (bidang putih, 30 permintaan/menit/IP, pencocokan hash di peramban tanpa unggah, 404 generik).
- Pembatalan naskah (nomor tidak dipakai ulang), register surat keluar dan masuk hanya-baca dengan ekspor XLSX (tmp dihapus otomatis ≤ 24 jam, unduhan bertanda tangan).

### Diubah
- Pola register `sk-dekan` dan `surat-tugas` memakai `{kode_jenis}`; formulir register menolak pola ganda (nomor kembar antarregister).
- Batas memori uji dinaikkan (1 GB) karena banyak render PDF.

## [0.4.0] - 2026-10-07

Fase 4: surat masuk dan disposisi.

### Ditambahkan
- Perintah `surat:buat-superadmin` untuk akun super-admin pertama.
- Registrasi surat masuk (`RegistrasiSuratMasuk`): nomor agenda dari register `agenda-masuk`, tautan pindaian wajib, klasifikasi keamanan dan derajat kecepatan (enum), resource panel dengan filter dan relation manager tautan berkas.
- `SuratMasukPolicy` (BR-04): surat rahasia/sangat rahasia hanya untuk dekan, penerima disposisi, dan super-admin; admin persuratan hanya metadata (perihal disamarkan `[RAHASIA]`, tanpa ringkasan dan tautan).
- Disposisi berjenjang (`BuatDisposisi`, `TandaiDibaca`, `LaporTindakLanjut`, `SelesaikanDisposisi`): batas waktu bawaan per derajat, disposisi lanjutan berantai, CHECK satu sumber di basis data, izin baru `disposisi.teruskan`.
- Kotak masuk pimpinan `/disposisi` (Livewire, ramah ponsel) dan relation manager status penerima di panel.
- Lembar disposisi PDF yang di-stream (`/surat-masuk/{id}/lembar-disposisi`).

### Diubah
- `disposisi.buat` hanya untuk dekan; wakil dekan dan kasubag meneruskan lewat `disposisi.teruskan`.

## [0.3.0] - 2026-10-07

Fase 3: master data.

### Ditambahkan
- Unit kerja (pohon), jabatan, dan pemangku jabatan (Plt, SK) dengan `Jabatan::pemangkuPada()` dan `User::jabatanAktif()`; larangan dua pemangku definitif yang tumpang tindih; seeder struktur FKIP (`UN58.10`).
- Klasifikasi arsip hierarkis dengan retensi, impor CSV lewat antrean `impor`, cache `surat:master:klasifikasi`, pencegahan siklus.
- Register nomor dan mesin penomoran `AmbilNomorBerikutnya` (Cache::lock + transaksi + `FOR UPDATE` + UNIQUE, dengan percobaan ulang deadlock), `TandaiNomorBatal`, token pola lengkap; nomor tidak dapat diubah/dihapus; uji 20 proses paralel.
- Jenis naskah dengan definisi variabel, mode tanda tangan diizinkan, validasi templat Blade, dan sepuluh templat awal.
- Pengaturan sistem ter-cache beserta halaman pengaturan (super-admin).
- Fondasi tautan berkas: `TautanBerkasValid`, `PenyimpananBerkas`/`TautanEksternal`, job `PeriksaTautanBerkas` (anti-SSRF: daftar putih, IP publik, DNS dipaku, tanpa redirect), komponen `x-tautan-berkas`, pengalihan berotorisasi, `ttd_visual` tidak pernah menjadi URL; uji arsitektur tanpa unggahan berkas.
- `docs/KEPUTUSAN.md` (K-01 tabel impor Filament, K-02 nilai terverifikasi, K-03 MariaDB).

### Catatan
- Notifikasi ke pemilik saat tautan mati dan pemeriksaan mingguan terjadwal menyusul di F11.
- Templat naskah awal minimal; dirancang ulang di F5.1.

## [0.2.0] - 2026-10-07

Fase 2: login, peran, audit, pengguna.

### Ditambahkan
- Perluasan `users` (NIP/NIM, telepon, unit kerja, aktif, sumber id lama), rule `SurelDomainUnsil`.
- Sembilan peran dan permission granular per modul (`PeranDanIzinSeeder`, idempoten) beserta `Gate::before` super-admin.
- Login aman: panel `/admin` (reset sandi, profil, MFA aplikasi wajib untuk super-admin, admin-persuratan, dekan, wakil-dekan), halaman `/masuk`, `/daftar`, `/lupa-sandi`, `/profil` untuk pengurus ormawa dan pegawai; rate limit 5/menit per surel+IP; akun nonaktif ditolak; tanpa registrasi terbuka dan tanpa akun bawaan.
- Pendaftaran mandiri pengurus hanya untuk surel mahasiswa dengan verifikasi surel.
- Jejak audit server-side (`TercatatAktivitas`), log aktivitas hanya-baca untuk super-admin, fitur "masuk sebagai" bertanda banner merah dan tercatat.
- Kelola pengguna: peran, izin langsung operator-layanan, aktif/nonaktif; aturan otorisasi ditegakkan di `SimpanPengguna`.

### Catatan
- Halaman `/masuk` dan sejenisnya memakai Blade + controller (bukan Livewire) agar pembatasan laju mengembalikan HTTP 429.
- Pola surel mahasiswa (`student.unsil.ac.id`) dan struktur peran WD perlu diverifikasi.

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
