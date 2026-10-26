<?php

use App\Actions\Kiriman\AjukanKirimanEksternal;
use App\Actions\Permohonan\AjukanPermohonan;
use App\Actions\Usulan\AjukanUsulan;
use App\Actions\Usulan\ArsipkanUsulan;
use App\Actions\Usulan\BatalkanUsulan;
use App\Actions\Usulan\PutusUsulan;
use App\Enums\StatusSuratMasuk;
use App\Enums\StatusUsulan;
use App\Livewire\Ormawa\UsulanMasuk as UsulanMasukOrmawa;
use App\Livewire\Pengirim\Beranda as BerandaPengirim;
use App\Livewire\Pengirim\KirimanBaru;
use App\Livewire\Unit\UsulanMasuk as UsulanMasukUnit;
use App\Models\JenisPermohonan;
use App\Models\Ormawa;
use App\Models\PengurusOrmawa;
use App\Models\SuratMasuk;
use App\Models\UnitKerja;
use App\Models\User;
use App\Models\UsulanMahasiswa;
use App\Notifications\NotifikasiTahap;
use App\Support\KodeLacak;
use App\Support\Pengaturan;
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
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    $this->withoutVite();
    $this->seed([PeranDanIzinSeeder::class, StrukturFkipSeeder::class, PengaturanSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class, JenisPermohonanSeeder::class]);
    CarbonImmutable::setTestNow('2026-06-01 09:00:00');

    $this->prodi = UnitKerja::create(['kode' => 'PMAT', 'nama' => 'Pendidikan Matematika', 'tipe' => 'prodi']);
    $this->lab = UnitKerja::create(['kode' => 'LAB-KOM', 'nama' => 'Laboratorium Komputer', 'tipe' => 'laboratorium']);
    $this->pengaju = User::factory()->create(['name' => 'Pak Pengaju Prodi'])->assignRole('pengaju-unit');
    $this->pengajuLab = User::factory()->create()->assignRole('pengaju-unit');
    $this->kaprodi = User::factory()->create()->assignRole('kaprodi');
    $this->prodi->pengaju()->attach($this->pengaju);
    $this->prodi->penyetuju()->attach($this->kaprodi);
    $this->lab->pengaju()->attach($this->pengajuLab);

    $this->ormawa = Ormawa::create(['nama' => 'HIMA Mat', 'tingkat' => 'prodi']);
    $this->ormawa->sk()->create(['nomor_sk' => 'SK/1', 'tanggal_sk' => '2026-01-01', 'periode_mulai' => '2026-01-01', 'periode_selesai' => '2026-12-31']);
    $this->pengurus = User::factory()->create(['nip_nim' => '3000001'])->assignRole('pengurus-ormawa');
    PengurusOrmawa::create(['ormawa_id' => $this->ormawa->id, 'user_id' => $this->pengurus->id, 'nama' => $this->pengurus->name, 'jabatan' => 'ketua']);

    $this->pengirim = User::factory()->create(['name' => 'Rani Putri', 'email' => 'rani@gmail.com'])->assignRole('pengirim-eksternal');
    $this->pengirimLain = User::factory()->create(['name' => 'Bima Lain'])->assignRole('pengirim-eksternal');
    $this->mhs = User::factory()->create()->assignRole('mahasiswa');
    RateLimiter::clear('kiriman-eksternal:'.$this->pengirim->id);
    RateLimiter::clear('kiriman-eksternal:'.$this->pengirimLain->id);
    RateLimiter::clear('usulan-mahasiswa:'.$this->mhs->id);
    RateLimiter::clear('ajukan-permohonan:'.$this->pengaju->id);
});

afterEach(fn () => CarbonImmutable::setTestNow());

function kepData(array $tujuan, array $ubah = []): array
{
    return $ubah + [
        'tujuan' => $tujuan, 'nama' => 'Rani Putri', 'instansi' => 'SMA Negeri 1 Tasikmalaya', 'perihal' => 'Undangan seminar pendidikan',
        'uraian' => 'Kami mengundang untuk hadir pada seminar.', 'surel' => 'rani@gmail.com', 'wa' => '081234567890',
        'lampiran' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view',
    ];
}

/** @return array{surat: ?SuratMasuk, usulan: list<UsulanMahasiswa>} */
function kepKirim(User $pengirim, array $tujuan, array $ubah = []): array
{
    return app(AjukanKirimanEksternal::class)->jalankan($pengirim, kepData($tujuan, $ubah));
}

function kepUsulan(User $pengirim, string $tujuan, array $ubah = []): UsulanMahasiswa
{
    return kepKirim($pengirim, [$tujuan], $ubah)['usulan'][0];
}

describe('pengiriman paralel', function () {
    it('fakultas, prodi, dan ormawa sekaligus: satu surat masuk dan dua usulan pihak luar, kode lacak berbeda', function () {
        $hasil = kepKirim($this->pengirim, ['fakultas', "unit:{$this->prodi->id}", "ormawa:{$this->ormawa->id}"]);

        expect($hasil['surat'])->toBeInstanceOf(SuratMasuk::class)->and($hasil['surat']->status)->toBe(StatusSuratMasuk::Diajukan)
            ->and($hasil['usulan'])->toHaveCount(2)
            ->and(SuratMasuk::count())->toBe(1)->and(UsulanMahasiswa::count())->toBe(2);

        foreach (UsulanMahasiswa::all() as $u) {
            expect($u->asal)->toBe('eksternal')->and($u->dariPihakLuar())->toBeTrue()
                ->and($u->mahasiswa_id)->toBe($this->pengirim->id)->and($u->status)->toBe(StatusUsulan::Diajukan)
                ->and($u->perihal)->toBe('Undangan seminar pendidikan')->and($u->permohonan_id)->toBeNull()
                ->and($u->tautan()->where('jenis', 'lampiran')->count())->toBe(1);
        }

        expect(UsulanMahasiswa::whereNotNull('unit_kerja_id')->sole()->unit_kerja_id)->toBe($this->prodi->id)
            ->and(UsulanMahasiswa::whereNotNull('ormawa_id')->sole()->ormawa_id)->toBe($this->ormawa->id);

        $kode = collect([$hasil['surat']->kode_lacak, ...array_map(fn ($u) => $u->kode_lacak, $hasil['usulan'])]);
        expect($kode->filter()->unique())->toHaveCount(3);
    });

    it('perantara diberi tahu dengan label pihak luar; usulan mahasiswa tetap berlabel mahasiswa', function () {
        kepKirim($this->pengirim, ["unit:{$this->prodi->id}", "ormawa:{$this->ormawa->id}"]);

        $judul = fn (User $u) => Notification::sent($u, NotifikasiTahap::class)->map(fn ($n) => $n->judul)->all();
        expect($judul($this->pengaju))->toBe(['Kiriman pihak luar baru'])->and($judul($this->pengurus))->toBe(['Kiriman pihak luar baru'])
            ->and($judul($this->pengajuLab))->toBe([]);

        app(AjukanUsulan::class)->jalankan($this->mhs, ['tujuan' => "ormawa:{$this->ormawa->id}", 'perihal' => 'Lomba', 'uraian' => 'Ikut lomba.']);
        expect($judul($this->pengurus))->toBe(['Kiriman pihak luar baru', 'Usulan mahasiswa baru'])
            ->and(UsulanMahasiswa::where('asal', 'mahasiswa')->sole()->dariPihakLuar())->toBeFalse();
    });

    it('hanya tujuan perantara: tidak ada surat masuk', function () {
        $hasil = kepKirim($this->pengirim, ["unit:{$this->lab->id}"]);

        expect($hasil['surat'])->toBeNull()->and($hasil['usulan'])->toHaveCount(1)
            ->and(SuratMasuk::count())->toBe(0)->and($hasil['usulan'][0]->unit_kerja_id)->toBe($this->lab->id);
    });

    it('tujuan ganda diperlakukan sebagai satu', function () {
        $hasil = kepKirim($this->pengirim, ['fakultas', 'fakultas', "unit:{$this->prodi->id}", "unit:{$this->prodi->id}"]);

        expect($hasil['usulan'])->toHaveCount(1)->and(SuratMasuk::count())->toBe(1)->and(UsulanMahasiswa::count())->toBe(1);
    });

    it('lebih dari batas tujuan perantara ditolak tanpa membuat apa pun; batas mengikuti pengaturan', function () {
        $unit = collect(range(1, 6))->map(function (int $i) {
            $u = UnitKerja::create(['kode' => "P{$i}", 'nama' => "Prodi {$i}", 'tipe' => 'prodi']);
            $u->pengaju()->attach($this->pengaju);

            return "unit:{$u->id}";
        })->all();

        expect(fn () => kepKirim($this->pengirim, ['fakultas', ...$unit]))->toThrow(ValidationException::class)
            ->and(SuratMasuk::count())->toBe(0)->and(UsulanMahasiswa::count())->toBe(0);

        expect(kepKirim($this->pengirim, ['fakultas', ...array_slice($unit, 0, 5)])['usulan'])->toHaveCount(5);

        Pengaturan::set('batas_tujuan_kiriman', 1);
        expect(fn () => kepKirim($this->pengirim, array_slice($unit, 0, 2)))->toThrow(ValidationException::class);
    });

    it('menolak seluruh pengiriman bila satu tujuan tidak sah (atomik)', function (string $jenis) {
        $tujuan = match ($jenis) {
            'ormawa nonaktif' => 'ormawa:'.Ormawa::create(['nama' => 'Mati', 'tingkat' => 'prodi', 'aktif' => false])->id,
            'unit nonaktif' => 'unit:'.tap(UnitKerja::create(['kode' => 'NA', 'nama' => 'Prodi Mati', 'tipe' => 'prodi', 'aktif' => false]), fn ($u) => $u->pengaju()->attach($this->pengaju))->id,
            'unit tanpa pengaju' => 'unit:'.UnitKerja::create(['kode' => 'KOS', 'nama' => 'Prodi Kosong', 'tipe' => 'prodi'])->id,
            'unit bukan prodi/lab' => 'unit:'.tap(UnitKerja::create(['kode' => 'BAG', 'nama' => 'Bagian', 'tipe' => 'lainnya']), fn ($u) => $u->pengaju()->attach($this->pengaju))->id,
            'ormawa acak' => 'ormawa:'.fake()->uuid(),
            'unit acak' => 'unit:'.fake()->uuid(),
            'bentuk asing' => 'rektorat',
        };

        expect(fn () => kepKirim($this->pengirim, ['fakultas', "ormawa:{$this->ormawa->id}", $tujuan]))->toThrow(ValidationException::class)
            ->and(SuratMasuk::count())->toBe(0)->and(UsulanMahasiswa::count())->toBe(0);
    })->with(['ormawa nonaktif', 'unit nonaktif', 'unit tanpa pengaju', 'unit bukan prodi/lab', 'ormawa acak', 'unit acak', 'bentuk asing']);

    it('tujuan kosong ditolak', function () {
        expect(fn () => kepKirim($this->pengirim, []))->toThrow(ValidationException::class);
    });

    it('perihal untuk perantara dibatasi 255 karakter', function () {
        expect(fn () => kepKirim($this->pengirim, ["ormawa:{$this->ormawa->id}"], ['perihal' => str_repeat('a', 300)]))->toThrow(ValidationException::class)
            ->and(UsulanMahasiswa::count())->toBe(0);
        expect(kepKirim($this->pengirim, ['fakultas'], ['perihal' => str_repeat('a', 300)])['surat'])->not->toBeNull();
    });

    it('batas harian menghitung pengiriman, bukan baris tujuan', function () {
        Pengaturan::set('batas_kiriman_harian', 2);
        kepKirim($this->pengirim, ['fakultas', "unit:{$this->prodi->id}", "ormawa:{$this->ormawa->id}"]);
        kepKirim($this->pengirim, ["unit:{$this->lab->id}"]);

        expect(fn () => kepKirim($this->pengirim, ['fakultas']))->toThrow(TooManyRequestsHttpException::class)
            ->and(UsulanMahasiswa::count())->toBe(3);
    });

    it('pengiriman yang gagal validasi tidak dihitung terhadap batas harian', function () {
        Pengaturan::set('batas_kiriman_harian', 1);

        expect(fn () => kepKirim($this->pengirim, ['ormawa:'.fake()->uuid()]))->toThrow(ValidationException::class);
        expect(kepKirim($this->pengirim, ["ormawa:{$this->ormawa->id}"])['usulan'])->toHaveCount(1);
    });
});

describe('arsip perantara', function () {
    it('pengaju unit mengarsipkan kiriman pihak luar; surat ke fakultas dari pengiriman yang sama tidak berubah', function () {
        $hasil = kepKirim($this->pengirim, ['fakultas', "unit:{$this->prodi->id}"]);
        $usulan = $hasil['usulan'][0];

        $arsip = app(ArsipkanUsulan::class)->jalankan($usulan, $this->pengaju);

        expect($arsip->status)->toBe(StatusUsulan::Diarsipkan)->and($usulan->fresh()->status)->toBe(StatusUsulan::Diarsipkan)
            ->and($usulan->fresh()->permohonan_id)->toBeNull()
            ->and($hasil['surat']->fresh()->status)->toBe(StatusSuratMasuk::Diajukan)
            ->and($hasil['surat']->fresh()->updated_at->equalTo($hasil['surat']->updated_at))->toBeTrue();

        $judul = Notification::sent($this->pengirim, NotifikasiTahap::class)->map(fn ($n) => $n->judul)->all();
        expect($judul)->toContain('Kiriman Anda diarsipkan');
    });

    it('status diarsipkan: label dan bukan status terbuka', function () {
        expect(StatusUsulan::Diarsipkan->value)->toBe('diarsipkan')
            ->and(StatusUsulan::Diarsipkan->label())->toBe('Diarsipkan oleh perantara')
            ->and(StatusUsulan::Diarsipkan->terbuka())->toBeFalse();
    });

    it('pengurus ormawa mengarsipkan kiriman pihak luar untuk ormawanya', function () {
        $u = kepUsulan($this->pengirim, "ormawa:{$this->ormawa->id}");

        expect(app(ArsipkanUsulan::class)->jalankan($u, $this->pengurus)->status)->toBe(StatusUsulan::Diarsipkan);
    });

    it('usulan mahasiswa tidak dapat diarsipkan', function () {
        $u = app(AjukanUsulan::class)->jalankan($this->mhs, ['tujuan' => "unit:{$this->prodi->id}", 'perihal' => 'Lomba', 'uraian' => 'Ikut lomba.']);

        expect(fn () => app(ArsipkanUsulan::class)->jalankan($u, $this->pengaju))->toThrow(ValidationException::class)
            ->and($u->fresh()->status)->toBe(StatusUsulan::Diajukan);
    });

    it('pengaju unit lain, pengurus ormawa, mahasiswa, atau pengirim tidak berhak mengarsipkan', function () {
        $u = kepUsulan($this->pengirim, "unit:{$this->prodi->id}");

        foreach ([$this->pengajuLab, $this->pengurus, $this->mhs, $this->pengirim, $this->kaprodi] as $pelaku) {
            expect(fn () => app(ArsipkanUsulan::class)->jalankan($u, $pelaku))->toThrow(AuthorizationException::class);
        }

        expect($u->fresh()->status)->toBe(StatusUsulan::Diajukan);
    });

    it('usulan yang sudah dipakai, diputus, atau dibatalkan tidak dapat diarsipkan', function () {
        $dipakai = kepUsulan($this->pengirim, "unit:{$this->prodi->id}", ['perihal' => 'Dipakai']);
        $dipakai->forceFill(['status' => StatusUsulan::Dipakai])->save();
        $ditolak = kepUsulan($this->pengirim, "unit:{$this->prodi->id}", ['perihal' => 'Ditolak']);
        app(PutusUsulan::class)->tolak($ditolak, $this->pengaju, 'Tidak relevan.');
        $batal = kepUsulan($this->pengirim, "unit:{$this->prodi->id}", ['perihal' => 'Batal']);
        app(BatalkanUsulan::class)->jalankan($batal, $this->pengirim);

        foreach ([$dipakai, $ditolak, $batal] as $u) {
            expect(fn () => app(ArsipkanUsulan::class)->jalankan($u, $this->pengaju))->toThrow(ValidationException::class);
        }
    });
});

describe('alur usulan lain untuk kiriman pihak luar', function () {
    it('pengirim membatalkan usulannya; pengirim lain tidak berhak', function () {
        $u = kepUsulan($this->pengirim, "ormawa:{$this->ormawa->id}");

        expect(fn () => app(BatalkanUsulan::class)->jalankan($u, $this->pengirimLain))->toThrow(AuthorizationException::class);

        expect(app(BatalkanUsulan::class)->jalankan($u, $this->pengirim)->status)->toBe(StatusUsulan::Dibatalkan);
    });

    it('perantara mengembalikan kiriman pihak luar; pengirim diberi tahu ke halaman pengirim', function () {
        $u = kepUsulan($this->pengirim, "unit:{$this->prodi->id}");

        app(PutusUsulan::class)->kembalikan($u, $this->pengaju, 'Lengkapi jadwal.');

        $n = Notification::sent($this->pengirim, NotifikasiTahap::class)->last();
        expect($u->fresh()->status)->toBe(StatusUsulan::Dikembalikan)->and($n->judul)->toBe('Usulan Anda dikembalikan')
            ->and($n->url)->toBe(route('pengirim'));
    });

    it('pengaju unit menjadikan kiriman pihak luar dasar permohonan', function () {
        $u = kepUsulan($this->pengirim, "unit:{$this->prodi->id}");

        $p = app(AjukanPermohonan::class)->jalankanUnit($this->pengaju, $this->prodi, JenisPermohonan::firstWhere('kode', 'kegiatan'), [
            'nama_kegiatan' => 'Seminar', 'perihal' => 'Izin seminar', 'tanggal_mulai' => '2026-06-20', 'tanggal_selesai' => '2026-06-21',
            'deskripsi' => 'Seminar bersama SMA.', 'penanggung_jawab' => ['ketua' => ['nama' => 'Dr. Rani']], 'dasar_usulan' => [$u->id],
            'berkas' => ['surat_permohonan' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view', 'proposal' => 'https://drive.google.com/file/d/1ZyXwVuTsRqPoNmLkJi/view'],
        ]);

        expect($u->fresh()->status)->toBe(StatusUsulan::Dipakai)->and($u->fresh()->permohonan_id)->toBe($p->id);
    });
});

describe('halaman perantara', function () {
    it('kotak usulan unit memberi label pihak luar dan tombol arsipkan; arsipkan menghilangkannya dari daftar', function () {
        $luar = kepUsulan($this->pengirim, "unit:{$this->prodi->id}", ['perihal' => 'Undangan dari luar', 'instansi' => 'SMA Negeri 9']);
        $mhs = app(AjukanUsulan::class)->jalankan($this->mhs, ['tujuan' => "unit:{$this->prodi->id}", 'perihal' => 'Usulan dari mahasiswa', 'uraian' => 'Ikut lomba.']);

        $halaman = Livewire::actingAs($this->pengaju)->test(UsulanMasukUnit::class, ['unit' => $this->prodi->id])
            ->assertSee('Undangan dari luar')->assertSee('Usulan dari mahasiswa')->assertSee('dari pihak luar')->assertSee('SMA Negeri 9')
            ->assertSeeHtml("arsipkan('{$luar->id}')")->assertDontSeeHtml("arsipkan('{$mhs->id}')");

        $halaman->call('arsipkan', $luar->id)->assertHasNoErrors()->assertDontSee('Undangan dari luar')->assertSee('Usulan dari mahasiswa');
        expect($luar->fresh()->status)->toBe(StatusUsulan::Diarsipkan);
    });

    it('mengarsipkan usulan mahasiswa lewat halaman menampilkan galat', function () {
        $mhs = app(AjukanUsulan::class)->jalankan($this->mhs, ['tujuan' => "unit:{$this->prodi->id}", 'perihal' => 'Usulan dari mahasiswa', 'uraian' => 'Ikut lomba.']);

        Livewire::actingAs($this->pengaju)->test(UsulanMasukUnit::class, ['unit' => $this->prodi->id])
            ->call('arsipkan', $mhs->id)->assertHasErrors("catatan.{$mhs->id}");
        expect($mhs->fresh()->status)->toBe(StatusUsulan::Diajukan);
    });

    it('kotak usulan ormawa juga dapat mengarsipkan', function () {
        $u = kepUsulan($this->pengirim, "ormawa:{$this->ormawa->id}");

        Livewire::actingAs($this->pengurus)->test(UsulanMasukOrmawa::class, ['ormawa' => $this->ormawa->id])
            ->assertSee('dari pihak luar')->call('arsipkan', $u->id)->assertHasNoErrors();
        expect($u->fresh()->status)->toBe(StatusUsulan::Diarsipkan);
    });

    it('perantara lain tidak dapat membuka atau mengarsipkan (403/404)', function () {
        $u = kepUsulan($this->pengirim, "unit:{$this->prodi->id}");

        Livewire::actingAs($this->pengajuLab)->test(UsulanMasukUnit::class, ['unit' => $this->prodi->id])->assertForbidden();
        // Id usulan unit lain pada halaman unit sendiri tidak ditemukan.
        Livewire::actingAs($this->pengajuLab)->test(UsulanMasukUnit::class, ['unit' => $this->lab->id])->call('arsipkan', $u->id)->assertNotFound();
        Livewire::actingAs($this->pengirim)->test(UsulanMasukUnit::class, ['unit' => $this->prodi->id])->assertForbidden();

        expect($u->fresh()->status)->toBe(StatusUsulan::Diajukan);
    });
});

describe('halaman pengirim', function () {
    it('formulir menampilkan tujuan yang dapat dipilih: fakultas, ormawa aktif, prodi/lab aktif berpengaju', function () {
        Ormawa::create(['nama' => 'Ormawa Mati', 'tingkat' => 'prodi', 'aktif' => false]);
        UnitKerja::create(['kode' => 'KOS', 'nama' => 'Prodi Tanpa Pengaju', 'tipe' => 'prodi']);

        Livewire::actingAs($this->pengirim)->test(KirimanBaru::class)
            ->assertSet('tujuan', ['fakultas'])
            ->assertSee('Fakultas')->assertSee('HIMA Mat')->assertSee('Pendidikan Matematika')->assertSee('Laboratorium Komputer')
            ->assertDontSee('Ormawa Mati')->assertDontSee('Prodi Tanpa Pengaju');
    });

    it('formulir mengirim ke beberapa tujuan sekaligus', function () {
        Livewire::actingAs($this->pengirim)->test(KirimanBaru::class)
            ->set('tujuan', ['fakultas', "unit:{$this->prodi->id}", "ormawa:{$this->ormawa->id}"])
            ->set('perihal', 'Undangan seminar')->set('uraian', 'Mengundang.')
            ->call('kirim')->assertHasNoErrors()->assertRedirect(route('pengirim'));

        expect(SuratMasuk::count())->toBe(1)->and(UsulanMahasiswa::where('asal', 'eksternal')->count())->toBe(2);

        Livewire::actingAs($this->pengirim)->test(KirimanBaru::class)->set('tujuan', [])->set('perihal', 'x')->set('uraian', 'y')
            ->call('kirim')->assertHasErrors('tujuan');
    });

    it('beranda menampilkan status per tujuan, kode lacak, dan catatan hanya bila dikembalikan/ditolak', function () {
        $hasil = kepKirim($this->pengirim, ['fakultas', "unit:{$this->prodi->id}", "ormawa:{$this->ormawa->id}"], ['perihal' => 'Undangan paralel']);
        [$keProdi, $keOrmawa] = [UsulanMahasiswa::where('unit_kerja_id', $this->prodi->id)->sole(), UsulanMahasiswa::where('ormawa_id', $this->ormawa->id)->sole()];
        app(PutusUsulan::class)->tolak($keOrmawa, $this->pengurus, 'Bukan bidang kami.');
        app(ArsipkanUsulan::class)->jalankan($keProdi, $this->pengaju);
        kepKirim($this->pengirimLain, ["unit:{$this->prodi->id}"], ['perihal' => 'Milik pengirim lain']);

        Livewire::actingAs($this->pengirim)->test(BerandaPengirim::class)
            ->assertSee('Undangan paralel')->assertSee('Menunggu diterima fakultas')->assertSee('Diarsipkan oleh perantara')->assertSee('Ditolak')
            ->assertSee('Pendidikan Matematika')->assertSee('HIMA Mat')->assertSee('Catatan perantara: Bukan bidang kami.')
            ->assertSee(KodeLacak::tampil($hasil['surat']->kode_lacak))->assertSee(KodeLacak::tampil($keProdi->kode_lacak))->assertSee(KodeLacak::tampil($keOrmawa->kode_lacak))
            ->assertDontSee('Milik pengirim lain');
    });

    it('pengirim membatalkan usulannya lewat beranda; id milik pengirim lain berakhir 404 (IDOR)', function () {
        $milikA = kepUsulan($this->pengirim, "unit:{$this->prodi->id}");
        $milikB = kepUsulan($this->pengirimLain, "unit:{$this->prodi->id}");

        Livewire::actingAs($this->pengirim)->test(BerandaPengirim::class)->call('batalkan', $milikB->id)->assertNotFound();
        expect($milikB->fresh()->status)->toBe(StatusUsulan::Diajukan);

        Livewire::actingAs($this->pengirim)->test(BerandaPengirim::class)->assertSeeHtml("batalkan('{$milikA->id}')")
            ->call('batalkan', $milikA->id)->assertHasNoErrors();
        expect($milikA->fresh()->status)->toBe(StatusUsulan::Dibatalkan);

        // Sudah dibatalkan: kebijakan batalkan menolak (seperti ruang mahasiswa).
        Livewire::actingAs($this->pengirim)->test(BerandaPengirim::class)->assertDontSeeHtml("batalkan('{$milikA->id}')")
            ->call('batalkan', $milikA->id)->assertForbidden();
    });

    it('usulan mahasiswa dengan id yang ditebak tidak dapat dibatalkan pengirim', function () {
        $mhs = app(AjukanUsulan::class)->jalankan($this->mhs, ['tujuan' => "unit:{$this->prodi->id}", 'perihal' => 'Lomba', 'uraian' => 'Ikut lomba.']);

        Livewire::actingAs($this->pengirim)->test(BerandaPengirim::class)->call('batalkan', $mhs->id)->assertNotFound();
        expect($mhs->fresh()->status)->toBe(StatusUsulan::Diajukan);
    });
});

describe('pelacakan publik', function () {
    it('kode lacak usulan diarsipkan menampilkan "Diarsipkan oleh perantara" tanpa nama maupun catatan', function () {
        $u = kepUsulan($this->pengirim, "unit:{$this->prodi->id}", ['perihal' => 'Undangan seminar publik']);
        app(ArsipkanUsulan::class)->jalankan($u, $this->pengaju);

        $this->get('/lacak/'.KodeLacak::tampil($u->kode_lacak))->assertOk()
            ->assertSee('Diarsipkan oleh perantara')->assertSee('Undangan seminar publik')->assertSee('Pendidikan Matematika')
            ->assertDontSee('Rani Putri')->assertDontSee('Pak Pengaju Prodi')->assertDontSee('rani@gmail.com');
    });

    it('kode lacak usulan ditolak tidak menampilkan catatan perantara', function () {
        $u = kepUsulan($this->pengirim, "ormawa:{$this->ormawa->id}");
        app(PutusUsulan::class)->tolak($u, $this->pengurus, 'Catatan rahasia perantara.');

        $this->get('/lacak/'.KodeLacak::tampil($u->kode_lacak))->assertOk()->assertSee('Ditolak')->assertDontSee('Catatan rahasia perantara.');
    });

    it('kode lacak usulan menunggu menampilkan tahap umum', function () {
        $u = kepUsulan($this->pengirim, "ormawa:{$this->ormawa->id}");

        $this->get('/lacak/'.KodeLacak::tampil($u->kode_lacak))->assertOk()->assertSee('Menunggu perantara')->assertSee('Kiriman diajukan pengirim');
    });
});
