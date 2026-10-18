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

## K-09 — Pelaksanaan Profil Ringan di kode: pilihan teknis (16 Oktober 2026)

Pilihan yang diambil saat menerapkan K-06 sampai K-08 pada kode (cabang `feat/profil-ringan`). **Menunggu persetujuan pemilik**; yang ditandai (!) berbeda dari teks standar atau menyentuh kebijakan.

1. Nama antrean `notifikasi`, `pdf`, `tautan`, `integrasi` digabung ke `default`; ekspor laporan ke koneksi `database-berat` (antrean `berat`). `impor` tidak dipakai job mana pun, jadi tidak dibuat.
2. (!) Worker `default` diberi `--memory=256` (standar §1b tidak mencantumkannya) agar job render PDF tidak menghabiskan RAM server 3,8 GiB. `scheduler` diberi `mem_limit: 768m` karena worker berjalan sebagai subproses (256m + 512m); semua angka memori masih **perkiraan awal** dan wajib dibuktikan dengan `docker stats` (STANDAR-TEKNIS §7.1).
3. `/api/health` membalas 503 hanya bila DB (atau Gotenberg yang diaktifkan lewat ADR) gagal. Job tertunda > 10 menit, job gagal 24 jam, dan detak penjadwal (> 5 menit) bersifat informatif agar healthcheck container tidak memicu restart berantai.
4. (!) `Cache::lock` pada penomoran dan transisi (7 titik) **dipertahankan**: dengan `CACHE_STORE=database` ia memakai tabel `cache_locks`. Jaring pengaman database (transaksi, `MAX(urut) FOR UPDATE`, UNIQUE, hingga 15 percobaan ulang) tetap ada. Standar §1b menganjurkan transaksi + `lockForUpdate` saja; penggantian ditunda sampai `NomorKonkurenTest` terbukti stabil (pernah gagal sekali di suite penuh, tidak terulang dalam 18 percobaan terpisah).
5. (!) `.env.production.example` kini dilacak git (pengecualian `.gitignore`); sebelumnya tidak pernah masuk repo padahal DEPLOY.md menyuruh menyalinnya. Isinya templat tanpa rahasia.
6. (!) CI **tidak** dihidupkan kembali: `ci.yml` sengaja dinonaktifkan lalu dihapus pemilik. Pagar mutu saat ini hanya hook `pre-commit` lokal (`pint --test` dan seluruh suite).
7. PHP-FPM `ondemand`, `max_children = 4`, `process_idle_timeout = 10s` (standar §1b); sebelumnya `dynamic` dengan 20 worker.
8. Peralihan produksi dari v1.0.0 (kuras antrean Redis, ubah `.env`, merge) ada di `docs/DEPLOY.md` §11. `deploy.yml` men-deploy otomatis setiap push ke `main`, jadi `.env` server wajib diubah sebelum merge.

## K-10 — Alur persuratan: pengirim, staf khusus, pelacakan (18 Oktober 2026)

Keputusan pemilik atas rencana "Alur Persuratan" (pengirim -> staf khusus dekanat -> dekan -> WD -> kasubag -> pembuat surat -> hasil kembali ke pengirim). Dikerjakan bertahap; tiap tahap bisa dirilis sendiri.

1. **Pengirim eksternal** (UKM/umum) boleh punya akun (OAuth Google, ditambahkan nanti untuk semua pengguna) **atau** diinput staf khusus; pelacakan ditawarkan dengan maupun tanpa login.
2. **Jumlah WD dipilih dekan** (satu atau kedua WD) beserta mode persetujuan salah satu/semua (BR-25; mengubah BR-14; tahap 3).
3. **Pembuat surat** berasal dari master "unit pembuat" yang dikelola super-admin secara fleksibel (BR-26; tahap 4).
4. **Pelacakan** = linimasa di akun pengirim + tautan lacak publik berkode (BR-27; tahap 5–6).

## K-11 — Pelaksanaan tahap 1–2: pilihan teknis (18 Oktober 2026)

Pilihan saat menerapkan K-10 (cabang `feat/alur-persuratan`). **Menunggu peninjauan pemilik**; yang bertanda (!) menyentuh kebijakan.

1. Peran `staf-khusus` dengan jabatan `staf-khusus-dekan`; izin baru `masuk.teruskan` dan `permohonan.lihat` (hanya baca). Memakai panel Filament seperti admin-persuratan (bukan `PERAN_TANPA_PANEL`). (!) MFA wajib seperti admin-persuratan karena memegang data kontak pengirim.
2. Penerusan memakai kolom `diteruskan_pada/oleh` pada `surat_masuk`, bukan status baru: `StatusSuratMasuk` dan seluruh pemakainya tidak berubah. (!) Registrasi oleh admin-persuratan tetap langsung terlihat dekan (jalur lama); surat lama diisi `diteruskan_pada = tanggal_terima` oleh migrasi. Bila pemilik ingin semua surat wajib lewat staf khusus, cukup ubah satu kondisi di `RegistrasiSuratMasuk`.
3. Jalur **prodi dan eksternal memakai surat masuk**; model `Permohonan` tidak diubah (seluruh kode, kebijakan, notifikasi, dan linimasanya bertumpu pada `ormawa_id`; menjadikannya opsional terlalu besar untuk tahap ini). Prodi tidak mendapat peran `pengurus-prodi`: prodi bersurat resmi dan diregistrasi staf khusus dengan menunjuk `unit_kerja`; peran/penugasan pengaju prodi dapat ditambah bila kelak prodi perlu mengajukan sendiri.
4. Kontak pengirim eksternal (surel, WhatsApp) = data pribadi: kolom `text` dengan cast `encrypted`, `$hidden` sehingga tidak masuk serialisasi maupun activitylog. Nama dan instansi tidak dienkripsi.
5. Staf khusus mendapat notifikasi surat baru yang menunggu diteruskan dan tembusan setiap permohonan ormawa baru; dekan dinotifikasi saat surat diteruskan. Notifikasi surat baru ke dekan hanya untuk surat yang langsung diteruskan (jalur admin).

## K-12 — Pelaksanaan tahap 3: mode persetujuan WD (18 Oktober 2026)

Menerapkan keputusan K-10 butir 2 (BR-25, mengubah BR-14). **Menunggu peninjauan pemilik**; (!) menyentuh kebijakan.

1. Mode disimpan pada `permohonan.mode_wd` (`semua` bawaan, `salah_satu`); permohonan lama dan semua pemanggil lama tetap `semua` sehingga perilaku BR-14 tidak berubah. Bila hanya satu WD dipilih, mode dipaksa `semua` (tidak relevan).
2. (!) Aturan penolakan mode `salah_satu`: satu penolakan **tidak** mengakhiri permohonan selama masih ada WD yang menunggu; permohonan `ditolak` hanya bila **semua** WD tujuan menolak. Persetujuan WD lain setelah ada penolakan tetap menutup tahap sebagai disetujui. Alasan: mode ini berarti "cukup satu WD yang setuju"; penolakan satu WD tidak seharusnya mengalahkan WD lain yang diminta dekan boleh menyetujui. Bila pemilik ingin penolakan pertama berlaku, ubah satu kondisi di `PutusanWd`.
3. Persetujuan pertama menutup tahap: baris WD lain menjadi `diabaikan` (nilai baru pada `persetujuan_wd.putusan`, muat di kolom 10 karakter, tanpa perubahan skema), disposisi pribadi mereka diselesaikan otomatis dengan laporan "Diabaikan: ...", mereka dinotifikasi, dan riwayat mencatat penyetuju serta WD yang diabaikan.
4. Keamanan balapan: seluruh putusan tetap di dalam `TransisiPermohonan::dalamKunci` (`DB::transaction` + `lockForUpdate`, Profil Ringan §1b). Diuji dengan proses paralel (`ModeWdKonkurenTest`).
5. Antarmuka dekan (kotak masuk Livewire dan aksi panel Filament): pilihan WD dengan radio "Cukup salah satu WD" / "Semua WD tujuan harus setuju", tampil hanya bila lebih dari satu WD dicentang.

## K-13 — Pelaksanaan tahap 4: unit pembuat surat (18 Oktober 2026)

Menerapkan keputusan K-10 butir 3 (BR-26). **Menunggu peninjauan pemilik**; (!) menyentuh kebijakan.

1. Master baru `unit_pembuat` + pivot `unit_pembuat_petugas` (PK komposit UUID), `jenis_permohonan.unit_pembuat_bawaan_id`, dan `permohonan.unit_pembuat_id` (RESTRICT agar unit yang pernah dipakai tidak terhapus; hanya dinonaktifkan). Resource Filament di grup Master memakai `UnitPembuatPolicy extends MasterPolicy` (izin `master.kelola`, praktis super-admin saja). Tidak ada data baku: `UnitPembuatSeeder` (rektorat, surat-tugas-dosen, izin-kegiatan) hanya contoh opsional, **tidak** dipanggil `DatabaseSeeder`, idempotent, tidak menimpa perubahan super-admin maupun unit bawaan yang sudah terisi.
2. Penugasan memakai pola yang ada: satu `disposisi` (permohonan_id, dari kasubag) dengan `disposisi_penerima` per petugas **aktif** unit. Tidak ada tabel penugasan baru. Unit nonaktif atau tanpa petugas aktif ditolak.
3. (!) Status baru `pembuatan` ditaruh di antara `rekomendasi_kasubag` dan `penerbitan`; `penerbitan` dipertahankan sebagai tahap naskah izin otomatis (draf -> paraf -> tanda tangan -> terbit -> `selesai` oleh listener) sehingga `SelesaikanPermohonanDariNaskah`, impor ormawa-hub, dan pengingat tidak berubah. Petugas yang membuat draf memindahkan status `pembuatan` -> `penerbitan`. Peta transisi: `rekomendasi_kasubag` -> {pembuatan, penerbitan, ditolak, dibatalkan}; `pembuatan` -> {penerbitan, dibatalkan}. Pembatalan oleh ormawa tidak diizinkan pada `pembuatan` (sama seperti `penerbitan`).
4. (!) Kompatibilitas: bila tidak ada unit (jenis tanpa unit bawaan dan kasubag tidak memilih), `RekomendasiKasubag` tetap langsung ke `penerbitan` dan admin dengan `permohonan.validasi` membuat draf seperti dulu. Bila ada unit, **hanya petugas unit terpilih (dan super-admin)** yang boleh `TerbitkanIzin`; admin persuratan lama tidak otomatis (perlu dijadikan petugas unit "izin-kegiatan" bila ingin tetap membuatnya). Petugas tetap memerlukan izin `naskah.draf` (tidak diberikan otomatis) dan, untuk membuka panel, peran yang bukan `pegawai`/`pengurus-ormawa`.
5. Petugas unit melihat (hanya) permohonan unitnya di panel dan di tab Permohonan kotak masuk; dinotifikasi saat ditugaskan dan oleh pengingat tertahan. Penugasan tampil di linimasa (`riwayat_permohonan`: kasubag sebagai pelaku, catatan memuat unit dan nama petugas).
6. Belum dikerjakan (tahap 5): penyelesaian permohonan non-izin dari tahap `pembuatan` dan "hasil cetak" ke pengirim. Semua jenis permohonan saat ini memakai templat naskah, jadi jalur izin mencakupnya.

## K-14 — Pelaksanaan tahap 5: hasil cetak dan pemberitahuan ke pengirim (18 Oktober 2026)

Menerapkan K-10 butir 4 (BR-28). **Menunggu peninjauan pemilik**; yang bertanda (!) menyentuh kebijakan.

1. Hasil = **tautan** (`tautan_berkas.jenis = hasil_cetak`, divalidasi `TautanBerkasValid`, tanpa unggah berkas; §1a). Nama jenis memakai garis bawah (`hasil_cetak`) mengikuti nilai jenis lain (`surat_permohonan`, `naskah_basah`), bukan `hasil-cetak` pada rencana. Petunjuk privasi §1a tampil di formulir.
2. Permohonan: transisi baru `pembuatan` -> `selesai` (`KirimHasilPermohonan`) untuk semua jenis; jalur izin lama (`penerbitan` -> naskah terbit -> `selesai` oleh listener) tidak diubah. Selesai (jalur mana pun) mengisi `hasil_dikirim_pada/oleh` dan memberi tahu ormawa/pengaju dengan tautan lacak. Ruangan ditahan dicek ulang di dalam kunci lalu dikonfirmasi (sama seperti listener). Pembatalan ormawa tetap tidak diizinkan pada `pembuatan`.
3. Surat masuk: `surat_masuk.unit_pembuat_id`; kasubag menugaskan unit lewat **disposisi lanjutan** dari disposisi yang diterimanya (`TugaskanPembuatSuratMasuk`, memakai `BuatDisposisi` sehingga rantai `induk_penerima_id`, notifikasi, dan batas waktu tidak diduplikasi); hanya petugas unit itu (dan super-admin) yang dapat `KirimHasilSuratMasuk`, yang menutup surat (`selesai`) dan menyelesaikan seluruh penerima disposisi yang masih terbuka. Penugasan ulang ke unit lain diizinkan selama surat belum selesai.
4. (!) Pemberitahuan ke pengirim tanpa akun lewat job `KirimHasilKePengirim` yang hanya membawa id surat: kontak terenkripsi didekripsi di dalam job, sehingga tidak masuk muatan antrean (`jobs`/`failed_jobs`), log (galat dicatat tanpa pesan pustaka), maupun audit. Surel plain-text tanpa perihal (surat rahasia aman); WhatsApp hanya bila `wa_aktif` dan gerbang tersedia. Pengirim prodi: pemangku jabatan pada unit prodi (mis. kaprodi) juga diberi tahu sebagai pengirim ber-akun.
5. Temuan tahap sebelumnya: (i) `PermohonanResource::getEloquentQuery` kini menyertakan `permohonan.lihat` sehingga staf khusus melihat daftar (hanya baca). (ii) (!) Petugas unit berperan `pegawai` **tidak** diberi akses panel Filament (K-13 butir 4 tetap): disediakan halaman Livewire `/pembuat` ("Tugas pembuat surat") untuk petugas unit (peran apa pun), berisi permohonan dan surat masuk unitnya beserta form kirim hasil; tautannya muncul di navigasi bagi petugas unit. Pembuatan draf surat izin lewat naskah tetap di panel dan memerlukan izin `naskah.draf` serta peran berakses panel; petugas yang hanya `pegawai` menyelesaikan permohonan lewat jalur hasil cetak. Alasan: tidak memperluas hak panel tanpa keputusan pemilik.

## K-15 — Pelaksanaan tahap 6: kode lacak dan halaman lacak publik (18 Oktober 2026)

Menerapkan K-10 butir 4 (BR-27). **Menunggu peninjauan pemilik**; (!) menyentuh kebijakan.

1. Kode lacak 12 karakter dari alfabet 32 simbol (±60 bit), unik lintas permohonan dan surat masuk, dibuat otomatis oleh model saat dibuat (juga impor/jalur lain), diisi untuk data lama oleh migrasi idempotent. Disimpan tanpa tanda hubung; masukan pengguna dinormalisasi (huruf besar, tanpa spasi/tanda hubung). Disembunyikan dari serialisasi dan audit karena kode = kunci akses.
2. Linimasa terpadu dihitung saat tampil (`App\Support\Pelacakan\Linimasa`), bukan tabel riwayat umum baru: menghindari data ganda dan migrasi riwayat. Pelaku = jabatan yang diemban saat itu; bila tak ada, label peran (mis. "Staf khusus dekanat").
3. (!) Akun ormawa (`Progres`) kini memakai linimasa yang sama: **nama pribadi pejabat dan catatan internal tidak lagi tampil**; hanya alasan pengembalian/penolakan/pembatalan yang ditujukan kepada pengirim. Ini mengubah tampilan lama yang memperlihatkan nama pelaku dan semua catatan.
4. (!) Halaman publik `/lacak/{kode}`: tautan hasil ditampilkan langsung (siapa pun yang memegang kode dapat membukanya; kendali akses sesungguhnya adalah izin berbagi tautan Drive). Surat masuk rahasia/sangat rahasia: tanpa perihal, tahap generik, tanpa jabatan/unit, **tanpa tautan hasil** (pengirim mengambil lewat fakultas). Permohonan ormawa tidak punya klasifikasi sehingga selalu perihal ringkas (120 karakter). Kode tak sah dan tak ada dijawab identik (404). Limiter `lacak`: 20/menit dan 200/jam per IP.
5. Tampilan linimasa untuk pengirim ber-akun pada **surat masuk** tidak dibuat: pengirim surat masuk tidak punya akun yang tertaut pada surat (eksternal/prodi bersurat resmi); mereka dilayani lewat tautan lacak publik dan pemberitahuan. Staf khusus/admin melihat tautan lacak di panel (rincian permohonan dan formulir surat masuk) untuk diberikan kepada pengirim.

