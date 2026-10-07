<?php

use App\Actions\Lpj\IsiLpj;
use App\Actions\Lpj\NilaiLpj;
use App\Actions\Permohonan\AjukanPermohonan;
use App\Actions\Permohonan\TransisiPermohonan;
use App\Enums\StatusPermohonan;
use App\Filament\Resources\Lpjs\NilaiRelationManager;
use App\Filament\Resources\Lpjs\Pages\ListLpjs;
use App\Filament\Resources\Lpjs\Pages\ViewLpj;
use App\Filament\Resources\RubrikLpjs\Pages\ManageRubrikLpjs;
use App\Livewire\Ormawa\IsiLpj as KomponenLpj;
use App\Models\Aktivitas;
use App\Models\Jabatan;
use App\Models\JenisPermohonan;
use App\Models\Lpj;
use App\Models\NilaiLpj as Nilai;
use App\Models\Ormawa;
use App\Models\PemangkuJabatan;
use App\Models\PengurusOrmawa;
use App\Models\Permohonan;
use App\Models\RubrikLpj;
use App\Models\User;
use App\Support\Pengaturan;
use Carbon\CarbonImmutable;
use Database\Seeders\JenisNaskahSeeder;
use Database\Seeders\JenisPermohonanSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Database\Seeders\RubrikLpjSeeder;
use Database\Seeders\StrukturFkipSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
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
    $this->seed([PeranDanIzinSeeder::class, StrukturFkipSeeder::class, PengaturanSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class, JenisPermohonanSeeder::class, RubrikLpjSeeder::class]);
    Filament::setCurrentPanel('admin');
    CarbonImmutable::setTestNow('2026-06-01 09:00:00');
});

afterEach(fn () => CarbonImmutable::setTestNow());

function penilai(string $peran, string $kodeJabatan, bool $plt = false): User
{
    $u = User::factory()->create()->assignRole($peran);
    $u->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
    PemangkuJabatan::create(['jabatan_id' => Jabatan::firstWhere('kode', $kodeJabatan)->id, 'user_id' => $u->id, 'mulai' => '2026-01-01', 'plt' => $plt]);

    return $u;
}

/** @return array{0: Lpj, 1: Ormawa, 2: User} LPJ berstatus diajukan */
function lpjDiajukan(string $nama = 'HIMA Nilai'): array
{
    $o = Ormawa::create(['nama' => $nama, 'tingkat' => 'prodi']);
    $o->sk()->create(['nomor_sk' => 'SK/1', 'tanggal_sk' => '2026-01-01', 'periode_mulai' => '2026-01-01', 'periode_selesai' => '2026-12-31']);
    $u = User::factory()->create(['nip_nim' => (string) random_int(1000000, 9999999)])->assignRole('pengurus-ormawa');
    PengurusOrmawa::create(['ormawa_id' => $o->id, 'user_id' => $u->id, 'nama' => $u->name, 'jabatan' => 'ketua']);
    RateLimiter::clear('ajukan-permohonan:'.$u->id);

    CarbonImmutable::setTestNow('2026-04-01 09:00:00');
    $p = app(AjukanPermohonan::class)->jalankan($u, $o, JenisPermohonan::firstWhere('kode', 'kegiatan'), [
        'nama_kegiatan' => 'Seminar Nilai', 'perihal' => 'Izin', 'tanggal_mulai' => '2026-05-10', 'tanggal_selesai' => '2026-05-11', 'deskripsi' => 'D',
        'penanggung_jawab' => ['ketua' => ['nama' => 'B']],
        'berkas' => ['surat_permohonan' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view', 'proposal' => 'https://drive.google.com/file/d/1ZyXwVuTsRqPoNmLkJi/view'],
    ]);
    CarbonImmutable::setTestNow('2026-06-01 09:00:00');
    $p->forceFill(['status' => StatusPermohonan::Penerbitan])->saveQuietly();
    app(TransisiPermohonan::class)->dalamKunci($p, fn (Permohonan $s) => app(TransisiPermohonan::class)->ke($s, StatusPermohonan::Selesai, $u));

    $lpj = Lpj::where('permohonan_id', $p->id)->firstOrFail();
    app(IsiLpj::class)->jalankan($lpj, $u, [
        'tanggal_pelaksanaan' => '2026-05-10', 'jumlah_peserta' => 10, 'ringkasan' => 'Sukses', 'berkas_lpj' => 'https://drive.google.com/file/d/1LpjLpjLpjLpjLpjLpj/view',
    ]);

    return [$lpj->fresh(), $o, $u];
}

/** @return array<string, User> */
function timPenilai(): array
{
    return [
        'dekan' => penilai('dekan', 'dekan'),
        'wd1' => penilai('wakil-dekan', 'wd-akademik'),
        'wd3' => penilai('wakil-dekan', 'wd-kemahasiswaan'),
        'kasubag' => penilai('kasubag', 'kasubag-umum'),
    ];
}

function isianPimpinan(float $ketepatan = 20, float $kepatuhan = 5): array
{
    return ['ketepatan' => ['nilai' => $ketepatan, 'catatan' => 'ok'], 'kepatuhan' => ['nilai' => $kepatuhan]];
}

function isianKasubag(float $kelengkapan = 20, float $kontribusi = 5): array
{
    return ['kelengkapan' => ['nilai' => $kelengkapan], 'kontribusi_fakultas' => ['nilai' => $kontribusi, 'catatan' => 'bagus']];
}

describe('rubrik', function () {
    it('menyemai rubrik lama dengan total maksimum 100', function () {
        $r = RubrikLpj::orderBy('urutan')->get()->keyBy('kode');

        expect($r->keys()->all())->toBe(['ketepatan', 'kepatuhan', 'kelengkapan', 'kontribusi_fakultas'])
            ->and($r['ketepatan']->nilai_maks)->toBe(20)->and($r['kepatuhan']->nilai_maks)->toBe(5)
            ->and($r['kelengkapan']->nilai_maks)->toBe(20)->and($r['kontribusi_fakultas']->nilai_maks)->toBe(5)
            ->and($r['ketepatan']->penilai_jabatan)->toBe(['dekan', 'wd-akademik', 'wd-kemahasiswaan'])
            ->and($r['kelengkapan']->penilai_jabatan)->toBe(['kasubag-umum'])
            ->and(RubrikLpj::totalMaks())->toBe(100);
    });

    it('menolak penilai berkode jabatan tak dikenal dan nilai maksimum di luar 1–100', function () {
        expect(fn () => RubrikLpj::create(['kode' => 'x', 'nama' => 'X', 'nilai_maks' => 10, 'penilai_jabatan' => ['jabatan-hantu']]))->toThrow(ValidationException::class)
            ->and(fn () => RubrikLpj::create(['kode' => 'y', 'nama' => 'Y', 'nilai_maks' => 10, 'penilai_jabatan' => []]))->toThrow(ValidationException::class)
            ->and(fn () => RubrikLpj::create(['kode' => 'z', 'nama' => 'Z', 'nilai_maks' => 101, 'penilai_jabatan' => ['dekan']]))->toThrow(ValidationException::class)
            ->and(fn () => RubrikLpj::create(['kode' => 'w', 'nama' => 'W', 'nilai_maks' => 0, 'penilai_jabatan' => ['dekan']]))->toThrow(ValidationException::class);
    });

    it('idempoten saat disemai ulang', function () {
        $this->seed(RubrikLpjSeeder::class);

        expect(RubrikLpj::count())->toBe(4);
    });
});

describe('penilaian', function () {
    it('nilai akhir hanya setelah semua penilai lengkap dan 100 untuk nilai maksimum', function () {
        [$lpj] = lpjDiajukan();
        ['dekan' => $dekan, 'wd1' => $wd1, 'wd3' => $wd3, 'kasubag' => $kasubag] = timPenilai();

        $h = app(NilaiLpj::class)->jalankan($lpj, $dekan, isianPimpinan());
        expect($h['lengkap'])->toBeFalse()->and($lpj->fresh()->nilai_akhir)->toBeNull()->and($lpj->fresh()->status)->toBe('diajukan');

        app(NilaiLpj::class)->jalankan($lpj, $wd1, isianPimpinan());
        app(NilaiLpj::class)->jalankan($lpj, $kasubag, isianKasubag());
        expect($lpj->fresh()->nilai_akhir)->toBeNull();

        $h = app(NilaiLpj::class)->jalankan($lpj, $wd3, isianPimpinan());

        expect($h['lengkap'])->toBeTrue()->and($lpj->fresh()->status)->toBe('dinilai')->and((float) $lpj->fresh()->nilai_akhir)->toBe(100.0)
            ->and($lpj->nilai()->count())->toBe(8);
    });

    it('menjumlahkan nilai sebenarnya dan mendukung rumus persen', function () {
        [$lpj] = lpjDiajukan();
        ['dekan' => $dekan, 'wd1' => $wd1, 'wd3' => $wd3, 'kasubag' => $kasubag] = timPenilai();

        app(NilaiLpj::class)->jalankan($lpj, $dekan, isianPimpinan(18, 4));
        app(NilaiLpj::class)->jalankan($lpj, $wd1, isianPimpinan(15.5, 5));
        app(NilaiLpj::class)->jalankan($lpj, $kasubag, isianKasubag(10, 2.5));
        app(NilaiLpj::class)->jalankan($lpj, $wd3, isianPimpinan(20, 3));

        // 18+15.5+20 = 53.5 ; 4+5+3 = 12 ; 10 ; 2.5 → 78.0
        expect((float) $lpj->fresh()->nilai_akhir)->toBe(78.0);

        Pengaturan::set('rumus_nilai_lpj', 'persen');
        [$lpj2] = lpjDiajukan('HIMA Dua');
        app(NilaiLpj::class)->jalankan($lpj2, $dekan, isianPimpinan(10, 2));
        app(NilaiLpj::class)->jalankan($lpj2, $wd1, isianPimpinan(10, 2));
        app(NilaiLpj::class)->jalankan($lpj2, $wd3, isianPimpinan(10, 2));
        app(NilaiLpj::class)->jalankan($lpj2, $kasubag, isianKasubag(10, 2));
        // jumlah = 30+6+10+2 = 48 → 48%
        expect((float) $lpj2->fresh()->nilai_akhir)->toBe(48.0);
    });

    it('penilai dapat merevisi nilainya sebelum final tanpa menggandakan baris', function () {
        [$lpj] = lpjDiajukan();
        ['dekan' => $dekan] = timPenilai();

        app(NilaiLpj::class)->jalankan($lpj, $dekan, ['ketepatan' => ['nilai' => 10, 'catatan' => 'awal']]);
        app(NilaiLpj::class)->jalankan($lpj, $dekan, ['ketepatan' => ['nilai' => 18, 'catatan' => 'revisi']]);

        $baris = $lpj->nilai()->get();
        expect($baris)->toHaveCount(1)->and((float) $baris[0]->nilai)->toBe(18.0)->and($baris[0]->catatan)->toBe('revisi')->and($baris[0]->penilai_user_id)->toBe($dekan->id);
    });

    it('menolak penilai yang bukan pemangku jabatan rubrik, WD tak tercantum, dan pengguna lain', function () {
        [$lpj] = lpjDiajukan();
        timPenilai();
        $wdKeuangan = penilai('wakil-dekan', 'wd-umum-keuangan');
        $kasubag = User::whereHas('roles', fn ($q) => $q->where('name', 'kasubag'))->first();

        expect(fn () => app(NilaiLpj::class)->jalankan($lpj, $wdKeuangan, isianPimpinan()))->toThrow(AuthorizationException::class)
            ->and(fn () => app(NilaiLpj::class)->jalankan($lpj, User::factory()->create()->assignRole('dekan'), isianPimpinan()))->toThrow(AuthorizationException::class)
            ->and(fn () => app(NilaiLpj::class)->jalankan($lpj, User::factory()->create()->assignRole('admin-persuratan'), isianPimpinan()))->toThrow(AuthorizationException::class)
            ->and(fn () => app(NilaiLpj::class)->jalankan($lpj, $kasubag, isianPimpinan()))->toThrow(ValidationException::class);
        expect(Nilai::count())->toBe(0);
    });

    it('menolak nilai di luar 0..maks, bukan angka, rubrik tak dikenal, dan isian kosong', function (array $isian) {
        [$lpj] = lpjDiajukan();
        ['dekan' => $dekan] = timPenilai();

        expect(fn () => app(NilaiLpj::class)->jalankan($lpj, $dekan, $isian))->toThrow(ValidationException::class);
        expect(Nilai::count())->toBe(0);
    })->with([
        'lebih dari maks' => [['ketepatan' => ['nilai' => 21]]],
        'negatif' => [['ketepatan' => ['nilai' => -1]]],
        'bukan angka' => [['ketepatan' => ['nilai' => 'bagus']]],
        'tanpa nilai' => [['ketepatan' => ['catatan' => 'x']]],
        'kepatuhan lebih dari 5' => [['kepatuhan' => ['nilai' => 5.01]]],
        'rubrik hantu' => [['hantu' => ['nilai' => 1]]],
        'kosong' => [[]],
        'sebagian valid sebagian tidak (atomik)' => [['ketepatan' => ['nilai' => 10], 'kepatuhan' => ['nilai' => 9]]],
    ]);

    it('menerima batas bawah dan atas dan nilai pecahan', function () {
        [$lpj] = lpjDiajukan();
        ['dekan' => $dekan] = timPenilai();

        app(NilaiLpj::class)->jalankan($lpj, $dekan, ['ketepatan' => ['nilai' => 0], 'kepatuhan' => ['nilai' => 4.75]]);

        expect($lpj->nilai()->pluck('nilai')->map(fn ($n) => (float) $n)->sort()->values()->all())->toBe([0.0, 4.75]);
    });

    it('hanya LPJ berstatus diajukan yang dapat dinilai, dan final tidak dapat diubah', function () {
        [$lpj] = lpjDiajukan();
        ['dekan' => $dekan, 'wd1' => $wd1, 'wd3' => $wd3, 'kasubag' => $kasubag] = timPenilai();

        $lpj->forceFill(['status' => 'draf'])->saveQuietly();
        expect(fn () => app(NilaiLpj::class)->jalankan($lpj->fresh(), $dekan, isianPimpinan()))->toThrow(AuthorizationException::class);

        $lpj->forceFill(['status' => 'diajukan'])->saveQuietly();
        foreach ([$dekan, $wd1, $wd3] as $p) {
            app(NilaiLpj::class)->jalankan($lpj->fresh(), $p, isianPimpinan());
        }
        app(NilaiLpj::class)->jalankan($lpj->fresh(), $kasubag, isianKasubag());

        expect(fn () => app(NilaiLpj::class)->jalankan($lpj->fresh(), $dekan, isianPimpinan(1, 1)))->toThrow(AuthorizationException::class);
        expect((float) $lpj->fresh()->nilai_akhir)->toBe(100.0);
    });

    it('Plt WD dapat menilai dan masa jabatan berakhir tidak', function () {
        [$lpj] = lpjDiajukan();
        $plt = penilai('wakil-dekan', 'wd-akademik', plt: true);
        $berakhir = User::factory()->create()->assignRole('wakil-dekan');
        PemangkuJabatan::create(['jabatan_id' => Jabatan::firstWhere('kode', 'wd-kemahasiswaan')->id, 'user_id' => $berakhir->id, 'mulai' => '2020-01-01', 'selesai' => '2025-12-31', 'plt' => true]);

        app(NilaiLpj::class)->jalankan($lpj, $plt, isianPimpinan(12, 3));
        expect(fn () => app(NilaiLpj::class)->jalankan($lpj, $berakhir, isianPimpinan()))->toThrow(AuthorizationException::class);
        expect($lpj->nilai()->where('penilai_user_id', $plt->id)->count())->toBe(2);
    });

    it('mengikuti perubahan rubrik: rubrik nonaktif tidak dihitung', function () {
        [$lpj] = lpjDiajukan();
        ['dekan' => $dekan, 'wd1' => $wd1, 'wd3' => $wd3] = timPenilai();
        RubrikLpj::whereIn('kode', ['kelengkapan', 'kontribusi_fakultas'])->update(['aktif' => false]);

        foreach ([$dekan, $wd1, $wd3] as $p) {
            app(NilaiLpj::class)->jalankan($lpj->fresh(), $p, isianPimpinan());
        }

        expect((float) $lpj->fresh()->nilai_akhir)->toBe(75.0)->and($lpj->fresh()->status)->toBe('dinilai')->and(RubrikLpj::totalMaks())->toBe(75);
    });

    it('mencatat penilaian di log aktivitas', function () {
        [$lpj] = lpjDiajukan();
        ['dekan' => $dekan] = timPenilai();
        $this->actingAs($dekan);

        app(NilaiLpj::class)->jalankan($lpj, $dekan, isianPimpinan());

        expect(Aktivitas::where('log_name', 'nilai-lpj')->where('event', 'created')->count())->toBe(2);
    });
});

describe('antarmuka', function () {
    it('penilai memberi nilai dari halaman detail panel; pengguna lain tidak melihat aksinya', function () {
        [$lpj] = lpjDiajukan();
        ['dekan' => $dekan, 'kasubag' => $kasubag] = timPenilai();
        $admin = User::factory()->create()->assignRole('admin-persuratan');
        $admin->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

        Livewire::actingAs($admin)->test(ViewLpj::class, ['record' => $lpj->getKey()])->assertActionHidden('nilai');

        Livewire::actingAs($dekan)->test(ViewLpj::class, ['record' => $lpj->getKey()])->assertActionVisible('nilai')
            ->callAction('nilai', ['nilai' => ['ketepatan' => ['nilai' => 17, 'catatan' => 'baik'], 'kepatuhan' => ['nilai' => 4]]])->assertNotified();
        expect($lpj->nilai()->count())->toBe(2);

        Livewire::actingAs($kasubag)->test(ViewLpj::class, ['record' => $lpj->getKey()])->assertActionVisible('nilai');
    });

    it('menampilkan matriks nilai dan membatasi daftar LPJ untuk pembina', function () {
        [$lpj, $o] = lpjDiajukan();
        [$lpjLain] = lpjDiajukan('HIMA Lain');
        ['dekan' => $dekan] = timPenilai();
        app(NilaiLpj::class)->jalankan($lpj, $dekan, isianPimpinan(9, 2));
        $pembina = User::factory()->create()->assignRole('pembina-ormawa');
        $pembina->givePermissionTo('lpj.lihat');
        $pembina->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();
        $o->update(['pembina_user_id' => $pembina->id]);

        Livewire::actingAs($dekan)->test(NilaiRelationManager::class, ['ownerRecord' => $lpj, 'pageClass' => ViewLpj::class])
            ->assertCanSeeTableRecords($lpj->nilai)->assertSee('Ketepatan pelaksanaan')->assertSee('Dekan');
        Livewire::actingAs($dekan)->test(ListLpjs::class)->assertCanSeeTableRecords([$lpj, $lpjLain]);
        Livewire::actingAs($pembina)->test(ListLpjs::class)->assertCanSeeTableRecords([$lpj])->assertCanNotSeeTableRecords([$lpjLain]);
    });

    it('ormawa melihat nilai akhir dan catatan penilai, tetapi tidak sebelum final', function () {
        [$lpj, $o, $u] = lpjDiajukan();
        ['dekan' => $dekan, 'wd1' => $wd1, 'wd3' => $wd3, 'kasubag' => $kasubag] = timPenilai();

        app(NilaiLpj::class)->jalankan($lpj, $dekan, ['ketepatan' => ['nilai' => 20, 'catatan' => 'Catatan dekan RAHASIA-NILAI'], 'kepatuhan' => ['nilai' => 5]]);
        Livewire::actingAs($u)->test(KomponenLpj::class, ['ormawa' => $o->id, 'lpj' => $lpj->id])->assertDontSee('Nilai akhir')->assertDontSee('RAHASIA-NILAI');

        app(NilaiLpj::class)->jalankan($lpj, $wd1, isianPimpinan());
        app(NilaiLpj::class)->jalankan($lpj, $wd3, isianPimpinan());
        app(NilaiLpj::class)->jalankan($lpj, $kasubag, isianKasubag());

        Livewire::actingAs($u)->test(KomponenLpj::class, ['ormawa' => $o->id, 'lpj' => $lpj->id])
            ->assertSee('Nilai akhir')->assertSee('100.00')->assertSee('Catatan dekan RAHASIA-NILAI')->assertSee('bagus');
    });

    it('mengelola rubrik hanya oleh pemegang master.kelola', function () {
        $operator = User::factory()->create()->assignRole('operator-layanan');
        $operator->givePermissionTo('master.kelola');
        $operator->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

        $this->actingAs($operator)->get('/admin/rubrik-lpj')->assertOk()->assertSee('100');

        Livewire::actingAs($operator)->test(ManageRubrikLpjs::class)
            ->callAction(TestAction::make(CreateAction::class), ['kode' => 'inovasi', 'nama' => 'Inovasi', 'nilai_maks' => 10, 'penilai_jabatan' => ['dekan'], 'urutan' => 5])
            ->assertHasNoActionErrors();
        expect(RubrikLpj::totalMaks())->toBe(110);

        $pegawai = User::factory()->create()->assignRole('pegawai');
        $this->actingAs($pegawai)->get('/admin/rubrik-lpj')->assertForbidden();
    });
});
