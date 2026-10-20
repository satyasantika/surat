<?php

use App\Enums\JenisPengaju;
use App\Enums\JenisPengirim;
use App\Enums\TipeUnitKerja;
use App\Models\JenisPermohonan;
use App\Models\Ormawa;
use App\Models\Permohonan;
use App\Models\UnitKerja;
use App\Models\User;
use Database\Seeders\JenisPermohonanSeeder;
use Database\Seeders\StrukturFkipSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([StrukturFkipSeeder::class, JenisPermohonanSeeder::class]);
});

function dataPengajuUji(array $ubah = []): array
{
    return array_merge([
        'nomor' => 'PMH-2026-'.random_int(1000, 9999), 'jenis_permohonan_id' => JenisPermohonan::firstWhere('kode', 'kegiatan')->id,
        'diajukan_oleh' => User::factory()->create()->id, 'nama_kegiatan' => 'Seminar', 'perihal' => 'Izin', 'tanggal_mulai' => '2026-05-10',
        'tanggal_selesai' => '2026-05-11', 'deskripsi' => 'D', 'penanggung_jawab' => ['ketua' => ['nama' => 'B']], 'status' => 'diajukan', 'diajukan_pada' => now(),
    ], $ubah);
}

function simpanPengajuUji(array $ubah = []): Permohonan
{
    $p = (new Permohonan)->forceFill(dataPengajuUji($ubah));
    $p->saveQuietly();

    return $p->fresh();
}

it('menambah kolom pengaju generik dan tipe unit kerja', function () {
    expect(Schema::hasColumns('permohonan', ['jenis_pengaju', 'unit_kerja_id', 'diajukan_atas_nama']))->toBeTrue()
        ->and(Schema::hasColumn('unit_kerja', 'tipe'))->toBeTrue();
});

it('menandai unit fakultas bawaan dan memberi tipe lainnya bagi unit lain', function () {
    expect(UnitKerja::firstWhere('kode', 'UN58.10')->tipe)->toBe(TipeUnitKerja::Fakultas)
        ->and(UnitKerja::create(['kode' => 'X1', 'nama' => 'Unit X'])->fresh()->tipe)->toBe(TipeUnitKerja::Lainnya)
        ->and(UnitKerja::create(['kode' => 'PMAT', 'nama' => 'Pendidikan Matematika', 'tipe' => 'prodi'])->fresh()->tipe)->toBe(TipeUnitKerja::Prodi);
});

it('pengaju ormawa tetap bawaan dan perilakunya tidak berubah', function () {
    $o = Ormawa::create(['nama' => 'HIMA Uji', 'tingkat' => 'prodi']);
    $p = simpanPengajuUji(['ormawa_id' => $o->id]);

    expect($p->jenis_pengaju)->toBe(JenisPengaju::Ormawa)->and($p->unit_kerja_id)->toBeNull()->and($p->ormawa->is($o))->toBeTrue()
        ->and($p->namaPengaju())->toBe('HIMA Uji');
});

it('pengaju prodi dan laboratorium memakai unit kerja tanpa ormawa', function (string $jenis, string $tipe) {
    $unit = UnitKerja::create(['kode' => 'U-'.$jenis, 'nama' => "Unit {$jenis}", 'tipe' => $tipe]);
    $p = simpanPengajuUji(['jenis_pengaju' => $jenis, 'unit_kerja_id' => $unit->id]);

    expect($p->ormawa_id)->toBeNull()->and($p->jenis_pengaju->berunit())->toBeTrue()->and($p->unitKerja->is($unit))->toBeTrue()
        ->and($p->namaPengaju())->toBe("Unit {$jenis}");
})->with([['prodi', 'prodi'], ['laboratorium', 'laboratorium']]);

it('basis data menolak pengaju ormawa tanpa ormawa dan pengaju unit tanpa unit', function () {
    expect(fn () => simpanPengajuUji())->toThrow(QueryException::class)
        ->and(fn () => simpanPengajuUji(['jenis_pengaju' => 'prodi']))->toThrow(QueryException::class)
        ->and(fn () => simpanPengajuUji(['jenis_pengaju' => 'laboratorium']))->toThrow(QueryException::class);
});

it('nama mahasiswa pengaju terenkripsi dan tersembunyi dari serialisasi', function () {
    $o = Ormawa::create(['nama' => 'HIMA Uji', 'tingkat' => 'prodi']);
    $p = simpanPengajuUji(['ormawa_id' => $o->id]);
    $p->forceFill(['diajukan_atas_nama' => 'Mahasiswa Rahasia'])->saveQuietly();

    expect(DB::table('permohonan')->where('id', $p->id)->value('diajukan_atas_nama'))->not->toContain('Mahasiswa Rahasia')
        ->and($p->fresh()->diajukan_atas_nama)->toBe('Mahasiswa Rahasia')->and($p->fresh()->toArray())->not->toHaveKey('diajukan_atas_nama');
});

it('jenis pengirim surat masuk mencakup laboratorium dan mewajibkan unit', function () {
    expect(JenisPengirim::Laboratorium->berunit())->toBeTrue()->and(JenisPengirim::Prodi->berunit())->toBeTrue()
        ->and(JenisPengirim::Ormawa->berunit())->toBeFalse()->and(JenisPengirim::Eksternal->berunit())->toBeFalse()
        ->and(JenisPengirim::pilihan())->toHaveKey('laboratorium');
});
