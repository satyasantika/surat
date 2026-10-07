<?php

use App\Http\Controllers\Auth\DaftarController;
use App\Http\Controllers\Auth\KataSandiController;
use App\Http\Controllers\Auth\MasukController;
use App\Http\Controllers\Auth\ProfilController;
use App\Http\Controllers\BukaTautanController;
use App\Http\Controllers\ImpersonasiController;
use App\Http\Controllers\LembarDisposisiPdfController;
use App\Http\Controllers\NaskahPdfController;
use App\Http\Controllers\PratinjauNaskahController;
use App\Http\Controllers\Publik\VerifikasiController;
use App\Http\Middleware\PastikanAktif;
use App\Livewire\Pimpinan\KotakMasuk;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/verifikasi/{id}', VerifikasiController::class)->middleware('throttle:verifikasi')->name('verifikasi');

Route::middleware('guest')->group(function () {
    Route::get('/masuk', [MasukController::class, 'create'])->name('login');
    Route::post('/masuk', [MasukController::class, 'store'])->middleware('throttle:masuk');

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
    Route::get('/disposisi', KotakMasuk::class)->name('disposisi');
    Route::get('/profil', [ProfilController::class, 'show'])->name('profil');
    Route::get('/berkas/{tautan}/buka', BukaTautanController::class)->whereUuid('tautan')->name('berkas.buka');
    Route::post('/impersonasi/selesai', [ImpersonasiController::class, 'selesai'])->name('impersonasi.selesai');
    Route::post('/impersonasi/{user}', [ImpersonasiController::class, 'mulai'])->whereUuid('user')->name('impersonasi.mulai');
    Route::put('/profil/sandi', [ProfilController::class, 'updateSandi'])->name('profil.sandi');
});
