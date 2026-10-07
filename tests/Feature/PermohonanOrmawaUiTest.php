<?php

use App\Contracts\LayananRuangan;
use App\Exceptions\LayananRuanganTidakTersedia;
use App\Livewire\Ormawa\AjukanPermohonan;
use App\Livewire\Ormawa\Beranda;
use App\Livewire\Ormawa\Progres;
use App\Models\JenisPermohonan;
use App\Models\Ormawa;
use App\Models\PengurusOrmawa;
use App\Models\Permohonan;
use App\Models\RuanganLokal;
use App\Models\User;
use App\Services\Ruangan\LayananRuanganLokal;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Seeders\JenisNaskahSeeder;
use Database\Seeders\JenisPermohonanSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $this->withoutVite();
    $this->seed([PeranDanIzinSeeder::class, PengaturanSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class, JenisPermohonanSeeder::class]);
    CarbonImmutable::setTestNow('2026-06-01 09:00:00');
});

afterEach(fn () => CarbonImmutable::setTestNow());

function himaUi(string $nama = 'HIMA Mat', string $jabatan = 'ketua'): array
{
    $o = Ormawa::create(['nama' => $nama, 'singkatan' => 'HM', 'tingkat' => 'prodi']);
    $o->sk()->create(['nomor_sk' => "SK/{$nama}", 'tanggal_sk' => '2026-01-01', 'periode_mulai' => '2026-01-01', 'periode_selesai' => '2026-12-31']);
    $u = User::factory()->create(['nip_nim' => (string) random_int(6000000, 6999999)])->assignRole('pengurus-ormawa');
    PengurusOrmawa::create(['ormawa_id' => $o->id, 'user_id' => $u->id, 'nama' => $u->name, 'jabatan' => $jabatan]);
    RateLimiter::clear('ajukan-permohonan:'.$u->id);

    return [$o, $u];
}

function isiFormulir($komponen, string $kodeJenis = 'kegiatan')
{
    return $komponen->set('jenis', JenisPermohonan::firstWhere('kode', $kodeJenis)->id)
        ->set('nama_kegiatan', 'Seminar')->set('perihal', 'Izin seminar')->set('tanggal_mulai', '2026-06-20')->set('tanggal_selesai', '2026-06-20')
        ->set('deskripsi', 'Seminar nasional')->set('pj.ketua.nama', 'Budi')->set('pj.ketua.hp', '0812000')
        ->set('berkas.surat_permohonan', 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view')
        ->set('berkas.proposal', 'https://drive.google.com/file/d/1ZyXwVuTsRqPoNmLkJi/view');
}

describe('akses', function () {
    it('hanya pengurus aktif ormawa itu', function () {
        [$o, $u] = himaUi();
        [$lain] = himaUi('Lain');

        $this->get(route('ormawa.permohonan.baru', $o))->assertRedirect('/masuk');
        Livewire::actingAs($u)->test(AjukanPermohonan::class, ['ormawa' => $o->id])->assertOk();
        Livewire::actingAs($u)->test(AjukanPermohonan::class, ['ormawa' => $lain->id])->assertForbidden();
        Livewire::actingAs($u)->test(Progres::class, ['ormawa' => $lain->id])->assertForbidden();
        Livewire::actingAs(User::factory()->create()->assignRole('pegawai'))->test(AjukanPermohonan::class, ['ormawa' => $o->id])->assertForbidden();
    });
});

describe('pengajuan lewat formulir', function () {
    it('mengirim permohonan sederhana dan mengalihkan ke progres', function () {
        [$o, $u] = himaUi();

        isiFormulir(Livewire::actingAs($u)->test(AjukanPermohonan::class, ['ormawa' => $o->id]))
            ->call('kirim')->assertHasNoErrors()->assertRedirect(route('ormawa.permohonan', $o));

        $p = Permohonan::firstOrFail();
        expect($p->nomor)->toBe('PMH-2026-0001')->and($p->diajukan_oleh)->toBe($u->id)->and($p->penanggung_jawab['ketua']['hp'])->toBe('0812000');
    });

    it('berpindah langkah dan menampilkan peringatan BR-15 saat terlalu dekat', function () {
        [$o, $u] = himaUi();

        Livewire::actingAs($u)->test(AjukanPermohonan::class, ['ormawa' => $o->id])
            ->set('tanggal_mulai', '2026-06-03')->assertSee('paling lambat 7 hari')->assertSee('Alasan mendesak')
            ->set('tanggal_mulai', '2026-06-25')->assertDontSee('Alasan mendesak')
            ->call('lanjut')->assertSet('langkah', 2)->call('kembali')->assertSet('langkah', 1);
    });

    it('menampilkan galat dari aksi (berkas wajib kosong) tanpa menyimpan', function () {
        [$o, $u] = himaUi();

        isiFormulir(Livewire::actingAs($u)->test(AjukanPermohonan::class, ['ormawa' => $o->id]))
            ->set('berkas.proposal', '')->call('kirim')->assertHasErrors();

        expect(Permohonan::count())->toBe(0);
    });

    it('menampilkan ketersediaan ruangan dan menyimpan pilihan sesi', function () {
        [$o, $u] = himaUi();
        RuanganLokal::create(['kode' => 'A101', 'nama' => 'Ruang A101']);
        (new LayananRuanganLokal)->catatPemakaian('A101', '2026-06-20', 'pagi');

        $k = isiFormulir(Livewire::actingAs($u)->test(AjukanPermohonan::class, ['ormawa' => $o->id]), 'kegiatan-ruangan')
            ->call('lanjut')->assertSee('Ruang A101')->assertSee('pagi (terpakai)')->assertSee('seharian (terpakai)')->assertDontSee('siang (terpakai)');

        $k->set('pilihanRuangan.A101|2026-06-20', 'siang')->call('kirim')->assertHasNoErrors();

        $p = Permohonan::with('ruangan')->firstOrFail();
        expect($p->ruangan)->toHaveCount(1)->and($p->ruangan[0]->sesi)->toBe('siang');
    });

    it('menolak pilihan sesi yang sudah terpakai saat kirim (pemeriksaan server)', function () {
        [$o, $u] = himaUi();
        RuanganLokal::create(['kode' => 'A101', 'nama' => 'Ruang A101']);
        (new LayananRuanganLokal)->catatPemakaian('A101', '2026-06-20', 'pagi');

        isiFormulir(Livewire::actingAs($u)->test(AjukanPermohonan::class, ['ormawa' => $o->id]), 'kegiatan-ruangan')
            ->set('pilihanRuangan.A101|2026-06-20', 'pagi')->call('kirim')->assertHasErrors('ruangan');

        expect(Permohonan::count())->toBe(0);
    });

    it('memberi tahu bila layanan ruangan tidak tersedia, di langkah ruangan maupun saat kirim', function () {
        [$o, $u] = himaUi();
        app()->bind(LayananRuangan::class, fn () => new class implements LayananRuangan
        {
            public function daftar(): array
            {
                throw new LayananRuanganTidakTersedia('down');
            }

            public function jadwal(string $kode, CarbonInterface $dari, CarbonInterface $sampai): array
            {
                throw new LayananRuanganTidakTersedia('down');
            }

            public function catatPemakaian(string $kode, string $tanggal, string $sesi, ?string $referensi = null, ?string $keterangan = null): string
            {
                throw new LayananRuanganTidakTersedia('down');
            }
        });

        isiFormulir(Livewire::actingAs($u)->test(AjukanPermohonan::class, ['ormawa' => $o->id]), 'kegiatan-ruangan')
            ->call('lanjut')->assertSee('Layanan ruangan sedang tidak tersedia')
            ->set('pilihanRuangan.A101|2026-06-20', 'pagi')->call('kirim')->assertHasErrors('umum');

        expect(Permohonan::count())->toBe(0);
    });

    it('menampilkan pesan batas laju setelah terlalu banyak percobaan', function () {
        [$o, $u] = himaUi();
        $k = Livewire::actingAs($u)->test(AjukanPermohonan::class, ['ormawa' => $o->id]);

        foreach (range(1, 10) as $i) {
            $k->call('kirim');
        }

        isiFormulir($k)->call('kirim')->assertHasErrors('umum')->assertSee('Terlalu banyak percobaan');
        expect(Permohonan::count())->toBe(0);
    });

    it('menambah dan menghapus baris fasilitas rektorat', function () {
        [$o, $u] = himaUi();

        Livewire::actingAs($u)->test(AjukanPermohonan::class, ['ormawa' => $o->id])
            ->call('tambahFasilitas')->call('tambahFasilitas')->assertCount('fasilitas', 2)->call('hapusFasilitas', 0)->assertCount('fasilitas', 1);
    });
});

describe('progres', function () {
    it('menampilkan daftar, detail, dan linimasa dari riwayat', function () {
        [$o, $u] = himaUi();
        isiFormulir(Livewire::actingAs($u)->test(AjukanPermohonan::class, ['ormawa' => $o->id]))->call('kirim');
        $p = Permohonan::firstOrFail();

        Livewire::actingAs($u)->test(Progres::class, ['ormawa' => $o->id])
            ->assertSee('PMH-2026-0001')->assertSee('Seminar')->assertSee('Validasi admin')
            ->call('pilih', $p->id)->assertSee('Seminar nasional')->assertSee('Diajukan')->assertSee('Linimasa');
    });

    it('menyembunyikan data pribadi penanggung jawab dari anggota biasa, menampilkannya untuk ketua dan pengaju', function () {
        [$o, $ketua] = himaUi();
        isiFormulir(Livewire::actingAs($ketua)->test(AjukanPermohonan::class, ['ormawa' => $o->id]))->call('kirim');
        $p = Permohonan::firstOrFail();
        $anggota = User::factory()->create(['nip_nim' => '6111111'])->assignRole('pengurus-ormawa');
        PengurusOrmawa::create(['ormawa_id' => $o->id, 'user_id' => $anggota->id, 'nama' => 'Anggota', 'jabatan' => 'anggota']);

        Livewire::actingAs($ketua)->test(Progres::class, ['ormawa' => $o->id])->call('pilih', $p->id)->assertSee('0812000');
        Livewire::actingAs($anggota)->test(Progres::class, ['ormawa' => $o->id])->assertSee('PMH-2026-0001')->call('pilih', $p->id)->assertDontSee('0812000');
    });

    it('menolak membuka permohonan ormawa lain', function () {
        [$o, $u] = himaUi();
        [$lain, $uLain] = himaUi('Lain');
        isiFormulir(Livewire::actingAs($uLain)->test(AjukanPermohonan::class, ['ormawa' => $lain->id]))->call('kirim');
        $milikLain = Permohonan::firstOrFail();

        Livewire::actingAs($u)->test(Progres::class, ['ormawa' => $o->id])->call('pilih', $milikLain->id)->assertNotFound();
        Livewire::actingAs($u)->test(Progres::class, ['ormawa' => $o->id])->assertSee('Belum ada permohonan')->assertDontSee('Seminar');
    });

    it('menampilkan ringkasan di beranda ormawa', function () {
        [$o, $u] = himaUi();
        Livewire::actingAs($u)->test(Beranda::class)->assertSee('Belum ada permohonan')->assertSee('Ajukan baru');

        isiFormulir(Livewire::actingAs($u)->test(AjukanPermohonan::class, ['ormawa' => $o->id]))->call('kirim');
        Livewire::actingAs($u)->test(Beranda::class)->assertSee('1 permohonan tercatat');
    });
});
