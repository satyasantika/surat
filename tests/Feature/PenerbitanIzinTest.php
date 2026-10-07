<?php

use App\Actions\Naskah\AjukanParaf;
use App\Actions\Naskah\TandaTangani;
use App\Actions\Naskah\TransisiNaskah;
use App\Actions\Permohonan\AjukanPermohonan;
use App\Actions\Permohonan\BuatPengantarRektorat;
use App\Actions\Permohonan\TerbitkanIzin;
use App\Contracts\LayananRuangan;
use App\Enums\StatusPermohonan;
use App\Exceptions\LayananRuanganTidakTersedia;
use App\Filament\Resources\Permohonans\Pages\ViewPermohonan;
use App\Jobs\CatatPemakaianRuangan;
use App\Jobs\TerbitkanNaskah;
use App\Livewire\Ormawa\Progres;
use App\Models\Aktivitas;
use App\Models\Jabatan;
use App\Models\JenisPermohonan;
use App\Models\Naskah;
use App\Models\Ormawa;
use App\Models\PemakaianRuanganLokal;
use App\Models\PemangkuJabatan;
use App\Models\PengurusOrmawa;
use App\Models\Permohonan;
use App\Models\PermohonanRuangan;
use App\Models\RuanganLokal;
use App\Models\User;
use App\Services\Naskah\RenderNaskah;
use App\Services\Ruangan\LayananRuanganLokal;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Seeders\JenisNaskahSeeder;
use Database\Seeders\JenisPermohonanSeeder;
use Database\Seeders\KlasifikasiArsipSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Database\Seeders\StrukturFkipSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $GLOBALS['urutanRuangIzin'] = 0;
    Queue::fake();
    $this->withoutVite();
    $this->seed([PeranDanIzinSeeder::class, StrukturFkipSeeder::class, PengaturanSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class, JenisPermohonanSeeder::class, KlasifikasiArsipSeeder::class]);
    Filament::setCurrentPanel('admin');
    CarbonImmutable::setTestNow('2026-06-01 09:00:00');
    RuanganLokal::create(['kode' => 'A101', 'nama' => 'Ruang A101']);
});

afterEach(fn () => CarbonImmutable::setTestNow());

function adminIzin(): User
{
    $u = User::factory()->create()->assignRole('admin-persuratan');
    $u->forceFill(['app_authentication_secret' => 'JBSWY3DPEHPK3PXP'])->save();

    return $u;
}

function dekanIzin(): User
{
    $jabatan = Jabatan::firstWhere('kode', 'dekan');

    if ($p = $jabatan->pemangkuPada(now())) {
        return $p->user;
    }

    $u = User::factory()->create()->assignRole('dekan');
    PemangkuJabatan::create(['jabatan_id' => $jabatan->id, 'user_id' => $u->id, 'mulai' => '2026-01-01']);

    return $u;
}

/** Permohonan yang sudah berstatus penerbitan, dengan ruangan ditahan. */
function permohonanPenerbitan(string $jenis = 'kegiatan-ruangan', array $ubah = []): array
{
    // Pemanggilan pertama memakai A101; berikutnya ruangan baru agar tidak saling bentrok.
    $urutan = $GLOBALS['urutanRuangIzin'] = ($GLOBALS['urutanRuangIzin'] ?? 0) + 1;
    $kodeRuang = $urutan === 1 ? 'A101' : 'RX'.$urutan;
    RuanganLokal::firstOrCreate(['kode' => $kodeRuang], ['nama' => "Ruang {$kodeRuang}"]);

    $o = Ormawa::create(['nama' => 'HIMA '.random_int(1, 99999), 'tingkat' => 'prodi']);
    $o->sk()->create(['nomor_sk' => 'SK/1', 'tanggal_sk' => '2026-01-01', 'periode_mulai' => '2026-01-01', 'periode_selesai' => '2026-12-31']);
    $u = User::factory()->create(['nip_nim' => (string) random_int(9000000, 9999999)])->assignRole('pengurus-ormawa');
    PengurusOrmawa::create(['ormawa_id' => $o->id, 'user_id' => $u->id, 'nama' => $u->name, 'jabatan' => 'ketua']);
    RateLimiter::clear('ajukan-permohonan:'.$u->id);

    $p = app(AjukanPermohonan::class)->jalankan($u, $o, JenisPermohonan::firstWhere('kode', $jenis), $ubah + [
        'nama_kegiatan' => 'Seminar Nasional', 'perihal' => 'Izin seminar', 'tanggal_mulai' => '2026-06-20', 'tanggal_selesai' => '2026-06-20', 'deskripsi' => 'Deskripsi',
        'penanggung_jawab' => ['ketua' => ['nama' => 'Budi Santoso']],
        'berkas' => ['surat_permohonan' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view', 'proposal' => 'https://drive.google.com/file/d/1ZyXwVuTsRqPoNmLkJi/view'],
        'ruangan' => [['kode' => $kodeRuang, 'tanggal' => '2026-06-20', 'sesi' => 'pagi']],
    ] + ($jenis === 'kegiatan-ruangan-rektorat' ? ['fasilitas_rektorat' => [['nama' => 'Aula <b>Rektorat</b>', 'jumlah' => 1, 'keterangan' => 'Pagi hari']]] : []));

    $p->forceFill(['status' => StatusPermohonan::Penerbitan])->saveQuietly();

    return [$p->fresh(), $o, $u];
}

/** Menandatangani & menerbitkan naskah izin hingga event NaskahTerbit berjalan. */
function terbitkanNaskahIzin(Naskah $naskah): Naskah
{
    $admin = adminIzin();
    app(AjukanParaf::class)->jalankan($naskah, $naskah->penyusun, []);
    app(TandaTangani::class)->jalankan($naskah->fresh(), dekanIzin());
    (new TerbitkanNaskah($naskah->fresh()))->handle(app(RenderNaskah::class), app(TransisiNaskah::class));

    return $naskah->fresh();
}

describe('pembuatan draf surat izin', function () {
    it('membuat draf dengan variabel terisi otomatis dan menautkannya ke permohonan', function () {
        [$p, $o] = permohonanPenerbitan();

        $n = app(TerbitkanIzin::class)->jalankan($p, adminIzin());
        $p = $p->fresh();

        expect($n->jenis->kode)->toBe('surat-izin-kegiatan')->and($n->status->value)->toBe('draf')->and($n->permohonan_id)->toBe($p->id)->and($p->naskah_izin_id)->toBe($n->id)
            ->and($n->perihal)->toBe('Izin seminar')
            ->and($n->data)->toMatchArray(['ormawa' => $o->nama, 'nama_kegiatan' => 'Seminar Nasional', 'tanggal_mulai' => '2026-06-20', 'tanggal_selesai' => '2026-06-20', 'penanggung_jawab' => 'Budi Santoso'])
            ->and($n->data['tempat'])->toContain('Ruang A101')->toContain('pagi')->toContain('20 Juni 2026')
            ->and($n->tujuan->pluck('nama')->all())->toBe(["Ketua {$o->nama}"])
            ->and($n->klasifikasiArsip->kode)->toBe('KM.03.02');
    });

    it('memakai templat pengantar proposal untuk jenisnya dan tempat lain bila tanpa ruangan', function () {
        [$p] = permohonanPenerbitan('pengantar-proposal', ['berkas' => ['proposal' => 'https://drive.google.com/file/d/1ZyXwVuTsRqPoNmLkJi/view']]);
        $p->update(['tempat_lain' => 'Aula Desa']);

        $n = app(TerbitkanIzin::class)->jalankan($p, adminIzin());

        expect($n->jenis->kode)->toBe('pengantar-proposal')->and($n->data)->toMatchArray(['nama_kegiatan' => 'Seminar Nasional', 'tujuan' => "Ketua {$p->ormawa->nama}"]);
    });

    it('hanya admin pada status penerbitan, dan hanya sekali', function () {
        [$p, , $u] = permohonanPenerbitan();

        expect(fn () => app(TerbitkanIzin::class)->jalankan($p, $u))->toThrow(AuthorizationException::class)
            ->and(fn () => app(TerbitkanIzin::class)->jalankan($p, User::factory()->create()->assignRole('dekan')))->toThrow(AuthorizationException::class);

        app(TerbitkanIzin::class)->jalankan($p, adminIzin());
        expect(fn () => app(TerbitkanIzin::class)->jalankan($p->fresh(), adminIzin()))->toThrow(AuthorizationException::class)
            ->and(Naskah::count())->toBe(1);

        $lain = permohonanPenerbitan()[0];
        $lain->forceFill(['status' => StatusPermohonan::ValidasiAdmin])->saveQuietly();
        expect(fn () => app(TerbitkanIzin::class)->jalankan($lain->fresh(), adminIzin()))->toThrow(ValidationException::class);
    });

    it('menolak penerbitan bila ruangan kini bentrok dan tidak membuat naskah', function () {
        [$p] = permohonanPenerbitan();
        (new LayananRuanganLokal)->catatPemakaian('A101', '2026-06-20', 'pagi', 'pihak-lain', 'Ujian');

        try {
            app(TerbitkanIzin::class)->jalankan($p, adminIzin());
            $this->fail('seharusnya ditolak');
        } catch (ValidationException $e) {
            expect($e->errors()['ruangan'][0])->toContain('Ruang A101')->toContain('20 Juni 2026')->toContain('Penerbitan ditolak');
        }

        expect(Naskah::count())->toBe(0)->and($p->fresh()->naskah_izin_id)->toBeNull();
    });
});

describe('setelah surat izin terbit', function () {
    it('menyelesaikan permohonan, mengonfirmasi ruangan, dan mengantrekan pencatatan', function () {
        [$p] = permohonanPenerbitan();
        $n = app(TerbitkanIzin::class)->jalankan($p, adminIzin());

        $n = terbitkanNaskahIzin($n);
        $p = $p->fresh();

        expect($n->status->value)->toBe('terbit')->and($n->nomor)->not->toBeNull()
            ->and($p->status)->toBe(StatusPermohonan::Selesai)->and($p->selesai_pada)->not->toBeNull()
            ->and($p->ruangan->pluck('status')->all())->toBe(['dikonfirmasi'])
            ->and($p->riwayat->last()->catatan)->toContain($n->nomor);
        Queue::assertPushedOn('integrasi', CatatPemakaianRuangan::class);
    });

    it('mencatat pemakaian ke layanan satu kali walau job diulang', function () {
        [$p] = permohonanPenerbitan();
        terbitkanNaskahIzin(app(TerbitkanIzin::class)->jalankan($p, adminIzin()));

        $job = new CatatPemakaianRuangan($p->fresh());
        $job->handle(app(LayananRuangan::class));
        $job->handle(app(LayananRuangan::class));
        (new CatatPemakaianRuangan($p->fresh()))->handle(app(LayananRuangan::class));

        $pemakaian = PemakaianRuanganLokal::where('permohonan_id', $p->id)->get();
        expect($pemakaian)->toHaveCount(1)->and($pemakaian[0]->sesi)->toBe('pagi')->and($pemakaian[0]->tanggal->toDateString())->toBe('2026-06-20')
            ->and($p->fresh()->ruangan[0]->id_pemakaian_aset)->not->toBeNull();
    });

    it('tidak menyelesaikan permohonan bila konfirmasi bentrok pada saat terbit (ditinjau admin)', function () {
        [$p] = permohonanPenerbitan();
        $n = app(TerbitkanIzin::class)->jalankan($p, adminIzin());
        (new LayananRuanganLokal)->catatPemakaian('A101', '2026-06-20', 'pagi', 'pihak-lain', 'Disisipkan setelah draf');

        $n = terbitkanNaskahIzin($n);
        $p = $p->fresh();

        expect($n->status->value)->toBe('terbit')->and($p->status)->toBe(StatusPermohonan::Penerbitan)->and($p->ruangan->pluck('status')->all())->toBe(['ditahan'])
            ->and(Aktivitas::where('log_name', 'permohonan')->where('event', 'bentrok-penerbitan')->exists())->toBeTrue();
        Queue::assertNotPushed(CatatPemakaianRuangan::class);
    });

    it('permohonan tanpa ruangan tetap selesai dan tidak mengantre pemakaian kosong', function () {
        [$p] = permohonanPenerbitan('kegiatan', ['ruangan' => []]);
        terbitkanNaskahIzin(app(TerbitkanIzin::class)->jalankan($p, adminIzin()));

        expect($p->fresh()->status)->toBe(StatusPermohonan::Selesai);
        (new CatatPemakaianRuangan($p->fresh()))->handle(app(LayananRuangan::class));
        expect(PemakaianRuanganLokal::count())->toBe(0);
    });

    it('naskah tanpa permohonan atau surat pengantar tidak menyelesaikan permohonan', function () {
        [$p] = permohonanPenerbitan('kegiatan-ruangan-rektorat');
        $izin = app(TerbitkanIzin::class)->jalankan($p, adminIzin());
        $pengantar = app(BuatPengantarRektorat::class)->jalankan($p->fresh(), adminIzin());

        terbitkanNaskahIzin($pengantar);
        expect($p->fresh()->status)->toBe(StatusPermohonan::Penerbitan);

        terbitkanNaskahIzin($izin);
        expect($p->fresh()->status)->toBe(StatusPermohonan::Selesai);
    });
});

describe('pencatatan ruangan gagal', function () {
    it('meneruskan galat layanan agar antrean mencoba ulang', function () {
        [$p] = permohonanPenerbitan();
        terbitkanNaskahIzin(app(TerbitkanIzin::class)->jalankan($p, adminIzin()));
        $gagal = new class implements LayananRuangan
        {
            public function daftar(): array
            {
                return [];
            }

            public function jadwal(string $kode, CarbonInterface $dari, CarbonInterface $sampai): array
            {
                return [];
            }

            public function catatPemakaian(string $kode, string $tanggal, string $sesi, ?string $referensi = null, ?string $keterangan = null): string
            {
                throw new LayananRuanganTidakTersedia('Aset down');
            }
        };

        expect(fn () => (new CatatPemakaianRuangan($p->fresh()))->handle($gagal))->toThrow(LayananRuanganTidakTersedia::class);
        expect($p->fresh()->ruangan[0]->id_pemakaian_aset)->toBeNull();
        expect((new CatatPemakaianRuangan($p))->backoff())->toBe([30, 120, 600, 1800])->and((new CatatPemakaianRuangan($p))->tries)->toBe(5);
    });

    it('memberi tahu admin lewat notifikasi database saat job gagal permanen', function () {
        [$p] = permohonanPenerbitan();
        $admin = adminIzin();

        (new CatatPemakaianRuangan($p))->failed(new LayananRuanganTidakTersedia('Aset down'));

        Queue::assertPushed(SendQueuedNotifications::class, 1);
        expect(Aktivitas::where('event', 'pemakaian-gagal')->first()->description)->toContain($p->nomor)->toContain('Aset down')
            ->and($admin->exists)->toBeTrue();
    });

    it('bentrok di layanan dilaporkan ke admin tanpa mengulang', function () {
        [$p] = permohonanPenerbitan();
        $admin = adminIzin();
        $p->ruangan()->update(['status' => PermohonanRuangan::DIKONFIRMASI]);
        (new LayananRuanganLokal)->catatPemakaian('A101', '2026-06-20', 'pagi', 'pihak-lain');

        $job = (new CatatPemakaianRuangan($p))->withFakeQueueInteractions();
        $job->handle(app(LayananRuangan::class));
        $job->assertFailed();

        Queue::assertPushed(SendQueuedNotifications::class, 1);
        expect($admin->exists)->toBeTrue()->and($p->ruangan()->first()->id_pemakaian_aset)->toBeNull();
    });
});

describe('surat pengantar rektorat', function () {
    it('membuat naskah terpisah dengan daftar fasilitas yang aman, hanya sekali dan hanya untuk jenisnya', function () {
        [$p] = permohonanPenerbitan('kegiatan-ruangan-rektorat');
        [$biasa] = permohonanPenerbitan();

        $n = app(BuatPengantarRektorat::class)->jalankan($p, adminIzin());

        expect($n->permohonan_id)->toBe($p->id)->and($n->jenis->kode)->toBe('surat-dinas')->and($n->perihal)->toContain('Seminar Nasional')
            ->and($n->tujuan->pluck('nama')->all())->toBe(['Rektor Universitas Siliwangi'])
            ->and($n->isi)->toContain('<li>Aula &lt;b&gt;Rektorat&lt;/b&gt; (1) — Pagi hari</li>')->not->toContain('<b>Rektorat')
            ->and($p->fresh()->naskah_izin_id)->toBeNull();

        expect(fn () => app(BuatPengantarRektorat::class)->jalankan($p, adminIzin()))->toThrow(ValidationException::class)
            ->and(fn () => app(BuatPengantarRektorat::class)->jalankan($biasa, adminIzin()))->toThrow(ValidationException::class)
            ->and(fn () => app(BuatPengantarRektorat::class)->jalankan($p, User::factory()->create()->assignRole('kasubag')))->toThrow(AuthorizationException::class);
    });
});

describe('akses ormawa dan panel', function () {
    it('pengurus aktif ormawa mengunduh surat izin terbit, bukan draf dan bukan ormawa lain', function () {
        [$p, , $u] = permohonanPenerbitan();
        [, , $uLain] = permohonanPenerbitan();
        $n = app(TerbitkanIzin::class)->jalankan($p, adminIzin());

        expect($u->can('view', $n))->toBeFalse();
        $n = terbitkanNaskahIzin($n);

        expect($u->can('view', $n))->toBeTrue()->and($uLain->can('view', $n))->toBeFalse();
        $this->actingAs($u)->get(route('naskah.pdf', $n))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($uLain)->get(route('naskah.pdf', $n))->assertForbidden();
    });

    it('menampilkan tautan unduh surat izin di progres setelah terbit', function () {
        [$p, $o, $u] = permohonanPenerbitan();
        $n = app(TerbitkanIzin::class)->jalankan($p, adminIzin());

        Livewire::actingAs($u)->test(Progres::class, ['ormawa' => $o->id])->call('pilih', $p->id)->assertDontSee('Unduh surat izin');

        $n = terbitkanNaskahIzin($n);
        Livewire::actingAs($u)->test(Progres::class, ['ormawa' => $o->id])->call('pilih', $p->id)->assertSee('Unduh surat izin')->assertSee($n->nomor);
    });

    it('membuat draf surat izin dari halaman detail panel', function () {
        [$p] = permohonanPenerbitan();
        $admin = adminIzin();

        Livewire::actingAs($admin)->test(ViewPermohonan::class, ['record' => $p->getKey()])
            ->assertActionVisible('terbitkanIzin')->assertActionHidden('pengantarRektorat')
            ->callAction('terbitkanIzin')->assertNotified();

        expect(Naskah::where('permohonan_id', $p->id)->count())->toBe(1);
        Livewire::actingAs($admin)->test(ViewPermohonan::class, ['record' => $p->getKey()])->assertActionHidden('terbitkanIzin');
    });
});
