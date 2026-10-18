<?php

use App\Actions\Disposisi\BuatDisposisi;
use App\Actions\Masuk\RegistrasiSuratMasuk;
use App\Actions\Masuk\TeruskanSuratKeDekan;
use App\Enums\JenisPengirim;
use App\Filament\Resources\SuratMasuks\Pages\CreateSuratMasuk;
use App\Filament\Resources\SuratMasuks\Pages\ListSuratMasuks;
use App\Livewire\Pimpinan\KotakMasuk;
use App\Models\Aktivitas;
use App\Models\Jabatan;
use App\Models\Permohonan;
use App\Models\SuratMasuk;
use App\Models\UnitKerja;
use App\Models\User;
use App\Notifications\NotifikasiTahap;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Database\Seeders\StrukturFkipSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    $this->seed([PeranDanIzinSeeder::class, StrukturFkipSeeder::class, RegisterNomorSeeder::class]);
    Filament::setCurrentPanel('admin');
});

function penggunaSk(string $peran): User
{
    $u = User::factory()->create()->assignRole($peran);
    $u->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

    return $u;
}

function dataSk(array $ubah = []): array
{
    return $ubah + [
        'nomor_surat' => '55/UKM/2026', 'tanggal_surat' => now()->toDateString(), 'asal' => 'UKM Pecinta Alam Kota',
        'perihal' => 'Permohonan kerja sama kegiatan', 'klasifikasi_keamanan' => 'biasa', 'derajat_kecepatan' => 'biasa',
        'pindaian_url' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view',
    ];
}

function eksternalSk(array $ubah = []): array
{
    return dataSk($ubah + [
        'jenis_pengirim' => 'eksternal', 'pengirim_nama' => 'Rani Putri', 'pengirim_instansi' => 'UKM Pecinta Alam Kota',
        'pengirim_surel' => 'rani@contoh.test', 'pengirim_wa' => '081234567890',
    ]);
}

describe('peran dan jabatan', function () {
    it('menyediakan peran staf-khusus dengan izin registrasi dan penerusan, tanpa hak dekan/admin', function () {
        $staf = penggunaSk('staf-khusus');

        expect($staf->can('masuk.registrasi'))->toBeTrue()
            ->and($staf->can('masuk.teruskan'))->toBeTrue()
            ->and($staf->can('disposisi.buat'))->toBeFalse()
            ->and($staf->can('permohonan.validasi'))->toBeFalse()
            ->and($staf->canAccessPanel(Filament::getCurrentPanel()))->toBeTrue()
            ->and(penggunaSk('admin-persuratan')->can('masuk.teruskan'))->toBeFalse()
            ->and(penggunaSk('dekan')->can('masuk.teruskan'))->toBeFalse();
    });

    it('membuat jabatan Staf Khusus Dekan secara idempoten', function () {
        $this->seed(StrukturFkipSeeder::class);

        expect(Jabatan::where('kode', 'staf-khusus-dekan')->count())->toBe(1);
    });

    it('mewajibkan MFA untuk staf khusus', function () {
        expect(User::factory()->create()->assignRole('staf-khusus')->wajibMfa())->toBeTrue();
    });
});

describe('registrasi pengirim', function () {
    it('staf khusus meregistrasi atas nama pengirim eksternal; kontak terenkripsi dan surat menunggu penerusan', function () {
        $staf = penggunaSk('staf-khusus');
        $surat = app(RegistrasiSuratMasuk::class)->jalankan(eksternalSk(), $staf);

        expect($surat->jenis_pengirim)->toBe(JenisPengirim::Eksternal)
            ->and($surat->pengirim_nama)->toBe('Rani Putri')
            ->and($surat->pengirim_surel)->toBe('rani@contoh.test')
            ->and($surat->diteruskan_pada)->toBeNull()
            ->and($surat->diregistrasi_oleh)->toBe($staf->id);

        $mentah = DB::table('surat_masuk')->where('id', $surat->id)->first();
        expect($mentah->pengirim_surel)->not->toContain('rani@contoh.test')
            ->and($mentah->pengirim_wa)->not->toContain('081234567890')
            ->and(json_encode($surat->toArray()))->not->toContain('rani@contoh.test');
        expect(Aktivitas::where('subject_id', $surat->id)->get()->pluck('properties')->toJson())->not->toContain('rani@contoh.test');
    });

    it('mewajibkan nama dan salah satu kontak untuk eksternal oleh staf khusus', function (array $ubah) {
        expect(fn () => app(RegistrasiSuratMasuk::class)->jalankan(eksternalSk($ubah), penggunaSk('staf-khusus')))
            ->toThrow(ValidationException::class);
        expect(SuratMasuk::count())->toBe(0);
    })->with([
        'tanpa nama' => [['pengirim_nama' => '']],
        'tanpa kontak' => [['pengirim_surel' => '', 'pengirim_wa' => '']],
        'surel tak sah' => [['pengirim_surel' => 'bukan-surel', 'pengirim_wa' => '']],
        'wa tak sah' => [['pengirim_surel' => '', 'pengirim_wa' => 'abc']],
    ]);

    it('menerima salah satu kontak saja', function () {
        $surat = app(RegistrasiSuratMasuk::class)->jalankan(eksternalSk(['pengirim_wa' => '']), penggunaSk('staf-khusus'));

        expect($surat->pengirim_wa)->toBeNull()->and($surat->pengirim_surel)->toBe('rani@contoh.test');
    });

    it('prodi mewajibkan unit kerja yang ada', function () {
        $staf = penggunaSk('staf-khusus');
        $prodi = UnitKerja::create(['kode' => 'PMAT', 'nama' => 'Pendidikan Matematika']);

        expect(fn () => app(RegistrasiSuratMasuk::class)->jalankan(dataSk(['jenis_pengirim' => 'prodi']), $staf))->toThrow(ValidationException::class);
        expect(fn () => app(RegistrasiSuratMasuk::class)->jalankan(dataSk(['jenis_pengirim' => 'prodi', 'pengirim_unit_kerja_id' => (string) fake()->uuid()]), $staf))->toThrow(ValidationException::class);

        $surat = app(RegistrasiSuratMasuk::class)->jalankan(dataSk(['jenis_pengirim' => 'prodi', 'pengirim_unit_kerja_id' => $prodi->id]), $staf);

        expect($surat->jenis_pengirim)->toBe(JenisPengirim::Prodi)->and($surat->pengirimUnitKerja->nama)->toBe('Pendidikan Matematika');
    });

    it('menolak jenis pengirim tak dikenal', function () {
        expect(fn () => app(RegistrasiSuratMasuk::class)->jalankan(dataSk(['jenis_pengirim' => 'siluman']), penggunaSk('staf-khusus')))
            ->toThrow(ValidationException::class);
    });

    it('menerima jenis ormawa tanpa data tambahan', function () {
        $surat = app(RegistrasiSuratMasuk::class)->jalankan(dataSk(['jenis_pengirim' => 'ormawa']), penggunaSk('staf-khusus'));

        expect($surat->jenis_pengirim)->toBe(JenisPengirim::Ormawa);
    });

    it('registrasi admin (jalur lama) tanpa jenis tetap sah, eksternal bawaan, dan langsung diteruskan', function () {
        $admin = penggunaSk('admin-persuratan');
        $surat = app(RegistrasiSuratMasuk::class)->jalankan(dataSk(), $admin);

        expect($surat->jenis_pengirim)->toBe(JenisPengirim::Eksternal)
            ->and($surat->sudahDiteruskan())->toBeTrue()
            ->and($surat->diteruskan_oleh)->toBe($admin->id);
    });
});

describe('penerusan staf khusus ke dekan', function () {
    it('meneruskan sekali, mencatat pelaku dan activitylog, lalu memberi tahu dekan', function () {
        $staf = penggunaSk('staf-khusus');
        $dekan = penggunaSk('dekan');
        $surat = app(RegistrasiSuratMasuk::class)->jalankan(eksternalSk(), $staf);

        $surat = app(TeruskanSuratKeDekan::class)->jalankan($surat, $staf, 'Mohon arahan');

        expect($surat->sudahDiteruskan())->toBeTrue()->and($surat->diteruskan_oleh)->toBe($staf->id);
        $log = Aktivitas::where('log_name', 'surat-masuk')->where('event', 'teruskan')->where('subject_id', $surat->id)->first();
        expect($log)->not->toBeNull()->and($log->causer_id)->toBe($staf->id)->and($log->properties['ke'])->toBe('dekan');

        Notification::assertSentTo($dekan, NotifikasiTahap::class, fn ($n) => $n->judul === 'Surat masuk diteruskan kepada Dekan' && str_contains($n->ringkas, 'Mohon arahan'));

        expect(fn () => app(TeruskanSuratKeDekan::class)->jalankan($surat, $staf))->toThrow(AuthorizationException::class);
    });

    it('memberi tahu staf khusus saat surat baru menunggu, bukan dekan', function () {
        $staf = penggunaSk('staf-khusus');
        $dekan = penggunaSk('dekan');
        $pencatat = penggunaSk('staf-khusus');

        app(RegistrasiSuratMasuk::class)->jalankan(eksternalSk(), $pencatat);

        Notification::assertSentTo($staf, NotifikasiTahap::class, fn ($n) => $n->judul === 'Surat masuk menunggu diteruskan ke Dekan');
        Notification::assertNotSentTo($dekan, NotifikasiTahap::class);
    });

    it('menolak peran selain staf khusus meneruskan', function (string $peran) {
        $surat = app(RegistrasiSuratMasuk::class)->jalankan(eksternalSk(), penggunaSk('staf-khusus'));

        expect(fn () => app(TeruskanSuratKeDekan::class)->jalankan($surat, penggunaSk($peran)))->toThrow(AuthorizationException::class);
        expect($surat->fresh()->sudahDiteruskan())->toBeFalse();
    })->with(['admin-persuratan', 'dekan', 'pegawai', 'kasubag']);

    it('tidak meneruskan surat jalur langsung yang sudah diteruskan', function () {
        $surat = app(RegistrasiSuratMasuk::class)->jalankan(dataSk(), penggunaSk('admin-persuratan'));

        expect(fn () => app(TeruskanSuratKeDekan::class)->jalankan($surat, penggunaSk('staf-khusus')))->toThrow(AuthorizationException::class);
    });
});

describe('dekan hanya menangani yang sudah diteruskan', function () {
    it('dekan tidak melihat dan tidak dapat mendisposisikan surat yang belum diteruskan', function () {
        $dekan = penggunaSk('dekan');
        $pegawai = penggunaSk('pegawai');
        $surat = app(RegistrasiSuratMasuk::class)->jalankan(eksternalSk(), penggunaSk('staf-khusus'));

        expect($dekan->can('view', $surat))->toBeFalse()
            ->and($dekan->can('lihatMetadata', $surat))->toBeFalse()
            ->and(SuratMasuk::tampakBagi($dekan)->count())->toBe(0);
        expect(fn () => app(BuatDisposisi::class)->jalankan($surat, $dekan, [$pegawai->id], ['tindak_lanjuti']))->toThrow(AuthorizationException::class);

        app(TeruskanSuratKeDekan::class)->jalankan($surat, penggunaSk('staf-khusus'));

        expect($dekan->can('view', $surat->fresh()))->toBeTrue()
            ->and(SuratMasuk::tampakBagi($dekan)->count())->toBe(1);
        expect(app(BuatDisposisi::class)->jalankan($surat->fresh(), $dekan, [$pegawai->id], ['tindak_lanjuti'])->penerima)->toHaveCount(1);
    });

    it('daftar Filament dan kotak masuk dekan hanya memuat surat yang diteruskan', function () {
        $staf = penggunaSk('staf-khusus');
        $dekan = penggunaSk('dekan');
        $tertahan = app(RegistrasiSuratMasuk::class)->jalankan(eksternalSk(['nomor_surat' => 'TAHAN-1']), $staf);
        $lanjut = app(TeruskanSuratKeDekan::class)->jalankan(app(RegistrasiSuratMasuk::class)->jalankan(eksternalSk(['nomor_surat' => 'LANJUT-1']), $staf), $staf);

        $this->actingAs($dekan);
        Livewire::test(ListSuratMasuks::class)->assertCanSeeTableRecords([$lanjut])->assertCanNotSeeTableRecords([$tertahan]);
        Livewire::test(KotakMasuk::class)->assertSee($lanjut->perihal)->assertViewHas('surat', fn ($s) => $s->pluck('id')->all() === [$lanjut->id]);

        $this->actingAs($staf);
        Livewire::test(ListSuratMasuks::class)->assertCanSeeTableRecords([$lanjut, $tertahan]);
    });

    it('surat rahasia yang belum diteruskan tertutup juga bagi dekan', function () {
        $surat = app(RegistrasiSuratMasuk::class)->jalankan(eksternalSk(['klasifikasi_keamanan' => 'rahasia']), penggunaSk('staf-khusus'));

        expect(penggunaSk('dekan')->can('view', $surat))->toBeFalse();
    });

    it('super-admin tetap dapat melihat dan mendisposisikan (pengecualian Gate::before)', function () {
        $surat = app(RegistrasiSuratMasuk::class)->jalankan(eksternalSk(), penggunaSk('staf-khusus'));
        $sa = penggunaSk('super-admin');

        expect($sa->can('view', $surat))->toBeTrue();
        expect(app(BuatDisposisi::class)->jalankan($surat, $sa, [penggunaSk('pegawai')->id], ['tindak_lanjuti']))->not->toBeNull();
    });
});

describe('antarmuka Filament', function () {
    it('formulir registrasi menyimpan data pengirim eksternal oleh staf khusus', function () {
        $this->actingAs(penggunaSk('staf-khusus'));

        Livewire::test(CreateSuratMasuk::class)
            ->fillForm([
                'tanggal_terima' => now()->toDateTimeString(), 'nomor_surat' => '9/UKM/2026', 'tanggal_surat' => now()->toDateString(),
                'asal' => 'UKM Musik', 'perihal' => 'Undangan', 'klasifikasi_keamanan' => 'biasa', 'derajat_kecepatan' => 'biasa',
                'jenis_pengirim' => 'eksternal', 'pengirim_nama' => 'Budi', 'pengirim_surel' => 'budi@contoh.test',
                'pindaian_url' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $surat = SuratMasuk::firstWhere('nomor_surat', '9/UKM/2026');
        expect($surat->pengirim_nama)->toBe('Budi')->and($surat->sudahDiteruskan())->toBeFalse();
    });

    it('formulir menolak eksternal tanpa nama/kontak bagi staf khusus', function () {
        $this->actingAs(penggunaSk('staf-khusus'));

        Livewire::test(CreateSuratMasuk::class)
            ->fillForm([
                'tanggal_terima' => now()->toDateTimeString(), 'nomor_surat' => '9/UKM/2026', 'tanggal_surat' => now()->toDateString(),
                'asal' => 'UKM Musik', 'perihal' => 'Undangan', 'jenis_pengirim' => 'eksternal',
                'pindaian_url' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view',
            ])
            ->call('create')
            ->assertHasFormErrors(['pengirim_nama', 'pengirim_surel', 'pengirim_wa']);
    });

    it('aksi Teruskan ke dekan hanya tampil dan berjalan untuk staf khusus', function () {
        $staf = penggunaSk('staf-khusus');
        $surat = app(RegistrasiSuratMasuk::class)->jalankan(eksternalSk(), $staf);

        $this->actingAs(penggunaSk('admin-persuratan'));
        Livewire::test(ListSuratMasuks::class)->assertTableActionHidden('teruskan', $surat);

        $this->actingAs($staf);
        Livewire::test(ListSuratMasuks::class)
            ->assertTableActionVisible('teruskan', $surat)
            ->callTableAction('teruskan', $surat, ['catatan' => 'Segera'])
            ->assertHasNoTableActionErrors()
            ->assertTableActionHidden('teruskan', $surat);

        expect($surat->fresh()->sudahDiteruskan())->toBeTrue();
    });
});

describe('tembusan permohonan ke staf khusus', function () {
    it('staf khusus dapat melihat daftar permohonan (hanya baca)', function () {
        $staf = penggunaSk('staf-khusus');

        expect($staf->can('viewAny', Permohonan::class))->toBeTrue()
            ->and($staf->can('validasi', new Permohonan))->toBeFalse();
    });
});
