<?php

use App\Actions\Permohonan\AjukanPermohonan;
use App\Actions\Permohonan\DisposisiPermohonan;
use App\Actions\Permohonan\PutusanWd;
use App\Actions\Permohonan\RekomendasiKasubag;
use App\Actions\Permohonan\ValidasiPermohonan;
use App\Enums\StatusDisposisiPenerima;
use App\Enums\StatusPermohonan;
use App\Filament\Resources\Permohonans\Pages\ViewPermohonan;
use App\Livewire\Pimpinan\KotakMasuk;
use App\Models\DisposisiPenerima;
use App\Models\Jabatan;
use App\Models\JenisPermohonan;
use App\Models\Ormawa;
use App\Models\PemangkuJabatan;
use App\Models\PengurusOrmawa;
use App\Models\Permohonan;
use App\Models\PermohonanRuangan;
use App\Models\PersetujuanWd;
use App\Models\RuanganLokal;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\JenisNaskahSeeder;
use Database\Seeders\JenisPermohonanSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Database\Seeders\StrukturFkipSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $this->withoutVite();
    $this->seed([PeranDanIzinSeeder::class, StrukturFkipSeeder::class, PengaturanSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class, JenisPermohonanSeeder::class]);
    Filament::setCurrentPanel('admin');
    CarbonImmutable::setTestNow('2026-06-01 09:00:00');
    RuanganLokal::create(['kode' => 'A101', 'nama' => 'Ruang A101']);
});

afterEach(fn () => CarbonImmutable::setTestNow());

function pejabatP(string $peran, string $kodeJabatan, bool $plt = false): User
{
    $u = User::factory()->create()->assignRole($peran);
    $u->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
    PemangkuJabatan::create(['jabatan_id' => Jabatan::firstWhere('kode', $kodeJabatan)->id, 'user_id' => $u->id, 'mulai' => '2026-01-01', 'plt' => $plt]);

    return $u;
}

function permohonanDiDekan(): Permohonan
{
    $o = Ormawa::create(['nama' => 'HIMA '.random_int(1, 9999), 'tingkat' => 'prodi']);
    $o->sk()->create(['nomor_sk' => 'SK/1', 'tanggal_sk' => '2026-01-01', 'periode_mulai' => '2026-01-01', 'periode_selesai' => '2026-12-31']);
    $u = User::factory()->create(['nip_nim' => (string) random_int(8000000, 8999999)])->assignRole('pengurus-ormawa');
    PengurusOrmawa::create(['ormawa_id' => $o->id, 'user_id' => $u->id, 'nama' => $u->name, 'jabatan' => 'ketua']);
    RateLimiter::clear('ajukan-permohonan:'.$u->id);

    $p = app(AjukanPermohonan::class)->jalankan($u, $o, JenisPermohonan::firstWhere('kode', 'kegiatan-ruangan'), [
        'nama_kegiatan' => 'Seminar', 'perihal' => 'Izin', 'tanggal_mulai' => '2026-06-20', 'tanggal_selesai' => '2026-06-20', 'deskripsi' => 'Deskripsi',
        'penanggung_jawab' => ['ketua' => ['nama' => 'Budi']],
        'berkas' => ['surat_permohonan' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view', 'proposal' => 'https://drive.google.com/file/d/1ZyXwVuTsRqPoNmLkJi/view'],
        'ruangan' => [['kode' => 'A101', 'tanggal' => '2026-06-20', 'sesi' => 'pagi']],
    ]);

    $admin = User::factory()->create()->assignRole('admin-persuratan');

    return app(ValidasiPermohonan::class)->jalankan($p, $admin);
}

function idJabatan(string $kode): string
{
    return Jabatan::firstWhere('kode', $kode)->id;
}

describe('disposisi dekan', function () {
    it('membuat disposisi bagi pemangku WD dan baris persetujuan menunggu', function () {
        $dekan = pejabatP('dekan', 'dekan');
        $wd1 = pejabatP('wakil-dekan', 'wd-akademik');
        $wd3 = pejabatP('wakil-dekan', 'wd-kemahasiswaan');
        $p = permohonanDiDekan();

        $d = app(DisposisiPermohonan::class)->jalankan($p, $dekan, [idJabatan('wd-akademik'), idJabatan('wd-kemahasiswaan')], 'Mohon pertimbangan');
        $p = $p->fresh();

        expect($p->status)->toBe(StatusPermohonan::PersetujuanWd)
            ->and($d->permohonan_id)->toBe($p->id)->and($d->surat_masuk_id)->toBeNull()->and($d->dari_user_id)->toBe($dekan->id)
            ->and($d->penerima->pluck('user_id')->sort()->values()->all())->toBe(collect([$wd1->id, $wd3->id])->sort()->values()->all())
            ->and($p->persetujuanWd->pluck('putusan')->unique()->all())->toBe(['menunggu'])->and($p->persetujuanWd)->toHaveCount(2)
            ->and($p->riwayat->last()->catatan)->toBe('Mohon pertimbangan');
    });

    it('mewajibkan minimal satu WD yang sah dan berpemangku aktif', function () {
        $dekan = pejabatP('dekan', 'dekan');
        pejabatP('wakil-dekan', 'wd-akademik');
        $p = permohonanDiDekan();

        expect(fn () => app(DisposisiPermohonan::class)->jalankan($p, $dekan, []))->toThrow(ValidationException::class)
            ->and(fn () => app(DisposisiPermohonan::class)->jalankan($p, $dekan, [idJabatan('kasubag-umum')]))->toThrow(ValidationException::class)
            ->and(fn () => app(DisposisiPermohonan::class)->jalankan($p, $dekan, [idJabatan('wd-umum-keuangan')]))->toThrow(ValidationException::class)
            ->and(fn () => app(DisposisiPermohonan::class)->jalankan($p, $dekan, [idJabatan('wd-akademik'), idJabatan('wd-umum-keuangan')]))->toThrow(ValidationException::class);
        expect($p->fresh()->status)->toBe(StatusPermohonan::DisposisiDekan)->and(PersetujuanWd::count())->toBe(0);
    });

    it('hanya dekan yang mendisposisikan dan hanya pada status disposisi dekan', function () {
        pejabatP('dekan', 'dekan');
        pejabatP('wakil-dekan', 'wd-akademik');
        $p = permohonanDiDekan();

        foreach (['wakil-dekan', 'kasubag', 'admin-persuratan', 'pegawai'] as $peran) {
            expect(fn () => app(DisposisiPermohonan::class)->jalankan($p, User::factory()->create()->assignRole($peran), [idJabatan('wd-akademik')]))->toThrow(AuthorizationException::class);
        }

        $dekan = User::whereHas('roles', fn ($q) => $q->where('name', 'dekan'))->first();
        app(DisposisiPermohonan::class)->jalankan($p, $dekan, [idJabatan('wd-akademik')]);
        expect(fn () => app(DisposisiPermohonan::class)->jalankan($p->fresh(), $dekan, [idJabatan('wd-akademik')]))->toThrow(ValidationException::class);
    });

    it('dekan dapat menolak dengan alasan dan ruangan dilepas', function () {
        $dekan = pejabatP('dekan', 'dekan');
        $p = permohonanDiDekan();

        expect(fn () => app(DisposisiPermohonan::class)->tolak($p, $dekan, ''))->toThrow(ValidationException::class);

        app(DisposisiPermohonan::class)->tolak($p, $dekan, 'Tidak sesuai kalender akademik');
        expect($p->fresh()->status)->toBe(StatusPermohonan::Ditolak)->and($p->fresh()->ruangan->pluck('status')->all())->toBe(['dilepas']);
    });
});

describe('putusan wakil dekan', function () {
    function siapPersetujuan(array $kodeWd = ['wd-akademik', 'wd-kemahasiswaan']): array
    {
        $dekan = pejabatP('dekan', 'dekan');
        $wd = collect($kodeWd)->map(fn ($k) => pejabatP('wakil-dekan', $k))->all();
        $p = permohonanDiDekan();
        app(DisposisiPermohonan::class)->jalankan($p, $dekan, array_map('idJabatan', $kodeWd));

        return [$p->fresh(), $wd, $dekan];
    }

    it('dua WD setuju → rekomendasi kasubag; putusan pertama belum memajukan', function () {
        [$p, [$wd1, $wd3]] = siapPersetujuan();

        app(PutusanWd::class)->jalankan($p, $wd1, 'setuju', 'OK akademik');
        expect($p->fresh()->status)->toBe(StatusPermohonan::PersetujuanWd)
            ->and($p->fresh()->persetujuanWd->where('putusan', 'setuju'))->toHaveCount(1);

        app(PutusanWd::class)->jalankan($p, $wd3, 'setuju');
        $p = $p->fresh();

        expect($p->status)->toBe(StatusPermohonan::RekomendasiKasubag)->and($p->persetujuanWd->pluck('putusan')->unique()->all())->toBe(['setuju'])
            ->and($p->persetujuanWd->firstWhere('jabatan_id', idJabatan('wd-akademik'))->user_id)->toBe($wd1->id)
            ->and($p->persetujuanWd->firstWhere('jabatan_id', idJabatan('wd-akademik'))->catatan)->toBe('OK akademik');
    });

    it('satu WD menolak → ditolak, ruangan dilepas, catatan wajib', function () {
        [$p, [$wd1, $wd3]] = siapPersetujuan();

        expect(fn () => app(PutusanWd::class)->jalankan($p, $wd1, 'tolak', ''))->toThrow(ValidationException::class);

        app(PutusanWd::class)->jalankan($p, $wd1, 'setuju');
        app(PutusanWd::class)->jalankan($p, $wd3, 'tolak', 'Bentrok dengan ujian');
        $p = $p->fresh();

        expect($p->status)->toBe(StatusPermohonan::Ditolak)->and($p->ruangan->pluck('status')->all())->toBe(['dilepas'])
            ->and($p->riwayat->last()->catatan)->toContain('Bentrok dengan ujian');
    });

    it('menolak WD yang tidak dituju, orang lain, dan putusan yang sudah diambil', function () {
        [$p, [$wd1]] = siapPersetujuan(['wd-akademik']);
        $wdLain = pejabatP('wakil-dekan', 'wd-kemahasiswaan');

        expect(fn () => app(PutusanWd::class)->jalankan($p, $wdLain, 'setuju'))->toThrow(AuthorizationException::class)
            ->and(fn () => app(PutusanWd::class)->jalankan($p, User::factory()->create()->assignRole('kasubag'), 'setuju'))->toThrow(AuthorizationException::class)
            ->and(fn () => app(PutusanWd::class)->jalankan($p, $wd1, 'mungkin'))->toThrow(ValidationException::class);

        app(PutusanWd::class)->jalankan($p, $wd1, 'setuju');
        expect(fn () => app(PutusanWd::class)->jalankan($p->fresh(), $wd1, 'setuju'))->toThrow(ValidationException::class);
    });

    it('Plt WD dapat memutus dan WD yang masa jabatannya berakhir tidak', function () {
        $dekan = pejabatP('dekan', 'dekan');
        $plt = pejabatP('pegawai', 'wd-akademik', plt: true);
        $plt->assignRole('wakil-dekan');
        $p = permohonanDiDekan();
        app(DisposisiPermohonan::class)->jalankan($p, $dekan, [idJabatan('wd-akademik')]);

        $berakhir = User::factory()->create()->assignRole('wakil-dekan');
        PemangkuJabatan::create(['jabatan_id' => idJabatan('wd-akademik'), 'user_id' => $berakhir->id, 'mulai' => '2020-01-01', 'selesai' => '2025-12-31', 'plt' => true]);
        expect(fn () => app(PutusanWd::class)->jalankan($p->fresh(), $berakhir, 'setuju'))->toThrow(AuthorizationException::class);

        app(PutusanWd::class)->jalankan($p->fresh(), $plt, 'setuju');
        expect($p->fresh()->status)->toBe(StatusPermohonan::RekomendasiKasubag);
    });

    it('menyelesaikan disposisi pribadi WD dengan putusan sebagai laporan', function () {
        [$p, [$wd1]] = siapPersetujuan(['wd-akademik']);

        app(PutusanWd::class)->jalankan($p, $wd1, 'setuju', 'Silakan');

        $penerima = DisposisiPenerima::where('user_id', $wd1->id)->first();
        expect($penerima->status)->toBe(StatusDisposisiPenerima::Selesai)->and($penerima->laporan_tindak_lanjut)->toBe('Setuju: Silakan');
    });
});

describe('rekomendasi kasubag', function () {
    function sampaiKasubag(): array
    {
        $dekan = pejabatP('dekan', 'dekan');
        $wd = pejabatP('wakil-dekan', 'wd-akademik');
        $kasubag = pejabatP('kasubag', 'kasubag-umum');
        $p = permohonanDiDekan();
        app(DisposisiPermohonan::class)->jalankan($p, $dekan, [idJabatan('wd-akademik')]);
        app(PutusanWd::class)->jalankan($p->fresh(), $wd, 'setuju');

        return [$p->fresh(), $kasubag];
    }

    it('kasubag merekomendasikan → penerbitan', function () {
        [$p, $kasubag] = sampaiKasubag();

        app(RekomendasiKasubag::class)->jalankan($p, $kasubag, 'Layak');

        expect($p->fresh()->status)->toBe(StatusPermohonan::Penerbitan)->and($p->fresh()->riwayat->last()->catatan)->toBe('Layak');
    });

    it('hanya pemangku kasubag yang boleh, dan dapat menolak dengan alasan (ruangan dilepas)', function () {
        [$p, $kasubag] = sampaiKasubag();
        $kasubagTanpaJabatan = User::factory()->create()->assignRole('kasubag');

        expect(fn () => app(RekomendasiKasubag::class)->jalankan($p, $kasubagTanpaJabatan))->toThrow(AuthorizationException::class)
            ->and(fn () => app(RekomendasiKasubag::class)->jalankan($p, User::factory()->create()->assignRole('dekan')))->toThrow(AuthorizationException::class)
            ->and(fn () => app(RekomendasiKasubag::class)->tolak($p, $kasubag, ' '))->toThrow(ValidationException::class);

        app(RekomendasiKasubag::class)->tolak($p, $kasubag, 'Dokumen tidak lengkap');
        expect($p->fresh()->status)->toBe(StatusPermohonan::Ditolak)->and($p->fresh()->ruangan->pluck('status')->all())->toBe(['dilepas']);
    });
});

describe('antarmuka pimpinan', function () {
    it('alur lengkap dari kotak masuk: dekan → WD → kasubag', function () {
        $dekan = pejabatP('dekan', 'dekan');
        $wd = pejabatP('wakil-dekan', 'wd-akademik');
        $kasubag = pejabatP('kasubag', 'kasubag-umum');
        $p = permohonanDiDekan();

        Livewire::actingAs($dekan)->test(KotakMasuk::class)->call('pilihTab', 'permohonan')->assertSee('Seminar')
            ->call('buka', "permohonan:{$p->id}")->set('jabatanWd', [idJabatan('wd-akademik')])->call('disposisikanPermohonan', $p->id);
        expect($p->fresh()->status)->toBe(StatusPermohonan::PersetujuanWd);

        Livewire::actingAs($wd)->test(KotakMasuk::class)->call('pilihTab', 'permohonan')->assertSee('Seminar')
            ->call('buka', "permohonan:{$p->id}")->call('putusWd', $p->id, 'setuju');
        expect($p->fresh()->status)->toBe(StatusPermohonan::RekomendasiKasubag);

        Livewire::actingAs($kasubag)->test(KotakMasuk::class)->call('pilihTab', 'permohonan')->assertSee('Seminar')
            ->call('rekomendasi', $p->id);
        expect($p->fresh()->status)->toBe(StatusPermohonan::Penerbitan);
    });

    it('tidak menampilkan permohonan kepada yang tidak berhak dan menolak aksinya', function () {
        pejabatP('dekan', 'dekan');
        $wd = pejabatP('wakil-dekan', 'wd-akademik');
        $p = permohonanDiDekan();

        Livewire::actingAs($wd)->test(KotakMasuk::class)->call('pilihTab', 'permohonan')->assertDontSee('Seminar');
        Livewire::actingAs($wd)->test(KotakMasuk::class)->call('putusWd', $p->id, 'setuju')->assertHasErrors('status');
        Livewire::actingAs($wd)->test(KotakMasuk::class)->set('jabatanWd', [idJabatan('wd-akademik')])->call('disposisikanPermohonan', $p->id)->assertForbidden();
    });

    it('menjalankan disposisi dan putusan dari halaman detail panel', function () {
        $dekan = pejabatP('dekan', 'dekan');
        $wd = pejabatP('wakil-dekan', 'wd-akademik');
        $p = permohonanDiDekan();

        Livewire::actingAs($dekan)->test(ViewPermohonan::class, ['record' => $p->getKey()])
            ->assertActionVisible('disposisiDekan')->assertActionHidden('setujuWd')
            ->callAction('disposisiDekan', ['jabatan' => [idJabatan('wd-akademik')], 'catatan' => 'Mohon'])->assertNotified();
        expect($p->fresh()->status)->toBe(StatusPermohonan::PersetujuanWd);

        Livewire::actingAs($wd)->test(ViewPermohonan::class, ['record' => $p->getKey()])
            ->assertActionVisible('setujuWd')->assertActionVisible('tolakWd')->assertActionHidden('disposisiDekan')
            ->callAction('setujuWd', ['catatan' => 'Setuju'])->assertNotified();
        expect($p->fresh()->status)->toBe(StatusPermohonan::RekomendasiKasubag)->and(PermohonanRuangan::first()->status)->toBe('ditahan');
    });
});
