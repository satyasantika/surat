<?php

use App\Actions\Disposisi\BuatDisposisi;
use App\Actions\Lpj\IsiLpj;
use App\Actions\Masuk\RegistrasiSuratMasuk;
use App\Enums\StatusDisposisiPenerima;
use App\Jobs\PeriksaTautanBerkas;
use App\Models\Disposisi;
use App\Models\Jabatan;
use App\Models\JenisPermohonan;
use App\Models\Lpj;
use App\Models\Ormawa;
use App\Models\PemangkuJabatan;
use App\Models\PengingatTerkirim;
use App\Models\PengurusOrmawa;
use App\Models\Permohonan;
use App\Models\RiwayatPermohonan;
use App\Models\TautanBerkas;
use App\Models\User;
use App\Notifications\NotifikasiTahap;
use App\Services\Berkas\PemeriksaTautan;
use App\Support\HostAman;
use App\Support\Pengaturan;
use App\Support\Pengingat;
use Carbon\CarbonImmutable;
use Database\Seeders\JenisNaskahSeeder;
use Database\Seeders\JenisPermohonanSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Database\Seeders\RubrikLpjSeeder;
use Database\Seeders\StrukturFkipSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PeranDanIzinSeeder::class, StrukturFkipSeeder::class, PengaturanSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class, JenisPermohonanSeeder::class, RubrikLpjSeeder::class]);
    Notification::fake();
    CarbonImmutable::setTestNow('2026-06-10 10:00:00');
});

afterEach(function () {
    CarbonImmutable::setTestNow();
    HostAman::$penyelesai = null;
});

function orangJadwal(string $peran, ?string $jabatan = null): User
{
    if ($jabatan !== null && ($ada = PemangkuJabatan::where('jabatan_id', Jabatan::firstWhere('kode', $jabatan)->id)->first()) !== null) {
        return $ada->user;
    }

    $u = User::factory()->create()->assignRole($peran);

    if ($jabatan !== null) {
        PemangkuJabatan::create(['jabatan_id' => Jabatan::firstWhere('kode', $jabatan)->id, 'user_id' => $u->id, 'mulai' => '2026-01-01']);
    }

    return $u;
}

function judulKe(User $u, ?string $kategori = null): array
{
    return Notification::sent($u, NotifikasiTahap::class)->filter(fn ($n) => $kategori === null || $n->kategori === $kategori)->map(fn ($n) => $n->judul)->values()->all();
}

function ormawaJadwal(string $nama = 'HIMA Jadwal'): array
{
    $o = Ormawa::create(['nama' => $nama, 'tingkat' => 'prodi']);
    $o->sk()->create(['nomor_sk' => 'SK/1', 'tanggal_sk' => '2026-01-01', 'periode_mulai' => '2026-01-01', 'periode_selesai' => '2026-12-31']);
    $u = User::factory()->create(['nip_nim' => (string) random_int(1000000, 9999999)])->assignRole('pengurus-ormawa');
    PengurusOrmawa::create(['ormawa_id' => $o->id, 'user_id' => $u->id, 'nama' => $u->name, 'jabatan' => 'ketua']);

    return [$o, $u];
}

function permohonanJadwal(Ormawa $o, User $u, string $status, string $tanggalMulai = '2026-05-10', string $tanggalSelesai = '2026-05-11'): Permohonan
{
    static $n = 0;
    $p = (new Permohonan)->forceFill([
        'nomor' => 'PMH-2026-'.(7000 + ++$n), 'ormawa_id' => $o->id, 'jenis_permohonan_id' => JenisPermohonan::firstWhere('kode', 'kegiatan')->id, 'diajukan_oleh' => $u->id,
        'nama_kegiatan' => 'Kegiatan '.$n, 'perihal' => 'Izin', 'tanggal_mulai' => $tanggalMulai, 'tanggal_selesai' => $tanggalSelesai, 'deskripsi' => 'D',
        'penanggung_jawab' => ['ketua' => ['nama' => 'B']], 'status' => $status, 'diajukan_pada' => now()->subDays(30),
    ]);
    $p->saveQuietly();

    return $p;
}

function riwayatJadwal(Permohonan $p, string $dari, string $ke, int $hariLalu): void
{
    (new RiwayatPermohonan(['permohonan_id' => $p->id, 'dari_status' => $dari, 'ke_status' => $ke]))->forceFill(['created_at' => now()->subDays($hariLalu)])->save();
}

function lpjJadwal(Permohonan $p, string $batas, string $status = 'draf'): Lpj
{
    $l = new Lpj;
    $l->forceFill(['permohonan_id' => $p->id, 'batas_waktu' => $batas, 'status' => $status])->save();

    return $l;
}

describe('jadwal', function () {
    it('mendaftarkan semua perintah pada waktu yang benar, tanpa tumpang tindih, satu server', function (string $perintah, string $ekspresi) {
        $e = collect(app(Schedule::class)->events())->first(fn ($ev) => str_contains((string) $ev->command, $perintah));

        expect($e)->not->toBeNull()->and($e->expression)->toBe($ekspresi)->and($e->withoutOverlapping)->toBeTrue()->and($e->onOneServer)->toBeTrue();
    })->with([
        'pengingat disposisi tiap jam' => ['surat:pengingat-disposisi', '0 * * * *'],
        'pengingat permohonan 07:00' => ['surat:pengingat-permohonan', '0 7 * * *'],
        'pengingat lpj 07:00' => ['surat:pengingat-lpj', '0 7 * * *'],
        'blokir lpj 01:00' => ['surat:tandai-blokir-lpj', '0 1 * * *'],
        'periksa tautan Senin 06:00' => ['surat:periksa-tautan', '0 6 * * 1'],
        'bersihkan tmp tiap jam' => ['surat:bersihkan-tmp', '0 * * * *'],
    ]);

    it('muncul pada schedule:list', function () {
        $this->artisan('schedule:list')->expectsOutputToContain('surat:pengingat-disposisi')->expectsOutputToContain('surat:periksa-tautan')->assertSuccessful();
    });

    it('Pengingat::sekali menjalankan sekali per kunci', function () {
        $n = 0;

        expect(Pengingat::sekali('k1', function () use (&$n) {
            $n++;
        }))->toBeTrue()->and(Pengingat::sekali('k1', function () use (&$n) {
            $n++;
        }))->toBeFalse()
            ->and(Pengingat::sekali('k2', function () use (&$n) {
                $n++;
            }))->toBeTrue()->and($n)->toBe(2)->and(PengingatTerkirim::count())->toBe(2);
    });
});

describe('surat:pengingat-disposisi', function () {
    function disposisiJadwal(string $keamanan, string $batas, string $perihal = 'Perihal Disposisi')
    {
        $dekan = orangJadwal('dekan', 'dekan');
        $wd = orangJadwal('wakil-dekan', 'wd-akademik');
        $surat = app(RegistrasiSuratMasuk::class)->jalankan([
            'nomor_surat' => '1/X/2026', 'tanggal_surat' => '2026-06-01', 'asal' => 'Instansi', 'perihal' => $perihal, 'klasifikasi_keamanan' => $keamanan, 'derajat_kecepatan' => 'biasa',
            'pindaian_url' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view',
        ], orangJadwal('admin-persuratan'));
        $d = app(BuatDisposisi::class)->jalankan($surat, $dekan, [$wd->id], ['tindak_lanjuti'], null, now()->addDays(10));
        Disposisi::whereKey($d->id)->update(['batas_waktu' => $batas]);
        Notification::fake();

        return [$d->penerima->first(), $dekan, $wd, $surat];
    }

    it('menandai terlambat dan mengingatkan penerima serta pemberi sekali saja', function () {
        [$p, $dekan, $wd] = disposisiJadwal('biasa', '2026-06-10 08:00:00');

        $this->artisan('surat:pengingat-disposisi')->assertSuccessful();

        expect($p->fresh()->terlambat)->toBeTrue()->and(judulKe($wd, 'pengingat'))->toBe(['Disposisi terlambat'])->and(judulKe($dekan, 'pengingat'))->toBe(['Disposisi yang Anda berikan terlambat']);

        $this->artisan('surat:pengingat-disposisi');
        expect(judulKe($wd, 'pengingat'))->toBe(['Disposisi terlambat']);
    });

    it('mengingatkan yang mendekati batas dalam jendela jam pengaturan, lalu terlambat saat lewat', function () {
        [$p, , $wd] = disposisiJadwal('biasa', '2026-06-11 06:00:00');

        $this->artisan('surat:pengingat-disposisi');
        expect($p->fresh()->terlambat)->toBeFalse()->and(judulKe($wd, 'pengingat'))->toBe(['Pengingat: disposisi mendekati batas waktu']);

        $this->artisan('surat:pengingat-disposisi');
        expect(judulKe($wd, 'pengingat'))->toHaveCount(1);

        CarbonImmutable::setTestNow('2026-06-11 07:00:00');
        $this->artisan('surat:pengingat-disposisi');
        expect($p->fresh()->terlambat)->toBeTrue()->and(judulKe($wd, 'pengingat'))->toBe(['Pengingat: disposisi mendekati batas waktu', 'Disposisi terlambat']);
    });

    it('jendela mendekati mengikuti pengaturan dan disposisi jauh atau selesai dilewati', function () {
        [$p, , $wd] = disposisiJadwal('biasa', '2026-06-13 10:00:00');

        $this->artisan('surat:pengingat-disposisi');
        expect(judulKe($wd))->toBe([]);

        Pengaturan::set('jam_pengingat_disposisi', 96);
        $this->artisan('surat:pengingat-disposisi');
        expect(judulKe($wd, 'pengingat'))->toBe(['Pengingat: disposisi mendekati batas waktu']);

        [$p2, , $wd2] = disposisiJadwal('biasa', '2026-06-09 10:00:00');
        $p2->update(['status' => StatusDisposisiPenerima::Selesai]);
        $this->artisan('surat:pengingat-disposisi');
        expect(judulKe($wd2))->toBe([])->and($p2->fresh()->terlambat)->toBeFalse();
    });

    it('surat rahasia tidak membocorkan perihal pada pengingat', function () {
        [, , $wd] = disposisiJadwal('rahasia', '2026-06-09 10:00:00', 'PERIHAL-RAHASIA-QQQ');

        $this->artisan('surat:pengingat-disposisi');

        $n = Notification::sent($wd, NotifikasiTahap::class)->first();
        expect($n->ringkas)->not->toContain('PERIHAL-RAHASIA-QQQ')->and($n->toWhatsapp($wd))->not->toContain('PERIHAL-RAHASIA-QQQ');
    });
});

describe('surat:pengingat-permohonan', function () {
    it('mengingatkan pihak berikutnya bila tertahan lebih dari N hari, sekali per pekan per tahap', function () {
        [$o, $u] = ormawaJadwal();
        $admin = orangJadwal('admin-persuratan');
        $p = permohonanJadwal($o, $u, 'validasi_admin');
        riwayatJadwal($p, 'diajukan', 'validasi_admin', 4);

        $this->artisan('surat:pengingat-permohonan')->assertSuccessful();
        $n = Notification::sent($admin, NotifikasiTahap::class);
        expect($n)->toHaveCount(1)->and($n->first()->judul)->toBe('Pengingat: Permohonan menunggu validasi')->and($n->first()->ringkas)->toContain($p->nomor)->toContain('tertahan 4 hari');

        $this->artisan('surat:pengingat-permohonan');
        expect(judulKe($admin))->toHaveCount(1);

        CarbonImmutable::setTestNow('2026-06-18 10:00:00');
        $this->artisan('surat:pengingat-permohonan');
        expect(judulKe($admin))->toHaveCount(2);
    });

    it('tidak mengingatkan yang belum melewati batas, berstatus akhir, atau saat pengaturan lebih longgar', function () {
        [$o, $u] = ormawaJadwal();
        $admin = orangJadwal('admin-persuratan');
        $baru = permohonanJadwal($o, $u, 'validasi_admin');
        riwayatJadwal($baru, 'diajukan', 'validasi_admin', 2);
        $selesai = permohonanJadwal($o, $u, 'selesai');
        riwayatJadwal($selesai, 'penerbitan', 'selesai', 20);

        $this->artisan('surat:pengingat-permohonan');
        expect(judulKe($admin))->toBe([]);

        Pengaturan::set('hari_tertahan_permohonan', 1);
        $this->artisan('surat:pengingat-permohonan');
        expect(judulKe($admin))->toHaveCount(1);
    });

    it('permohonan dikembalikan mengingatkan ormawa; tanpa riwayat memakai tanggal pengajuan', function () {
        [$o, $u] = ormawaJadwal();
        $p = permohonanJadwal($o, $u, 'dikembalikan');

        $this->artisan('surat:pengingat-permohonan');

        expect(judulKe($u, 'pengingat'))->toBe(['Pengingat: Permohonan menunggu perbaikan Anda']);
    });
});

describe('surat:pengingat-lpj', function () {
    it('mengingatkan H-3, hari H, dan terlambat (mingguan); LPJ diajukan atau jauh dilewati', function () {
        [$o, $u] = ormawaJadwal();
        $h3 = lpjJadwal(permohonanJadwal($o, $u, 'selesai'), '2026-06-13');
        $h = lpjJadwal(permohonanJadwal($o, $u, 'selesai'), '2026-06-10');
        $lewat = lpjJadwal(permohonanJadwal($o, $u, 'selesai'), '2026-06-08');
        lpjJadwal(permohonanJadwal($o, $u, 'selesai'), '2026-06-12');
        lpjJadwal(permohonanJadwal($o, $u, 'selesai'), '2026-06-09', 'diajukan');
        lpjJadwal(permohonanJadwal($o, $u, 'selesai'), '2026-06-20');

        $this->artisan('surat:pengingat-lpj')->assertSuccessful();

        expect(collect(judulKe($u, 'pengingat'))->sort()->values()->all())->toBe(['LPJ terlambat', 'Pengingat: LPJ jatuh tempo 3 hari lagi', 'Pengingat: LPJ jatuh tempo hari ini']);

        $this->artisan('surat:pengingat-lpj');
        expect(judulKe($u, 'pengingat'))->toHaveCount(3);

        CarbonImmutable::setTestNow('2026-06-17 07:00:00');
        $this->artisan('surat:pengingat-lpj');
        // pekan berikutnya: 4 LPJ terlambat (termasuk H-3 dan H yang lalu), plus 1 H-3 baru (batas 20 Juni)
        expect(collect(judulKe($u, 'pengingat'))->filter(fn ($j) => $j === 'LPJ terlambat'))->toHaveCount(5)->and(judulKe($u, 'pengingat'))->toHaveCount(8);
    });
});

describe('surat:tandai-blokir-lpj', function () {
    it('memblokir ormawa ber-LPJ terlambat dan memberi tahu ormawa serta admin sekali', function () {
        [$o, $u] = ormawaJadwal();
        [$bersih] = ormawaJadwal('HIMA Bersih');
        $admin = orangJadwal('admin-persuratan');
        lpjJadwal(permohonanJadwal($o, $u, 'selesai'), '2026-06-01');

        $this->artisan('surat:tandai-blokir-lpj')->expectsOutputToContain('1 ormawa diblokir')->assertSuccessful();

        expect($o->fresh()->diblokir_lpj)->toBeTrue()->and($o->fresh()->diblokir_sejak)->not->toBeNull()->and($bersih->fresh()->diblokir_lpj)->toBeFalse()
            ->and(judulKe($u, 'lpj'))->toBe(['Pengajuan permohonan diblokir: LPJ terlambat'])->and(judulKe($admin, 'lpj'))->toBe(['Pengajuan permohonan diblokir: LPJ terlambat']);

        $this->artisan('surat:tandai-blokir-lpj')->expectsOutputToContain('0 ormawa diblokir');
        expect(judulKe($u, 'lpj'))->toHaveCount(1);
    });

    it('toleransi dihormati dan blokir dibuka saat LPJ lengkap atau kebijakan dimatikan', function () {
        [$o, $u] = ormawaJadwal();
        $l = lpjJadwal(permohonanJadwal($o, $u, 'selesai'), '2026-06-08');
        Pengaturan::set('toleransi_lpj_hari', 5);

        $this->artisan('surat:tandai-blokir-lpj');
        expect($o->fresh()->diblokir_lpj)->toBeFalse();

        Pengaturan::set('toleransi_lpj_hari', 0);
        $this->artisan('surat:tandai-blokir-lpj');
        expect($o->fresh()->diblokir_lpj)->toBeTrue();

        $l->forceFill(['status' => 'diajukan'])->save();
        $this->artisan('surat:tandai-blokir-lpj')->expectsOutputToContain('1 dibuka');
        expect($o->fresh()->diblokir_lpj)->toBeFalse()->and($o->fresh()->diblokir_sejak)->toBeNull()->and(judulKe($u, 'lpj'))->toBe(['Pengajuan permohonan diblokir: LPJ terlambat', 'Blokir pengajuan permohonan dicabut']);

        $l->forceFill(['status' => 'draf'])->save();
        $this->artisan('surat:tandai-blokir-lpj');
        expect($o->fresh()->diblokir_lpj)->toBeTrue();

        Pengaturan::set('blokir_lpj_terlambat', false);
        $this->artisan('surat:tandai-blokir-lpj');
        expect($o->fresh()->diblokir_lpj)->toBeFalse();
    });

    it('pengajuan LPJ langsung mencabut penanda blokir tanpa menunggu penjadwal', function () {
        [$o, $u] = ormawaJadwal();
        $l = lpjJadwal(permohonanJadwal($o, $u, 'selesai'), '2026-06-01');
        $this->artisan('surat:tandai-blokir-lpj');
        expect($o->fresh()->diblokir_lpj)->toBeTrue();

        Queue::fake();
        app(IsiLpj::class)->jalankan($l, $u, ['tanggal_pelaksanaan' => '2026-05-10', 'jumlah_peserta' => 5, 'ringkasan' => 'Ok', 'berkas_lpj' => 'https://drive.google.com/file/d/1LpjLpjLpjLpjLpjLpj/view']);

        expect($o->fresh()->diblokir_lpj)->toBeFalse();
    });
});

describe('surat:periksa-tautan', function () {
    function tautanJadwal(?string $dicek, string $url = 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view'): TautanBerkas
    {
        Queue::fake();
        $o = Ormawa::create(['nama' => 'HIMA T'.str()->random(4), 'tingkat' => 'prodi']);
        $t = $o->tautan()->create(['jenis' => 'logo', 'url' => $url, 'label' => 'Logo']);
        $t->forceFill(['dicek_pada' => $dicek])->saveQuietly();

        return $t;
    }

    it('menjadwalkan hanya tautan yang belum atau lama tidak diperiksa, belum pernah lebih dulu', function () {
        $belum = tautanJadwal(null);
        $lama = tautanJadwal('2026-05-20 10:00:00');
        tautanJadwal('2026-06-09 10:00:00');
        Queue::fake();

        $this->artisan('surat:periksa-tautan')->expectsOutputToContain('2 tautan dijadwalkan')->assertSuccessful();

        Queue::assertPushed(PeriksaTautanBerkas::class, 2);
        Queue::assertPushed(PeriksaTautanBerkas::class, fn ($j) => $j->tautan->is($belum));
        Queue::assertPushed(PeriksaTautanBerkas::class, fn ($j) => $j->tautan->is($lama));

        Queue::fake();
        $this->artisan('surat:periksa-tautan', ['--batas' => 1]);
        Queue::assertPushed(PeriksaTautanBerkas::class, 1);
    });

    it('memberi tahu admin sekali saat tautan berubah menjadi tidak dapat diakses', function () {
        $admin = orangJadwal('admin-persuratan');
        $t = tautanJadwal(null);
        HostAman::$penyelesai = fn () => ['142.250.4.100'];
        Http::fake(['*' => Http::sequence()->push('', 404)->push('', 404)->push('', 200)->push('', 404)]);

        expect(app(PemeriksaTautan::class)->periksa($t))->toBe('tidak_dapat_diakses');
        $n = Notification::sent($admin, NotifikasiTahap::class);
        expect($n)->toHaveCount(1)->and($n->first()->judul)->toBe('Tautan berkas tidak dapat diakses')->and($n->first()->ringkas)->toContain('drive.google.com')->not->toContain('1AbCdEfGhIjKlMnOpQr');

        app(PemeriksaTautan::class)->periksa($t->fresh());
        expect(judulKe($admin))->toHaveCount(1);

        expect(app(PemeriksaTautan::class)->periksa($t->fresh()))->toBe('dapat_diakses')->and(judulKe($admin))->toHaveCount(1);
        app(PemeriksaTautan::class)->periksa($t->fresh());
        expect(judulKe($admin))->toHaveCount(2);
    });
});
