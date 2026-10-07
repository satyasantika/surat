<?php

use App\Actions\Nomor\AmbilNomorBerikutnya;
use App\Actions\Nomor\TandaiNomorBatal;
use App\Filament\Resources\RegisterNomors\Pages\ManageRegisterNomors;
use App\Models\Aktivitas;
use App\Models\NomorTerpakai;
use App\Models\RegisterNomor;
use App\Models\User;
use App\Support\PolaNomor;
use Carbon\CarbonImmutable;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PeranDanIzinSeeder::class, RegisterNomorSeeder::class]);
});

function ambil(string $kode, ?string $tanggal = null, array $konteks = ['klasifikasi' => 'KM.03.02'], ?User $pemilik = null): NomorTerpakai
{
    return app(AmbilNomorBerikutnya::class)->jalankan(
        RegisterNomor::firstWhere('kode', $kode),
        $pemilik ?? User::factory()->create(),
        $konteks,
        $tanggal ? CarbonImmutable::parse($tanggal) : null,
    );
}

it('menyemai register standar', function () {
    expect(RegisterNomor::pluck('kode')->sort()->values()->all())
        ->toBe(['agenda-masuk', 'naskah-dekan', 'permohonan', 'sk-dekan', 'surat-tugas']);
});

it('memberi nomor berurutan per register', function () {
    expect(ambil('agenda-masuk', '2026-03-10')->nomor_lengkap)->toBe('0001/AGD/2026')
        ->and(ambil('agenda-masuk', '2026-03-11')->nomor_lengkap)->toBe('0002/AGD/2026')
        ->and(ambil('permohonan', '2026-03-11')->nomor_lengkap)->toBe('PMH-2026-0001');
});

it('mereset urutan pada 1 Januari', function () {
    expect(ambil('agenda-masuk', '2026-12-31')->urut)->toBe(1)
        ->and(ambil('agenda-masuk', '2026-12-31')->urut)->toBe(2)
        ->and(ambil('agenda-masuk', '2027-01-01')->nomor_lengkap)->toBe('0001/AGD/2027')
        ->and(ambil('agenda-masuk', '2027-01-02')->urut)->toBe(2);
});

it('melanjutkan urutan lintas tahun bila register tidak direset', function () {
    RegisterNomor::firstWhere('kode', 'agenda-masuk')->update(['reset' => 'tidak']);

    expect(ambil('agenda-masuk', '2026-12-31')->urut)->toBe(1)
        ->and(ambil('agenda-masuk', '2027-01-01')->nomor_lengkap)->toBe('0002/AGD/2027');
});

it('tidak memakai ulang nomor yang dibatalkan', function () {
    $satu = ambil('naskah-dekan', '2026-05-01');
    $dua = ambil('naskah-dekan', '2026-05-02');

    app(TandaiNomorBatal::class)->jalankan($dua, 'salah ketik');
    $tiga = ambil('naskah-dekan', '2026-05-03');

    expect($satu->urut)->toBe(1)
        ->and($dua->fresh()->dibatalkan)->toBeTrue()
        ->and($tiga->urut)->toBe(3)
        ->and(NomorTerpakai::where('nomor_lengkap', $dua->nomor_lengkap)->count())->toBe(1);
});

it('mencatat batal di log aktivitas dan idempoten', function () {
    $n = ambil('naskah-dekan', '2026-05-01');

    app(TandaiNomorBatal::class)->jalankan($n, 'alasan');
    app(TandaiNomorBatal::class)->jalankan($n, 'alasan lagi');

    expect(Aktivitas::where('log_name', 'nomor')->where('event', 'batal')->count())->toBe(1);
});

it('menjaga nomor tidak dapat diubah atau dihapus', function () {
    $n = ambil('naskah-dekan', '2026-05-01');

    expect(fn () => $n->fresh()->update(['nomor_lengkap' => 'palsu']))->toThrow(LogicException::class)
        ->and(fn () => $n->fresh()->update(['urut' => 99]))->toThrow(LogicException::class)
        ->and(fn () => $n->fresh()->delete())->toThrow(LogicException::class);

    $n->fresh()->update(['dibatalkan' => true]);
    expect(fn () => $n->fresh()->update(['dibatalkan' => false]))->toThrow(LogicException::class);
});

it('menyimpan pemilik polimorfik dan pelaku dari autentikasi', function () {
    $pelaku = User::factory()->create();
    $pemilik = User::factory()->create();
    $this->actingAs($pelaku);

    $n = ambil('agenda-masuk', null, [], $pemilik);

    expect($n->pemilik->is($pemilik))->toBeTrue()
        ->and($n->dibuat_oleh)->toBe($pelaku->id);
});

it('menolak register tidak aktif dan konteks yang kurang', function () {
    expect(fn () => ambil('naskah-dekan', '2026-05-01', []))->toThrow(InvalidArgumentException::class);

    RegisterNomor::firstWhere('kode', 'agenda-masuk')->update(['aktif' => false]);
    expect(fn () => ambil('agenda-masuk'))->toThrow(RuntimeException::class);
});

it('tidak menghabiskan urutan saat render gagal', function () {
    try {
        ambil('naskah-dekan', '2026-05-01', []);
    } catch (InvalidArgumentException) {
    }

    expect(ambil('naskah-dekan', '2026-05-01')->urut)->toBe(1);
});

it('me-render semua token pola', function () {
    $tgl = CarbonImmutable::parse('2026-09-15');

    expect(PolaNomor::render('{urut}/{kode_unit}/{klasifikasi}/{tahun}', 7, $tgl, ['klasifikasi' => 'KM.03.02']))->toBe('7/UN58.10/KM.03.02/2026')
        ->and(PolaNomor::render('{urut:3}', 7, $tgl))->toBe('007')
        ->and(PolaNomor::render('{urut:5}/{bulan_romawi}', 42, $tgl))->toBe('00042/IX')
        ->and(PolaNomor::render('{urut}/{kode_jenis}/{tahun}', 1, $tgl, ['kode_jenis' => 'ST']))->toBe('1/ST/2026')
        ->and(PolaNomor::render('{urut}/{kode_unit}', 1, $tgl, ['kode_unit' => 'ZZ']))->toBe('1/ZZ');
});

it('me-render bulan romawi untuk seluruh bulan', function () {
    $hasil = collect(range(1, 12))->map(fn ($b) => PolaNomor::render('{urut}{bulan_romawi}', 1, CarbonImmutable::create(2026, $b, 1)))->all();

    expect($hasil)->toBe(['1I', '1II', '1III', '1IV', '1V', '1VI', '1VII', '1VIII', '1IX', '1X', '1XI', '1XII']);
});

it('memvalidasi pola nomor', function (string $pola, bool $sah) {
    expect(PolaNomor::valid($pola))->toBe($sah);
})->with([
    ['{urut}/{tahun}', true],
    ['{urut:4}/AGD/{tahun}', true],
    ['tanpa urut/{tahun}', false],
    ['{urut}/{urut}', false],
    ['{urut}/{tidak_dikenal}', false],
    ['{urut}/{tahun', false],
    ['{urut:123}', false],
]);

it('menolak dua register dengan pola yang sama lewat panel', function () {
    Filament::setCurrentPanel('admin');
    $u = User::factory()->create()->assignRole('operator-layanan');
    $u->givePermissionTo('master.kelola');
    $u->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
    $this->actingAs($u);

    Livewire::test(ManageRegisterNomors::class)
        ->callAction(TestAction::make(CreateAction::class), ['kode' => 'kembar', 'nama' => 'Kembar', 'pola' => '{urut:4}/AGD/{tahun}', 'reset' => 'tahunan'])
        ->assertHasActionErrors(['pola']);
});

it('mengelola register lewat panel dengan validasi pola', function () {
    Filament::setCurrentPanel('admin');
    $u = User::factory()->create()->assignRole('operator-layanan');
    $u->givePermissionTo('master.kelola');
    $u->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
    $this->actingAs($u);

    Livewire::test(ManageRegisterNomors::class)
        ->callAction(TestAction::make(CreateAction::class), ['kode' => 'uji', 'nama' => 'Uji', 'pola' => 'tanpa-urut', 'reset' => 'tahunan'])
        ->assertHasActionErrors(['pola']);

    Livewire::test(ManageRegisterNomors::class)
        ->callAction(TestAction::make(CreateAction::class), ['kode' => 'uji', 'nama' => 'Uji', 'pola' => '{urut}/UJI/{tahun}', 'reset' => 'tahunan'])
        ->assertHasNoActionErrors();

    expect(RegisterNomor::where('kode', 'uji')->exists())->toBeTrue();

    Livewire::test(ManageRegisterNomors::class)->assertCanSeeTableRecords(RegisterNomor::all())->assertSee('Tiap tahun kalender');
});
