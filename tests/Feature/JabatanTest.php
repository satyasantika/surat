<?php

use App\Filament\Resources\Jabatans\Pages\ManageJabatans;
use App\Filament\Resources\Jabatans\RelationManagers\PemangkuRelationManager;
use App\Models\Jabatan;
use App\Models\PemangkuJabatan;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\StrukturFkipSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PeranDanIzinSeeder::class, StrukturFkipSeeder::class]);
    Filament::setCurrentPanel('admin');
});

function jabatan(string $kode = 'dekan'): Jabatan
{
    return Jabatan::firstWhere('kode', $kode);
}

function menjabat(Jabatan $j, string $mulai, ?string $selesai = null, bool $plt = false, ?User $user = null): PemangkuJabatan
{
    return PemangkuJabatan::create([
        'jabatan_id' => $j->id, 'user_id' => ($user ?? User::factory()->create())->id,
        'mulai' => $mulai, 'selesai' => $selesai, 'plt' => $plt,
    ]);
}

function pengelolaMaster(): User
{
    $u = User::factory()->create()->assignRole('operator-layanan');
    $u->givePermissionTo('master.kelola');
    $u->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

    return $u;
}

it('menyemai struktur FKIP secara idempoten', function () {
    $this->seed(StrukturFkipSeeder::class);

    expect(UnitKerja::where('kode', 'UN58.10')->count())->toBe(1)
        ->and(Jabatan::pluck('kode')->sort()->values()->all())->toBe(['dekan', 'kasubag-umum', 'wd-akademik', 'wd-kemahasiswaan', 'wd-umum-keuangan'])
        ->and(jabatan('wd-kemahasiswaan')->bidang)->toBe('kemahasiswaan');
});

it('memilih pemangku yang benar saat pergantian pejabat', function () {
    $dekan = jabatan();
    $lama = menjabat($dekan, '2022-01-01', '2026-06-30');
    $baru = menjabat($dekan, '2026-07-01');

    expect($dekan->pemangkuPada(now()->setDate(2026, 6, 30))->is($lama))->toBeTrue()
        ->and($dekan->pemangkuPada(now()->setDate(2026, 7, 1))->is($baru))->toBeTrue()
        ->and($dekan->pemangkuPada(now()->setDate(2021, 12, 31)))->toBeNull()
        ->and($dekan->pemangkuPada(now()->setDate(2030, 1, 1))->is($baru))->toBeTrue();
});

it('mendahulukan Plt yang berlaku pada rentangnya', function () {
    $dekan = jabatan();
    $definitif = menjabat($dekan, '2026-01-01');
    $plt = menjabat($dekan, '2026-03-01', '2026-03-31', plt: true);

    expect($dekan->pemangkuPada(now()->setDate(2026, 3, 15))->is($plt))->toBeTrue()
        ->and($dekan->pemangkuPada(now()->setDate(2026, 4, 1))->is($definitif))->toBeTrue();
});

it('menolak dua pemangku definitif yang tumpang tindih', function (string $mulai, ?string $selesai) {
    $dekan = jabatan();
    menjabat($dekan, '2026-01-01', '2026-12-31');

    expect(fn () => menjabat($dekan, $mulai, $selesai))->toThrow(ValidationException::class);
})->with([
    'di tengah' => ['2026-06-01', '2026-06-30'],
    'menyambung ke dalam' => ['2026-12-31', '2027-06-30'],
    'mulai sebelum dan selesai di dalam' => ['2025-06-01', '2026-01-01'],
    'tanpa batas akhir' => ['2026-06-01', null],
]);

it('mengizinkan pemangku berurutan dan jabatan berbeda', function () {
    menjabat(jabatan(), '2026-01-01', '2026-06-30');
    menjabat(jabatan(), '2026-07-01');
    menjabat(jabatan('wd-akademik'), '2026-01-01');

    expect(PemangkuJabatan::count())->toBe(3);
});

it('menolak tanggal selesai sebelum mulai', function () {
    expect(fn () => menjabat(jabatan(), '2026-05-01', '2026-04-01'))->toThrow(ValidationException::class);
});

it('mengizinkan memperbarui pemangku tanpa menabrak dirinya sendiri', function () {
    $p = menjabat(jabatan(), '2026-01-01');
    $p->update(['nomor_sk' => 'SK/1/2026']);

    expect($p->fresh()->nomor_sk)->toBe('SK/1/2026');
});

it('menyediakan jabatanAktif pada pengguna', function () {
    $user = User::factory()->create();
    menjabat(jabatan(), '2026-01-01', '2026-12-31', user: $user);
    menjabat(jabatan('wd-akademik'), '2020-01-01', '2021-01-01', user: $user);

    expect($user->jabatanAktif(now()->setDate(2026, 6, 1))->pluck('kode')->all())->toBe(['dekan'])
        ->and($user->jabatanAktif(now()->setDate(2020, 6, 1))->pluck('kode')->all())->toBe(['wd-akademik'])
        ->and($user->jabatanAktif(now()->setDate(2019, 1, 1)))->toBeEmpty();
});

it('membatasi menu master ke pemegang izin master.kelola', function (string $url) {
    $this->actingAs(pengelolaMaster())->get($url)->assertOk();

    $biasa = User::factory()->create()->assignRole('admin-persuratan');
    $biasa->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
    $this->actingAs($biasa)->get($url)->assertForbidden();
})->with(['/admin/unit-kerja', '/admin/jabatan']);

it('menampilkan kesalahan saat pemangku definitif tumpang tindih lewat relation manager', function () {
    $dekan = jabatan();
    menjabat($dekan, '2026-01-01');
    $this->actingAs(pengelolaMaster());

    Livewire::test(PemangkuRelationManager::class, ['ownerRecord' => $dekan, 'pageClass' => ManageJabatans::class])
        ->callAction(TestAction::make(CreateAction::class)->table(), [
            'user_id' => User::factory()->create()->id, 'mulai' => '2026-06-01', 'plt' => false,
        ])
        ->assertHasActionErrors();

    expect(PemangkuJabatan::count())->toBe(1);
});

it('membuat jabatan lewat resource', function () {
    $this->actingAs(pengelolaMaster());
    $unit = UnitKerja::first();

    Livewire::test(ManageJabatans::class)
        ->callAction(CreateAction::class, [
            'kode' => 'kaprodi-pmat', 'nama' => 'Ketua Program Studi Pendidikan Matematika',
            'unit_kerja_id' => $unit->id, 'dapat_menandatangani' => false, 'urutan' => 9,
        ])
        ->assertHasNoActionErrors();

    expect(Jabatan::where('kode', 'kaprodi-pmat')->exists())->toBeTrue();
});
