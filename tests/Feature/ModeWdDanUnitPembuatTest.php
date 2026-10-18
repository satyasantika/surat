<?php

use App\Actions\Permohonan\AjukanPermohonan;
use App\Actions\Permohonan\DisposisiPermohonan;
use App\Actions\Permohonan\PutusanWd;
use App\Actions\Permohonan\RekomendasiKasubag;
use App\Actions\Permohonan\TerbitkanIzin;
use App\Actions\Permohonan\ValidasiPermohonan;
use App\Enums\StatusDisposisiPenerima;
use App\Enums\StatusPermohonan;
use App\Events\NaskahTerbit;
use App\Filament\Resources\JenisPermohonans\JenisPermohonanResource;
use App\Filament\Resources\Permohonans\Pages\ViewPermohonan;
use App\Filament\Resources\Permohonans\PermohonanResource;
use App\Filament\Resources\UnitPembuats\Pages\ManageUnitPembuats;
use App\Filament\Resources\UnitPembuats\UnitPembuatResource;
use App\Listeners\SelesaikanPermohonanDariNaskah;
use App\Livewire\Pimpinan\KotakMasuk;
use App\Models\DisposisiPenerima;
use App\Models\Jabatan;
use App\Models\JenisPermohonan;
use App\Models\Ormawa;
use App\Models\PemangkuJabatan;
use App\Models\PengurusOrmawa;
use App\Models\Permohonan;
use App\Models\PersetujuanWd;
use App\Models\RuanganLokal;
use App\Models\UnitPembuat;
use App\Models\User;
use App\Notifications\NotifikasiTahap;
use App\Services\Notifikasi\NotifikasiAlur;
use Carbon\CarbonImmutable;
use Database\Seeders\JenisNaskahSeeder;
use Database\Seeders\JenisPermohonanSeeder;
use Database\Seeders\KlasifikasiArsipSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Database\Seeders\StrukturFkipSeeder;
use Database\Seeders\UnitPembuatSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    $this->withoutVite();
    $this->seed([PeranDanIzinSeeder::class, StrukturFkipSeeder::class, PengaturanSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class, JenisPermohonanSeeder::class, KlasifikasiArsipSeeder::class]);
    Filament::setCurrentPanel('admin');
    CarbonImmutable::setTestNow('2026-06-01 09:00:00');
    RuanganLokal::firstOrCreate(['kode' => 'A101'], ['nama' => 'Ruang A101']);
});

afterEach(fn () => CarbonImmutable::setTestNow());

function mwPejabat(string $peran, string $kodeJabatan): User
{
    $jabatan = Jabatan::firstWhere('kode', $kodeJabatan);

    if ($sudah = $jabatan->pemangkuPada(now())) {
        return $sudah->user;
    }

    $u = User::factory()->create()->assignRole($peran);
    $u->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
    PemangkuJabatan::create(['jabatan_id' => Jabatan::firstWhere('kode', $kodeJabatan)->id, 'user_id' => $u->id, 'mulai' => '2026-01-01']);

    return $u;
}

function mwJabatan(string $kode): string
{
    return Jabatan::firstWhere('kode', $kode)->id;
}

function mwPermohonanDiDekan(string $jenis = 'kegiatan'): Permohonan
{
    $o = Ormawa::create(['nama' => 'HIMA '.random_int(1, 99999), 'tingkat' => 'prodi']);
    $o->sk()->create(['nomor_sk' => 'SK/1', 'tanggal_sk' => '2026-01-01', 'periode_mulai' => '2026-01-01', 'periode_selesai' => '2026-12-31']);
    $u = User::factory()->create(['nip_nim' => (string) random_int(8000000, 8999999)])->assignRole('pengurus-ormawa');
    PengurusOrmawa::create(['ormawa_id' => $o->id, 'user_id' => $u->id, 'nama' => $u->name, 'jabatan' => 'ketua']);
    RateLimiter::clear('ajukan-permohonan:'.$u->id);

    $p = app(AjukanPermohonan::class)->jalankan($u, $o, JenisPermohonan::firstWhere('kode', $jenis), [
        'nama_kegiatan' => 'Seminar Nasional', 'perihal' => 'Izin seminar', 'tanggal_mulai' => '2026-06-20', 'tanggal_selesai' => '2026-06-20', 'deskripsi' => 'Deskripsi',
        'penanggung_jawab' => ['ketua' => ['nama' => 'Budi']],
        'berkas' => ['surat_permohonan' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view', 'proposal' => 'https://drive.google.com/file/d/1ZyXwVuTsRqPoNmLkJi/view'],
    ]);

    return app(ValidasiPermohonan::class)->jalankan($p, User::factory()->create()->assignRole('admin-persuratan'));
}

/** @return array{0: Permohonan, 1: User, 2: array<int, User>} permohonan di tahap WD dengan mode yang dipilih dekan */
function mwDiTahapWd(string $mode, array $kodeWd = ['wd-akademik', 'wd-kemahasiswaan']): array
{
    $dekan = mwPejabat('dekan', 'dekan');
    $wd = collect($kodeWd)->map(fn ($k) => mwPejabat('wakil-dekan', $k))->all();
    $p = mwPermohonanDiDekan();
    app(DisposisiPermohonan::class)->jalankan($p, $dekan, array_map('mwJabatan', $kodeWd), null, $mode);

    return [$p->fresh(), $dekan, $wd];
}

function mwUnit(string $kode = 'izin-kegiatan', int $petugas = 2): UnitPembuat
{
    $unit = UnitPembuat::create(['kode' => $kode, 'nama' => 'Unit '.$kode]);
    $unit->petugas()->attach(User::factory()->count($petugas)->create()->pluck('id'));

    return $unit;
}

function mwSampaiKasubag(string $jenis = 'kegiatan'): array
{
    $dekan = mwPejabat('dekan', 'dekan');
    $wd = mwPejabat('wakil-dekan', 'wd-akademik');
    $kasubag = mwPejabat('kasubag', 'kasubag-umum');
    $p = mwPermohonanDiDekan($jenis);
    app(DisposisiPermohonan::class)->jalankan($p, $dekan, [mwJabatan('wd-akademik')]);
    app(PutusanWd::class)->jalankan($p->fresh(), $wd, 'setuju');

    return [$p->fresh(), $kasubag];
}

function mwNotifikasi(User $u): array
{
    return Notification::sent($u, NotifikasiTahap::class)->map(fn ($n) => $n->judul)->values()->all();
}

describe('mode WD', function () {
    it('menyimpan mode pilihan dekan; bawaan semua; satu WD selalu semua; mode tak sah ditolak', function () {
        [$p] = mwDiTahapWd(Permohonan::MODE_WD_SALAH_SATU);
        expect($p->modeWd())->toBe('salah_satu')->and($p->riwayat->last()->catatan)->toContain('cukup salah satu WD');

        [$p2] = mwDiTahapWd(Permohonan::MODE_WD_SALAH_SATU, ['wd-akademik']);
        expect($p2->modeWd())->toBe('semua');

        $dekan = mwPejabat('dekan', 'dekan');
        mwPejabat('wakil-dekan', 'wd-akademik');
        $p3 = mwPermohonanDiDekan();
        app(DisposisiPermohonan::class)->jalankan($p3, $dekan, [mwJabatan('wd-akademik'), mwJabatan('wd-kemahasiswaan')]);
        expect($p3->fresh()->modeWd())->toBe('semua');

        $p4 = mwPermohonanDiDekan();
        expect(fn () => app(DisposisiPermohonan::class)->jalankan($p4, $dekan, [mwJabatan('wd-akademik')], null, 'acak'))->toThrow(ValidationException::class)
            ->and($p4->fresh()->status)->toBe(StatusPermohonan::DisposisiDekan);
    });

    it('salah_satu: persetujuan pertama menutup tahap, WD lain diabaikan dan disposisinya diselesaikan', function () {
        [$p, , [$wd1, $wd3]] = mwDiTahapWd(Permohonan::MODE_WD_SALAH_SATU);

        app(PutusanWd::class)->jalankan($p, $wd3, 'setuju', 'Silakan');
        $p = $p->fresh();

        $diabaikan = $p->persetujuanWd->firstWhere('jabatan_id', mwJabatan('wd-akademik'));
        $penerima1 = DisposisiPenerima::where('user_id', $wd1->id)->first();

        expect($p->status)->toBe(StatusPermohonan::RekomendasiKasubag)
            ->and($p->persetujuanWd->firstWhere('jabatan_id', mwJabatan('wd-kemahasiswaan'))->putusan)->toBe(PersetujuanWd::SETUJU)
            ->and($diabaikan->putusan)->toBe(PersetujuanWd::DIABAIKAN)->and($diabaikan->catatan)->toContain('telah menyetujui')
            ->and($penerima1->status)->toBe(StatusDisposisiPenerima::Selesai)->and($penerima1->laporan_tindak_lanjut)->toContain('Diabaikan')
            ->and($p->riwayat->last()->catatan)->toContain($wd3->name)->toContain('Wakil Dekan Bidang Akademik');

        expect(mwNotifikasi($wd1))->toContain('Disposisi permohonan tidak perlu diputus');

        // WD yang diabaikan tidak bisa memutus lagi.
        expect(fn () => app(PutusanWd::class)->jalankan($p, $wd1, 'tolak', 'terlambat'))->toThrow(ValidationException::class);
        expect(fn () => app(PutusanWd::class)->jalankan($p, $wd1, 'setuju'))->toThrow(ValidationException::class);
    });

    it('salah_satu: satu penolakan belum mengakhiri; WD lain masih bisa menyetujui', function () {
        [$p, , [$wd1, $wd3]] = mwDiTahapWd(Permohonan::MODE_WD_SALAH_SATU);

        app(PutusanWd::class)->jalankan($p, $wd1, 'tolak', 'Bentrok jadwal');
        expect($p->fresh()->status)->toBe(StatusPermohonan::PersetujuanWd);

        app(PutusanWd::class)->jalankan($p->fresh(), $wd3, 'setuju');
        $p = $p->fresh();

        expect($p->status)->toBe(StatusPermohonan::RekomendasiKasubag)
            ->and($p->persetujuanWd->pluck('putusan')->sort()->values()->all())->toBe([PersetujuanWd::SETUJU, PersetujuanWd::TOLAK]);
    });

    it('salah_satu: ditolak hanya bila SEMUA WD tujuan menolak', function () {
        [$p, , [$wd1, $wd3]] = mwDiTahapWd(Permohonan::MODE_WD_SALAH_SATU);

        app(PutusanWd::class)->jalankan($p, $wd1, 'tolak', 'Alasan A');
        app(PutusanWd::class)->jalankan($p->fresh(), $wd3, 'tolak', 'Alasan B');
        $p = $p->fresh();

        expect($p->status)->toBe(StatusPermohonan::Ditolak)->and($p->riwayat->last()->catatan)->toContain('Seluruh WD tujuan menolak')->toContain('Alasan B');
    });

    it('mode semua tetap: satu penolakan langsung menolak, dan satu persetujuan belum cukup', function () {
        [$p, , [$wd1, $wd3]] = mwDiTahapWd(Permohonan::MODE_WD_SEMUA);
        app(PutusanWd::class)->jalankan($p, $wd1, 'setuju');
        expect($p->fresh()->status)->toBe(StatusPermohonan::PersetujuanWd);

        app(PutusanWd::class)->jalankan($p->fresh(), $wd3, 'tolak', 'Tidak');
        expect($p->fresh()->status)->toBe(StatusPermohonan::Ditolak)->and($p->fresh()->persetujuanWd->pluck('putusan')->contains(PersetujuanWd::DIABAIKAN))->toBeFalse();
    });

    it('salah_satu dengan satu WD tujuan berperilaku seperti biasa', function () {
        [$p, , [$wd]] = mwDiTahapWd(Permohonan::MODE_WD_SALAH_SATU, ['wd-akademik']);

        app(PutusanWd::class)->jalankan($p, $wd, 'tolak', 'Tidak');

        expect($p->fresh()->status)->toBe(StatusPermohonan::Ditolak);
    });

    it('antarmuka dekan: kotak masuk dan panel meneruskan pilihan mode', function () {
        $dekan = mwPejabat('dekan', 'dekan');
        mwPejabat('wakil-dekan', 'wd-akademik');
        mwPejabat('wakil-dekan', 'wd-kemahasiswaan');

        $p = mwPermohonanDiDekan();
        Livewire::actingAs($dekan)->test(KotakMasuk::class)->call('pilihTab', 'permohonan')->call('buka', 'permohonan:'.$p->id)
            ->set('jabatanWd', [mwJabatan('wd-akademik')])->assertDontSee('Cukup salah satu WD')
            ->set('jabatanWd', [mwJabatan('wd-akademik'), mwJabatan('wd-kemahasiswaan')])->assertSee('Cukup salah satu WD')->assertSee('Semua WD tujuan harus setuju')
            ->set('modeWd', 'salah_satu')->call('disposisikanPermohonan', $p->id);
        expect($p->fresh()->modeWd())->toBe('salah_satu');

        $p2 = mwPermohonanDiDekan();
        Livewire::actingAs($dekan)->test(ViewPermohonan::class, ['record' => $p2->getKey()])
            ->callAction('disposisiDekan', ['jabatan' => [mwJabatan('wd-akademik'), mwJabatan('wd-kemahasiswaan')], 'mode_wd' => 'salah_satu'])->assertNotified();
        expect($p2->fresh()->modeWd())->toBe('salah_satu');
    });
});

describe('master unit pembuat', function () {
    it('super-admin mengelola (buat, ubah, hapus) tanpa data baku', function () {
        $super = User::factory()->create()->assignRole('super-admin');
        $super->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
        $petugas = User::factory()->create();

        expect(UnitPembuat::count())->toBe(0);

        Livewire::actingAs($super)->test(ManageUnitPembuats::class)
            ->callAction('create', ['kode' => 'sk-rektor', 'nama' => 'SK Rektor', 'aktif' => true, 'petugas' => [$petugas->id]])->assertHasNoActionErrors();
        $unit = UnitPembuat::firstWhere('kode', 'sk-rektor');
        expect($unit->petugas->pluck('id')->all())->toBe([$petugas->id]);

        Livewire::actingAs($super)->test(ManageUnitPembuats::class)
            ->callTableAction('edit', $unit, ['kode' => 'sk-rektor', 'nama' => 'SK Rektor (baru)', 'aktif' => false, 'petugas' => []])->assertHasNoTableActionErrors();
        expect($unit->fresh()->nama)->toBe('SK Rektor (baru)')->and($unit->fresh()->aktif)->toBeFalse()->and($unit->fresh()->petugas)->toBeEmpty();

        Livewire::actingAs($super)->test(ManageUnitPembuats::class)->callTableAction('delete', $unit);
        expect(UnitPembuat::count())->toBe(0);
    });

    it('menolak kode ganda dan melarang hapus unit yang pernah dipakai permohonan', function () {
        $super = User::factory()->create()->assignRole('super-admin');
        $super->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
        $unit = mwUnit('izin-kegiatan');

        Livewire::actingAs($super)->test(ManageUnitPembuats::class)
            ->callAction('create', ['kode' => 'izin-kegiatan', 'nama' => 'Ganda', 'aktif' => true])->assertHasActionErrors(['kode' => 'unique']);

        [$p, $kasubag] = mwSampaiKasubag();
        app(RekomendasiKasubag::class)->jalankan($p, $kasubag, null, $unit->id);

        Livewire::actingAs($super)->test(ManageUnitPembuats::class)->assertTableActionHidden('delete', $unit);
    });

    it('peran lain (admin, dekan, kasubag, operator tanpa izin) dilarang; pemegang master.kelola boleh', function () {
        foreach (['admin-persuratan', 'dekan', 'kasubag', 'staf-khusus', 'pegawai', 'operator-layanan'] as $peran) {
            $u = User::factory()->create()->assignRole($peran);
            $u->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
            $this->actingAs($u);

            expect(UnitPembuatResource::canViewAny())->toBeFalse("{$peran} tidak boleh");
            $this->get('/admin/unit-pembuat')->assertForbidden();
        }

        $master = User::factory()->create()->assignRole('operator-layanan');
        $master->givePermissionTo('master.kelola');
        $master->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
        $this->actingAs($master)->get('/admin/unit-pembuat')->assertOk();
    });

    it('jenis layanan memiliki unit bawaan yang dapat diatur di master jenis permohonan', function () {
        $super = User::factory()->create()->assignRole('super-admin');
        $super->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
        $unit = mwUnit();
        $jenis = JenisPermohonan::firstWhere('kode', 'kegiatan');

        Livewire::actingAs($super)->test(JenisPermohonanResource::getPages()['index']->getPage())
            ->callTableAction('edit', $jenis, ['kode' => 'kegiatan', 'nama' => 'Izin kegiatan', 'unit_pembuat_bawaan_id' => $unit->id, 'berkas_wajib' => ['surat_permohonan', 'proposal'], 'aktif' => true])
            ->assertHasNoTableActionErrors();

        expect($jenis->fresh()->unit_pembuat_bawaan_id)->toBe($unit->id)->and($jenis->fresh()->unitPembuatBawaan->is($unit))->toBeTrue();
    });

    it('seeder contoh idempoten, tidak menimpa perubahan super-admin dan tidak menimpa unit bawaan', function () {
        $this->seed(UnitPembuatSeeder::class);
        expect(UnitPembuat::pluck('kode')->sort()->values()->all())->toBe(['izin-kegiatan', 'rektorat', 'surat-tugas-dosen'])
            ->and(JenisPermohonan::firstWhere('kode', 'kegiatan')->unitPembuatBawaan->kode)->toBe('izin-kegiatan');

        UnitPembuat::firstWhere('kode', 'rektorat')->update(['nama' => 'Biro Rektorat', 'aktif' => false]);
        $lain = UnitPembuat::create(['kode' => 'lain', 'nama' => 'Lain']);
        JenisPermohonan::firstWhere('kode', 'pengantar-proposal')->update(['unit_pembuat_bawaan_id' => $lain->id]);

        $this->seed(UnitPembuatSeeder::class);

        expect(UnitPembuat::count())->toBe(4)->and(UnitPembuat::firstWhere('kode', 'rektorat')->nama)->toBe('Biro Rektorat')->and(UnitPembuat::firstWhere('kode', 'rektorat')->aktif)->toBeFalse()
            ->and(JenisPermohonan::firstWhere('kode', 'pengantar-proposal')->unit_pembuat_bawaan_id)->toBe($lain->id);
    });

    it('migrasi dapat dijalankan ulang tanpa galat (idempotent)', function () {
        foreach (['2026_10_18_110000_add_mode_wd_ke_permohonan_table', '2026_10_18_120000_create_unit_pembuat_tables'] as $berkas) {
            (require database_path("migrations/{$berkas}.php"))->up();
        }

        expect(Schema::hasColumn('permohonan', 'mode_wd'))->toBeTrue()->and(Schema::hasTable('unit_pembuat_petugas'))->toBeTrue();
    });
});

describe('penugasan kasubag ke unit pembuat', function () {
    it('menugaskan petugas aktif unit: status pembuatan, disposisi, riwayat, dan notifikasi', function () {
        [$p, $kasubag] = mwSampaiKasubag();
        $unit = mwUnit('izin-kegiatan', 2);
        $nonaktif = User::factory()->create(['aktif' => false]);
        $unit->petugas()->attach($nonaktif->id);
        $luar = mwUnit('rektorat', 1);

        app(RekomendasiKasubag::class)->jalankan($p, $kasubag, 'Mohon dibuatkan', $unit->id);
        $p = $p->fresh();

        $disposisi = $p->disposisi()->where('dari_user_id', $kasubag->id)->firstOrFail();
        $catatan = $p->riwayat->last()->catatan;
        $aktif = $unit->petugasAktif()->get();

        expect($p->status)->toBe(StatusPermohonan::Pembuatan)->and($p->unit_pembuat_id)->toBe($unit->id)
            ->and($disposisi->penerima->pluck('user_id')->sort()->values()->all())->toBe($aktif->pluck('id')->sort()->values()->all())
            ->and($disposisi->penerima->pluck('user_id')->contains($nonaktif->id))->toBeFalse()
            ->and($p->riwayat->last()->oleh)->toBe($kasubag->id)->and($p->riwayat->last()->ke_status)->toBe('pembuatan')
            ->and($catatan)->toContain($kasubag->name)->toContain($unit->nama)->toContain($aktif[0]->name)->toContain($aktif[1]->name)->toContain('Mohon dibuatkan');

        foreach ($aktif as $petugas) {
            expect(mwNotifikasi($petugas))->toContain('Permohonan ditugaskan kepada unit Anda untuk dibuatkan suratnya');
        }
        expect(mwNotifikasi(User::find($luar->petugas()->value('users.id'))))->toBeEmpty()->and(mwNotifikasi($nonaktif))->toBeEmpty();
    });

    it('memakai unit bawaan jenis layanan bila kasubag tidak memilih, dan pilihan kasubag mengalahkannya', function () {
        $bawaan = mwUnit('izin-kegiatan');
        $lain = mwUnit('rektorat');
        JenisPermohonan::firstWhere('kode', 'kegiatan')->update(['unit_pembuat_bawaan_id' => $bawaan->id]);

        [$p, $kasubag] = mwSampaiKasubag();
        app(RekomendasiKasubag::class)->jalankan($p, $kasubag);
        expect($p->fresh()->unit_pembuat_id)->toBe($bawaan->id);

        [$p2, $kasubag2] = mwSampaiKasubag();
        app(RekomendasiKasubag::class)->jalankan($p2, $kasubag2, null, $lain->id);
        expect($p2->fresh()->unit_pembuat_id)->toBe($lain->id)->and($p2->fresh()->status)->toBe(StatusPermohonan::Pembuatan);
    });

    it('menolak unit tidak aktif, tanpa petugas aktif, atau tak dikenal tanpa mengubah status', function () {
        [$p, $kasubag] = mwSampaiKasubag();
        $nonaktif = mwUnit('a');
        $nonaktif->update(['aktif' => false]);
        $kosong = UnitPembuat::create(['kode' => 'kosong', 'nama' => 'Kosong']);
        $kosong->petugas()->attach(User::factory()->create(['aktif' => false])->id);

        foreach ([$nonaktif->id, $kosong->id, '0199aaaa-0000-7000-8000-000000000000'] as $id) {
            expect(fn () => app(RekomendasiKasubag::class)->jalankan($p->fresh(), $kasubag, null, $id))->toThrow(ValidationException::class);
        }

        expect($p->fresh()->status)->toBe(StatusPermohonan::RekomendasiKasubag)->and($p->fresh()->unit_pembuat_id)->toBeNull();
    });

    it('tanpa unit sama sekali memakai jalur lama: langsung penerbitan oleh admin', function () {
        [$p, $kasubag] = mwSampaiKasubag();

        app(RekomendasiKasubag::class)->jalankan($p, $kasubag, 'Layak');

        expect($p->fresh()->status)->toBe(StatusPermohonan::Penerbitan)->and($p->fresh()->unit_pembuat_id)->toBeNull();

        $admin = User::factory()->create()->assignRole('admin-persuratan');
        expect($admin->can('terbitkanIzin', $p->fresh()))->toBeTrue();
    });

    it('hanya kasubag jabatan yang berwenang yang dapat menugaskan', function () {
        [$p] = mwSampaiKasubag();
        $unit = mwUnit();
        $bukan = User::factory()->create()->assignRole('kasubag');

        expect(fn () => app(RekomendasiKasubag::class)->jalankan($p, $bukan, null, $unit->id))->toThrow(AuthorizationException::class)
            ->and(fn () => app(RekomendasiKasubag::class)->jalankan($p, mwPejabat('wakil-dekan', 'wd-akademik'), null, $unit->id))->toThrow(AuthorizationException::class);
    });

    it('kotak masuk kasubag dan panel meneruskan unit pilihan', function () {
        $unit = mwUnit();
        [$p, $kasubag] = mwSampaiKasubag();

        Livewire::actingAs($kasubag)->test(KotakMasuk::class)->call('pilihTab', 'permohonan')->call('buka', 'permohonan:'.$p->id)
            ->assertSee('Unit pembuat surat')->assertSee($unit->nama)->set('unitPembuatId', $unit->id)->call('rekomendasi', $p->id);
        expect($p->fresh()->status)->toBe(StatusPermohonan::Pembuatan)->and($p->fresh()->unit_pembuat_id)->toBe($unit->id);

        [$p2, $kasubag2] = mwSampaiKasubag();
        Livewire::actingAs($kasubag2)->test(ViewPermohonan::class, ['record' => $p2->getKey()])
            ->callAction('rekomendasi', ['unit_pembuat_id' => $unit->id, 'catatan' => 'ok'])->assertNotified();
        expect($p2->fresh()->unit_pembuat_id)->toBe($unit->id);

        [$p3, $kasubag3] = mwSampaiKasubag();
        Livewire::actingAs($kasubag3)->test(ViewPermohonan::class, ['record' => $p3->getKey()])
            ->callAction('rekomendasi', ['unit_pembuat_id' => null])->assertHasActionErrors(['unit_pembuat_id' => 'required']);
    });
});

describe('otorisasi pembuat surat', function () {
    function mwDiPembuatan(): array
    {
        $unit = mwUnit('izin-kegiatan', 2);
        $unitLain = mwUnit('rektorat', 1);
        [$p, $kasubag] = mwSampaiKasubag();
        app(RekomendasiKasubag::class)->jalankan($p, $kasubag, null, $unit->id);
        $petugas = User::find($unit->petugas()->value('users.id'));
        $petugas->givePermissionTo('naskah.draf');
        $petugas->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

        return [$p->fresh(), $petugas, $unit, $unitLain, $kasubag];
    }

    it('hanya petugas unit terpilih (dan super-admin) yang boleh membuat draf; kasubag, admin, dan unit lain tidak', function () {
        [$p, $petugas, , $unitLain, $kasubag] = mwDiPembuatan();
        $admin = User::factory()->create()->assignRole('admin-persuratan');
        $super = User::factory()->create()->assignRole('super-admin');
        $petugasLain = User::find($unitLain->petugas()->value('users.id'));
        $petugasLain->givePermissionTo('naskah.draf');
        $kasubag->givePermissionTo('naskah.draf');
        $petugasTanpaIzin = User::factory()->create();
        $petugasTanpaIzin->unitPembuat()->attach($p->unit_pembuat_id);

        expect($petugas->can('terbitkanIzin', $p))->toBeTrue()
            ->and($super->can('terbitkanIzin', $p))->toBeTrue()
            ->and($admin->can('terbitkanIzin', $p))->toBeFalse()
            ->and($kasubag->can('terbitkanIzin', $p))->toBeFalse()
            ->and($petugasLain->can('terbitkanIzin', $p))->toBeFalse()
            ->and($petugasTanpaIzin->can('terbitkanIzin', $p))->toBeFalse();

        foreach ([$admin, $kasubag, $petugasLain, $petugasTanpaIzin] as $bukan) {
            expect(fn () => app(TerbitkanIzin::class)->jalankan($p, $bukan))->toThrow(AuthorizationException::class);
        }

        expect($p->fresh()->status)->toBe(StatusPermohonan::Pembuatan)->and($p->fresh()->naskah_izin_id)->toBeNull();
    });

    it('petugas membuat draf: status penerbitan, penyusun petugas, riwayat, lalu selesai saat naskah terbit', function () {
        [$p, $petugas, $unit] = mwDiPembuatan();

        $naskah = app(TerbitkanIzin::class)->jalankan($p, $petugas);
        $p = $p->fresh();

        expect($naskah->penyusun_id)->toBe($petugas->id)->and($p->status)->toBe(StatusPermohonan::Penerbitan)->and($p->naskah_izin_id)->toBe($naskah->id)
            ->and($p->riwayat->last()->catatan)->toContain($petugas->name)->toContain($unit->nama)
            ->and(DisposisiPenerima::where('user_id', $petugas->id)->first()->status)->toBe(StatusDisposisiPenerima::Ditindaklanjuti);

        // Saat penerbitan, hanya petugas unit (bukan admin lama) yang tetap berwenang; admin tidak.
        $admin = User::factory()->create()->assignRole('admin-persuratan');
        expect(fn () => app(TerbitkanIzin::class)->jalankan($p, $admin))->toThrow(AuthorizationException::class);

        // Jalur naskah yang ada tetap menyelesaikan permohonan.
        $naskah->forceFill(['status' => 'terbit', 'nomor' => '001/UN58.10/2026'])->save();
        app(SelesaikanPermohonanDariNaskah::class)->handle(new NaskahTerbit($naskah->fresh()));
        expect($p->fresh()->status)->toBe(StatusPermohonan::Selesai);
    });

    it('draf tidak dapat dibuat dua kali', function () {
        [$p, $petugas] = mwDiPembuatan();
        app(TerbitkanIzin::class)->jalankan($p, $petugas);

        expect(fn () => app(TerbitkanIzin::class)->jalankan($p->fresh(), $petugas))->toThrow(AuthorizationException::class);
    });

    it('tombol panel mengikuti kewenangan; petugas melihat permohonan unitnya saja', function () {
        [$p, $petugas, , $unitLain, $kasubag] = mwDiPembuatan();
        $petugas->assignRole('operator-layanan');
        $petugasLain = User::find($unitLain->petugas()->value('users.id'))->assignRole('operator-layanan');
        $petugasLain->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

        Livewire::actingAs($petugas)->test(ViewPermohonan::class, ['record' => $p->getKey()])
            ->assertActionVisible('terbitkanIzin')->assertActionHidden('rekomendasi');

        $this->actingAs($petugas);
        expect(PermohonanResource::getEloquentQuery()->pluck('id')->all())->toBe([$p->id])->and($petugas->can('view', $p))->toBeTrue();

        $this->actingAs($petugasLain);
        expect(PermohonanResource::getEloquentQuery()->pluck('id')->all())->toBe([])->and($petugasLain->can('view', $p))->toBeFalse();
        $kotak = Livewire::actingAs($kasubag)->test(KotakMasuk::class)->call('pilihTab', 'permohonan');
        $kotak->assertDontSee('Seminar Nasional');
    });

    it('pembatalan oleh ormawa tidak dapat dilakukan saat pembuatan; pengingat tertahan ditujukan ke petugas', function () {
        [$p, $petugas] = mwDiPembuatan();
        $pengurus = $p->pengaju;

        expect($pengurus->can('batalkan', $p))->toBeFalse();

        $penerima = app(NotifikasiAlur::class)->penerimaTahap($p->load('unitPembuat'), StatusPermohonan::Pembuatan);
        expect(collect($penerima[0])->pluck('id')->all())->toContain($petugas->id);
    });
});
