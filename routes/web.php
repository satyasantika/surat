<?php

use App\Http\Controllers\Auth\DaftarController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\KataSandiController;
use App\Http\Controllers\Auth\MasukController;
use App\Http\Controllers\Auth\ProfilController;
use App\Http\Controllers\BukaTautanController;
use App\Http\Controllers\EksporUnduhController;
use App\Http\Controllers\ImpersonasiController;
use App\Http\Controllers\LembarDisposisiPdfController;
use App\Http\Controllers\NaskahPdfController;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\PratinjauNaskahController;
use App\Http\Controllers\Publik\FormVerifikasiController;
use App\Http\Controllers\Publik\HalamanController;
use App\Http\Controllers\Publik\LacakController;
use App\Http\Controllers\Publik\VerifikasiController;
use App\Http\Middleware\HeaderHalamanPublik;
use App\Http\Middleware\PastikanAktif;
use App\Livewire\Mahasiswa\Beranda as BerandaMahasiswa;
use App\Livewire\Mahasiswa\UsulanBaru;
use App\Livewire\Ormawa\AjukanPermohonan;
use App\Livewire\Ormawa\Beranda as BerandaOrmawa;
use App\Livewire\Ormawa\IsiLpj;
use App\Livewire\Ormawa\Profil as ProfilOrmawa;
use App\Livewire\Ormawa\Progres;
use App\Livewire\Ormawa\UsulanMasuk as UsulanMasukOrmawa;
use App\Livewire\Pembuat\Tugas as TugasPembuat;
use App\Livewire\Pimpinan\KotakMasuk;
use App\Livewire\Unit\AjukanPermohonan as AjukanPermohonanUnit;
use App\Livewire\Unit\Beranda as BerandaUnit;
use App\Livewire\Unit\Persetujuan as PersetujuanUnit;
use App\Livewire\Unit\Progres as ProgresUnit;
use App\Livewire\Unit\UsulanMasuk as UsulanMasukUnit;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;

Route::middleware(HeaderHalamanPublik::class)->group(function () {
    Route::get('/', [HalamanController::class, 'beranda'])->name('beranda');
    // /ormawa sudah dipakai beranda pengurus (butuh login), maka profil publik di /organisasi.
    Route::get('/organisasi', [HalamanController::class, 'organisasiIndex'])->name('organisasi');
    Route::get('/organisasi/{slug}', [HalamanController::class, 'organisasiShow'])->name('organisasi.tampil');
    Route::get('/verifikasi', FormVerifikasiController::class)->middleware('throttle:verifikasi')->name('verifikasi.form');
    // Pelacakan tanpa login (BR-27): hanya tahap, jabatan pemroses, dan tautan hasil; dibatasi laju dan tak diindeks.
    Route::get('/lacak', [LacakController::class, 'form'])->middleware('throttle:lacak')->name('lacak.form');
    Route::get('/lacak/{kode}', [LacakController::class, 'tampil'])->middleware('throttle:lacak')->name('lacak.tampil');
});

Route::get('/verifikasi/{id}', VerifikasiController::class)->middleware('throttle:verifikasi')->name('verifikasi');

Route::middleware('guest')->group(function () {
    Route::get('/masuk', [MasukController::class, 'create'])->name('login');
    Route::post('/masuk', [MasukController::class, 'store'])->middleware('throttle:masuk');
    // Masuk dengan Google (publik dan panel; ?dari=admin menandai alur dari halaman masuk panel).
    Route::get('/masuk/google', [GoogleController::class, 'arah'])->middleware('throttle:masuk')->name('login.google');
    Route::get('/masuk/google/callback', [GoogleController::class, 'callback'])->middleware('throttle:masuk')->name('login.google.callback');

    Route::get('/daftar', [DaftarController::class, 'create'])->name('daftar');
    Route::post('/daftar', [DaftarController::class, 'store'])->middleware('throttle:daftar');

    Route::get('/lupa-sandi', [KataSandiController::class, 'request'])->name('password.request');
    Route::post('/lupa-sandi', [KataSandiController::class, 'email'])->middleware('throttle:daftar')->name('password.email');
    Route::get('/atur-ulang-sandi/{token}', [KataSandiController::class, 'reset'])->name('password.reset');
    Route::post('/atur-ulang-sandi', [KataSandiController::class, 'store'])->middleware('throttle:daftar')->name('password.store');
});

Route::get('/verifikasi-surel/{id}/{hash}', [DaftarController::class, 'verifikasi'])
    ->middleware('throttle:6,1')->name('verification.verify');

Route::middleware(['auth', AuthenticateSession::class, PastikanAktif::class])->group(function () {
    Route::post('/keluar', [MasukController::class, 'destroy'])->name('logout');
    Route::get('/surat-masuk/{surat}/lembar-disposisi', LembarDisposisiPdfController::class)->whereUuid('surat')->name('surat-masuk.lembar-disposisi');
    Route::get('/naskah/{naskah}/pratinjau', PratinjauNaskahController::class)->whereUuid('naskah')->name('naskah.pratinjau');
    Route::get('/naskah/{naskah}/pdf', NaskahPdfController::class)->whereUuid('naskah')->name('naskah.pdf');
    Route::get('/ekspor/{berkas}', EksporUnduhController::class)->middleware('signed')->name('ekspor.unduh');
    Route::get('/ormawa', BerandaOrmawa::class)->name('ormawa');
    Route::get('/ormawa/{ormawa}/profil', ProfilOrmawa::class)->whereUuid('ormawa')->name('ormawa.profil');
    Route::get('/ormawa/{ormawa}/permohonan', Progres::class)->whereUuid('ormawa')->name('ormawa.permohonan');
    Route::get('/ormawa/{ormawa}/permohonan/baru', AjukanPermohonan::class)->whereUuid('ormawa')->name('ormawa.permohonan.baru');
    Route::get('/ormawa/{ormawa}/usulan', UsulanMasukOrmawa::class)->whereUuid('ormawa')->name('ormawa.usulan');
    Route::get('/ormawa/{ormawa}/lpj/{lpj}', IsiLpj::class)->whereUuid(['ormawa', 'lpj'])->name('ormawa.lpj');
    // Mahasiswa perorangan mengusulkan kepada ormawa atau program studi/laboratorium.
    Route::get('/mahasiswa', BerandaMahasiswa::class)->name('mahasiswa');
    Route::get('/mahasiswa/usulan/baru', UsulanBaru::class)->name('mahasiswa.usulan.baru');
    // Pengaju dan kepala program studi/laboratorium (alur unit: pengaju -> kepala unit -> fakultas).
    Route::get('/unit', BerandaUnit::class)->name('unit');
    Route::get('/unit/persetujuan', PersetujuanUnit::class)->name('unit.persetujuan');
    Route::get('/unit/{unit}/permohonan', ProgresUnit::class)->whereUuid('unit')->name('unit.permohonan');
    Route::get('/unit/{unit}/usulan', UsulanMasukUnit::class)->whereUuid('unit')->name('unit.usulan');
    Route::get('/unit/{unit}/permohonan/baru', AjukanPermohonanUnit::class)->whereUuid('unit')->name('unit.permohonan.baru');
    Route::get('/disposisi', KotakMasuk::class)->name('disposisi');
    Route::get('/pembuat', TugasPembuat::class)->name('pembuat');
    Route::get('/profil', [ProfilController::class, 'show'])->name('profil');
    Route::get('/berkas/{tautan}/buka', BukaTautanController::class)->whereUuid('tautan')->name('berkas.buka');
    Route::post('/impersonasi/selesai', [ImpersonasiController::class, 'selesai'])->name('impersonasi.selesai');
    Route::post('/impersonasi/{user}', [ImpersonasiController::class, 'mulai'])->whereUuid('user')->name('impersonasi.mulai');
    Route::put('/profil/sandi', [ProfilController::class, 'updateSandi'])->name('profil.sandi');
    Route::put('/profil/notifikasi', [ProfilController::class, 'updateNotifikasi'])->name('profil.notifikasi');
    Route::get('/notifikasi', [NotifikasiController::class, 'index'])->name('notifikasi');
    Route::post('/notifikasi/baca-semua', [NotifikasiController::class, 'bacaSemua'])->name('notifikasi.baca-semua');
    Route::get('/notifikasi/{id}/buka', [NotifikasiController::class, 'buka'])->whereUuid('id')->name('notifikasi.buka');
});
