<?php

use App\Filament\Resources\JenisNaskahs\Pages\ManageJenisNaskahs;
use App\Models\JenisNaskah;
use App\Models\RegisterNomor;
use App\Models\User;
use Database\Seeders\JenisNaskahSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Database\Seeders\StrukturFkipSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PeranDanIzinSeeder::class, StrukturFkipSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class]);
    Filament::setCurrentPanel('admin');
});

function pengelolaJenis(string $peran = 'operator-layanan', bool $izin = true): User
{
    $u = User::factory()->create()->assignRole($peran);
    $izin && $u->givePermissionTo('master.kelola');
    $u->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

    return $u;
}

it('menyemai sepuluh jenis naskah dengan templat yang ada', function () {
    $kode = JenisNaskah::pluck('kode')->sort()->values()->all();

    expect($kode)->toBe(['berita-acara', 'nota-dinas', 'pengantar-proposal', 'sk', 'surat-dinas', 'surat-edaran', 'surat-izin-kegiatan', 'surat-keterangan', 'surat-tugas', 'undangan']);

    foreach (JenisNaskah::all() as $jenis) {
        expect(view()->exists($jenis->templat_blade))->toBeTrue("{$jenis->kode}: templat hilang")
            ->and($jenis->variabel)->toBeArray()->not->toBeEmpty()
            ->and(array_diff($jenis->mode_tanda_tangan_diizinkan, array_keys(JenisNaskah::MODE_TANDA_TANGAN)))->toBeEmpty();
    }
});

it('memetakan register dan penanda tangan bawaan', function () {
    $tugas = JenisNaskah::firstWhere('kode', 'surat-tugas');
    $sk = JenisNaskah::firstWhere('kode', 'sk');

    expect($tugas->register->kode)->toBe('surat-tugas')
        ->and($sk->register->kode)->toBe('sk-dekan')
        ->and($sk->jabatanPenandaTanganBawaan->kode)->toBe('dekan')
        ->and(JenisNaskah::firstWhere('kode', 'nota-dinas')->jabatan_penanda_tangan_bawaan_id)->toBeNull();
});

it('idempoten saat disemai ulang', function () {
    $this->seed(JenisNaskahSeeder::class);

    expect(JenisNaskah::count())->toBe(10);
});

it('hanya menerima templat yang ada di resources/views/naskah', function (?string $nama, bool $sah) {
    expect(JenisNaskah::templatAda($nama))->toBe($sah);
})->with([
    ['naskah.surat-dinas', true],
    ['naskah.tidak-ada', false],
    ['welcome', false],
    ['naskah.', false],
    ['naskah..welcome', false],
    ['naskah.surat-dinas.x', false],
    ['../naskah/surat-dinas', false],
    ['Naskah.Surat-Dinas', false],
    [null, false],
]);

it('menolak menyimpan model dengan templat tidak ada', function () {
    $jenis = JenisNaskah::firstWhere('kode', 'sk');

    expect(fn () => $jenis->update(['templat_blade' => 'naskah.hantu']))->toThrow(ValidationException::class);
});

it('membatasi menu jenis naskah ke master.kelola', function () {
    $this->actingAs(pengelolaJenis())->get('/admin/jenis-naskah')->assertOk();
    $this->actingAs(pengelolaJenis('admin-persuratan', false))->get('/admin/jenis-naskah')->assertForbidden();
});

it('menampilkan daftar jenis naskah', function () {
    $this->actingAs(pengelolaJenis());

    Livewire::test(ManageJenisNaskahs::class)
        ->assertCanSeeTableRecords(JenisNaskah::all())
        ->assertSee('Surat Izin Kegiatan')
        ->assertSee('Korespondensi');
});

it('membuat jenis naskah baru lewat panel dengan variabel', function () {
    $this->actingAs(pengelolaJenis());
    $register = RegisterNomor::firstWhere('kode', 'naskah-dekan');

    Livewire::test(ManageJenisNaskahs::class)
        ->callAction(TestAction::make(CreateAction::class), [
            'kode' => 'pengumuman', 'nama' => 'Pengumuman', 'kelompok' => 'khusus', 'register_nomor_id' => $register->id,
            'templat_blade' => 'naskah.surat-dinas', 'versi_templat' => 1,
            'mode_tanda_tangan_diizinkan' => ['basah'],
            'variabel' => [
                ['kunci' => 'judul', 'label' => 'Judul', 'tipe' => 'text', 'wajib' => true],
                ['kunci' => 'status', 'label' => 'Status', 'tipe' => 'select', 'wajib' => false, 'opsi' => ['a' => 'A', 'b' => 'B']],
            ],
        ])
        ->assertHasNoActionErrors();

    $baru = JenisNaskah::firstWhere('kode', 'pengumuman');
    expect($baru->variabel)->toHaveCount(2)
        ->and($baru->variabel[1]['opsi'])->toBe(['a' => 'A', 'b' => 'B'])
        ->and($baru->mode_tanda_tangan_diizinkan)->toBe(['basah']);
});

it('menolak templat tidak ada, kunci variabel ganda, dan mode kosong lewat panel', function (array $ubah, array $galat) {
    $this->actingAs(pengelolaJenis());
    $register = RegisterNomor::firstWhere('kode', 'naskah-dekan');

    Livewire::test(ManageJenisNaskahs::class)
        ->callAction(TestAction::make(CreateAction::class), $ubah + [
            'kode' => 'uji', 'nama' => 'Uji', 'kelompok' => 'lainnya', 'register_nomor_id' => $register->id,
            'templat_blade' => 'naskah.surat-dinas', 'versi_templat' => 1, 'mode_tanda_tangan_diizinkan' => ['basah'],
            'variabel' => [['kunci' => 'a', 'label' => 'A', 'tipe' => 'text', 'wajib' => true]],
        ])
        ->assertHasActionErrors($galat);

    expect(JenisNaskah::where('kode', 'uji')->exists())->toBeFalse();
})->with([
    'templat tidak ada' => [['templat_blade' => 'naskah.hantu'], ['templat_blade']],
    'templat di luar naskah' => [['templat_blade' => 'welcome'], ['templat_blade']],
    'mode kosong' => [['mode_tanda_tangan_diizinkan' => []], ['mode_tanda_tangan_diizinkan']],
    'kode duplikat' => [['kode' => 'sk'], ['kode']],
]);

it('mengubah versi templat lewat panel', function () {
    $this->actingAs(pengelolaJenis());
    $sk = JenisNaskah::firstWhere('kode', 'sk');

    Livewire::test(ManageJenisNaskahs::class)
        ->callAction(TestAction::make(EditAction::class)->table($sk), ['versi_templat' => 2])
        ->assertHasNoActionErrors();

    expect($sk->fresh()->versi_templat)->toBe(2);
});
