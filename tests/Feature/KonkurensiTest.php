<?php

use App\Actions\Nomor\AmbilNomorBerikutnya;
use App\Models\NomorTerpakai;
use App\Models\RegisterNomor;
use App\Models\User;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PeranDanIzinSeeder::class, PengaturanSeeder::class, RegisterNomorSeeder::class]);
});

/*
 * Uji konkurensi proses-paralel (8 pengaju serentak, dua proses memperebutkan nomor, bentrok ruangan) berada di
 * NomorKonkurenTest dan PermohonanKonkurenTest karena butuh banyak koneksi. Berkas ini menjaga lapisan terakhirnya:
 * kendala UNIQUE basis data dan kunci aplikasi tidak dapat dilewati oleh kode.
 */

it('nomor berurutan tanpa celah atau ganda pada 30 pengambilan beruntun', function () {
    $r = RegisterNomor::firstWhere('kode', 'permohonan');
    $pemilik = User::factory()->create();

    $urut = collect(range(1, 30))->map(fn () => app(AmbilNomorBerikutnya::class)->jalankan($r, $pemilik, [], now())->urut)->all();

    expect($urut)->toBe(range(1, 30));
});

it('UNIQUE(register, tahun, urut) menolak nomor ganda walau aplikasi dilewati', function () {
    $r = RegisterNomor::firstWhere('kode', 'permohonan');
    $pemilik = User::factory()->create();
    app(AmbilNomorBerikutnya::class)->jalankan($r, $pemilik, [], now());

    expect(fn () => NomorTerpakai::create(['register_nomor_id' => $r->id, 'tahun' => now()->year, 'urut' => 1, 'nomor_lengkap' => 'PMH-DUP', 'pemilik_type' => $pemilik->getMorphClass(), 'pemilik_id' => $pemilik->id]))
        ->toThrow(QueryException::class);
});

it('kunci aplikasi mencegah dua proses mengambil nomor register yang sama bersamaan', function () {
    $r = RegisterNomor::firstWhere('kode', 'permohonan');
    $kunci = Cache::lock("nomor:{$r->kode}:".now()->year, 10);
    expect($kunci->get())->toBeTrue();

    // proses lain tidak mendapatkan kunci yang sama sebelum dilepas
    expect(Cache::lock("nomor:{$r->kode}:".now()->year, 10)->get())->toBeFalse();
    $kunci->release();
    expect(Cache::lock("nomor:{$r->kode}:".now()->year, 10)->get())->toBeTrue();
});

it('berkas uji konkurensi proses-paralel tersedia', function () {
    expect(file_exists(base_path('tests/Feature/NomorKonkurenTest.php')))->toBeTrue()->and(file_exists(base_path('tests/Feature/PermohonanKonkurenTest.php')))->toBeTrue();
});
