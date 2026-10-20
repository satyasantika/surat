<?php

use App\Enums\JenisPengaju;
use App\Filament\Resources\UnitKerjas\Pages\ManageUnitKerjas;
use App\Models\JenisPermohonan;
use App\Models\Ormawa;
use App\Models\Permohonan;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\JenisPermohonanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\StrukturFkipSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PeranDanIzinSeeder::class, StrukturFkipSeeder::class, JenisPermohonanSeeder::class]);
    $this->prodi = UnitKerja::create(['kode' => 'PMAT', 'nama' => 'Pendidikan Matematika', 'tipe' => 'prodi']);
    $this->lab = UnitKerja::create(['kode' => 'LAB-KOM', 'nama' => 'Laboratorium Komputer', 'tipe' => 'laboratorium']);
});

function akunPeran(string $peran, array $ubah = []): User
{
    return User::factory()->create($ubah)->assignRole($peran);
}

function permohonanUnit(UnitKerja $unit, JenisPengaju $jenis, ?User $pengaju = null): Permohonan
{
    $p = (new Permohonan)->forceFill([
        'nomor' => 'PMH-2026-'.random_int(1000, 9999), 'jenis_pengaju' => $jenis->value, 'unit_kerja_id' => $unit->id,
        'jenis_permohonan_id' => JenisPermohonan::firstWhere('kode', 'kegiatan')->id, 'diajukan_oleh' => ($pengaju ?? User::factory()->create())->id,
        'nama_kegiatan' => 'Workshop', 'perihal' => 'Izin', 'tanggal_mulai' => '2026-05-10', 'tanggal_selesai' => '2026-05-11', 'deskripsi' => 'D',
        'penanggung_jawab' => ['ketua' => ['nama' => 'B']], 'status' => 'diajukan', 'diajukan_pada' => now(),
    ]);
    $p->saveQuietly();

    return $p->fresh();
}

it('menugaskan pengaju dan penyetuju per unit dan membacanya dari sisi akun', function () {
    $pengaju = akunPeran('pengaju-unit');
    $kaprodi = akunPeran('kaprodi');
    $this->prodi->pengaju()->attach($pengaju);
    $this->prodi->penyetuju()->attach($kaprodi);

    expect($this->prodi->pengaju->pluck('id')->all())->toBe([$pengaju->id])->and($this->prodi->penyetuju->pluck('id')->all())->toBe([$kaprodi->id])
        ->and($pengaju->unitDiajukan->pluck('id')->all())->toBe([$this->prodi->id])->and($pengaju->unitDisetujui)->toBeEmpty()
        ->and($kaprodi->unitDisetujui->pluck('id')->all())->toBe([$this->prodi->id])->and($kaprodi->unitDiajukan)->toBeEmpty();
});

it('menyinkronkan satu peran tidak menyentuh peran lain pada unit yang sama', function () {
    $a = akunPeran('pengaju-unit');
    $k = akunPeran('kaprodi');
    $this->prodi->pengaju()->attach($a);
    $this->prodi->penyetuju()->attach($k);

    $this->prodi->pengaju()->sync([]);

    expect($this->prodi->pengaju()->count())->toBe(0)->and($this->prodi->penyetuju()->count())->toBe(1);
});

it('unit nonaktif tidak lagi menjadi unit pengaju', function () {
    $pengaju = akunPeran('pengaju-unit');
    $this->prodi->pengaju()->attach($pengaju);
    $this->prodi->update(['aktif' => false]);

    expect($pengaju->unitDiajukan()->count())->toBe(0)->and(Gate::forUser($pengaju)->allows('ajukanPermohonan', $this->prodi))->toBeFalse();
});

it('hanya pengaju yang ditugaskan pada unit itu yang boleh mengajukan atas nama unit', function () {
    $pengaju = akunPeran('pengaju-unit');
    $lain = akunPeran('pengaju-unit');
    $tanpaIzin = akunPeran('kaprodi');
    $this->prodi->pengaju()->attach($pengaju);
    $this->prodi->pengaju()->attach($tanpaIzin);

    expect(Gate::forUser($pengaju)->allows('ajukanPermohonan', $this->prodi))->toBeTrue()
        ->and(Gate::forUser($pengaju)->allows('ajukanPermohonan', $this->lab))->toBeFalse()
        ->and(Gate::forUser($lain)->allows('ajukanPermohonan', $this->prodi))->toBeFalse()
        ->and(Gate::forUser($tanpaIzin)->allows('ajukanPermohonan', $this->prodi))->toBeFalse();
});

it('pengaju dan kepala unit melihat permohonan unitnya, bukan unit lain', function () {
    $pengaju = akunPeran('pengaju-unit');
    $kaprodi = akunPeran('kaprodi');
    $kalab = akunPeran('kalab');
    $this->prodi->pengaju()->attach($pengaju);
    $this->prodi->penyetuju()->attach($kaprodi);
    $this->lab->penyetuju()->attach($kalab);
    $p = permohonanUnit($this->prodi, JenisPengaju::Prodi, $pengaju);

    expect($pengaju->can('view', $p))->toBeTrue()->and($kaprodi->can('view', $p))->toBeTrue()
        ->and($kalab->can('view', $p))->toBeFalse()->and(akunPeran('pengaju-unit')->can('view', $p))->toBeFalse()
        ->and($kaprodi->can('lihatDataPribadi', $p))->toBeTrue()->and($kalab->can('lihatDataPribadi', $p))->toBeFalse();
});

it('kebijakan permohonan tidak error untuk permohonan tanpa ormawa dan menolak pengurus ormawa', function () {
    $pengurus = akunPeran('pengurus-ormawa');
    $p = permohonanUnit($this->prodi, JenisPengaju::Prodi);

    expect($pengurus->can('view', $p))->toBeFalse()->and($pengurus->can('setujuiPembina', $p))->toBeFalse()
        ->and($pengurus->can('revisi', $p))->toBeFalse()->and($pengurus->can('batalkan', $p))->toBeFalse()
        ->and(Ormawa::count())->toBe(0);
});

it('pengaju unit boleh membatalkan permohonan miliknya sendiri, bukan milik rekan', function () {
    $pengaju = akunPeran('pengaju-unit');
    $rekan = akunPeran('pengaju-unit');
    $this->prodi->pengaju()->attach([$pengaju->id, $rekan->id]);
    $p = permohonanUnit($this->prodi, JenisPengaju::Prodi, $pengaju);

    expect($pengaju->can('batalkan', $p))->toBeTrue()->and($rekan->can('batalkan', $p))->toBeFalse();
});

it('peran unit memakai halaman Livewire, bukan panel admin', function (string $peran) {
    $akun = akunPeran($peran);
    $akun->forceFill(['aktif' => true])->save();

    expect($akun->canAccessPanel(Filament\Facades\Filament::getPanel('admin')))->toBeFalse();
})->with(['pengaju-unit', 'kaprodi', 'kalab', 'mahasiswa']);

it('admin menugaskan pengaju dan kepala unit lewat formulir unit kerja', function () {
    Filament\Facades\Filament::setCurrentPanel('admin');
    $admin = akunPeran('super-admin', ['aktif' => true]);
    $pengaju = akunPeran('pengaju-unit');
    $kaprodi = akunPeran('kaprodi');
    $this->actingAs($admin);

    Livewire\Livewire::test(ManageUnitKerjas::class)
        ->callAction(TestAction::make('edit')->table($this->prodi), ['kode' => 'PMAT', 'nama' => 'Pendidikan Matematika', 'tipe' => 'prodi', 'aktif' => true, 'pengaju' => [$pengaju->id], 'penyetuju' => [$kaprodi->id]])
        ->assertHasNoFormErrors();

    expect($this->prodi->pengaju()->pluck('users.id')->all())->toBe([$pengaju->id])->and($this->prodi->penyetuju()->pluck('users.id')->all())->toBe([$kaprodi->id]);
});
