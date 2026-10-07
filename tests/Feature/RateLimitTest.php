<?php

use App\Actions\Permohonan\AjukanPermohonan;
use App\Models\JenisPermohonan;
use App\Models\Ormawa;
use App\Models\PengurusOrmawa;
use App\Models\User;
use Database\Seeders\JenisPermohonanSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed([PeranDanIzinSeeder::class, PengaturanSeeder::class, RegisterNomorSeeder::class, JenisPermohonanSeeder::class]);
    RateLimiter::clear('x');
});

it('masuk dibatasi 5 percobaan per menit per surel dan IP', function () {
    foreach (range(1, 5) as $i) {
        expect($this->post('/masuk', ['email' => 'korban@unsil.ac.id', 'password' => "salah{$i}"])->status())->not->toBe(429);
    }

    $this->post('/masuk', ['email' => 'korban@unsil.ac.id', 'password' => 'salah6'])->assertStatus(429);
    // surel lain tidak ikut terkunci
    expect($this->post('/masuk', ['email' => 'lain@unsil.ac.id', 'password' => 'x'])->status())->not->toBe(429);
});

it('pendaftaran dan lupa kata sandi dibatasi 5 per menit per IP', function () {
    foreach (range(1, 5) as $i) {
        expect($this->post('/lupa-sandi', ['email' => "u{$i}@unsil.ac.id"])->status())->not->toBe(429);
    }

    $this->post('/lupa-sandi', ['email' => 'u6@unsil.ac.id'])->assertStatus(429);
    $this->post('/daftar', ['email' => 'baru@student.unsil.ac.id'])->assertStatus(429);
});

it('verifikasi publik dan formulir verifikasi dibatasi 30 per menit per IP', function () {
    $id = Str::uuid()->toString();

    foreach (range(1, 30) as $i) {
        $this->get("/verifikasi/{$id}")->assertNotFound();
    }

    $this->get("/verifikasi/{$id}")->assertStatus(429);
    $this->get('/verifikasi')->assertStatus(429);
});

it('pengajuan permohonan dibatasi 10 per jam per pengguna', function () {
    $o = Ormawa::create(['nama' => 'HIMA RL', 'tingkat' => 'prodi']);
    $o->sk()->create(['nomor_sk' => 'SK/1', 'tanggal_sk' => '2026-01-01', 'periode_mulai' => '2026-01-01', 'periode_selesai' => '2099-12-31']);
    $u = User::factory()->create(['nip_nim' => '1234567'])->assignRole('pengurus-ormawa');
    PengurusOrmawa::create(['ormawa_id' => $o->id, 'user_id' => $u->id, 'nama' => $u->name, 'jabatan' => 'ketua']);

    foreach (range(1, 10) as $i) {
        RateLimiter::hit('ajukan-permohonan:'.$u->id, 3600);
    }

    expect(fn () => app(AjukanPermohonan::class)->jalankan($u, $o, JenisPermohonan::firstWhere('kode', 'kegiatan'), []))->toThrow(TooManyRequestsHttpException::class);
});
