<?php

use App\Actions\Permohonan\AjukanPermohonan;
use App\Actions\Permohonan\AjukanUlangPermohonan;
use App\Actions\Permohonan\BatalkanPermohonan;
use App\Actions\Permohonan\KembalikanPermohonan;
use App\Actions\Permohonan\SetujuiUnit;
use App\Actions\Permohonan\TolakPermohonan;
use App\Actions\Permohonan\TransisiPermohonan;
use App\Actions\Permohonan\ValidasiPermohonan;
use App\Enums\JenisPengaju;
use App\Enums\StatusPermohonan;
use App\Exceptions\TransisiTidakSah;
use App\Models\JenisPermohonan;
use App\Models\Lpj;
use App\Models\Permohonan;
use App\Models\UnitKerja;
use App\Models\User;
use App\Notifications\NotifikasiTahap;
use App\Support\AlurPermohonan;
use App\Support\Pelacakan\Linimasa;
use Carbon\CarbonImmutable;
use Database\Seeders\JenisNaskahSeeder;
use Database\Seeders\JenisPermohonanSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Database\Seeders\StrukturFkipSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    $this->seed([PeranDanIzinSeeder::class, StrukturFkipSeeder::class, PengaturanSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class, JenisPermohonanSeeder::class]);
    CarbonImmutable::setTestNow('2026-06-01 09:00:00');

    $this->prodi = UnitKerja::create(['kode' => 'PMAT', 'nama' => 'Pendidikan Matematika', 'tipe' => 'prodi']);
    $this->lab = UnitKerja::create(['kode' => 'LAB-KOM', 'nama' => 'Laboratorium Komputer', 'tipe' => 'laboratorium']);
    $this->pengaju = User::factory()->create()->assignRole('pengaju-unit');
    $this->kaprodi = User::factory()->create()->assignRole('kaprodi');
    $this->kalab = User::factory()->create()->assignRole('kalab');
    $this->prodi->pengaju()->attach($this->pengaju);
    $this->prodi->penyetuju()->attach($this->kaprodi);
    $this->lab->pengaju()->attach($this->pengaju);
    $this->lab->penyetuju()->attach($this->kalab);
    $this->admin = User::factory()->create()->assignRole('admin-persuratan');
    RateLimiter::clear('ajukan-permohonan:'.$this->pengaju->id);
});

afterEach(fn () => CarbonImmutable::setTestNow());

function dataUnit(array $ubah = []): array
{
    return $ubah + [
        'nama_kegiatan' => 'Workshop Dosen', 'perihal' => 'Izin workshop', 'tanggal_mulai' => '2026-06-20', 'tanggal_selesai' => '2026-06-21',
        'deskripsi' => 'Workshop internal.', 'penanggung_jawab' => ['ketua' => ['nama' => 'Dr. Rani']],
        'berkas' => ['surat_permohonan' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view', 'proposal' => 'https://drive.google.com/file/d/1ZyXwVuTsRqPoNmLkJi/view'],
    ];
}

function ajukanUnit(User $u, UnitKerja $unit, array $ubah = []): Permohonan
{
    return app(AjukanPermohonan::class)->jalankanUnit($u, $unit, JenisPermohonan::firstWhere('kode', 'kegiatan'), dataUnit($ubah));
}

function judulUntuk(User $u): array
{
    return Notification::sent($u, NotifikasiTahap::class)->map(fn ($n) => $n->judul)->values()->all();
}

describe('pengajuan', function () {
    it('prodi mengajukan: berjenis prodi, tanpa ormawa, menunggu persetujuan kepala unit', function () {
        $p = ajukanUnit($this->pengaju, $this->prodi);

        expect($p->jenis_pengaju)->toBe(JenisPengaju::Prodi)->and($p->ormawa_id)->toBeNull()->and($p->unit_kerja_id)->toBe($this->prodi->id)
            ->and($p->status)->toBe(StatusPermohonan::PersetujuanUnit)->and($p->nomor)->toStartWith('PMH-2026-')
            ->and($p->riwayat->map(fn ($r) => ($r->dari_status ?? '∅').'>'.$r->ke_status)->all())->toBe(['∅>diajukan', 'diajukan>persetujuan_unit'])
            ->and($p->namaPengaju())->toBe('Pendidikan Matematika');
    });

    it('laboratorium mengajukan dan bertipe laboratorium', function () {
        $p = ajukanUnit($this->pengaju, $this->lab);

        expect($p->jenis_pengaju)->toBe(JenisPengaju::Laboratorium)->and($p->sebutanPimpinanPengaju())->toBe('Kepala Laboratorium Komputer');
    });

    it('menolak pengaju yang tidak ditugaskan pada unit itu', function () {
        $luar = User::factory()->create()->assignRole('pengaju-unit');

        expect(fn () => ajukanUnit($luar, $this->prodi))->toThrow(AuthorizationException::class)
            ->and(fn () => ajukanUnit($this->kaprodi, $this->prodi))->toThrow(AuthorizationException::class);
    });

    it('menolak unit tanpa kepala penyetuju aktif atau yang bukan prodi/laboratorium', function () {
        $this->prodi->penyetuju()->sync([]);
        expect(fn () => ajukanUnit($this->pengaju, $this->prodi))->toThrow(ValidationException::class, 'belum memiliki kepala');

        $lain = UnitKerja::create(['kode' => 'BAG', 'nama' => 'Bagian Umum', 'tipe' => 'lainnya']);
        $lain->pengaju()->attach($this->pengaju);
        $lain->penyetuju()->attach($this->kaprodi);
        expect(fn () => ajukanUnit($this->pengaju, $lain))->toThrow(ValidationException::class, 'program studi atau laboratorium');
    });

    it('memberi tahu kepala unit yang tepat, bukan kepala unit lain', function () {
        ajukanUnit($this->pengaju, $this->prodi);

        expect(judulUntuk($this->kaprodi))->toBe(['Permohonan menunggu persetujuan kepala unit'])->and(judulUntuk($this->kalab))->toBe([]);
    });

    it('tidak membuat LPJ saat permohonan unit selesai', function () {
        $p = ajukanUnit($this->pengaju, $this->prodi);
        $p->forceFill(['status' => StatusPermohonan::Pembuatan])->saveQuietly();
        app(TransisiPermohonan::class)->ke($p->fresh(), StatusPermohonan::Selesai, $this->admin);

        expect(Lpj::count())->toBe(0);
    });
});

describe('persetujuan kepala unit', function () {
    it('kaprodi menyetujui lalu permohonan masuk validasi admin', function () {
        $p = ajukanUnit($this->pengaju, $this->prodi);

        $p = app(SetujuiUnit::class)->jalankan($p, $this->kaprodi, 'Layak');

        expect($p->status)->toBe(StatusPermohonan::ValidasiAdmin)->and($p->riwayat->last()->catatan)->toBe('Layak');
    });

    it('hanya kepala unit pada unit itu yang boleh memutus', function () {
        $p = ajukanUnit($this->pengaju, $this->prodi);

        expect(fn () => app(SetujuiUnit::class)->jalankan($p, $this->kalab))->toThrow(AuthorizationException::class)
            ->and(fn () => app(SetujuiUnit::class)->jalankan($p, $this->pengaju))->toThrow(AuthorizationException::class)
            ->and(fn () => app(SetujuiUnit::class)->jalankan($p, $this->admin))->toThrow(AuthorizationException::class)
            ->and($p->fresh()->status)->toBe(StatusPermohonan::PersetujuanUnit);
    });

    it('persetujuan hanya sah pada tahap persetujuan unit', function () {
        $p = ajukanUnit($this->pengaju, $this->prodi);
        app(SetujuiUnit::class)->jalankan($p, $this->kaprodi);

        expect(fn () => app(SetujuiUnit::class)->jalankan($p->fresh(), $this->kaprodi))->toThrow(ValidationException::class);
    });

    it('kaprodi mengembalikan, pengaju merevisi, dan kembali ke validasi admin', function () {
        $p = ajukanUnit($this->pengaju, $this->prodi);
        app(KembalikanPermohonan::class)->jalankan($p, $this->kaprodi, 'Lengkapi anggaran');

        expect($p->fresh()->status)->toBe(StatusPermohonan::Dikembalikan)->and(judulUntuk($this->pengaju))->toContain('Permohonan: Dikembalikan');

        app(AjukanUlangPermohonan::class)->jalankan($p->fresh(), $this->pengaju, ['deskripsi' => 'Sudah dilengkapi.']);

        expect($p->fresh()->status)->toBe(StatusPermohonan::ValidasiAdmin);
    });

    it('rekan pengaju tidak dapat merevisi permohonan unit lain', function () {
        $p = ajukanUnit($this->pengaju, $this->prodi);
        app(KembalikanPermohonan::class)->jalankan($p, $this->kaprodi, 'Lengkapi');
        $luar = User::factory()->create()->assignRole('pengaju-unit');

        expect(fn () => app(AjukanUlangPermohonan::class)->jalankan($p->fresh(), $luar, ['deskripsi' => 'x']))->toThrow(AuthorizationException::class);
    });

    it('kaprodi menolak dengan alasan', function () {
        $p = ajukanUnit($this->pengaju, $this->prodi);

        expect(fn () => app(TolakPermohonan::class)->jalankan($p, $this->kaprodi, ' '))->toThrow(ValidationException::class);

        app(TolakPermohonan::class)->jalankan($p->fresh(), $this->kaprodi, 'Tidak sesuai rencana');

        expect($p->fresh()->status)->toBe(StatusPermohonan::Ditolak);
    });

    it('kalab tidak dapat mengembalikan atau menolak permohonan prodi', function () {
        $p = ajukanUnit($this->pengaju, $this->prodi);

        expect(fn () => app(KembalikanPermohonan::class)->jalankan($p, $this->kalab, 'x'))->toThrow(AuthorizationException::class)
            ->and(fn () => app(TolakPermohonan::class)->jalankan($p->fresh(), $this->kalab, 'x'))->toThrow(AuthorizationException::class);
    });

    it('pengaju dapat membatalkan permohonan miliknya, rekan tidak', function () {
        $p = ajukanUnit($this->pengaju, $this->prodi);
        $rekan = User::factory()->create()->assignRole('pengaju-unit');
        $this->prodi->pengaju()->attach($rekan);

        expect(fn () => app(BatalkanPermohonan::class)->jalankan($p, $rekan, 'Salah'))->toThrow(AuthorizationException::class);

        app(BatalkanPermohonan::class)->jalankan($p->fresh(), $this->pengaju, 'Dibatalkan');

        expect($p->fresh()->status)->toBe(StatusPermohonan::Dibatalkan);
    });

    it('setelah disetujui, admin memvalidasi dan permohonan melanjutkan alur fakultas', function () {
        $p = ajukanUnit($this->pengaju, $this->prodi);
        app(SetujuiUnit::class)->jalankan($p, $this->kaprodi);
        app(ValidasiPermohonan::class)->jalankan($p->fresh(), $this->admin);

        expect($p->fresh()->status)->toBe(StatusPermohonan::DisposisiDekan);
    });
});

describe('peta alur dan pelacakan', function () {
    it('peta mengizinkan jalur unit dan menolak jalan pintas', function () {
        expect(AlurPermohonan::boleh(StatusPermohonan::Diajukan, StatusPermohonan::PersetujuanUnit))->toBeTrue()
            ->and(AlurPermohonan::boleh(StatusPermohonan::PersetujuanUnit, StatusPermohonan::ValidasiAdmin))->toBeTrue()
            ->and(AlurPermohonan::boleh(StatusPermohonan::PersetujuanUnit, StatusPermohonan::DisposisiDekan))->toBeFalse()
            ->and(fn () => AlurPermohonan::pastikan(StatusPermohonan::PersetujuanUnit, StatusPermohonan::Selesai))->toThrow(TransisiTidakSah::class);
    });

    it('linimasa publik menampilkan tahap persetujuan kepala unit tanpa nama pribadi', function () {
        $p = ajukanUnit($this->pengaju, $this->prodi);
        app(SetujuiUnit::class)->jalankan($p, $this->kaprodi);
        $tahap = (new Linimasa)->permohonan($p->fresh());

        expect(collect($tahap)->pluck('tahap')->all())->toContain('Menunggu persetujuan kepala program studi / laboratorium')
            ->and(json_encode($tahap))->not->toContain($this->kaprodi->name)->not->toContain($this->pengaju->name);
        expect((new Linimasa)->statusPermohonan($p->fresh()->forceFill(['status' => StatusPermohonan::PersetujuanUnit])))->toBe('Menunggu persetujuan kepala program studi / laboratorium');
    });
});
