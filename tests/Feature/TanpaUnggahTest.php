<?php

use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(PeranDanIzinSeeder::class);
});

it('titik unggah bawaan Livewire tidak dapat dipakai (tanpa komponen unggah)', function () {
    $u = User::factory()->create()->assignRole('super-admin');
    $berkas = UploadedFile::fake()->create('x.pdf', 10);

    foreach ([null, $u] as $pelaku) {
        $pelaku ? $this->actingAs($pelaku) : null;
        $status = $this->post('/livewire/upload-file', ['files' => [$berkas]])->status();

        expect($status)->toBeIn([401, 403, 404, 419, 422, 302], "unggah Livewire status {$status}");
    }

    expect(glob(storage_path('app/livewire-tmp/*')) ?: [])->toBe([]);
});

it('pengiriman berkas ke rute aplikasi tidak menyimpan apa pun ke penyimpanan', function () {
    $u = User::factory()->create()->assignRole('pengurus-ormawa');
    $sebelum = glob(storage_path('app/*/*')) ?: [];

    $this->actingAs($u)->put('/profil/notifikasi', ['telepon' => '0812', 'berkas' => UploadedFile::fake()->create('evil.php', 5)]);
    $this->actingAs($u)->post('/masuk', ['email' => 'a@b.c', 'password' => 'x', 'berkas' => UploadedFile::fake()->create('evil.php', 5)]);

    expect(glob(storage_path('app/*/*')) ?: [])->toBe($sebelum)->and(glob(public_path('*.php')))->toBe([public_path('index.php')]);
});

it('konfigurasi disk tidak membuka penyimpanan publik untuk unggahan', function () {
    expect(config('berkas.mode'))->toBe('tautan')->and(config('filesystems.default'))->toBe('local')->and(config('filesystems.disks.local.visibility', 'private'))->toBe('private');
    expect(file_exists(public_path('storage')))->toBeFalse();
});
