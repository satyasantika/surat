<?php

use App\Actions\Permohonan\AjukanPermohonan;
use App\Actions\Permohonan\BatalkanPermohonan;
use App\Actions\Permohonan\SetujuiPembina;
use App\Actions\Permohonan\TolakPermohonan;
use App\Actions\Usulan\AjukanUsulan;
use App\Actions\Usulan\BatalkanUsulan;
use App\Actions\Usulan\PutusUsulan;
use App\Enums\StatusPermohonan;
use App\Enums\StatusUsulan;
use App\Livewire\Mahasiswa\Beranda;
use App\Livewire\Mahasiswa\UsulanBaru;
use App\Livewire\Ormawa\AjukanPermohonan as FormulirOrmawa;
use App\Livewire\Ormawa\UsulanMasuk as UsulanMasukOrmawa;
use App\Livewire\Unit\UsulanMasuk as UsulanMasukUnit;
use App\Models\JenisPermohonan;
use App\Models\Ormawa;
use App\Models\PengurusOrmawa;
use App\Models\Permohonan;
use App\Models\UnitKerja;
use App\Models\User;
use App\Models\UsulanMahasiswa;
use App\Notifications\NotifikasiTahap;
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

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    $this->withoutVite();
    $this->seed([PeranDanIzinSeeder::class, StrukturFkipSeeder::class, PengaturanSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class, JenisPermohonanSeeder::class]);
    CarbonImmutable::setTestNow('2026-06-01 09:00:00');

    $this->ormawa = Ormawa::create(['nama' => 'HIMA Mat', 'tingkat' => 'prodi']);
    $this->ormawa->sk()->create(['nomor_sk' => 'SK/1', 'tanggal_sk' => '2026-01-01', 'periode_mulai' => '2026-01-01', 'periode_selesai' => '2026-12-31']);
    $this->pengurus = User::factory()->create(['nip_nim' => '3000001'])->assignRole('pengurus-ormawa');
    PengurusOrmawa::create(['ormawa_id' => $this->ormawa->id, 'user_id' => $this->pengurus->id, 'nama' => $this->pengurus->name, 'jabatan' => 'ketua']);

    $this->prodi = UnitKerja::create(['kode' => 'PMAT', 'nama' => 'Pendidikan Matematika', 'tipe' => 'prodi']);
    $this->pengaju = User::factory()->create()->assignRole('pengaju-unit');
    $this->kaprodi = User::factory()->create()->assignRole('kaprodi');
    $this->prodi->pengaju()->attach($this->pengaju);
    $this->prodi->penyetuju()->attach($this->kaprodi);

    $this->mhs = User::factory()->create()->assignRole('mahasiswa');
    $this->mhs2 = User::factory()->create()->assignRole('mahasiswa');
    RateLimiter::clear('usulan-mahasiswa:'.$this->mhs->id);
    RateLimiter::clear('ajukan-permohonan:'.$this->pengurus->id);
    RateLimiter::clear('ajukan-permohonan:'.$this->pengaju->id);
});

afterEach(fn () => CarbonImmutable::setTestNow());

function usul(User $mhs, string $tujuan, array $ubah = []): UsulanMahasiswa
{
    return app(AjukanUsulan::class)->jalankan($mhs, $ubah + ['tujuan' => $tujuan, 'perihal' => 'Mohon izin lomba', 'uraian' => 'Kami ingin mengikuti lomba nasional.']);
}

function dataPermohonanDasar(array $ubah = []): array
{
    return $ubah + [
        'nama_kegiatan' => 'Delegasi Lomba', 'perihal' => 'Izin delegasi', 'tanggal_mulai' => '2026-06-20', 'tanggal_selesai' => '2026-06-21',
        'deskripsi' => 'Delegasi lomba nasional.', 'penanggung_jawab' => ['ketua' => ['nama' => 'Budi']],
        'berkas' => ['surat_permohonan' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view', 'proposal' => 'https://drive.google.com/file/d/1ZyXwVuTsRqPoNmLkJi/view'],
    ];
}

function ajukanDasar(User $pengurus, Ormawa $o, array $dasar): Permohonan
{
    return app(AjukanPermohonan::class)->jalankan($pengurus, $o, JenisPermohonan::firstWhere('kode', 'kegiatan'), dataPermohonanDasar(['dasar_usulan' => $dasar]));
}

describe('usulan mahasiswa', function () {
    it('mahasiswa mengusulkan kepada ormawa dengan lampiran tautan', function () {
        $u = usul($this->mhs, "ormawa:{$this->ormawa->id}", ['lampiran' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view']);

        expect($u->status)->toBe(StatusUsulan::Diajukan)->and($u->ormawa_id)->toBe($this->ormawa->id)->and($u->unit_kerja_id)->toBeNull()
            ->and($u->tautan()->where('jenis', 'lampiran')->count())->toBe(1)->and($u->namaTujuan())->toBe('HIMA Mat');
    });

    it('mahasiswa mengusulkan kepada program studi yang punya pengelola', function () {
        $u = usul($this->mhs, "unit:{$this->prodi->id}");

        expect($u->unit_kerja_id)->toBe($this->prodi->id)->and($u->ormawa_id)->toBeNull();
    });

    it('menolak tujuan tak sah, nonaktif, bukan prodi/lab, atau unit tanpa pengelola', function () {
        $nonaktif = Ormawa::create(['nama' => 'Mati', 'tingkat' => 'prodi', 'aktif' => false]);
        $bagian = UnitKerja::create(['kode' => 'BAG', 'nama' => 'Bagian', 'tipe' => 'lainnya']);
        $kosong = UnitKerja::create(['kode' => 'KOS', 'nama' => 'Prodi Kosong', 'tipe' => 'prodi']);

        foreach (["ormawa:{$nonaktif->id}", "unit:{$bagian->id}", "unit:{$kosong->id}", 'ormawa:'.fake()->uuid(), 'sembarang'] as $tujuan) {
            expect(fn () => usul($this->mhs, $tujuan))->toThrow(ValidationException::class);
        }

        expect(UsulanMahasiswa::count())->toBe(0);
    });

    it('hanya berperan mahasiswa yang boleh mengusulkan', function () {
        expect(fn () => usul($this->pengurus, "ormawa:{$this->ormawa->id}"))->toThrow(AuthorizationException::class);
    });

    it('memberi tahu pengurus ormawa tujuan, bukan pengurus lain', function () {
        usul($this->mhs, "ormawa:{$this->ormawa->id}");

        $judul = fn (User $u) => Notification::sent($u, NotifikasiTahap::class)->map(fn ($n) => $n->judul)->all();
        expect($judul($this->pengurus))->toBe(['Usulan mahasiswa baru'])->and($judul($this->pengaju))->toBe([]);

        usul($this->mhs, "unit:{$this->prodi->id}");
        expect($judul($this->pengaju))->toBe(['Usulan mahasiswa baru']);
    });

    it('mahasiswa membatalkan usulannya sendiri, bukan milik orang lain', function () {
        $u = usul($this->mhs, "ormawa:{$this->ormawa->id}");

        expect(fn () => app(BatalkanUsulan::class)->jalankan($u, $this->mhs2))->toThrow(AuthorizationException::class);

        app(BatalkanUsulan::class)->jalankan($u, $this->mhs);

        expect($u->fresh()->status)->toBe(StatusUsulan::Dibatalkan)
            ->and(fn () => app(BatalkanUsulan::class)->jalankan($u->fresh(), $this->mhs))->toThrow(AuthorizationException::class);
    });
});

describe('perantara memilah', function () {
    it('mengembalikan dan menolak mewajibkan catatan, dan mahasiswa diberi tahu', function () {
        $u = usul($this->mhs, "ormawa:{$this->ormawa->id}");

        expect(fn () => app(PutusUsulan::class)->tolak($u, $this->pengurus, ' '))->toThrow(ValidationException::class);

        app(PutusUsulan::class)->kembalikan($u, $this->pengurus, 'Lengkapi lampiran');

        expect($u->fresh()->status)->toBe(StatusUsulan::Dikembalikan)->and($u->fresh()->catatan)->toBe('Lengkapi lampiran')
            ->and(Notification::sent($this->mhs, NotifikasiTahap::class)->map(fn ($n) => $n->judul)->all())->toBe(['Usulan Anda dikembalikan']);
    });

    it('hanya perantara tujuan yang boleh memutus', function () {
        $u = usul($this->mhs, "ormawa:{$this->ormawa->id}");

        expect(fn () => app(PutusUsulan::class)->tolak($u, $this->pengaju, 'x'))->toThrow(AuthorizationException::class)
            ->and(fn () => app(PutusUsulan::class)->tolak($u, $this->mhs, 'x'))->toThrow(AuthorizationException::class)
            ->and($u->fresh()->status)->toBe(StatusUsulan::Diajukan);
    });

    it('usulan yang sudah diputus tidak dapat diputus lagi', function () {
        $u = usul($this->mhs, "ormawa:{$this->ormawa->id}");
        app(PutusUsulan::class)->tolak($u, $this->pengurus, 'Tidak sesuai');

        expect(fn () => app(PutusUsulan::class)->tolak($u->fresh(), $this->pengurus, 'lagi'))->toThrow(ValidationException::class);
    });
});

describe('dasar permohonan', function () {
    it('menggabungkan beberapa usulan menjadi dasar satu permohonan', function () {
        $a = usul($this->mhs, "ormawa:{$this->ormawa->id}", ['perihal' => 'Lomba A']);
        $b = usul($this->mhs2, "ormawa:{$this->ormawa->id}", ['perihal' => 'Lomba B']);

        $p = ajukanDasar($this->pengurus, $this->ormawa, [$a->id, $b->id]);

        expect($p->usulanDasar()->pluck('perihal')->sort()->values()->all())->toBe(['Lomba A', 'Lomba B'])
            ->and($a->fresh()->status)->toBe(StatusUsulan::Dipakai)->and($b->fresh()->permohonan_id)->toBe($p->id);
    });

    it('meneruskan satu usulan apa adanya', function () {
        $a = usul($this->mhs, "ormawa:{$this->ormawa->id}");

        $p = ajukanDasar($this->pengurus, $this->ormawa, [$a->id]);

        expect($p->usulanDasar()->count())->toBe(1);
    });

    it('menolak dasar milik perantara lain, yang sudah dipakai, atau yang bukan hak pengaju', function () {
        $lain = Ormawa::create(['nama' => 'Lain', 'tingkat' => 'prodi']);
        $untukLain = usul($this->mhs, "ormawa:{$lain->id}");
        $untukProdi = usul($this->mhs, "unit:{$this->prodi->id}");
        $sendiri = usul($this->mhs, "ormawa:{$this->ormawa->id}");
        ajukanDasar($this->pengurus, $this->ormawa, [$sendiri->id]);

        foreach ([$untukLain->id, $untukProdi->id, $sendiri->id, fake()->uuid()] as $id) {
            expect(fn () => ajukanDasar($this->pengurus, $this->ormawa, [$id]))->toThrow(ValidationException::class);
        }

        expect(Permohonan::count())->toBe(1);
    });

    it('pengaju unit memakai usulan untuk unitnya', function () {
        $a = usul($this->mhs, "unit:{$this->prodi->id}");

        $p = app(AjukanPermohonan::class)->jalankanUnit($this->pengaju, $this->prodi, JenisPermohonan::firstWhere('kode', 'kegiatan'), dataPermohonanDasar(['dasar_usulan' => [$a->id]]));

        expect($p->usulanDasar()->count())->toBe(1)->and($p->status)->toBe(StatusPermohonan::PersetujuanUnit);
    });

    it('permohonan ditolak atau dibatalkan melepas dasar kembali menunggu perantara', function (string $cara) {
        $a = usul($this->mhs, "ormawa:{$this->ormawa->id}");
        $p = ajukanDasar($this->pengurus, $this->ormawa, [$a->id]);
        $admin = User::factory()->create()->assignRole('admin-persuratan');

        $cara === 'tolak'
            ? app(TolakPermohonan::class)->jalankan($p, $admin, 'Tidak layak')
            : app(BatalkanPermohonan::class)->jalankan($p, $this->pengurus, 'Dibatalkan pengurus');

        $a->refresh();
        expect($a->status)->toBe(StatusUsulan::Diajukan)->and($a->permohonan_id)->toBeNull()->and($a->catatan)->toContain($p->nomor);
    })->with(['tolak', 'batalkan']);

    it('mahasiswa pengusul diberi tahu tiap perubahan tahap permohonan dasar', function () {
        $a = usul($this->mhs, "ormawa:{$this->ormawa->id}");
        $p = ajukanDasar($this->pengurus, $this->ormawa, [$a->id]);
        app(TolakPermohonan::class)->jalankan($p, User::factory()->create()->assignRole('admin-persuratan'), 'Tidak layak');

        expect(Notification::sent($this->mhs, NotifikasiTahap::class)->map(fn ($n) => $n->judul)->all())->toBe(['Permohonan dasar usulan Anda: Ditolak']);
    });

    it('pembina menyetujui permohonan dasar seperti permohonan ormawa biasa', function () {
        $a = usul($this->mhs, "ormawa:{$this->ormawa->id}");
        $p = ajukanDasar($this->pengurus, $this->ormawa, [$a->id]);

        expect($p->status)->toBe(StatusPermohonan::ValidasiAdmin)->and(class_exists(SetujuiPembina::class))->toBeTrue();
    });
});

describe('antarmuka', function () {
    it('mahasiswa mengusulkan lewat formulir dan melihat usulannya', function () {
        Livewire::actingAs($this->mhs)->test(UsulanBaru::class)
            ->set('tujuan', "ormawa:{$this->ormawa->id}")->set('perihal', 'Mohon izin')->set('uraian', 'Uraian usulan')
            ->call('kirim')->assertHasNoErrors()->assertRedirect(route('mahasiswa'));

        Livewire::actingAs($this->mhs)->test(Beranda::class)->assertSee('Mohon izin')->assertSee('Menunggu perantara');
        Livewire::actingAs($this->mhs2)->test(Beranda::class)->assertDontSee('Mohon izin');
    });

    it('peran lain tidak dapat membuka ruang mahasiswa', function () {
        Livewire::actingAs($this->pengurus)->test(Beranda::class)->assertForbidden();
        Livewire::actingAs($this->pengaju)->test(UsulanBaru::class)->assertForbidden();
    });

    it('pengurus memilih usulan lalu formulir pengajuan terisi awal dengan dasar', function () {
        $a = usul($this->mhs, "ormawa:{$this->ormawa->id}", ['perihal' => 'Lomba A']);
        $b = usul($this->mhs2, "ormawa:{$this->ormawa->id}", ['perihal' => 'Lomba B']);

        $halaman = Livewire::actingAs($this->pengurus)->test(UsulanMasukOrmawa::class, ['ormawa' => $this->ormawa->id])
            ->assertSee('Lomba A')->set('dipilih', [$a->id, $b->id])->call('jadikanDasar');
        $halaman->assertRedirect();

        Livewire::actingAs($this->pengurus)->withQueryParams(['dasar' => [$a->id, $b->id]])->test(FormulirOrmawa::class, ['ormawa' => $this->ormawa->id])
            ->assertSet('dasar', [$a->id, $b->id])->assertSee('Dasar permohonan: 2 usulan')->assertSet('perihal', 'Usulan kolektif: Lomba A; Lomba B');
    });

    it('usulan perantara lain dibuang dari dasar formulir', function () {
        $lain = Ormawa::create(['nama' => 'Lain', 'tingkat' => 'prodi']);
        $asing = usul($this->mhs, "ormawa:{$lain->id}");

        Livewire::actingAs($this->pengurus)->withQueryParams(['dasar' => [$asing->id]])->test(FormulirOrmawa::class, ['ormawa' => $this->ormawa->id])
            ->assertSet('dasar', [])->assertDontSee('Dasar permohonan');
    });

    it('usulan masuk menolak orang luar dan menampilkan hanya usulan perantaranya', function () {
        usul($this->mhs, "ormawa:{$this->ormawa->id}", ['perihal' => 'Untuk ormawa']);
        usul($this->mhs, "unit:{$this->prodi->id}", ['perihal' => 'Untuk prodi']);

        Livewire::actingAs($this->pengurus)->test(UsulanMasukOrmawa::class, ['ormawa' => $this->ormawa->id])->assertSee('Untuk ormawa')->assertDontSee('Untuk prodi');
        Livewire::actingAs($this->pengaju)->test(UsulanMasukUnit::class, ['unit' => $this->prodi->id])->assertSee('Untuk prodi')->assertDontSee('Untuk ormawa');
        Livewire::actingAs($this->pengaju)->test(UsulanMasukOrmawa::class, ['ormawa' => $this->ormawa->id])->assertForbidden();
        Livewire::actingAs($this->kaprodi)->test(UsulanMasukUnit::class, ['unit' => $this->prodi->id])->assertForbidden();
        Livewire::actingAs($this->mhs)->test(UsulanMasukOrmawa::class, ['ormawa' => $this->ormawa->id])->assertForbidden();
    });

    it('pendaftaran mandiri dapat memilih jalur mahasiswa', function () {
        $this->post(route('daftar'), [
            'name' => 'Siti', 'nip_nim' => '9911223', 'email' => 'siti@student.unsil.ac.id', 'password' => 'Rahasia12345', 'password_confirmation' => 'Rahasia12345', 'jalur' => 'mahasiswa',
        ])->assertRedirect(route('login'));

        expect(User::firstWhere('email', 'siti@student.unsil.ac.id')->hasRole('mahasiswa'))->toBeTrue();
    });
});
