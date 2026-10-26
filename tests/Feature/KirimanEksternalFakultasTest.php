<?php

use App\Actions\Disposisi\BuatDisposisi;
use App\Actions\Kiriman\AjukanKirimanEksternal;
use App\Actions\Kiriman\TarikKirimanEksternal;
use App\Actions\Kiriman\TerimaKirimanEksternal;
use App\Enums\JenisPengirim;
use App\Enums\StatusSuratMasuk;
use App\Filament\Pages\Arsip;
use App\Filament\Resources\RegisterSuratMasuks\Pages\ListRegisterSuratMasuks;
use App\Filament\Resources\SuratMasuks\Pages\EditSuratMasuk;
use App\Filament\Resources\SuratMasuks\Pages\ListSuratMasuks;
use App\Filament\Widgets\RingkasanPersuratan;
use App\Livewire\Pengirim\Beranda as BerandaPengirim;
use App\Livewire\Pengirim\KirimanBaru;
use App\Models\Aktivitas;
use App\Models\NomorTerpakai;
use App\Models\Ormawa;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Notifications\NotifikasiTahap;
use App\Services\Laporan\Lap01Register;
use App\Support\Beranda;
use App\Support\KodeLacak;
use App\Support\Pelacakan\Linimasa;
use App\Support\Pengaturan;
use Carbon\CarbonImmutable;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Database\Seeders\StrukturFkipSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    $this->withoutVite();
    $this->seed([PeranDanIzinSeeder::class, StrukturFkipSeeder::class, PengaturanSeeder::class, RegisterNomorSeeder::class]);
    Filament::setCurrentPanel('admin');

    $this->pengirim = User::factory()->create(['name' => 'Rani Putri', 'email' => 'rani@gmail.com'])->assignRole('pengirim-eksternal');
    $this->pengirimLain = User::factory()->create()->assignRole('pengirim-eksternal');
    $this->admin = User::factory()->create()->assignRole('admin-persuratan');
    $this->staf = User::factory()->create()->assignRole('staf-khusus');
    $this->dekan = User::factory()->create()->assignRole('dekan');
});

function kefData(array $ubah = []): array
{
    return $ubah + [
        'tujuan' => ['fakultas'], 'nama' => 'Rani Putri', 'instansi' => 'SMA Negeri 1 Tasikmalaya', 'perihal' => 'Permohonan izin observasi',
        'uraian' => 'Kami memohon izin observasi pembelajaran di FKIP.', 'surel' => 'rani@gmail.com', 'wa' => '081234567890',
        'lampiran' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view',
    ];
}

function kefKirim(User $pengirim, array $ubah = []): SuratMasuk
{
    return app(AjukanKirimanEksternal::class)->jalankan($pengirim, kefData($ubah))['surat'];
}

describe('mengajukan kiriman ke fakultas', function () {
    it('membuat satu surat masuk berstatus diajukan tanpa nomor agenda dan memberi tahu pemegang izin registrasi', function () {
        $hasil = app(AjukanKirimanEksternal::class)->jalankan($this->pengirim, kefData());
        $surat = $hasil['surat'];

        expect($hasil['usulan'])->toBe([])
            ->and(SuratMasuk::count())->toBe(1)
            ->and($surat->status)->toBe(StatusSuratMasuk::Diajukan)
            ->and($surat->jenis_pengirim)->toBe(JenisPengirim::Eksternal)
            ->and($surat->nomor_agenda)->toBeNull()
            ->and($surat->nomor_surat)->toBeNull()
            ->and($surat->diregistrasi_oleh)->toBeNull()
            ->and($surat->diteruskan_pada)->toBeNull()
            ->and($surat->pengirim_user_id)->toBe($this->pengirim->id)
            ->and($surat->asal)->toBe('SMA Negeri 1 Tasikmalaya')
            ->and($surat->pengirim_nama)->toBe('Rani Putri')
            ->and($surat->ringkasan)->toBe('Kami memohon izin observasi pembelajaran di FKIP.')
            ->and($surat->kode_lacak)->not->toBeEmpty()
            ->and($surat->fresh()->pengirim_surel)->toBe('rani@gmail.com')
            ->and($surat->fresh()->getRawOriginal('pengirim_surel'))->not->toBe('rani@gmail.com')
            ->and($surat->fresh()->getRawOriginal('pengirim_wa'))->not->toBe('081234567890')
            ->and($surat->pengirimUser->is($this->pengirim))->toBeTrue()
            ->and($surat->tautan()->where('jenis', 'pindaian')->count())->toBe(1);

        Notification::assertSentTo([$this->admin, $this->staf], NotifikasiTahap::class, fn ($n) => $n->judul === 'Kiriman pihak luar menunggu diterima'
            && str_contains($n->ringkas, 'Permohonan izin observasi') && str_contains($n->ringkas, 'SMA Negeri 1 Tasikmalaya')
            && ! str_contains($n->ringkas, 'null') && ! str_contains($n->ringkas, 'rani@gmail.com') && ! str_contains($n->ringkas, '0812'));
        Notification::assertNotSentTo($this->dekan, NotifikasiTahap::class);
    });

    it('kontak tidak masuk jejak audit dan tidak ikut serialisasi', function () {
        $surat = kefKirim($this->pengirim);

        expect(Aktivitas::all()->pluck('properties')->toJson())->not->toContain('rani@gmail.com')->not->toContain('081234567890')
            ->and(json_encode($surat->toArray()))->not->toContain('rani@gmail.com')->not->toContain('081234567890');
    });

    it('menolak kiriman tanpa kontak, tanpa tujuan, tujuan selain fakultas, WA tidak sah, atau tautan di luar domain', function (array $ubah) {
        expect(fn () => kefKirim($this->pengirim, $ubah))->toThrow(ValidationException::class);
        expect(SuratMasuk::count())->toBe(0);
    })->with([
        'tanpa kontak' => [['surel' => '', 'wa' => '']],
        'tujuan kosong' => [['tujuan' => []]],
        'tujuan bukan daftar' => [['tujuan' => 'fakultas']],
        'tujuan perantara (belum didukung)' => [['tujuan' => ['ormawa:'.'00000000-0000-0000-0000-000000000000']]],
        'tujuan asing' => [['tujuan' => ['rektorat']]],
        'WA tidak sah' => [['surel' => '', 'wa' => 'abc']],
        'surel tidak sah' => [['surel' => 'bukan-surel', 'wa' => '']],
        'tautan di luar domain' => [['lampiran' => 'https://contoh-jahat.test/berkas']],
        'tanpa perihal' => [['perihal' => '']],
    ]);

    it('cukup salah satu kontak dan lampiran opsional', function () {
        $surat = kefKirim($this->pengirim, ['wa' => '', 'lampiran' => '']);

        expect($surat->fresh()->pengirim_wa)->toBeNull()->and($surat->tautan()->count())->toBe(0);
    });

    it('membatasi pengiriman per hari sesuai pengaturan', function () {
        foreach (range(1, 5) as $i) {
            kefKirim($this->pengirim);
        }

        expect(fn () => kefKirim($this->pengirim))->toThrow(TooManyRequestsHttpException::class)
            ->and(SuratMasuk::count())->toBe(5);

        // Batas per akun: pengirim lain tidak terkena.
        kefKirim($this->pengirimLain);

        Pengaturan::set('batas_kiriman_harian', 1);
        $ketiga = User::factory()->create()->assignRole('pengirim-eksternal');
        kefKirim($ketiga);
        expect(fn () => kefKirim($ketiga))->toThrow(TooManyRequestsHttpException::class);
    });

    it('menolak pengguna tanpa izin kiriman.ajukan', function () {
        $mhs = User::factory()->create()->assignRole('mahasiswa');

        expect(fn () => kefKirim($mhs))->toThrow(AuthorizationException::class)
            ->and(fn () => kefKirim($this->admin))->toThrow(AuthorizationException::class);
    });
});

describe('menerima kiriman', function () {
    it('staf khusus menerima: nomor agenda dari register agenda-masuk, menunggu diteruskan', function () {
        $surat = kefKirim($this->pengirim);
        $stafLain = User::factory()->create()->assignRole('staf-khusus');
        Notification::fake();

        $hasil = app(TerimaKirimanEksternal::class)->jalankan($surat, $this->staf);
        $agenda = NomorTerpakai::where('pemilik_id', $surat->id)->sole();

        expect($hasil->status)->toBe(StatusSuratMasuk::Diterima)
            ->and($hasil->nomor_agenda)->not->toBeNull()->toBe($agenda->nomor_lengkap)
            ->and($agenda->register->kode)->toBe('agenda-masuk')
            ->and($hasil->diregistrasi_oleh)->toBe($this->staf->id)
            ->and($hasil->diteruskan_pada)->toBeNull()
            ->and($hasil->fresh()->status)->toBe(StatusSuratMasuk::Diterima);

        // Pelaku sendiri tidak diberi tahu; staf khusus lain diminta meneruskan.
        Notification::assertSentTo($stafLain, NotifikasiTahap::class, fn ($n) => $n->judul === 'Surat masuk menunggu diteruskan ke Dekan' && str_contains($n->ringkas, $agenda->nomor_lengkap));
        Notification::assertNotSentTo($this->dekan, NotifikasiTahap::class);
    });

    it('admin persuratan menerima: langsung diteruskan kepada dekan', function () {
        $surat = kefKirim($this->pengirim);

        $hasil = app(TerimaKirimanEksternal::class)->jalankan($surat, $this->admin);

        expect($hasil->diteruskan_pada)->not->toBeNull()->and($hasil->diteruskan_oleh)->toBe($this->admin->id)
            ->and(SuratMasuk::tampakBagi($this->dekan)->pluck('id')->all())->toBe([$surat->id]);
    });

    it('menerima dua kali ditolak tanpa nomor ganda, juga dengan objek basi', function () {
        $surat = kefKirim($this->pengirim);
        $basi = SuratMasuk::find($surat->id);

        app(TerimaKirimanEksternal::class)->jalankan($surat, $this->admin);
        $nomor = $surat->fresh()->nomor_agenda;

        expect(fn () => app(TerimaKirimanEksternal::class)->jalankan($basi, $this->staf))->toThrow(ValidationException::class)
            ->and(NomorTerpakai::where('pemilik_id', $surat->id)->count())->toBe(1)
            ->and($surat->fresh()->nomor_agenda)->toBe($nomor)
            ->and($surat->fresh()->diregistrasi_oleh)->toBe($this->admin->id);
    });

    it('menolak pelaku tanpa izin registrasi', function () {
        $surat = kefKirim($this->pengirim);

        expect(fn () => app(TerimaKirimanEksternal::class)->jalankan($surat, User::factory()->create()->assignRole('mahasiswa')))->toThrow(AuthorizationException::class)
            ->and(fn () => app(TerimaKirimanEksternal::class)->jalankan($surat, $this->pengirim))->toThrow(AuthorizationException::class)
            ->and($surat->fresh()->status)->toBe(StatusSuratMasuk::Diajukan);
    });
});

describe('menarik kiriman', function () {
    it('pemilik menarik selama diajukan; setelah diterima tidak bisa; pengirim lain tidak berhak', function () {
        $a = kefKirim($this->pengirim);
        $b = kefKirim($this->pengirim);

        expect(fn () => app(TarikKirimanEksternal::class)->jalankan($a, $this->pengirimLain))->toThrow(AuthorizationException::class);

        app(TarikKirimanEksternal::class)->jalankan($a, $this->pengirim);
        expect(SuratMasuk::find($a->id))->toBeNull()->and(SuratMasuk::withTrashed()->find($a->id)->trashed())->toBeTrue();

        app(TerimaKirimanEksternal::class)->jalankan($b, $this->admin);
        expect(fn () => app(TarikKirimanEksternal::class)->jalankan($b->fresh(), $this->pengirim))->toThrow(ValidationException::class)
            ->and(SuratMasuk::find($b->id))->not->toBeNull();
    });

    it('kebijakan terima dan tarik', function () {
        $surat = kefKirim($this->pengirim);

        expect($this->admin->can('terima', $surat))->toBeTrue()
            ->and($this->staf->can('terima', $surat))->toBeTrue()
            ->and($this->dekan->can('terima', $surat))->toBeFalse()
            ->and($this->pengirim->can('terima', $surat))->toBeFalse()
            ->and($this->pengirim->can('tarik', $surat))->toBeTrue()
            ->and($this->pengirimLain->can('tarik', $surat))->toBeFalse()
            ->and($this->admin->can('tarik', $surat))->toBeFalse()
            // Pengirim tidak mendapat akses panel atas suratnya.
            ->and($this->pengirim->can('view', $surat))->toBeFalse()
            ->and($this->pengirim->can('viewAny', SuratMasuk::class))->toBeFalse();

        app(TerimaKirimanEksternal::class)->jalankan($surat, $this->admin);
        $surat->refresh();

        expect($this->admin->can('terima', $surat))->toBeFalse()->and($this->pengirim->can('tarik', $surat))->toBeFalse();
    });
});

describe('kiriman belum bernomor pada konsumen lain', function () {
    it('dekan tidak melihat kiriman diajukan, dan disposisi atasnya ditolak', function () {
        $surat = kefKirim($this->pengirim);

        expect(SuratMasuk::tampakBagi($this->dekan)->count())->toBe(0)
            ->and($this->dekan->can('view', $surat))->toBeFalse();

        $this->actingAs($this->dekan);
        Livewire::test(ListSuratMasuks::class)->assertCanNotSeeTableRecords([$surat]);

        $super = User::factory()->create()->assignRole('super-admin');
        expect(fn () => app(BuatDisposisi::class)->jalankan($surat, $super, [$this->staf->id], ['tindak-lanjuti']))->toThrow(ValidationException::class);
    });

    it('LAP-01 melewatkan surat tanpa nomor agenda', function () {
        kefKirim($this->pengirim);
        $diterima = app(TerimaKirimanEksternal::class)->jalankan(kefKirim($this->pengirim), $this->admin);

        $tabel = app(Lap01Register::class)->susun(CarbonImmutable::now()->startOfDay(), CarbonImmutable::now()->endOfDay());

        expect($tabel[0]['baris'])->toHaveCount(1)->and($tabel[0]['baris'][0][0])->toBe($diterima->nomor_agenda);
    });

    it('linimasa dan halaman lacak publik menampilkan "Menunggu diterima fakultas"', function () {
        $surat = kefKirim($this->pengirim);

        expect(app(Linimasa::class)->statusSuratMasuk($surat))->toBe('Menunggu diterima fakultas')
            ->and(collect(app(Linimasa::class)->suratMasuk($surat))->pluck('tahap')->all())->toBe(['Kiriman diajukan pengirim']);

        $this->get('/lacak/'.KodeLacak::tampil($surat->kode_lacak))->assertOk()
            ->assertSee('Menunggu diterima fakultas')->assertSee('Kiriman diajukan pengirim')
            ->assertDontSee('Rani Putri')->assertDontSee('rani@gmail.com')->assertDontSee('Petugas fakultas');

        app(TerimaKirimanEksternal::class)->jalankan($surat, $this->staf);
        expect(collect(app(Linimasa::class)->suratMasuk($surat->fresh()))->pluck('tahap')->all())->toBe(['Kiriman diajukan pengirim', 'Surat diterima dan didaftarkan']);
        $this->get('/lacak/'.KodeLacak::tampil($surat->kode_lacak))->assertOk()->assertSee('Diterima, menunggu diteruskan');
    });

    it('kiriman yang ditarik tidak dapat dilacak lagi', function () {
        $surat = kefKirim($this->pengirim);
        app(TarikKirimanEksternal::class)->jalankan($surat, $this->pengirim);

        $this->get('/lacak/'.KodeLacak::tampil($surat->kode_lacak))->assertNotFound();
    });

    it('daftar surat masuk admin dan staf khusus menampilkan kiriman dengan aksi terima', function (string $peran) {
        $surat = kefKirim($this->pengirim);
        $this->actingAs($peran === 'admin' ? $this->admin : $this->staf);

        Livewire::test(ListSuratMasuks::class)->assertOk()
            ->assertCanSeeTableRecords([$surat])
            ->assertSee('Diajukan pengirim')
            ->assertTableActionVisible('terima', $surat)
            ->assertTableActionHidden('teruskan', $surat)
            ->filterTable('status', 'diajukan')->assertCanSeeTableRecords([$surat])
            ->callTableAction('terima', $surat)->assertHasNoTableActionErrors();

        expect($surat->fresh()->status)->toBe(StatusSuratMasuk::Diterima)->and($surat->fresh()->nomor_agenda)->not->toBeNull();
    })->with(['admin', 'staf']);

    it('filter menunggu diteruskan tidak memuat kiriman yang belum diterima', function () {
        $surat = kefKirim($this->pengirim);
        $this->actingAs($this->staf);

        Livewire::test(ListSuratMasuks::class)->filterTable('menunggu_diteruskan')->assertCanNotSeeTableRecords([$surat]);
    });

    it('halaman ubah kiriman diajukan terbuka, nomor agenda tetap kosong, dan dapat disimpan tanpa nomor surat', function () {
        $surat = kefKirim($this->pengirim);
        $this->actingAs($this->admin);

        Livewire::test(EditSuratMasuk::class, ['record' => $surat->getRouteKey()])->assertOk()
            ->assertActionHidden('lembar')
            ->assertActionVisible('terima')
            ->fillForm(['perihal' => 'Izin observasi (diperbaiki)', 'nomor_agenda' => '999/X'])
            ->call('save')->assertHasNoFormErrors();

        expect($surat->fresh()->perihal)->toBe('Izin observasi (diperbaiki)')
            ->and($surat->fresh()->nomor_agenda)->toBeNull()
            ->and($surat->fresh()->status)->toBe(StatusSuratMasuk::Diajukan);

        $this->get(route('surat-masuk.lembar-disposisi', $surat))->assertNotFound();
    });

    it('register surat masuk dan pencarian arsip tidak memuat kiriman yang belum diterima', function () {
        $surat = kefKirim($this->pengirim);
        $this->actingAs($this->admin);

        Livewire::test(ListRegisterSuratMasuks::class)->assertOk()->assertCanNotSeeTableRecords([$surat]);
        expect(Livewire::test(Arsip::class)->set('kata', 'observasi')->instance()->hasil()['masuk'])->toBe([]);

        app(TerimaKirimanEksternal::class)->jalankan($surat, $this->admin);
        Livewire::test(ListRegisterSuratMasuks::class)->assertCanSeeTableRecords([$surat->fresh()]);
        expect(Livewire::test(Arsip::class)->set('kata', 'observasi')->instance()->hasil()['masuk'])->toHaveCount(1);
    });

    it('dasbor menghitung kiriman menunggu diterima', function () {
        kefKirim($this->pengirim);
        $this->actingAs($this->admin);

        Livewire::test(RingkasanPersuratan::class)->assertSee('Kiriman menunggu diterima');
    });
});

describe('halaman pengirim', function () {
    it('beranda pengirim-eksternal adalah /pengirim', function () {
        expect(Beranda::url($this->pengirim))->toBe(route('pengirim'));

        $this->actingAs($this->pengirim)->get('/pengirim')->assertOk()->assertSee('Kiriman saya');
    });

    it('hanya menampilkan kiriman milik sendiri', function () {
        $milikA = kefKirim($this->pengirim, ['perihal' => 'Kiriman milik A']);
        kefKirim($this->pengirimLain, ['perihal' => 'Kiriman milik B']);

        $this->actingAs($this->pengirim)->get('/pengirim')->assertOk()
            ->assertSee('Kiriman milik A')->assertSee('Menunggu diterima fakultas')->assertSee(KodeLacak::tampil($milikA->kode_lacak))
            ->assertDontSee('Kiriman milik B');
        $this->actingAs($this->pengirimLain)->get('/pengirim')->assertOk()->assertDontSee('Kiriman milik A');
    });

    it('menampilkan pesan status sesi dan galat umum', function () {
        $this->actingAs($this->pengirim)->withSession(['status' => 'Akun ini tidak memakai panel admin.'])->get('/pengirim')
            ->assertOk()->assertSee('Akun ini tidak memakai panel admin.');
    });

    it('pengirim menarik kirimannya lewat beranda, tetapi tidak milik pengirim lain (IDOR)', function () {
        $milikA = kefKirim($this->pengirim);
        $milikB = kefKirim($this->pengirimLain);

        Livewire::actingAs($this->pengirim)->test(BerandaPengirim::class)->call('tarik', $milikB->id)->assertNotFound();
        expect(SuratMasuk::find($milikB->id))->not->toBeNull();

        Livewire::actingAs($this->pengirim)->test(BerandaPengirim::class)->call('tarik', $milikA->id)->assertHasNoErrors();
        expect(SuratMasuk::find($milikA->id))->toBeNull();

        $diterima = kefKirim($this->pengirim);
        app(TerimaKirimanEksternal::class)->jalankan($diterima, $this->admin);
        Livewire::actingAs($this->pengirim)->test(BerandaPengirim::class)->call('tarik', $diterima->id)->assertHasErrors('umum');
        expect(SuratMasuk::find($diterima->id))->not->toBeNull();
    });

    it('formulir kiriman baru: nilai awal dari akun, mengirim, lalu kembali ke beranda', function () {
        Livewire::actingAs($this->pengirim)->test(KirimanBaru::class)
            ->assertSet('nama', 'Rani Putri')->assertSet('surel', 'rani@gmail.com')
            ->set('instansi', 'SMA Negeri 1')->set('perihal', 'Undangan seminar')->set('uraian', 'Mengundang dekan.')
            ->call('kirim')->assertHasNoErrors()->assertRedirect(route('pengirim'));

        $surat = SuratMasuk::sole();
        expect($surat->pengirim_user_id)->toBe($this->pengirim->id)->and($surat->status)->toBe(StatusSuratMasuk::Diajukan);

        Livewire::actingAs($this->pengirim)->test(KirimanBaru::class)->set('surel', '')->set('wa', '')->set('perihal', 'x')->set('uraian', 'y')
            ->call('kirim')->assertHasErrors();
        expect(SuratMasuk::count())->toBe(1);

        $this->actingAs($this->pengirim)->get('/pengirim/kiriman/baru')->assertOk()->assertSee('Kirim surat ke fakultas');
    });

    it('rute pengirim tertutup bagi peran lain', function () {
        $mhs = User::factory()->create()->assignRole('mahasiswa');

        $this->actingAs($mhs)->get('/pengirim')->assertForbidden();
        $this->actingAs($mhs)->get('/pengirim/kiriman/baru')->assertForbidden();
        $this->actingAs($this->admin)->get('/pengirim')->assertForbidden();
    });

    it('pengirim-eksternal tidak dapat memakai halaman peran lain', function () {
        $ormawa = Ormawa::create(['nama' => 'HIMA Uji', 'tingkat' => 'prodi']);
        $this->actingAs($this->pengirim);

        $this->get('/mahasiswa')->assertForbidden();
        $this->get('/mahasiswa/usulan/baru')->assertForbidden();
        $this->get('/disposisi')->assertForbidden();
        $this->get('/unit/persetujuan')->assertForbidden();
        $this->get("/ormawa/{$ormawa->id}/permohonan")->assertForbidden();
        $this->get('/admin')->assertForbidden();
    });
});
