<?php

use App\Actions\Permohonan\AjukanPermohonan as AksiAjukan;
use App\Enums\StatusPermohonan;
use App\Livewire\Unit\AjukanPermohonan;
use App\Livewire\Unit\Beranda;
use App\Livewire\Unit\Persetujuan;
use App\Livewire\Unit\Progres;
use App\Models\JenisPermohonan;
use App\Models\Permohonan;
use App\Models\UnitKerja;
use App\Models\User;
use App\Support\Beranda as BerandaPeran;
use Carbon\CarbonImmutable;
use Database\Seeders\JenisNaskahSeeder;
use Database\Seeders\JenisPermohonanSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Database\Seeders\StrukturFkipSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    $this->withoutVite();
    $this->seed([PeranDanIzinSeeder::class, StrukturFkipSeeder::class, PengaturanSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class, JenisPermohonanSeeder::class]);
    CarbonImmutable::setTestNow('2026-06-01 09:00:00');

    $this->prodi = UnitKerja::create(['kode' => 'PMAT', 'nama' => 'Pendidikan Matematika', 'tipe' => 'prodi']);
    $this->lain = UnitKerja::create(['kode' => 'PIND', 'nama' => 'Pendidikan Bahasa Indonesia', 'tipe' => 'prodi']);
    $this->pengaju = User::factory()->create()->assignRole('pengaju-unit');
    $this->kaprodi = User::factory()->create()->assignRole('kaprodi');
    $this->prodi->pengaju()->attach($this->pengaju);
    $this->prodi->penyetuju()->attach($this->kaprodi);
    $this->lain->penyetuju()->attach(User::factory()->create()->assignRole('kaprodi'));
    RateLimiter::clear('ajukan-permohonan:'.$this->pengaju->id);
});

afterEach(fn () => CarbonImmutable::setTestNow());

function isiFormulirUnit($komponen)
{
    return $komponen->set('jenis', JenisPermohonan::firstWhere('kode', 'kegiatan')->id)
        ->set('nama_kegiatan', 'Workshop')->set('perihal', 'Izin workshop')->set('tanggal_mulai', '2026-06-20')->set('tanggal_selesai', '2026-06-20')
        ->set('deskripsi', 'Workshop internal')->set('pj.ketua.nama', 'Dr. Rani')
        ->set('berkas.surat_permohonan', 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view')
        ->set('berkas.proposal', 'https://drive.google.com/file/d/1ZyXwVuTsRqPoNmLkJi/view');
}

function permohonanMenunggu(UnitKerja $unit, User $pengaju): Permohonan
{
    return app(AksiAjukan::class)->jalankanUnit($pengaju, $unit, JenisPermohonan::firstWhere('kode', 'kegiatan'), [
        'nama_kegiatan' => 'Workshop', 'perihal' => 'Izin workshop', 'tanggal_mulai' => '2026-06-20', 'tanggal_selesai' => '2026-06-20',
        'deskripsi' => 'Workshop internal', 'penanggung_jawab' => ['ketua' => ['nama' => 'Dr. Rani']],
        'berkas' => ['surat_permohonan' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view', 'proposal' => 'https://drive.google.com/file/d/1ZyXwVuTsRqPoNmLkJi/view'],
    ]);
}

describe('akses', function () {
    it('mengarahkan peran unit ke ruang unit dan menolak tamu serta peran lain', function () {
        expect(BerandaPeran::url($this->pengaju))->toBe(route('unit'))->and(BerandaPeran::url($this->kaprodi))->toBe(route('unit'));

        $this->get(route('unit'))->assertRedirect('/masuk');
        Livewire::actingAs(User::factory()->create()->assignRole('pegawai'))->test(Beranda::class)->assertForbidden();
        Livewire::actingAs(User::factory()->create()->assignRole('pengurus-ormawa'))->test(Beranda::class)->assertForbidden();
    });

    it('hanya pengaju yang ditugaskan boleh membuka formulir; kepala unit dan unit lain tidak', function () {
        Livewire::actingAs($this->pengaju)->test(AjukanPermohonan::class, ['unit' => $this->prodi->id])->assertOk();
        Livewire::actingAs($this->pengaju)->test(AjukanPermohonan::class, ['unit' => $this->lain->id])->assertForbidden();
        Livewire::actingAs($this->kaprodi)->test(AjukanPermohonan::class, ['unit' => $this->prodi->id])->assertForbidden();
    });

    it('progres hanya untuk pengaju atau kepala unit yang ditugaskan', function () {
        Livewire::actingAs($this->pengaju)->test(Progres::class, ['unit' => $this->prodi->id])->assertOk();
        Livewire::actingAs($this->kaprodi)->test(Progres::class, ['unit' => $this->prodi->id])->assertOk();
        Livewire::actingAs($this->pengaju)->test(Progres::class, ['unit' => $this->lain->id])->assertForbidden();
        Livewire::actingAs($this->kaprodi)->test(Progres::class, ['unit' => $this->lain->id])->assertForbidden();
    });

    it('properti unit terkunci dari klien', function () {
        expect(fn () => Livewire::actingAs($this->pengaju)->test(AjukanPermohonan::class, ['unit' => $this->prodi->id])->set('unitId', $this->lain->id))
            ->toThrow(Exception::class, 'Cannot update locked property');
    });
});

describe('beranda dan pengajuan', function () {
    it('menampilkan unit yang ditugaskan beserta tautan ajukan hanya bagi pengaju', function () {
        Livewire::actingAs($this->pengaju)->test(Beranda::class)->assertSee('Pendidikan Matematika')->assertDontSee('Pendidikan Bahasa Indonesia')->assertSee('Ajukan baru');
        Livewire::actingAs($this->kaprodi)->test(Beranda::class)->assertSee('Pendidikan Matematika')->assertDontSee('Ajukan baru')->assertSee('Menunggu persetujuan Anda');
    });

    it('menjelaskan bila akun belum ditugaskan', function () {
        Livewire::actingAs(User::factory()->create()->assignRole('pengaju-unit'))->test(Beranda::class)->assertSee('belum ditugaskan');
    });

    it('mengirim permohonan atas nama unit lalu mengalihkan ke progres unit', function () {
        isiFormulirUnit(Livewire::actingAs($this->pengaju)->test(AjukanPermohonan::class, ['unit' => $this->prodi->id]))
            ->call('kirim')->assertHasNoErrors()->assertRedirect(route('unit.permohonan', $this->prodi));

        $p = Permohonan::firstOrFail();
        expect($p->unit_kerja_id)->toBe($this->prodi->id)->and($p->ormawa_id)->toBeNull()->and($p->status)->toBe(StatusPermohonan::PersetujuanUnit);
    });

    it('menampilkan galat bila unit belum punya kepala penyetuju', function () {
        $this->prodi->penyetuju()->sync([]);

        isiFormulirUnit(Livewire::actingAs($this->pengaju)->test(AjukanPermohonan::class, ['unit' => $this->prodi->id]))
            ->call('kirim')->assertHasErrors('unit');

        expect(Permohonan::count())->toBe(0);
    });

    it('progres menampilkan daftar dan linimasa permohonan unit', function () {
        $p = permohonanMenunggu($this->prodi, $this->pengaju);

        Livewire::actingAs($this->pengaju)->test(Progres::class, ['unit' => $this->prodi->id])
            ->assertSee('Workshop')->call('pilih', $p->id)->assertSee('Menunggu persetujuan kepala program studi / laboratorium');
    });
});

describe('persetujuan', function () {
    it('kepala unit hanya melihat permohonan unitnya', function () {
        permohonanMenunggu($this->prodi, $this->pengaju);
        $kepalaLain = $this->lain->penyetuju()->first();

        Livewire::actingAs($this->kaprodi)->test(Persetujuan::class)->assertSee('Workshop');
        Livewire::actingAs($kepalaLain)->test(Persetujuan::class)->assertDontSee('Workshop');
        Livewire::actingAs($this->pengaju)->test(Persetujuan::class)->assertForbidden();
    });

    it('menyetujui meneruskan permohonan ke validasi admin', function () {
        $p = permohonanMenunggu($this->prodi, $this->pengaju);

        Livewire::actingAs($this->kaprodi)->test(Persetujuan::class)->call('setujui', $p->id)->assertHasNoErrors();

        expect($p->fresh()->status)->toBe(StatusPermohonan::ValidasiAdmin);
    });

    it('mengembalikan dan menolak mewajibkan catatan', function () {
        $p = permohonanMenunggu($this->prodi, $this->pengaju);

        Livewire::actingAs($this->kaprodi)->test(Persetujuan::class)
            ->call('kembalikan', $p->id)->assertHasErrors("catatan.{$p->id}")
            ->call('tolak', $p->id)->assertHasErrors("catatan.{$p->id}")
            ->set("catatan.{$p->id}", 'Lengkapi anggaran')->call('kembalikan', $p->id)->assertHasNoErrors();

        expect($p->fresh()->status)->toBe(StatusPermohonan::Dikembalikan);
    });

    it('kepala unit lain tidak dapat memutus permohonan unit ini', function () {
        $p = permohonanMenunggu($this->prodi, $this->pengaju);
        $kepalaLain = $this->lain->penyetuju()->first();

        Livewire::actingAs($kepalaLain)->test(Persetujuan::class)->call('setujui', $p->id)->assertNotFound();

        expect($p->fresh()->status)->toBe(StatusPermohonan::PersetujuanUnit);
    });
});
