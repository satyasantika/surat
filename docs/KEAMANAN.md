# Keamanan — hasil pengerasan F12.1

Dokumen ini merangkum kontrol keamanan (05-UJI §3), uji yang menjaganya, hasil audit dependensi, serta risiko sisa.
Diperbarui setiap rilis; jalankan ulang `composer audit` dan `npm audit` sebelum tiap rilis.

## 1. Kontrol dan bukti uji

| Kontrol (05-UJI §3) | Bukti |
|---|---|
| K-01 Tidak ada endpoint tabel mentah; semua lewat Policy | `Arch/BerkasTest`, `IdorTest`, `MatriksAksesTest` (setiap izin sistem wajib ada di matriks), policy per modul |
| K-02 Tanpa kredensial bawaan; kata sandi hanya hash | `TanpaKredensialBawaanTest` (seeder produksi tidak membuat akun; tak ada pola kata sandi bawaan di kode; login kredensial umum gagal; super-admin hanya lewat perintah interaktif) |
| K-03 Pelaku = pengguna terautentikasi; tanpa akun bersama | `Arch/AuditTest`, `ImporOrmawaHubTest` (akun ormawa bersama tidak dimigrasikan), `VerifikasiMigrasiTest` |
| K-04 Bentrok ruangan dan penomoran diuji konkuren | `NomorKonkurenTest`, `PermohonanKonkurenTest` (proses paralel), `KonkurensiTest` (UNIQUE dan kunci aplikasi) |
| K-05 Nomor dari register | `NomorTest`, `KonkurensiTest`, `AjukanPermohonanTest` |
| K-06 "TTE" hanya untuk mode tersertifikasi; gambar tanda tangan tak terekspos | `TandaTanganTest`, `IdorTest` (tautan `ttd_visual` tidak pernah dapat dibuka) |
| K-07 QR → halaman verifikasi dengan hash | `VerifikasiTest`, `VerifikasiPublikTest` (hanya bidang putih; 404 seragam; tanpa cache/indeks; CSP ketat) |
| K-08 Templat dan isi tersanitasi; CSP aktif | `XssTest` (naskah, ormawa), `Arch/TanpaHtmlMentahTest` (hanya `isi-aman` yang boleh `{!! !!}`), `HeaderKeamananTest` |
| K-09 Klasifikasi rahasia dihormati di UI, PDF, notifikasi, ekspor, pencarian | `KlasifikasiRahasiaTest`, `NotifikasiTest`, `LaporanTest`, `ArsipRetensiTest` |
| K-10 Data pribadi hanya bagi yang berhak | `HalamanPublikTest`, `PengurusOrmawaTest`, `LaporanTest` (tanpa data pribadi), `VerifikasiMigrasiTest` (cek privasi) |
| K-11 Tautan: https + daftar putih; anti-SSRF; tanpa unggahan | `TautanBerkasTest`, `Arch/BerkasTest`, `TanpaUnggahTest` |
| K-12 MFA super-admin, admin-persuratan, staf khusus, dekan, WD; sejak K-18 berlaku hanya bila saklar `mfa_wajib` menyala (bawaan mati) | `AutentikasiTest`, `FondasiTest` (`WajibMfa`) |
| K-13 Rate limit login, verifikasi, ajukan | `RateLimitTest` (masuk 5/mnt, daftar & lupa sandi 5/mnt, verifikasi 30/mnt, ajukan 10/jam) |
| K-14 Deployment Web App GAS OrmawaHub dihapus setelah cutover | Langkah manusia (07-MIGRASI §9, `DEPLOY.md` §Cutover) |
| G-01 Masuk dengan Google (K-18): hanya `email_verified` yang dipercaya; domain mahasiswa akhiran persis; akun nonaktif tanpa sesi; peran panel tidak pernah dibuat dari Google | `PenautanGoogleTest`, `GoogleControllerTest` |
| G-02 Cegah pengambilalihan akun: akun cocok yang surelnya belum terverifikasi diganti kata sandi acak dan sesinya dihapus saat penautan pertama; akun ber-MFA ditolak sebelum efek samping | `PenautanGoogleTest`, `GoogleControllerTest` |
| G-03 Limiter `masuk-google` 60/mnt/IP (kunci tanpa surel); state/penolakan Google kembali ke `/masuk` tanpa 500 | `GoogleControllerTest` |
| G-04 Saklar MFA (`mfa_wajib`) bawaan mati; hanya super-admin yang melihat dan mengubah | `SaklarMfaTest`, `AutentikasiTest` |
| G-05 Peran `pengirim-eksternal` (izin hanya `kiriman.ajukan`): tanpa panel, `/mahasiswa`, `/disposisi`; hanya melihat dan menarik/membatalkan kiriman sendiri (id milik orang lain 404); kontak pengirim pada surat masuk terenkripsi dan tidak di log (usulan tidak menyimpan kontak); batas 5/hari dan 5 tujuan | `KirimanEksternalFakultasTest`, `KirimanEksternalPerantaraTest`, `MatriksAksesTest`, `PeranDanIzinTest` (akses sendiri/IDOR: "hanya menampilkan kiriman milik sendiri", "pengirim menarik kirimannya lewat beranda, tetapi tidak milik pengirim lain (IDOR)", "pengirim membatalkan usulannya lewat beranda; id milik pengirim lain berakhir 404 (IDOR)") |

## 2. Header keamanan dan CSP

`HeaderKeamanan` (middleware global) menambahkan ke **setiap** respons web: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`,
`Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` (kamera, mikrofon, lokasi, pembayaran, USB dimatikan), dan
`Strict-Transport-Security` (hanya produksi + HTTPS).

| Area | CSP |
|---|---|
| Halaman aplikasi (masuk, daftar, profil, Livewire ormawa/pimpinan) | `script-src 'self' 'nonce-…' 'unsafe-eval'` — **tanpa `unsafe-inline` untuk skrip**; nonce baru tiap respons (dipakai `@vite` dan `@livewireScripts`); `frame-ancestors 'self'`, `object-src 'none'`, `form-action 'self'` |
| Halaman publik (`/`, `/organisasi`) | `script-src 'self'`; gambar hanya `lh3.googleusercontent.com` dan `*.unsil.ac.id`; tanpa bingkai pihak ketiga; `frame-ancestors 'none'` |
| Verifikasi QR | `default-src 'none'`, skrip hanya ber-nonce, `frame-ancestors 'none'`, `no-store`, `noindex` |
| Pratinjau naskah | `default-src 'none'` (tanpa skrip) |
| Panel Filament dan update Livewire | Tidak diberi CSP aplikasi: Filament menyuntikkan skrip/gaya inline tanpa nonce. Header dasar tetap berlaku; akses panel dibatasi login + MFA + Policy |

## 3. Audit dependensi

| Alat | Hasil | Catatan |
|---|---|---|
| `composer audit` | 1 advisori (medium): GHSA-mhf7-xp6r-6qrc pada `livewire/livewire` (>=4.3.0 <4.4.8) | Lihat §5 |
| `npm audit` | 0 kerentanan | Sebelum F12.1: 2 kritis pada `shell-quote` yang dibawa `concurrently` (dependensi dev yang tak dipakai skrip proyek); `concurrently` dihapus |

Produksi memasang dependensi tanpa dev (`composer install --no-dev`, aset dibangun lalu `node_modules` tidak dikirim).

## 4. Temuan dan perbaikan F12.1

1. Header keamanan belum seragam (panel dan halaman 404 tanpa header dasar; tak ada CSP untuk halaman aplikasi) → `HeaderKeamanan` global + CSP bernonce.
2. Dependensi dev rentan (`shell-quote` via `concurrently`) → dihapus.
3. Matriks akses PRD §3.1 sebelumnya hanya diuji sebagian → `MatriksAksesTest` memetakan setiap izin sistem × peran dan gagal bila ada izin baru tanpa tinjauan.

Tidak ditemukan kebocoran data rahasia, IDOR, atau kredensial bawaan pada pengujian lintas-permukaan.

### Masuk dengan Google dan saklar MFA (K-18)

- Google dipercaya hanya bila `email_verified` benar. Surel dinormalkan ke huruf kecil; peran `mahasiswa` hanya untuk akhiran persis `student.unsil.ac.id` (diperiksa bersama klaim `hd`), selain itu `pengirim-eksternal`. Peran panel tidak pernah dibuat atau dinaikkan lewat Google.
- Mitigasi pengambilalihan akun: bila akun yang cocok belum terverifikasi, kata sandi diganti acak dan sesi lain dihapus pada penautan pertama. Akun ber-MFA ditolak lewat Google (penyimpangan K-18); mereka masuk lewat `/admin` dengan kata sandi dan kode.
- **Saklar MFA bawaan mati.** Selama mati, pejabat (super-admin, admin-persuratan, staf khusus, dekan, wakil dekan) hanya dilindungi kata sandi atau akun Google mereka. Disarankan menyalakannya setelah masa uji coba (Pengaturan sistem, Keamanan); setelah itu pejabat wajib memasang MFA dan tidak lagi dapat memakai Google.

## 5. Risiko sisa dan pekerjaan lanjutan

| Risiko | Mitigasi / rencana |
|---|---|
| `unsafe-eval` pada CSP halaman aplikasi (Alpine/Livewire edisi standar) | Beralih ke edisi CSP-aman Livewire (`livewire.csp_safe`) setelah semua `wire:` ekspresi diuji di peramban |
| Panel Filament tanpa CSP ketat | Pantau dukungan nonce Filament; sementara dibatasi MFA + Policy |
| Tautan Google Drive publik yang dibagikan pengguna | Tautan hanya disimpan; pengguna diarahkan memakai Shared Drive dan pembagian terbatas (STANDAR-TEKNIS §1a) |
| Token WhatsApp/Aset API di `.env` | Rotasi berkala (DEPLOY.md §Rotasi); tidak pernah dicatat di log |
| Advisori Livewire GHSA-mhf7-xp6r-6qrc (medium; `livewire/livewire` >=4.3.0 <4.4.8): bypass otorisasi `#[Authorize]` lewat urutan kunci parameter dan urutan eksekusi `#[Json]`, ditemukan `composer audit`; sudah ada sebelum K-18 | Ditangani lewat pembaruan Livewire terpisah; kebijakan aplikasi memakai Policy/Gate di dalam aksi, bukan hanya atribut |
| Saklar MFA mati (K-18) | Nyalakan setelah masa uji coba; lihat bagian Masuk dengan Google dan saklar MFA |
| Kode klasifikasi arsip, pola nomor, kop surat | Wajib diverifikasi fakultas sebelum go-live (05-UJI §5 GL-02) |
