# Panduan Admin Persuratan

> Panduan ini kini berupa **HTML dengan tangkapan layar** agar tidak ada dua versi yang berbeda (STANDAR-TEKNIS §2.5).

Panduan lengkap (HTML dengan tangkapan layar): [`public/panduan/admin-persuratan.html`](../public/panduan/admin-persuratan.html) — juga untuk operator layanan ([`operator-layanan.html`](../public/panduan/operator-layanan.html)) dan super-admin (internal, tidak dipublikasikan: [`docs/panduan-internal/super-admin.html`](panduan-internal/super-admin.html)).

Cakupan: Registrasi surat masuk, naskah dan penomoran, register dan arsip, validasi permohonan, laporan, dan migrasi OrmawaHub (perintah terminal di `README.md` dan `docs/DEPLOY.md`).

Indeks semua panduan: [`public/panduan/index.html`](../public/panduan/index.html); di aplikasi: `/panduan/` (tertaut dari halaman publik, masuk, aplikasi, dan panel). Cara memperbarui: bagian "Memperbarui panduan" pada `README.md`.

## Tambahan K-18 (kiriman eksternal dan saklar MFA)

- **Saklar MFA (super-admin)**: Pengaturan sistem > Keamanan > "Wajibkan MFA untuk pejabat dan admin". Bawaannya mati; hanya super-admin yang melihatnya. Menyalakannya mewajibkan pejabat (super-admin, admin persuratan, staf khusus, dekan, wakil dekan) memasang MFA pada masuk berikutnya; akun yang sudah ber-MFA tidak dapat masuk lewat Google dan masuk lewat `/admin` dengan kata sandi dan kode.
- **Batas kiriman (pengelola Pengaturan sistem, yaitu pemegang izin `pengaturan.kelola`; hanya saklar MFA yang khusus super-admin)**: Pengaturan sistem > Kiriman eksternal: "Batas pengiriman per hari per akun" dan "Batas tujuan perantara per pengiriman" (bawaan 5 dan 5, nilai 1-50).
- **Akun buatan admin yang lebih dulu masuk dengan Google**: bila pengguna yang akunnya dibuat admin masuk dengan Google sebelum memakai tautan atur kata sandi, kata sandi sementaranya diganti acak (dan kewajiban ganti sandi dimatikan); sesudahnya ia masuk dengan Google, atau menyetel kata sandi lewat `/lupa-sandi`.
- **Menerima kiriman eksternal (staf khusus / admin persuratan)**: kiriman pihak luar untuk fakultas masuk ke Surat masuk berstatus **Diajukan pengirim** (ada widget "Kiriman menunggu diterima"). Buka kiriman, periksa, lalu tekan **Terima kiriman** (di baris tabel atau di halaman sunting). Nomor agenda baru diberikan saat diterima; sebelum itu surat tidak punya nomor agenda dan tidak dapat didisposisi. Setelah diterima alurnya sama dengan surat masuk biasa. Pengirim dapat menarik kiriman selama masih berstatus Diajukan.
- **Perantara (ormawa, prodi, laboratorium)**: kotak "Usulan masuk" juga memuat kiriman eksternal berlabel "dari pihak luar". Selain meneruskan, menggabungkan, mengembalikan, atau menolak, tersedia aksi **Arsipkan** khusus untuk kiriman eksternal (tidak diteruskan ke fakultas; pengirim diberi tahu).
