# Masuk dengan Google, Kiriman Eksternal, dan Saklar MFA: Rencana Implementasi

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Siapa pun dengan akun Google dapat masuk dan membuat akunnya sendiri; surel mahasiswa menjadi `mahasiswa`, surel lain menjadi `pengirim-eksternal` yang mengirim surat ke fakultas dan/atau ormawa/prodi/lab; MFA dimatikan dan dapat dinyalakan superadmin dari panel.

**Architecture:** Laravel Socialite menangani OAuth; satu service (`PenautanGoogle`) memutuskan akun mana yang masuk dan menjaga keamanannya; controller tipis. Kiriman eksternal memakai mesin yang ada: tujuan fakultas menjadi baris `surat_masuk` berstatus baru `diajukan`, tujuan ormawa/prodi/lab menjadi baris `usulan_mahasiswa` dengan penanda `asal = eksternal`. Saklar MFA adalah satu kunci `Pengaturan`.

**Tech Stack:** PHP ^8.5, Laravel 13, Filament 5, Livewire, spatie/laravel-permission, Pest 4, laravel/socialite (baru).

**Spec:** `docs/superpowers/specs/2026-10-26-login-google-design.md`

## Global Constraints

- Antarmuka, pesan galat, dan komentar dalam bahasa Indonesia; ikuti gaya berkas sekitar (nama kelas/metode berbahasa Indonesia, `declare` tidak dipakai, Action berupa kelas dengan metode `jalankan`).
- Peran panel (super-admin, admin-persuratan, staf-khusus, dekan, wakil-dekan, kasubag, operator-layanan) **tidak pernah** dibuat atau dinaikkan oleh login Google. Akun baru dari Google hanya `mahasiswa` atau `pengirim-eksternal`.
- Google dipercaya hanya bila `email_verified` benar; surel dinormalkan ke huruf kecil; mahasiswa = akhiran persis dari `config('unsil.domain_mahasiswa')` (`student.unsil.ac.id`).
- Akun `aktif = false` tidak pernah mendapat sesi.
- Akun cocok yang `email_verified_at`-nya kosong: saat penautan pertama kata sandi diganti acak dan sesi lain diakhiri.
- Batas bawaan: `5` pengiriman per hari per akun dan `5` tujuan perantara per pengiriman; keduanya kunci `Pengaturan`.
- Kontak pengirim (surel, WhatsApp) tetap kolom terenkripsi dan `$hidden`; tidak masuk log audit.
- `mfa_wajib` bawaan `false`; hanya super-admin yang melihat dan mengubahnya.
- Kerjakan di cabang baru (`feat/login-google`). Working tree berisi perubahan lain yang belum di-commit: `git add` hanya berkas tugas ini, jangan `git add -A`. Pesan commit diakhiri baris `Co-Authored-By` dari pengaturan sistem.
- Setiap tugas selesai dengan `vendor/bin/pint --dirty` bersih dan tes tugas hijau; sebelum tugas terakhir selesai, `vendor/bin/pest` penuh dan `vendor/bin/phpstan analyse` tidak menambah galat.

## Review Focus

1. Pengguna menolak izin di Google (`error=access_denied`) atau `state` tidak cocok: kembali ke `/masuk` dengan pesan ramah, bukan 500. (Tugas 3)
2. Surel Google berhuruf campuran (`Nama@Student.Unsil.ac.id`) tetap `mahasiswa`; domain mirip (`student.unsil.ac.id.evil.com`, `xstudent.unsil.ac.id`) menjadi `pengirim-eksternal`. (Tugas 2)
2a. Akun hasil `/daftar` yang belum diverifikasi lalu ditautkan Google: kata sandi lama tidak lagi bisa dipakai. (Tugas 2)
3. Pengirim A membuka, menarik, atau membatalkan kiriman pengirim B (IDOR). (Tugas 4, 5)
4. Kiriman tanpa tujuan, tujuan ganda, lebih dari 5 tujuan perantara, ormawa/unit nonaktif atau UUID tidak ada. (Tugas 5)
5. `nomor_agenda` kosong pada status `diajukan` tidak membuat laporan register (`Lap01Register`), notifikasi, pelacakan publik, atau daftar panel galat. (Tugas 4)
6. Membaca `Pengaturan` saat tabel `pengaturan` belum ada (migrasi, `composer install`, tes awal) tidak boleh melempar galat. (Tugas 1)

---

## File Structure

| Berkas | Tanggung jawab |
|---|---|
| `app/Support/Pengaturan.php` (ubah) | kunci baru `mfa_wajib`, `batas_kiriman_harian`, `batas_tujuan_kiriman`; aman bila tabel belum ada |
| `app/Models/User.php` (ubah) | `wajibMfa()` mengikuti saklar; `PERAN_TANPA_PANEL` + `pengirim-eksternal` |
| `app/Filament/Pages/PengaturanSistem.php` (ubah) | bagian "Keamanan" (saklar MFA, super-admin saja) dan "Kiriman eksternal" |
| `database/migrations/2026_10_26_100000_create_identitas_google_table.php` | tabel `identitas_google` |
| `app/Models/IdentitasGoogle.php` | model identitas |
| `app/Services/Auth/PenautanGoogle.php` + `GagalMasukGoogle.php` | aturan akun mana yang masuk |
| `app/Http/Controllers/Auth/GoogleController.php` | `arah`, `callback` |
| `database/migrations/2026_10_26_110000_kiriman_eksternal_surat_masuk_dan_usulan.php` | kolom surat masuk dilonggarkan + `pengirim_user_id`; `usulan_mahasiswa.asal` |
| `app/Actions/Kiriman/AjukanKirimanEksternal.php`, `TerimaKirimanEksternal.php`, `TarikKirimanEksternal.php` | alur fakultas dan fan-out tujuan |
| `app/Actions/Usulan/ArsipkanUsulan.php` | perantara mengarsipkan |
| `app/Livewire/Pengirim/Beranda.php`, `KirimanBaru.php` + views | halaman pengirim eksternal |
| `database/seeders/PeranDanIzinSeeder.php` (ubah) | peran `pengirim-eksternal`, izin `kiriman.ajukan` |

---

### Task 1: Saklar MFA

**Files:**
- Modify: `app/Support/Pengaturan.php`, `app/Models/User.php:wajibMfa`, `app/Providers/Filament/AdminPanelProvider.php`, `app/Filament/Pages/PengaturanSistem.php`, `database/seeders/PengaturanSeeder.php` (bila ia mendaftar kunci)
- Test: `tests/Feature/SaklarMfaTest.php`

**Interfaces:**
- Produces: `Pengaturan::get('mfa_wajib'): bool` (bawaan `false`); `User::wajibMfa(): bool` benar hanya bila saklar menyala **dan** pengguna berperan di `config('unsil.peran_wajib_mfa')`.

- [ ] **Step 1: Tes gagal** di `tests/Feature/SaklarMfaTest.php` (seed `PeranDanIzinSeeder`, `RefreshDatabase`):
  - `it('MFA tidak wajib bawaan untuk semua peran')`: untuk tiap peran di `config('unsil.peran_wajib_mfa')`, pengguna berperan itu `->wajibMfa()` adalah `false`.
  - `it('menyalakan saklar mewajibkan MFA hanya untuk peran terdaftar')`: `Pengaturan::set('mfa_wajib', true)`; `dekan->wajibMfa()` true, `mahasiswa->wajibMfa()` false.
  - `it('hanya super-admin yang dapat mengubah saklar')`: `Livewire::test(PengaturanSistem::class)` sebagai admin-persuratan dengan izin `pengaturan.kelola` memanggil `save` dengan `mfa_wajib => true` → `Pengaturan::get('mfa_wajib')` tetap `false`; sebagai super-admin → `true`.
  - `it('akses panel tanpa MFA terpasang tidak dialihkan saat saklar mati')`: `dekan` login → `get('/admin')` bukan redirect ke halaman setup MFA.
  - `it('membaca pengaturan saat tabel belum ada tidak melempar galat')`: `Schema::drop('pengaturan'); Cache::forget(Pengaturan::CACHE_KUNCI); expect(Pengaturan::get('mfa_wajib'))->toBeFalse()`.
- [ ] **Step 2:** Jalankan `vendor/bin/pest tests/Feature/SaklarMfaTest.php`. Expected: FAIL.
- [ ] **Step 3: Implementasi.** Tambah ke `Pengaturan::BAWAAN`: `'mfa_wajib' => ['nilai' => false, 'grup' => 'keamanan']`, `'batas_kiriman_harian' => ['nilai' => 5, 'grup' => 'kiriman']`, `'batas_tujuan_kiriman' => ['nilai' => 5, 'grup' => 'kiriman']`. Bungkus pembacaan DB di `Pengaturan::semua()` dengan `try/catch (QueryException)` yang mengembalikan `[]` tanpa men-cache. `User::wajibMfa()` mempertahankan pengecualian akun demo lokal yang ada, lalu `return (bool) Pengaturan::get('mfa_wajib') && $this->hasAnyRole(config('unsil.peran_wajib_mfa'))`. Di `AdminPanelProvider` ganti argumen `isRequired` menjadi Closure `fn (): bool => (bool) Pengaturan::get('mfa_wajib')` bila Filament 5 menerima `bool|Closure` (periksa tanda tangan di `vendor/filament`); bila tidak, ubah menjadi `false` dan biarkan middleware `WajibMfa` yang memaksa. Di `PengaturanSistem` tambahkan `Section::make('Keamanan')` berisi `Toggle::make('mfa_wajib')->label('Wajibkan MFA untuk pejabat dan admin')` yang `visible`/`disabled` kecuali `hasRole('super-admin')`; di metode simpan, buang kunci `mfa_wajib` dari data bila pelaku bukan super-admin. Tambahkan juga `Section::make('Kiriman eksternal')` berisi dua `TextInput` angka untuk kunci `batas_*` di atas (1–50).
- [ ] **Step 4:** Jalankan tes Task 1 → PASS; jalankan `vendor/bin/pest tests/Feature/AutentikasiTest.php tests/Feature/FondasiTest.php` dan sesuaikan tes lama yang mengasumsikan MFA wajib (set `Pengaturan::set('mfa_wajib', true)` di tes itu, jangan melemahkan asersinya).
- [ ] **Step 5: Commit** `feat(keamanan): saklar MFA di pengaturan sistem, bawaan mati`.

---

### Task 2: Penautan akun Google (service, skema, peran)

**Files:**
- Create: `database/migrations/2026_10_26_100000_create_identitas_google_table.php`, `app/Models/IdentitasGoogle.php`, `app/Services/Auth/PenautanGoogle.php`, `app/Services/Auth/GagalMasukGoogle.php`
- Modify: `composer.json` (via `composer require laravel/socialite`), `config/services.php`, `.env.example`, `.env.production.example`, `database/seeders/PeranDanIzinSeeder.php`, `app/Models/User.php` (`PERAN_TANPA_PANEL`), `app/Support/Beranda.php`
- Test: `tests/Feature/Auth/PenautanGoogleTest.php`

**Interfaces:**
- Produces: `PenautanGoogle::masuk(string $googleId, string $surel, bool $surelTerverifikasi, string $nama, ?string $hd = null): User` — mengembalikan pengguna yang boleh mendapat sesi, atau melempar `GagalMasukGoogle` (extends `RuntimeException`, pesan siap tampil). `IdentitasGoogle` (uuid, `user_id` unik, `google_id` unik, `surel`). Izin `kiriman.ajukan`; peran `pengirim-eksternal` memilikinya. `Beranda::url()` mengarah ke `route('pengirim')` (rute dibuat di Task 4) untuk `pengirim-eksternal`.

- [ ] **Step 1:** `composer require laravel/socialite`; pastikan terpasang untuk Laravel 13. Tambah `'google' => ['client_id' => env('GOOGLE_CLIENT_ID'), 'client_secret' => env('GOOGLE_CLIENT_SECRET'), 'redirect' => env('GOOGLE_REDIRECT_URI')]` ke `config/services.php` dan tiga kunci kosong ke kedua berkas `.env*.example`.
- [ ] **Step 2: Tes gagal** `PenautanGoogleTest.php` (seed `PeranDanIzinSeeder`), satu `it` per baris, semua memanggil `app(PenautanGoogle::class)->masuk(...)`:
  - surel `Budi@Student.Unsil.ac.id` baru → pengguna dibuat, peran `mahasiswa`, `email_verified_at` terisi, `email` huruf kecil, ada `IdentitasGoogle`.
  - surel `orang@gmail.com` baru → peran `pengirim-eksternal`.
  - surel `pegawai@unsil.ac.id` baru → `pengirim-eksternal` (bukan pegawai).
  - `x@student.unsil.ac.id.evil.com` dan `x@xstudent.unsil.ac.id` → `pengirim-eksternal`.
  - `$surelTerverifikasi = false` → `GagalMasukGoogle`, tidak ada pengguna baru.
  - `hd` terisi dan berbeda dari domain surel → `GagalMasukGoogle`.
  - akun lama aktif dengan surel sama → pengguna yang sama dikembalikan, perannya tidak berubah, tidak ada akun ganda, `IdentitasGoogle` tertaut.
  - akun lama `dekan` → ditautkan dan dikembalikan (peran tetap `dekan`); tidak ada peran bertambah.
  - akun lama `aktif = false` → `GagalMasukGoogle` dengan pesan `Surel atau kata sandi salah.`
  - akun lama `email_verified_at = null` dengan kata sandi `rahasia-lama` → setelah masuk, `Hash::check('rahasia-lama', $user->password)` false, `email_verified_at` terisi, dan baris `sessions` milik pengguna itu terhapus.
  - panggilan kedua dengan `google_id` sama tetapi surel berbeda → pengguna sama; `users.email` tidak berubah.
  - `google_id` baru dengan surel yang sudah dimiliki akun lain yang sudah punya identitas Google berbeda → `GagalMasukGoogle`.
- [ ] **Step 3:** Jalankan → FAIL. Buat migrasi dan model (`HasUuids`; `user_id` FK `cascadeOnDelete`, `unique`; `google_id` `unique`). Implementasikan `PenautanGoogle::masuk` dalam `DB::transaction`: (1) tolak bila `!$surelTerverifikasi` atau `hd` bertentangan; (2) cari `IdentitasGoogle` menurut `google_id` → pengguna; (3) bila tak ada, cari `User` menurut surel huruf kecil → tautkan (terapkan aturan kata sandi acak dan hapus sesi bila `email_verified_at` kosong, lalu isi `email_verified_at`); (4) bila tak ada, buat `User` (`password` acak `Str::random(64)`, `email_verified_at = now()`), beri peran menurut `Str::endsWith($surel, '@'.$domain)` untuk tiap domain di `config('unsil.domain_mahasiswa')`; (5) tolak bila `!$user->aktif`; kembalikan. Tambah izin `kiriman.ajukan` dan peran `pengirim-eksternal` (hanya izin itu) ke `PeranDanIzinSeeder`; tambahkan `'pengirim-eksternal'` ke `User::PERAN_TANPA_PANEL`; tambahkan cabang `hasRole('pengirim-eksternal') => route('pengirim')` ke `Beranda::url`.
- [ ] **Step 4:** Jalankan tes → PASS; `vendor/bin/pest tests/Feature/MatriksAksesTest.php tests/Feature/KelolaPenggunaTest.php` (daftar peran bertambah; perbarui tes yang menghitung peran).
- [ ] **Step 5: Commit** `feat(auth): penautan akun Google dan peran pengirim-eksternal`.

---

### Task 3: Rute, controller, dan tombol Google

**Files:**
- Create: `app/Http/Controllers/Auth/GoogleController.php`
- Modify: `routes/web.php` (grup `guest`), `resources/views/auth/masuk.blade.php`, `app/Providers/Filament/AdminPanelProvider.php` (render hook), `app/Http/Middleware/HeaderKeamanan.php` bila CSP menghalangi pengalihan
- Test: `tests/Feature/Auth/GoogleControllerTest.php`

**Interfaces:**
- Consumes: `PenautanGoogle::masuk(...)` dari Task 2.
- Produces: rute `login.google` (`GET /masuk/google`, query opsional `dari=admin`) dan `login.google.callback` (`GET /masuk/google/callback`), keduanya `throttle:masuk`.

- [ ] **Step 1: Tes gagal.** Fake Socialite: `Socialite::fake('google', (new \Laravel\Socialite\Two\User)->map(['id' => 'g-1', 'name' => 'Budi', 'email' => 'budi@student.unsil.ac.id', 'user' => ['email_verified' => true]]))` (bila versi terpasang tak punya `fake`, gunakan `Socialite::shouldReceive('driver->user')`).
  - `GET /masuk/google` → redirect ke `accounts.google.com`.
  - callback sukses mahasiswa baru → `assertAuthenticated`, redirect ke `route('mahasiswa')`.
  - callback pengguna `dekan` lama → terautentikasi, redirect ke `/admin`.
  - callback dengan `dari=admin` tersimpan di sesi oleh `arah`, pengguna `mahasiswa` → terautentikasi **tetapi** redirect ke `route('mahasiswa')` dengan pesan sesi `Akun ini tidak memakai panel admin.`
  - `email_verified` salah / akun nonaktif → `assertGuest`, redirect ke `login` dengan galat pada `email`.
  - pengguna menolak izin (`?error=access_denied`) dan pengecualian `InvalidStateException` → `assertGuest`, redirect ke `login` dengan galat, status bukan 500.
  - halaman `GET /masuk` memuat tautan `route('login.google')`; halaman login panel `/admin/login` memuat tautan `login.google` dengan `dari=admin`.
- [ ] **Step 2:** Jalankan → FAIL. Implementasikan `GoogleController::arah(Request): RedirectResponse` (simpan `google_dari` di sesi bila `dari=admin`; `Socialite::driver('google')->scopes(['openid','email','profile'])->redirect()`) dan `callback(Request): RedirectResponse` (tangani `error`, `InvalidStateException`, dan `GagalMasukGoogle`; `Auth::login($user, true)`; `session()->regenerate()`; tujuan: pengguna dengan peran di luar `PERAN_TANPA_PANEL` → `url('admin')`; selain itu `Beranda::url($user)` dengan pesan di atas bila datang dari `admin`). Tambah rute di grup `guest`. Tambah tombol "Masuk dengan Google" pada `masuk.blade.php` dan via `PanelsRenderHook::AUTH_LOGIN_FORM_AFTER` di panel.
- [ ] **Step 3: MFA.** Periksa di `vendor/filament` bagaimana halaman login Filament menuntaskan tantangan MFA. Bila ada API yang dapat dipakai ulang agar pengguna yang MFA-nya terpasang ditantang setelah Google, pakai itu dan tambahkan tes. Bila tidak ada, pengguna yang `MultiFactorChallenge::make()->hasEnabledProviders($user)` ditolak lewat Google dengan pesan `Akun ini memakai verifikasi dua langkah; masuk lewat halaman admin.`, tambahkan tesnya, dan **laporkan penyimpangan dari spec ini kepada pemilik** serta catat di `docs/KEPUTUSAN.md`.
- [ ] **Step 4:** Tes Task 3 PASS; `vendor/bin/pest tests/Feature/HeaderKeamananTest.php` tetap hijau.
- [ ] **Step 5: Commit** `feat(auth): masuk dengan Google di halaman masuk dan panel`.

---

### Task 4: Kiriman eksternal ke fakultas

**Files:**
- Create: `database/migrations/2026_10_26_110000_kiriman_eksternal_surat_masuk_dan_usulan.php`, `app/Actions/Kiriman/AjukanKirimanEksternal.php`, `app/Actions/Kiriman/TerimaKirimanEksternal.php`, `app/Actions/Kiriman/TarikKirimanEksternal.php`, `app/Livewire/Pengirim/Beranda.php`, `app/Livewire/Pengirim/KirimanBaru.php`, `resources/views/livewire/pengirim/beranda.blade.php`, `resources/views/livewire/pengirim/kiriman-baru.blade.php`
- Modify: `app/Enums/StatusSuratMasuk.php`, `app/Models/SuratMasuk.php`, `app/Policies/SuratMasukPolicy.php`, `app/Filament/Resources/SuratMasuks/SuratMasukResource.php`, `app/Support/Pelacakan/Linimasa.php:151`, `app/Services/Notifikasi/NotifikasiAlur.php`, `app/Filament/Widgets/RingkasanPersuratan.php`, `app/Services/Laporan/Lap01Register.php`, `routes/web.php`
- Test: `tests/Feature/KirimanEksternalFakultasTest.php`

**Interfaces:**
- Produces: `StatusSuratMasuk::Diajukan` (`'diajukan'`). Kolom `surat_masuk.nomor_agenda`, `nomor_surat`, `diregistrasi_oleh` nullable; kolom baru `pengirim_user_id` (FK `users`, nullOnDelete). `SuratMasuk::pengirimUser(): BelongsTo`. `AjukanKirimanEksternal::jalankan(User $pengirim, array $data): array{surat: ?SuratMasuk, usulan: list<UsulanMahasiswa>}` dengan `$data` berisi `tujuan` (list string: `'fakultas'`, nanti `'ormawa:{uuid}'`/`'unit:{uuid}'`), `perihal`, `uraian`, `instansi`, `surel`/`wa` (minimal satu), `lampiran` (tautan, opsional). `TerimaKirimanEksternal::jalankan(SuratMasuk $surat, User $pelaku): SuratMasuk`. `TarikKirimanEksternal::jalankan(SuratMasuk $surat, User $pelaku): void`. Rute `pengirim` (`/pengirim`) dan `pengirim.kiriman.baru` (`/pengirim/kiriman/baru`). Kebijakan `SuratMasukPolicy::terima` dan `::tarik`.

- [ ] **Step 1: Tes gagal** `KirimanEksternalFakultasTest.php` (seed peran, register nomor, struktur FKIP, pengaturan; `Notification::fake()`):
  - pengirim mengirim `tujuan => ['fakultas']` → satu `SuratMasuk` ber-`status = diajukan`, `jenis_pengirim = eksternal`, `nomor_agenda` null, `pengirim_user_id` = pengirim, `asal` = instansi, kode lacak terisi, kontak terenkripsi (`getRawOriginal('pengirim_surel')` ≠ surel asli); notifikasi dikirim ke pemegang izin `masuk.registrasi`.
  - tanpa `surel` dan `wa` → `ValidationException`; `tujuan => []` → `ValidationException`.
  - pengirim ke-6 dalam sehari → `TooManyRequestsHttpException` (batas dari `Pengaturan::get('batas_kiriman_harian')`).
  - pengguna tanpa izin `kiriman.ajukan` (mis. `mahasiswa`) → `AuthorizationException`.
  - `TerimaKirimanEksternal` oleh `staf-khusus`: status `diterima`, `nomor_agenda` terisi (format register `agenda-masuk`), `diregistrasi_oleh` = staf, `diteruskan_pada` null; oleh `admin-persuratan`: `diteruskan_pada` terisi; terima dua kali → `ValidationException`; oleh `mahasiswa` → `AuthorizationException`.
  - `TarikKirimanEksternal` oleh pemilik saat `diajukan` → surat terhapus lunak; sesudah diterima → `ValidationException`; oleh pengirim lain → `AuthorizationException`.
  - dekan tidak melihat surat `diajukan` (`SuratMasuk::tampakBagi($dekan)` kosong); `Lap01Register` untuk rentang waktu surat itu tidak melempar galat dan melewatkan surat tanpa nomor agenda; `Linimasa::statusSuratMasuk` mengembalikan `'Menunggu diterima fakultas'`.
  - `GET /pengirim` oleh pengirim → 200 dan hanya menampilkan kirimannya; oleh pengirim lain tidak menampilkan kiriman itu; `GET /lacak/{kode}` kiriman `diajukan` → 200.
- [ ] **Step 2:** Jalankan → FAIL. Implementasi: migrasi (lewati `ALTER` khusus Postgres bila driver sqlite, ikuti pola `DB::getDriverName()` pada migrasi `usulan_mahasiswa`; di tahap ini kolom `usulan_mahasiswa.asal` boleh ditambahkan sekaligus: `string('asal', 20)->default('mahasiswa')`); enum + `Linimasa::statusSuratMasuk` (cabang `Diajukan`); `AjukanKirimanEksternal` (validasi, `RateLimiter` kunci `kiriman-eksternal:{id}` 86400 detik, `RegistrasiSuratMasuk` **tidak** dipakai karena mewajibkan nomor agenda; simpan lampiran lewat `PenyimpananBerkas::simpan($surat, 'pindaian', ...)`, `tanggal_surat = today`, `tanggal_terima = now`); `TerimaKirimanEksternal` (kunci baris `lockForUpdate`, ambil nomor lewat `AmbilNomorBerikutnya` dengan pola yang sama seperti `RegistrasiSuratMasuk`, aturan penerusan staf-khusus vs admin sama dengan `RegistrasiSuratMasuk`, panggil `NotifikasiAlur::suratMasukBaru`); `TarikKirimanEksternal`; kebijakan `terima` (`masuk.registrasi` & status `diajukan`) dan `tarik` (pemilik & `diajukan`); `NotifikasiAlur::kirimanEksternalBaru(SuratMasuk, User)`; aksi tabel "Terima kiriman" pada `SuratMasukResource` dan filter status; stat "Kiriman menunggu diterima" pada `RingkasanPersuratan`; Livewire `Pengirim\Beranda` (daftar kiriman milik sendiri, tombol tarik) dan `KirimanBaru` (formulir, pola `Mahasiswa\UsulanBaru`; nama dan surel Google sebagai nilai awal); rute di grup `auth`, dijaga `can('kiriman.ajukan')`.
- [ ] **Step 3:** Tes PASS; jalankan `vendor/bin/pest tests/Feature/AlurStafKhususTest.php tests/Feature/HasilCetakDanLacakTest.php tests/Feature/LaporanTest.php` untuk memastikan jalur lama tetap hijau.
- [ ] **Step 4: Commit** `feat(kiriman): pengirim eksternal mengirim surat ke fakultas`.

---

### Task 5: Kiriman eksternal ke ormawa/prodi/lab dan arsip perantara

**Files:**
- Create: `app/Actions/Usulan/ArsipkanUsulan.php`
- Modify: `app/Actions/Kiriman/AjukanKirimanEksternal.php`, `app/Actions/Usulan/AjukanUsulan.php` (ekstrak pemilihan tujuan agar dipakai bersama), `app/Enums/StatusUsulan.php`, `app/Models/UsulanMahasiswa.php` (`asal` di `$fillable`, `daripihakLuar(): bool`), `app/Policies/UsulanMahasiswaPolicy.php`, `app/Livewire/Ormawa/UsulanMasuk.php`, `resources/views/livewire/ormawa/usulan-masuk.blade.php`, `app/Livewire/Pengirim/KirimanBaru.php` + view, `app/Livewire/Pengirim/Beranda.php` + view, `app/Support/Pelacakan/Linimasa.php` (label usulan), `app/Services/Notifikasi/NotifikasiAlur.php`
- Test: `tests/Feature/KirimanEksternalPerantaraTest.php`

**Interfaces:**
- Consumes: `AjukanKirimanEksternal` dari Task 4.
- Produces: `StatusUsulan::Diarsipkan` (`'diarsipkan'`, `label()` = `Diarsipkan oleh perantara`, `terbuka()` false). `ArsipkanUsulan::jalankan(UsulanMahasiswa $usulan, User $pelaku): UsulanMahasiswa`. `AjukanKirimanEksternal` kini memproses `tujuan` berbentuk `'ormawa:{uuid}'`/`'unit:{uuid}'` (maks. `Pengaturan::get('batas_tujuan_kiriman')`), satu `UsulanMahasiswa` per tujuan dengan `asal = 'eksternal'` dan `mahasiswa_id` = id pengirim.

- [ ] **Step 1: Tes gagal** `KirimanEksternalPerantaraTest.php` (pola `beforeEach` dari `AlurPengajuUnitTest.php`: prodi, lab, pengaju, ormawa dengan pengurus aktif):
  - `tujuan => ['fakultas', "unit:{$prodi->id}", "ormawa:{$ormawa->id}"]` → 1 `SuratMasuk` + 2 `UsulanMahasiswa` (`asal = eksternal`, status `diajukan`), semua berkode lacak berbeda; ketiganya berdiri sendiri.
  - hanya tujuan perantara → `surat` null.
  - tujuan ganda diperlakukan sebagai satu; 6 tujuan perantara → `ValidationException`; ormawa/unit nonaktif atau UUID acak → `ValidationException`; unit tanpa pengaju → `ValidationException`.
  - `ArsipkanUsulan` oleh pengaju unit pada usulan `asal = eksternal` → status `diarsipkan`, `permohonan_id` tetap null; pada usulan `asal = mahasiswa` → `ValidationException`; oleh pengaju unit lain atau mahasiswa → `AuthorizationException`; usulan sudah `dipakai` → `ValidationException`.
  - mengarsipkan usulan di prodi tidak mengubah `SuratMasuk` kiriman yang sama.
  - pengirim membatalkan usulannya lewat `BatalkanUsulan` → `dibatalkan`; pengirim lain → `AuthorizationException`.
  - Livewire `Unit\UsulanMasuk` memuat label "dari pihak luar" untuk usulan eksternal dan tombol `arsipkan`; memanggil `arsipkan($id)` menghilangkan usulan dari daftar; perantara lain mendapat 403.
  - kode lacak publik usulan `diarsipkan` menampilkan `Diarsipkan oleh perantara` tanpa nama pengirim maupun catatan.
- [ ] **Step 2:** Jalankan → FAIL. Implementasi sesuai Interfaces. `ArsipkanUsulan` mengikuti pola `PutusUsulan` (`lockForUpdate`, `Gate::authorize('tangani')`, hanya status `diajukan`, hanya `asal = eksternal`). Tambahkan notifikasi kepada pengirim saat diarsipkan lewat pola `NotifikasiAlur` yang ada (isi tanpa data sensitif). Perluas formulir `KirimanBaru` dengan daftar centang tujuan (fakultas, ormawa, prodi/lab; sumber daftar sama dengan `Mahasiswa\UsulanBaru::render`) dan `Pengirim\Beranda` dengan status per tujuan.
- [ ] **Step 3:** Tes PASS; jalankan seluruh `tests/Feature/*Usulan*` dan `AlurPengajuUnitTest.php`.
- [ ] **Step 4: Commit** `feat(kiriman): tujuan paralel ke ormawa/prodi/lab dan arsip perantara`.

---

### Task 6: Dokumentasi dan verifikasi akhir

**Files:**
- Modify: `docs/KEPUTUSAN.md` (K-18), `docs/KEAMANAN.md`, `docs/DEPLOY.md` (variabel `GOOGLE_*`, langkah membuat OAuth Client dan URI callback), `docs/PANDUAN-ADMIN.md`, `CHANGELOG.md`, `README.md` bila memuat daftar fitur

- [ ] **Step 1:** Tulis K-18 (keputusan pemilik dan pilihan teknis dari spec, termasuk penyimpangan MFA bila terjadi di Task 3), perbarui matriks keamanan dengan peran baru dan saklar MFA, dan tambahkan entri `CHANGELOG.md`.
- [ ] **Step 2:** Jalankan `vendor/bin/pint`, `vendor/bin/phpstan analyse`, `vendor/bin/pest`. Expected: semua hijau; bandingkan jumlah galat phpstan dengan sebelum perubahan.
- [ ] **Step 3: Verifikasi manual** (lokal, setelah pemilik mengisi `GOOGLE_*`): masuk dengan akun Gmail → beranda pengirim; kirim ke fakultas + satu prodi; terima di panel; arsipkan di prodi. Laporkan hasilnya apa adanya.
- [ ] **Step 4: Commit** `docs: K-18 login Google, kiriman eksternal, saklar MFA`.
