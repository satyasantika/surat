# Desain: Masuk dengan Google, pengirim eksternal, dan saklar MFA

Tanggal: 26 Oktober 2026. Status: **menunggu persetujuan pemilik**.

## Maksud dan alasan

Sekarang mahasiswa harus mendaftar lewat `/daftar` (surel `@student.unsil.ac.id` + kata sandi + verifikasi surel), dan pihak luar tidak punya akun sama sekali: suratnya diinput staf khusus. Yang merepotkan pemilik: setiap pengusul baru bergantung pada admin. Tujuannya, siapa pun dengan akun Google dapat masuk sendiri dan langsung mengusulkan surat, tanpa admin membuatkan akun.

## Sasaran

1. Tombol "Masuk dengan Google" di halaman masuk publik (`/masuk`) dan di halaman masuk panel (`/admin`), untuk semua peran.
2. Akun dibuat otomatis saat masuk pertama:
   - surel `@student.unsil.ac.id` -> peran `mahasiswa`;
   - surel lain (Gmail, institusi lain, termasuk `@unsil.ac.id` yang belum punya akun) -> peran baru `pengirim-eksternal`.
3. `mahasiswa` tetap hanya bisa mengusulkan lewat ormawa atau prodi/laboratorium (alur K-17 yang sudah ada), tidak langsung ke fakultas.
4. `pengirim-eksternal` mengisi satu formulir sederhana yang masuk sebagai **surat masuk** (jenis pengirim `eksternal`) langsung ke admin fakultas, tanpa perantara dan tanpa jalur.
5. Kewajiban MFA untuk semua peran dimatikan; superadmin dapat menyalakannya lagi dari panel `/admin`.
6. Pengirim eksternal dapat menujukan satu kiriman ke fakultas, ke satu atau lebih ormawa/prodi/laboratorium aktif, atau keduanya secara paralel. Ormawa/prodi/lab yang menerimanya boleh langsung mengarsipkannya (mis. undangan) tanpa kewajiban meneruskan ke fakultas.

## Bukan sasaran

- Tidak mengganti masuk dengan kata sandi: tetap ada, termasuk `/daftar`, `/lupa-sandi`, dan `/admin`.
- Tidak membuat alur persetujuan baru untuk pengirim eksternal; memakai mesin surat masuk yang ada.
- Tidak membuat akun pejabat atau admin dari Google; peran itu tetap hanya diberikan lewat panel atau `surat:buat-superadmin`.
- Tidak mengubah model `Permohonan`.

## Anti-sasaran (dianggap gagal walau secara teknis jalan)

- Orang yang bukan pemilik surel memperoleh akses ke akun yang sudah ada.
- Login Google membuat atau menaikkan peran di atas `mahasiswa` / `pengirim-eksternal`.
- Akun nonaktif (`aktif = false`) dapat masuk lewat Google.
- Data kontak pengirim (surel, WhatsApp) bocor ke log audit atau serialisasi (aturan kontak pengirim eksternal di `docs/KEPUTUSAN.md` tetap berlaku: kolom terenkripsi, `$hidden`).

## Keputusan pemilik (diambil dalam percakapan)

1. Login Google berlaku **untuk semua peran**, termasuk peran panel (super-admin, admin-persuratan, staf-khusus, dekan, wakil-dekan). Alasan pemilik: semua akun terafiliasi Google. Ini melonggarkan usul awal Claude yang menolak Google untuk akun panel.
2. Akun yang sudah ada dengan surel sama **ditautkan otomatis** ke identitas Google tersebut (tidak membuat akun ganda).
3. Pengusul umum = pengirim eksternal (tidak ada konsep ketiga).
4. Pengajuan pengirim eksternal berupa formulir sederhana yang menjadi surat masuk.
4a. Formulir yang sama memberi pilihan tujuan: fakultas dan/atau ormawa/prodi/lab mana saja yang aktif, boleh lebih dari satu. Tujuan-tujuan itu berjalan paralel dan mandiri. Perantara boleh langsung mengarsipkan kiriman tanpa meneruskan ke fakultas.
5. MFA mati untuk semua peran. Saklarnya **di panel**, hanya dapat diubah **superadmin**, bukan di `.env`.

## Rancangan (keputusan Claude, untuk diperiksa pemilik)

### Pengaman penautan akun
- Google hanya dipercaya bila klaim `email_verified` benar. Pencocokan surel tidak membedakan huruf besar-kecil.
- Akun yang cocok dan `aktif = true`: ditautkan dan masuk dengan perannya yang ada. Akun yang cocok tetapi nonaktif: ditolak dengan pesan yang sama seperti kata sandi salah.
- Akun baru hanya pernah diberi `mahasiswa` atau `pengirim-eksternal`. Surel pejabat yang belum punya akun tidak otomatis menjadi pejabat.
- Setelah tertaut, identitas Google (ID Google, `sub`) disimpan; masuk berikutnya mencocokkan `sub` lebih dulu, baru surel. Bila surel di Google berubah tetapi `sub` sama, tetap akun yang sama.
- Akun baru lewat Google ditandai surel terverifikasi (`email_verified_at`) karena Google sudah memverifikasi. Kata sandi acak yang tidak diketahui siapa pun; pengguna dapat menyetel kata sandi lewat `/lupa-sandi` bila mau.
- Pengguna yang dibuat lewat Google tidak punya `nip_nim`: kolom itu perlu dibuat opsional di jalur ini atau diisi kemudian di profil (lihat "Diserahkan ke pembangun").

### Masuk panel (`/admin`)
- Halaman masuk Filament diberi tombol Google yang memakai alur callback yang sama. Akun tanpa peran panel tidak boleh lolos ke `/admin` (`canAccessPanel` tetap berlaku).
- Bila pengguna sudah memasang MFA, tantangan MFA tetap muncul setelah Google (Google dianggap faktor pertama). Saklar hanya mencabut **kewajiban**.

### Saklar MFA
- Kunci pengaturan baru `mfa_wajib` (grup `keamanan`, bawaan `false`) pada sistem `App\Support\Pengaturan`.
- Tiga titik pemaksaan MFA yang kini terpisah mengikuti kunci ini: `User::wajibMfa()` (bersama `config('unsil.peran_wajib_mfa')`), middleware `WajibMfa`, dan `isRequired` pada `AdminPanelProvider`.
- Hanya peran `super-admin` yang dapat melihat dan mengubahnya, di halaman `PengaturanSistem` yang sudah ada. Perubahan dicatat di log aktivitas.
- Hati-hati: `isRequired` dibaca saat panel didaftarkan; jangan membaca database pada saat boot sebelum tabel `pengaturan` ada (migrasi, `composer install`, tes).

### Pengirim eksternal
- Peran baru `pengirim-eksternal`, rumah baru di `Beranda::url` (halaman daftar kiriman miliknya + tombol "Ajukan surat"), dan ditambahkan ke `User::PERAN_TANPA_PANEL`.
- Formulir: nama (terisi dari Google, dapat diubah), instansi, perihal, uraian singkat, tautan berkas (aturan `TautanBerkasValid` yang ada), kontak surel/WhatsApp. Jenis pengirim `eksternal`.
- Hasilnya adalah baris `surat_masuk` dengan kode lacak (`MemilikiKodeLacak`) sehingga `/lacak/{kode}` berfungsi. Surat itu perlu tertaut ke akun pengirim (kolom baru, mis. `pengirim_user_id`) agar pengirim melihat daftar kirimannya.
- Kebijakan akses: pengirim hanya melihat kirimannya sendiri (uji IDOR).

### Konfigurasi
- Laravel Socialite + penyedia Google. Kunci `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` di `.env`, `.env.example`, `.env.production.example`, dan `config/services.php`.
- **Prasyarat dari pemilik**: membuat OAuth Client (jenis Web) di Google Cloud Console dan mendaftarkan URI callback produksi dan lokal. Claude tidak dapat membuatnya.
- Throttle pada rute callback, seperti `masuk` dan `daftar`. CSP/header halaman masuk perlu mengizinkan pengalihan ke `accounts.google.com` bila diperlukan.

### Kiriman eksternal ke ormawa/prodi/lab (paralel)

Pemilik menetapkan: tujuan bebas dipilih dari semua ormawa/prodi/lab aktif, boleh lebih dari satu, dan perantara boleh langsung mengarsipkan. Sisanya pilihan Claude:

- Satu pengiriman menghasilkan satu kiriman per tujuan. Tujuan fakultas menjadi baris `surat_masuk` (lihat di atas); tiap tujuan ormawa/prodi/lab menjadi baris di kotak usulan perantara yang sudah ada (`usulan_mahasiswa`, yang kini mensyaratkan `mahasiswa_id` dan tepat satu dari `ormawa_id`/`unit_kerja_id`). Pembangun memutuskan apakah tabel itu digeneralisasi (pengusul bisa mahasiswa atau pengirim eksternal) atau dibuat tabel serupa; yang penting perantara melihat satu kotak masuk, tidak dua.
- Tujuan-tujuan itu mandiri: mengarsipkan di prodi tidak menghentikan atau mengubah kiriman ke fakultas, dan sebaliknya. Tidak ada "tujuan utama".
- Status baru **diarsipkan** (akhir, tanpa tindak lanjut) di samping status usulan yang ada (diajukan, dikembalikan, ditolak, dibatalkan, dipakai). Perantara boleh mengarsipkan, mengembalikan/menolak dengan catatan, atau meneruskan/menggabungkan seperti usulan mahasiswa. Kiriman eksternal yang diteruskan mengikuti alur usulan yang sudah ada.
- Pengirim melihat status per tujuan di daftar kirimannya. Kode lacak publik tetap menampilkan tahap umum saja, tanpa nama dan tanpa catatan perantara.
- Batas penyalahgunaan: paling banyak 5 pengiriman per hari per akun (bukan per tujuan) dan paling banyak 5 tujuan perantara per pengiriman (angka bawaan, bisa diubah di pengaturan).
- Perantara (pengurus ormawa dan pengaju/kepala unit) diberi tahu lewat notifikasi yang sudah ada; kiriman eksternal diberi label jelas "dari pihak luar" agar dibedakan dari usulan mahasiswa.

### Tambahan setelah pemeriksaan pembangun (semua pilihan Claude)

- **Pengguna tanpa peran panel di `/admin`**: callback mengingat dari halaman mana alur dimulai. Pengguna `mahasiswa` / `pengirim-eksternal` yang mulai dari `/admin` diarahkan ke beranda perannya dengan pesan "Akun ini tidak memakai panel admin". Mereka tidak pernah mendapat sesi panel.
- **Pencegahan pembajakan lewat pendaftaran kata sandi**: bila akun yang cocok surelnya belum terverifikasi (`email_verified_at` kosong), saat penautan pertama kata sandinya diganti acak dan semua sesi lain diakhiri, sehingga kata sandi buatan pihak yang tidak memiliki surel itu tidak berlaku. Akun yang surelnya sudah terverifikasi ditautkan tanpa mengubah kata sandi.
- **Pencocokan domain**: surel dinormalkan ke huruf kecil lalu dicocokkan persis dengan akhiran `@student.unsil.ac.id` (daftar di `config('unsil.domain_mahasiswa')`); subdomain atau domain mirip tidak lolos. Klaim `hd` tidak diwajibkan (Google hanya menerbitkan surel terverifikasi di domain itu kepada pemiliknya), tetapi bila `hd` ada dan berbeda dari domain surel, masuk ditolak.
- **Surel berubah di Google**: akun tetap dikenali dari `sub`; `users.email` tidak disinkronkan otomatis. Bila surel Google yang baru bentrok dengan akun lain, tidak ada penggabungan; pengguna diberi pesan agar menghubungi admin. Identitas Google dibuat unik.
- **Kiriman pengirim eksternal**: tampil di daftar surat masuk admin yang sudah ada dengan status awal yang membedakannya ("diajukan pengirim"), dan ikut hitungan dasbor serta notifikasi admin. Pengirim dapat menarik kembali kirimannya selama belum diterima admin; setelah itu tidak dapat diubah. Nomor agenda diberikan admin saat menerima, bukan saat dikirim; kolomnya perlu dilonggarkan atau diganti agar kiriman belum bernomor sah.
- **Penyalahgunaan**: formulir dibatasi laju per pengguna dan maksimal 5 kiriman per hari per akun (angka bawaan, bisa diubah di pengaturan). Tautan berkas hanya domain yang sudah diizinkan `TautanBerkasValid`. Tanpa captcha pada tahap ini.
- **Saklar MFA dan konfigurasi peran**: `mfa_wajib = false` meniadakan kewajiban untuk semua peran; `mfa_wajib = true` mewajibkan peran yang tercantum di `config('unsil.peran_wajib_mfa')` (bukan semua peran). Jadi saklar menjadi satu-satunya pengendali, dan daftar peran pada config hanya menentukan siapa yang kena saat saklar menyala.

## Diserahkan ke pembangun

- Skema tepat untuk menyimpan identitas Google (kolom pada `users` atau tabel `identitas_sosial`), dan nama kolom.
- Cara memenuhi `surat_masuk.nomor_agenda` (unik, wajib) dan `diregistrasi_oleh` (wajib, FK ke `users`) untuk kiriman yang tidak diregistrasi staf: apakah nomor agenda diberikan saat dikirim atau saat admin menerima, serta status awal yang membedakan "baru dikirim pengirim" dari "sudah diregistrasi".
- Perlakuan `nip_nim` (kini wajib dan unik) untuk akun yang dibuat lewat Google.
- Notifikasi ke admin fakultas bila ada kiriman baru, memakai `NotifikasiAlur` yang ada.
- Pembaruan `docs/KEAMANAN.md`, `docs/KEPUTUSAN.md` (catat sebagai K-18), panduan pengguna, dan `CHANGELOG.md`.
- Tes: penautan akun (cocok, nonaktif, `email_verified` salah), peran hasil pendaftaran otomatis, akses `/admin` lewat Google, saklar MFA (hanya super-admin, efek pada `WajibMfa`), IDOR kiriman pengirim eksternal.

## Hal yang perlu dicek pemilik

1. Pengecualian: pejabat bisa masuk hanya dengan Google; bila akun Google mereka tanpa verifikasi dua langkah dan MFA mati, perlindungannya hanya kata sandi Google. Disarankan menyalakan saklar MFA begitu masa uji selesai.
2. Surel `@unsil.ac.id` tanpa akun menjadi `pengirim-eksternal`, bukan pegawai.
3. Pengguna yang sudah memasang MFA tetap ditantang kodenya setelah saklar dimatikan.
