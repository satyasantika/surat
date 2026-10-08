<?php

use App\Actions\Pengguna\SimpanPengguna;
use App\Filament\Imports\PenggunaImporter;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Actions\ImportAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(PeranDanIzinSeeder::class);
    Filament::setCurrentPanel('admin');
    Notification::fake();
});

function pengelolaUser(string $peran = 'super-admin'): User
{
    $u = User::factory()->create()->assignRole($peran);
    $u->givePermissionTo('pengguna.kelola');
    $u->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

    return $u;
}

function pemetaanPengguna(): array
{
    return array_combine(
        ['name', 'email', 'nip_nim', 'telepon', 'peran', 'aktif'],
        ['name', 'email', 'nip_nim', 'telepon', 'peran', 'aktif'],
    );
}

it('mengimpor baris pengguna baru dengan peran', function () {
    $this->actingAs(pengelolaUser());

    PenggunaImporter::test(pemetaanPengguna())
        ->import(['name' => 'Budi Santoso', 'email' => 'budi@unsil.ac.id', 'nip_nim' => '123', 'telepon' => '0811', 'peran' => 'pegawai', 'aktif' => '1'])
        ->assertImported();

    $u = User::firstWhere('email', 'budi@unsil.ac.id');
    expect($u)->not->toBeNull()
        ->and($u->hasRole('pegawai'))->toBeTrue()
        ->and($u->nip_nim)->toBe('123')
        ->and($u->aktif)->toBeTrue();
});

it('mengimpor banyak peran dipisah titik koma', function () {
    $this->actingAs(pengelolaUser());

    PenggunaImporter::test(pemetaanPengguna())
        ->import(['name' => 'Dwi', 'email' => 'dwi@unsil.ac.id', 'nip_nim' => '', 'telepon' => '', 'peran' => 'kasubag;dekan', 'aktif' => '1'])
        ->assertImported();

    $u = User::firstWhere('email', 'dwi@unsil.ac.id');
    expect($u->hasRole('kasubag'))->toBeTrue()->and($u->hasRole('dekan'))->toBeTrue();
});

it('memperbarui pengguna yang sudah ada berdasarkan surel', function () {
    $pelaku = pengelolaUser();
    $this->actingAs($pelaku);
    $lama = app(SimpanPengguna::class)->jalankan(null, ['name' => 'Lama', 'email' => 'ada@unsil.ac.id', 'peran' => ['pegawai']], $pelaku);

    PenggunaImporter::test(pemetaanPengguna())
        ->import(['name' => 'Baru', 'email' => 'ada@unsil.ac.id', 'nip_nim' => '', 'telepon' => '', 'peran' => 'kasubag', 'aktif' => '1'])
        ->assertImported();

    expect(User::where('email', 'ada@unsil.ac.id')->count())->toBe(1)
        ->and($lama->fresh()->name)->toBe('Baru')
        ->and($lama->fresh()->hasRole('kasubag'))->toBeTrue();
});

it('menolak surel di luar domain unsil', function () {
    $this->actingAs(pengelolaUser());

    $hasil = PenggunaImporter::test(pemetaanPengguna())
        ->import(['name' => 'X', 'email' => 'x@gmail.com', 'nip_nim' => '', 'telepon' => '', 'peran' => '', 'aktif' => '1']);

    expect($hasil->errors()->isNotEmpty())->toBeTrue();
});

it('menggagalkan baris dengan peran yang tidak dikenal', function () {
    $this->actingAs(pengelolaUser());

    PenggunaImporter::test(pemetaanPengguna())
        ->import(['name' => 'X', 'email' => 'xx@unsil.ac.id', 'nip_nim' => '', 'telepon' => '', 'peran' => 'dewa', 'aktif' => '1'])
        ->assertHasRowFailure("Peran 'dewa' tidak dikenal.");

    expect(User::where('email', 'xx@unsil.ac.id')->exists())->toBeFalse();
});

it('tidak mengizinkan non-super-admin memberi peran terbatas lewat impor', function () {
    $this->actingAs(pengelolaUser('operator-layanan'));

    PenggunaImporter::test(pemetaanPengguna())
        ->import(['name' => 'X', 'email' => 'admin-baru@unsil.ac.id', 'nip_nim' => '', 'telepon' => '', 'peran' => 'super-admin', 'aktif' => '1'])
        ->assertHasRowFailure();

    expect(User::where('email', 'admin-baru@unsil.ac.id')->exists())->toBeFalse();
});

it('mengimpor berkas CSV lewat aksi impor massal di halaman pengguna', function () {
    $this->actingAs(pengelolaUser());
    $csv = "name,email,nip_nim,telepon,peran,aktif\nSatu Dua,satu.dua@unsil.ac.id,111,0812,pegawai,1\nTiga Empat,tiga.empat@unsil.ac.id,222,0813,kasubag,1\n";
    $berkas = UploadedFile::fake()->createWithContent('pengguna.csv', $csv);

    Livewire::test(ListUsers::class)
        ->callAction(ImportAction::class, data: ['file' => $berkas, 'columnMap' => pemetaanPengguna()])
        ->assertHasNoActionErrors();

    expect(User::whereIn('email', ['satu.dua@unsil.ac.id', 'tiga.empat@unsil.ac.id'])->count())->toBe(2)
        ->and(User::firstWhere('email', 'tiga.empat@unsil.ac.id')->hasRole('kasubag'))->toBeTrue();
});

it('menampilkan tombol impor massal hanya untuk pemegang izin pengguna.kelola', function () {
    $this->actingAs(pengelolaUser());
    Livewire::test(ListUsers::class)->assertActionExists(ImportAction::class);
});
