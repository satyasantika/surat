<?php

use App\Contracts\LayananRuangan;
use App\Exceptions\LayananRuanganTidakTersedia;
use App\Exceptions\RuanganBentrok;
use App\Filament\Resources\RuanganLokals\Pages\ManageRuanganLokals;
use App\Filament\Resources\RuanganLokals\PemakaianRelationManager;
use App\Models\JenisPermohonan;
use App\Models\PemakaianRuanganLokal;
use App\Models\RuanganLokal;
use App\Models\User;
use App\Services\Ruangan\LayananRuanganAset;
use App\Services\Ruangan\LayananRuanganLokal;
use App\Support\Pengaturan;
use App\Support\SesiRuangan;
use Carbon\CarbonImmutable;
use Database\Seeders\JenisNaskahSeeder;
use Database\Seeders\JenisPermohonanSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PeranDanIzinSeeder::class, PengaturanSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class, JenisPermohonanSeeder::class]);
    Filament::setCurrentPanel('admin');
    Cache::flush();
});

function ruang(string $kode = 'A101', array $ubah = []): RuanganLokal
{
    return RuanganLokal::create($ubah + ['kode' => $kode, 'nama' => "Ruang {$kode}", 'gedung' => 'Gedung A', 'kapasitas' => 40]);
}

function tgl(string $s): CarbonImmutable
{
    return CarbonImmutable::parse($s);
}

describe('sesi ruangan', function () {
    it('membaca sesi dari pengaturan', function () {
        expect(SesiRuangan::kode())->toBe(['pagi', 'siang', 'seharian'])->and(SesiRuangan::valid('pagi'))->toBeTrue()->and(SesiRuangan::valid('malam'))->toBeFalse();
    });

    it('mendefinisikan bentrok: sama, atau seharian dengan sesi mana pun', function (string $a, string $b, bool $bentrok) {
        expect(SesiRuangan::bentrok($a, $b))->toBe($bentrok)->and(SesiRuangan::bentrok($b, $a))->toBe($bentrok);
    })->with([
        ['pagi', 'pagi', true], ['siang', 'siang', true], ['pagi', 'siang', false],
        ['seharian', 'pagi', true], ['seharian', 'siang', true], ['seharian', 'seharian', true],
    ]);

    it('mengikuti sesi kustom dari pengaturan', function () {
        Pengaturan::set('sesi', [['kode' => 'malam', 'mulai' => '18:00', 'selesai' => '21:00'], ['kode' => 'seharian', 'mulai' => '06:00', 'selesai' => '21:00']]);

        expect(SesiRuangan::kode())->toBe(['malam', 'seharian'])->and(SesiRuangan::yangBentrok('seharian'))->toBe(['malam', 'seharian']);
    });
});

describe('jenis permohonan', function () {
    it('menyemai empat jenis dengan atribut yang benar', function () {
        $jenis = JenisPermohonan::with('jenisNaskah')->get()->keyBy('kode');

        expect($jenis->keys()->sort()->values()->all())->toBe(['kegiatan', 'kegiatan-ruangan', 'kegiatan-ruangan-rektorat', 'pengantar-proposal'])
            ->and($jenis['kegiatan']->butuh_ruangan)->toBeFalse()
            ->and($jenis['kegiatan-ruangan']->butuh_ruangan)->toBeTrue()->and($jenis['kegiatan-ruangan']->butuh_fasilitas_rektorat)->toBeFalse()
            ->and($jenis['kegiatan-ruangan-rektorat']->butuh_fasilitas_rektorat)->toBeTrue()
            ->and($jenis['kegiatan']->jenisNaskah->kode)->toBe('surat-izin-kegiatan')
            ->and($jenis['pengantar-proposal']->jenisNaskah->kode)->toBe('pengantar-proposal')
            ->and($jenis['kegiatan']->berkas_wajib)->toBe(['surat_permohonan', 'proposal'])
            ->and($jenis['pengantar-proposal']->berkas_wajib)->toBe(['proposal']);
    });

    it('idempoten dan menolak jenis berkas tak dikenal', function () {
        $this->seed(JenisPermohonanSeeder::class);

        expect(JenisPermohonan::count())->toBe(4)
            ->and(fn () => JenisPermohonan::firstWhere('kode', 'kegiatan')->update(['berkas_wajib' => ['ktp_rahasia']]))->toThrow(ValidationException::class);
    });
});

describe('layanan lokal', function () {
    beforeEach(fn () => $this->layanan = new LayananRuanganLokal);

    it('mendaftar hanya ruangan tersedia di katalog', function () {
        ruang('A101');
        ruang('B202', ['dalam_perawatan' => true]);
        ruang('C303', ['tampil_katalog' => false]);

        expect(collect($this->layanan->daftar())->pluck('kode')->all())->toBe(['A101']);
    });

    it('mencatat dan membaca jadwal pada rentang', function () {
        ruang();
        $this->layanan->catatPemakaian('A101', '2026-07-10', 'pagi', null, 'Rapat');
        $this->layanan->catatPemakaian('A101', '2026-07-12', 'siang');
        $this->layanan->catatPemakaian('A101', '2026-08-01', 'pagi');

        $jadwal = $this->layanan->jadwal('A101', tgl('2026-07-01'), tgl('2026-07-31'));

        expect($jadwal)->toHaveCount(2)->and($jadwal[0])->toBe(['tanggal' => '2026-07-10', 'sesi' => 'pagi', 'keterangan' => 'Rapat'])
            ->and($this->layanan->jadwal('TIDAKADA', tgl('2026-07-01'), tgl('2026-07-31')))->toBe([]);
    });

    it('mendeteksi bentrok per sesi dan seharian', function (string $pertama, string $kedua, bool $bentrok) {
        ruang();
        $this->layanan->catatPemakaian('A101', '2026-07-10', $pertama, (string) Str::uuid());

        $aksi = fn () => $this->layanan->catatPemakaian('A101', '2026-07-10', $kedua, (string) Str::uuid());

        $bentrok ? expect($aksi)->toThrow(RuanganBentrok::class) : expect($aksi())->not->toBeEmpty();
    })->with([
        ['pagi', 'pagi', true], ['pagi', 'siang', false], ['siang', 'pagi', false],
        ['seharian', 'pagi', true], ['seharian', 'siang', true], ['pagi', 'seharian', true], ['siang', 'seharian', true], ['seharian', 'seharian', true],
    ]);

    it('tidak bentrok pada tanggal atau ruangan lain', function () {
        ruang('A101');
        ruang('A102');
        $this->layanan->catatPemakaian('A101', '2026-07-10', 'seharian');

        expect($this->layanan->catatPemakaian('A101', '2026-07-11', 'seharian'))->not->toBeEmpty()
            ->and($this->layanan->catatPemakaian('A102', '2026-07-10', 'seharian'))->not->toBeEmpty();
    });

    it('idempoten untuk referensi yang sama tetapi menolak referensi berbeda', function () {
        ruang();
        $ref = (string) Str::uuid();

        $a = $this->layanan->catatPemakaian('A101', '2026-07-10', 'pagi', $ref);
        $b = $this->layanan->catatPemakaian('A101', '2026-07-10', 'pagi', $ref);

        expect($a)->toBe($b)->and(PemakaianRuanganLokal::count())->toBe(1)
            ->and(fn () => $this->layanan->catatPemakaian('A101', '2026-07-10', 'pagi', (string) Str::uuid()))->toThrow(RuanganBentrok::class)
            ->and(fn () => $this->layanan->catatPemakaian('A101', '2026-07-10', 'pagi'))->toThrow(RuanganBentrok::class);
    });

    it('menolak ruangan atau sesi tak dikenal', function () {
        ruang();

        expect(fn () => $this->layanan->catatPemakaian('XXX', '2026-07-10', 'pagi'))->toThrow(InvalidArgumentException::class)
            ->and(fn () => $this->layanan->catatPemakaian('A101', '2026-07-10', 'malam'))->toThrow(InvalidArgumentException::class);
    });
});

describe('layanan Aset (API)', function () {
    beforeEach(function () {
        config(['layanan.aset.url' => 'https://aset.test', 'layanan.aset.token' => 'rahasia-token']);
        $this->aset = new LayananRuanganAset('https://aset.test', 'rahasia-token', 5, 60);
    });

    it('memetakan daftar ruangan dan mengirim token', function () {
        Http::fake(['aset.test/api/v1/ruangan' => Http::response(['data' => [
            ['kode' => 'R1', 'nama' => 'Aula', 'gedung' => 'A', 'kapasitas' => '200', 'fasilitas' => 'Proyektor'],
            ['kode' => 'R2', 'nama' => 'Kelas'],
        ]])]);

        $daftar = $this->aset->daftar();

        expect($daftar)->toHaveCount(2)->and($daftar[0])->toBe(['kode' => 'R1', 'nama' => 'Aula', 'gedung' => 'A', 'kapasitas' => 200, 'fasilitas' => 'Proyektor'])
            ->and($daftar[1]['kapasitas'])->toBeNull();
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer rahasia-token') && $r->hasHeader('Accept', 'application/json'));
    });

    it('membaca jadwal per tanggal dan meng-cache-nya', function () {
        Http::fake(['aset.test/api/v1/ruangan/R1/jadwal*' => Http::response(['data' => [['tanggal' => '2026-07-10', 'sesi' => 'pagi', 'keterangan' => 'Kuliah']]])]);

        $a = $this->aset->jadwal('R1', tgl('2026-07-10'), tgl('2026-07-10'));
        $b = $this->aset->jadwal('R1', tgl('2026-07-10'), tgl('2026-07-10'));

        expect($a)->toBe([['tanggal' => '2026-07-10', 'sesi' => 'pagi', 'keterangan' => 'Kuliah']])->and($b)->toBe($a)
            ->and(Cache::has('surat:ruangan:R1:2026-07-10'))->toBeTrue();
        Http::assertSentCount(1);

        $this->aset->jadwal('R1', tgl('2026-07-10'), tgl('2026-07-12'));
        Http::assertSentCount(3); // dua tanggal baru
    });

    it('mencatat pemakaian dan mengosongkan cache tanggal itu', function () {
        Http::fake([
            'aset.test/api/v1/ruangan/R1/jadwal*' => Http::response(['data' => []]),
            'aset.test/api/v1/ruangan/R1/pemakaian' => Http::response(['data' => ['id' => 'AS-77']], 201),
        ]);
        $this->aset->jadwal('R1', tgl('2026-07-10'), tgl('2026-07-10'));
        expect(Cache::has('surat:ruangan:R1:2026-07-10'))->toBeTrue();

        $id = $this->aset->catatPemakaian('R1', '2026-07-10', 'pagi', 'ref-1', 'Kegiatan HIMA');

        expect($id)->toBe('AS-77')->and(Cache::has('surat:ruangan:R1:2026-07-10'))->toBeFalse();
        Http::assertSent(fn ($r) => $r->method() === 'POST' && $r['tanggal'] === '2026-07-10' && $r['sesi'] === 'pagi' && $r['referensi'] === 'ref-1');
    });

    it('menerjemahkan 409 menjadi RuanganBentrok', function () {
        Http::fake(['aset.test/*' => Http::response(['message' => 'bentrok'], 409)]);

        expect(fn () => $this->aset->catatPemakaian('R1', '2026-07-10', 'pagi'))->toThrow(RuanganBentrok::class);
    });

    it('menerjemahkan 5xx, galat klien, dan jaringan menjadi LayananRuanganTidakTersedia (bukan data palsu)', function (Closure $respons) {
        Http::fake(['aset.test/*' => $respons]);

        expect(fn () => $this->aset->daftar())->toThrow(LayananRuanganTidakTersedia::class)
            ->and(fn () => $this->aset->jadwal('R1', tgl('2026-07-10'), tgl('2026-07-10')))->toThrow(LayananRuanganTidakTersedia::class)
            ->and(fn () => $this->aset->catatPemakaian('R1', '2026-07-10', 'pagi'))->toThrow(LayananRuanganTidakTersedia::class);
        expect(Cache::has('surat:ruangan:R1:2026-07-10'))->toBeFalse();
    })->with([
        '500' => [fn () => Http::response('', 500)],
        '503' => [fn () => Http::response('', 503)],
        '401' => [fn () => Http::response('', 401)],
        'jaringan' => [fn () => fn () => throw new ConnectionException('timeout')],
    ]);

    it('melempar galat bila integrasi belum dikonfigurasi', function () {
        $kosong = new LayananRuanganAset('', '');

        expect(fn () => $kosong->daftar())->toThrow(LayananRuanganTidakTersedia::class);
    });
});

describe('pemilihan layanan dari pengaturan', function () {
    it('memakai lokal secara bawaan dan Aset bila dipilih', function () {
        expect(app(LayananRuangan::class))->toBeInstanceOf(LayananRuanganLokal::class);

        Pengaturan::set('layanan_ruangan', 'aset_api');
        expect(app(LayananRuangan::class))->toBeInstanceOf(LayananRuanganAset::class);
    });

    it('mode Aset tanpa kredensial melempar galat, bukan jatuh ke lokal', function () {
        Pengaturan::set('layanan_ruangan', 'aset_api');
        config(['layanan.aset.url' => null, 'layanan.aset.token' => null]);

        expect(fn () => app(LayananRuangan::class)->daftar())->toThrow(LayananRuanganTidakTersedia::class);
    });
});

describe('panel ruangan lokal', function () {
    function pengelolaRuangan(): User
    {
        $u = User::factory()->create()->assignRole('operator-layanan');
        $u->givePermissionTo('ruangan.kelola');
        $u->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

        return $u;
    }

    it('membatasi akses ke ruangan.kelola', function () {
        $this->actingAs(pengelolaRuangan())->get('/admin/ruangan-lokal')->assertOk();

        $admin = User::factory()->create()->assignRole('admin-persuratan');
        $admin->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
        $this->actingAs($admin)->get('/admin/ruangan-lokal')->assertForbidden();
    });

    it('membuat ruangan dan mencatat pemakaian manual dengan deteksi bentrok', function () {
        $pengelola = pengelolaRuangan();
        $r = ruang('A101');

        Livewire::actingAs($pengelola)->test(ManageRuanganLokals::class)
            ->callAction(TestAction::make(CreateAction::class), ['kode' => 'B202', 'nama' => 'Ruang B202', 'kapasitas' => 30])->assertHasNoActionErrors();
        expect(RuanganLokal::where('kode', 'B202')->exists())->toBeTrue();

        $rm = fn () => Livewire::actingAs($pengelola)->test(PemakaianRelationManager::class, ['ownerRecord' => $r, 'pageClass' => ManageRuanganLokals::class]);
        $rm()->callAction(TestAction::make(CreateAction::class)->table(), ['tanggal' => '2026-07-10', 'sesi' => 'seharian', 'keterangan' => 'Ujian']);
        expect($r->pemakaian()->count())->toBe(1);

        $rm()->callAction(TestAction::make(CreateAction::class)->table(), ['tanggal' => '2026-07-10', 'sesi' => 'pagi']);
        expect($r->pemakaian()->count())->toBe(1);
    });
});
