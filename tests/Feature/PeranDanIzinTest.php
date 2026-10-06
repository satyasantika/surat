<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Rules\SurelDomainUnsil;
use Database\Seeders\PeranDanIzinSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(PeranDanIzinSeeder::class));

function penggunaDenganPeran(string $peran): User
{
    return User::factory()->create()->assignRole($peran);
}

it('membuat seluruh peran PRD §3', function () {
    expect(array_keys(PeranDanIzinSeeder::PERAN))->toBe([
        'super-admin', 'admin-persuratan', 'operator-layanan', 'dekan', 'wakil-dekan',
        'kasubag', 'pembina-ormawa', 'pengurus-ormawa', 'pegawai',
    ]);
});

it('idempoten saat dijalankan ulang', function () {
    $this->seed(PeranDanIzinSeeder::class);

    expect(Role::count())->toBe(9)
        ->and(Permission::count())->toBe(count(PeranDanIzinSeeder::IZIN));
});

it('memberi izin sesuai matriks per peran', function (string $peran, array $boleh, array $tidak) {
    $user = penggunaDenganPeran($peran);

    foreach ($boleh as $izin) {
        expect($user->can($izin))->toBeTrue("{$peran} seharusnya punya {$izin}");
    }
    foreach ($tidak as $izin) {
        expect($user->can($izin))->toBeFalse("{$peran} seharusnya tanpa {$izin}");
    }
})->with([
    'admin-persuratan' => ['admin-persuratan', ['masuk.registrasi', 'nomor.terbitkan', 'permohonan.validasi', 'naskah.templat'], ['naskah.tandatangan', 'disposisi.buat', 'pengguna.kelola', 'lpj.nilai']],
    'operator-layanan' => ['operator-layanan', [], ['masuk.registrasi', 'kabar.kelola', 'ruangan.kelola']],
    'dekan' => ['dekan', ['disposisi.buat', 'naskah.tandatangan', 'permohonan.putuskan', 'lpj.nilai'], ['nomor.terbitkan', 'masuk.registrasi', 'pengguna.kelola']],
    'wakil-dekan' => ['wakil-dekan', ['disposisi.tindaklanjut', 'naskah.paraf', 'naskah.tandatangan', 'lpj.nilai'], ['nomor.terbitkan', 'permohonan.validasi']],
    'kasubag' => ['kasubag', ['naskah.paraf', 'permohonan.putuskan', 'lpj.nilai'], ['naskah.tandatangan', 'nomor.terbitkan']],
    'pembina-ormawa' => ['pembina-ormawa', ['permohonan.setujui-pembina', 'lpj.lihat'], ['lpj.nilai', 'permohonan.ajukan', 'masuk.lihat']],
    'pengurus-ormawa' => ['pengurus-ormawa', ['permohonan.ajukan', 'lpj.isi', 'kabar.usul'], ['kabar.kelola', 'masuk.lihat', 'lpj.nilai']],
    'pegawai' => ['pegawai', ['masuk.lihat', 'disposisi.tindaklanjut'], ['disposisi.buat', 'arsip.lihat', 'naskah.draf']],
]);

it('memberi super-admin semua akses lewat Gate::before', function () {
    $user = penggunaDenganPeran('super-admin');

    expect($user->can('pengguna.kelola'))->toBeTrue()
        ->and($user->can('izin.apa-saja'))->toBeTrue();
});

it('hanya mengizinkan panel bagi akun aktif dengan peran non-Livewire', function (string $peran, bool $aktif, bool $hasil) {
    $user = User::factory()->create(['aktif' => $aktif])->assignRole($peran);

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBe($hasil);
})->with([
    ['dekan', true, true],
    ['operator-layanan', true, true],
    ['pembina-ormawa', true, true],
    ['pengurus-ormawa', true, false],
    ['pegawai', true, false],
    ['dekan', false, false],
]);

it('menolak akun tanpa peran di panel', function () {
    expect(User::factory()->create()->canAccessPanel(Filament::getPanel('admin')))->toBeFalse();
});

it('memvalidasi domain surel unsil', function (string $surel, bool $lolos) {
    $v = Validator::make(['surel' => $surel], ['surel' => [new SurelDomainUnsil]]);

    expect($v->passes())->toBe($lolos);
})->with([
    ['dosen@unsil.ac.id', true],
    ['mhs@student.unsil.ac.id', true],
    ['MHS@Student.Unsil.ac.id', true],
    ['x@gmail.com', false],
    ['x@unsil.ac.id.evil.com', false],
    ['tanpa-at', false],
]);

it('membatasi domain lewat konfigurasi', function () {
    config(['unsil.domain_surel' => ['unsil.ac.id']]);

    expect(Validator::make(['s' => 'a@student.unsil.ac.id'], ['s' => [new SurelDomainUnsil]])->passes())->toBeFalse();
});
