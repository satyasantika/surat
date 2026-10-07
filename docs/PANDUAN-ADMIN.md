# Panduan Admin Persuratan

Untuk admin persuratan (`admin-persuratan`) dan operator layanan (`operator-layanan`, izin terbatas). Panel: `/admin` — **wajib MFA**
(aplikasi autentikator) pada masuk pertama. Kata sandi tidak pernah dibagikan; tidak ada akun bersama.

## 1. Registrasi surat masuk
1. Menu **Persuratan › Surat masuk › Buat**. Isi nomor dan tanggal surat, asal, perihal, klasifikasi keamanan, derajat kecepatan, klasifikasi arsip.
2. **Tautan pindaian** wajib (satu berkas Google Drive atau unsil.ac.id; bukan folder, bukan pemendek URL). Surat rahasia: bagikan terbatas.
3. Simpan: **nomor agenda** terbentuk otomatis. Dekan menerima notifikasi untuk disposisi. Admin tidak dapat membuka isi surat rahasia (hanya metadata).
4. Surat selesai dapat **Diarsipkan** (aksi pada daftar surat masuk) → retensi arsip dihitung sejak itu.

## 2. Naskah keluar
1. **Persuratan › Naskah keluar › Buat**: pilih jenis naskah (templat), perihal, klasifikasi, penanda tangan, mode tanda tangan, tujuan, isi.
2. **Ajukan paraf** (pilih pemaraf berurutan) atau langsung ke penanda tangan. Pemaraf/penanda tangan diberi tahu; yang dikembalikan kembali ke Anda dengan catatan.
3. Setelah **ditandatangani**, nomor dan tanggal terbentuk otomatis (BR-07), PDF berQR verifikasi dibuat, lalu **Terbitkan**. Naskah terbit tidak dapat diubah; salah → **Batalkan** (nomor tidak dipakai ulang).
4. Penomoran hanya dari register (**Master › Register nomor**): jangan menomori manual.

## 3. Register dan arsip
- **Register & arsip › Register surat masuk/keluar**: pencarian, filter periode, ekspor XLSX (perihal rahasia disamarkan).
- **Arsip**: cari nomor, perihal, asal/tujuan, klasifikasi; menghormati klasifikasi keamanan.
- Laporan retensi bulanan dikirim ke admin sebagai notifikasi; **tidak ada penghapusan otomatis** — pemusnahan lewat prosedur resmi dengan berita acara.

## 4. Validasi permohonan ormawa
1. **Layanan Ormawa › Permohonan ormawa**: tab per status. Buka permohonan, periksa berkas (tautan surat dan proposal), ruangan, dan jadwal.
2. **Validasi** → diteruskan ke dekan; **Kembalikan** (catatan wajib) → ormawa memperbaiki dan mengajukan ulang; **Tolak** (alasan wajib; ruangan dilepas).
3. Setelah semua WD setuju dan kasubag merekomendasikan, status **Penerbitan**: terbitkan surat izin (draf naskah otomatis, cek bentrok ruangan ulang). Ormawa diberi tahu di setiap tahap.
4. **Plot ruangan**: kalender per ruangan × sesi; catat pemakaian manual non-ormawa (hanya yang berizin `ruangan.kelola-jadwal`).

## 5. Ormawa, LPJ, kabar, dan galeri
- **Ormawa**: profil, SK kepengurusan (nomor dan periode wajib; pengurus tidak aktif tanpa SK berlaku), pengurus. Tautkan akun pengurus lewat NIM.
- **LPJ**: pantau status; blokir pengajuan otomatis bila LPJ terlambat (pengaturan fakultas). Penilaian oleh pimpinan; **Master › Rubrik LPJ** mengatur aspek dan bobot.
- **Kabar**: setujui (**Terbitkan**) atau **Tolak** dengan catatan; perihal dan isi disanitasi. **Galeri**: tautan foto/video/Instagram; **Ambil saran dari LPJ** menyalin tautan media LPJ sebagai item nonaktif.

## 6. Laporan
**Laporan › Laporan**: pilih LAP-01…LAP-06, atur periode, **Tampilkan**; **Ekspor XLSX** diproses antrean dan tautan unduh muncul di lonceng notifikasi (berkas dihapus otomatis ≤ 24 jam).

## 7. Master, pengguna, dan pengaturan
- **Master**: unit kerja, jabatan dan pemangku (satu pemangku definitif per jabatan; Plt terpisah), klasifikasi arsip (impor CSV), jenis naskah, jenis permohonan, register nomor.
- **Sistem › Pengguna** (super-admin): peran dan izin; **Pengaturan sistem**: min. hari pengajuan, batas LPJ, blokir, sesi ruangan, kop surat, WhatsApp, pengingat.

## 8. Migrasi dari OrmawaHub (super-admin, dari terminal server)
```bash
php artisan ormawahub:impor storage/app/tmp/ormawahub.xlsx --pemetaan=storage/app/tmp/pemetaan --dry-run
php artisan ormawahub:impor storage/app/tmp/ormawahub.xlsx --pemetaan=storage/app/tmp/pemetaan --pelaksana=<surel-admin>
php artisan ormawahub:verifikasi          # semua cek harus LULUS sebelum potong
php artisan ormawahub:undang-pengguna     # undangan atur kata sandi (sekali per akun)
```
Pantau **Sistem › Log migrasi**. XLSX dan CSV pemetaan dihapus otomatis setelah impor tanpa galat (berisi data pribadi).

## 9. Bila ada masalah
| Gejala | Tindakan |
|---|---|
| Tidak dapat masuk panel | Pastikan MFA sudah disiapkan; hubungi super-admin untuk atur ulang |
| Tautan berkas "tidak dapat diakses" | Minta pemilik membagikan ulang tautan (akses "siapa saja dengan tautan" atau domain unsil) |
| Nomor tidak muncul | Naskah belum ditandatangani; nomor hanya terbentuk saat tanda tangan |
| Permohonan ditolak sistem karena bentrok ruangan | Pilih sesi/ruangan lain; periksa Plot ruangan |
