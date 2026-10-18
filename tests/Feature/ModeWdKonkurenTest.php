<?php

use App\Actions\Permohonan\AjukanPermohonan;
use App\Actions\Permohonan\DisposisiPermohonan;
use App\Actions\Permohonan\PutusanWd;
use App\Actions\Permohonan\ValidasiPermohonan;
use App\Enums\StatusPermohonan;
use App\Models\Jabatan;
use App\Models\JenisPermohonan;
use App\Models\Ormawa;
use App\Models\PemangkuJabatan;
use App\Models\PengurusOrmawa;
use App\Models\Permohonan;
use App\Models\PersetujuanWd;
use App\Models\RiwayatPermohonan;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\JenisNaskahSeeder;
use Database\Seeders\JenisPermohonanSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
use Database\Seeders\StrukturFkipSeeder;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Tanpa transaksi pembungkus: proses anak harus melihat data yang sudah di-commit.
uses(DatabaseTruncation::class);

afterEach(function () {
    Schema::disableForeignKeyConstraints();
    foreach (DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'") as $baris) {
        $tabel = array_values((array) $baris)[0];

        if ($tabel !== 'migrations') {
            DB::table($tabel)->truncate();
        }
    }
    Schema::enableForeignKeyConstraints();
    CarbonImmutable::setTestNow();
});

/** @return array{0: string, 1: array<int, string>} id permohonan di tahap WD (mode salah_satu) dan id kedua WD */
function kkSiapkan(): array
{
    test()->seed([PeranDanIzinSeeder::class, StrukturFkipSeeder::class, PengaturanSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class, JenisPermohonanSeeder::class]);
    CarbonImmutable::setTestNow('2026-06-01 09:00:00');

    $o = Ormawa::create(['nama' => 'HIMA Konkuren', 'tingkat' => 'prodi']);
    $o->sk()->create(['nomor_sk' => 'SK/1', 'tanggal_sk' => '2026-01-01', 'periode_mulai' => '2026-01-01', 'periode_selesai' => '2026-12-31']);
    $u = User::factory()->create(['nip_nim' => '7000001'])->assignRole('pengurus-ormawa');
    PengurusOrmawa::create(['ormawa_id' => $o->id, 'user_id' => $u->id, 'nama' => $u->name, 'jabatan' => 'ketua']);

    $p = app(AjukanPermohonan::class)->jalankan($u, $o, JenisPermohonan::firstWhere('kode', 'kegiatan'), [
        'nama_kegiatan' => 'Acara', 'perihal' => 'Izin', 'tanggal_mulai' => '2026-06-20', 'tanggal_selesai' => '2026-06-20', 'deskripsi' => 'Uji',
        'penanggung_jawab' => ['ketua' => ['nama' => 'Budi']],
        'berkas' => ['surat_permohonan' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view', 'proposal' => 'https://drive.google.com/file/d/1ZyXwVuTsRqPoNmLkJi/view'],
    ]);
    $p = app(ValidasiPermohonan::class)->jalankan($p, User::factory()->create()->assignRole('admin-persuratan'));

    $pejabat = function (string $peran, string $kode): User {
        $x = User::factory()->create()->assignRole($peran);
        PemangkuJabatan::create(['jabatan_id' => Jabatan::firstWhere('kode', $kode)->id, 'user_id' => $x->id, 'mulai' => '2026-01-01']);

        return $x;
    };

    $dekan = $pejabat('dekan', 'dekan');
    $wd1 = $pejabat('wakil-dekan', 'wd-akademik');
    $wd3 = $pejabat('wakil-dekan', 'wd-kemahasiswaan');
    app(DisposisiPermohonan::class)->jalankan($p, $dekan, [Jabatan::firstWhere('kode', 'wd-akademik')->id, Jabatan::firstWhere('kode', 'wd-kemahasiswaan')->id], null, Permohonan::MODE_WD_SALAH_SATU);

    return [$p->getKey(), [$wd1->getKey(), $wd3->getKey()]];
}

/** @param  list<array{0: string, 1: string}>  $putusan pasangan [id WD, putusan] yang dijalankan serentak */
function kkJalankanSerentak(string $permohonanId, array $putusan): void
{
    $pids = [];

    foreach ($putusan as [$wdId, $isi]) {
        $pid = pcntl_fork();

        if ($pid === 0) {
            config(['database.connections.anak' => config('database.connections.'.config('database.default'))]);
            DB::setDefaultConnection('anak');

            try {
                app(PutusanWd::class)->jalankan(Permohonan::findOrFail($permohonanId), User::findOrFail($wdId), $isi, $isi === 'tolak' ? 'Tidak' : null);
            } catch (Throwable) {
            } finally {
                posix_kill(posix_getpid(), SIGKILL);
            }
        }

        $pids[] = $pid;
    }

    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
    }
}

it('mode salah_satu: dua WD menyetujui serentak menutup tahap tepat sekali', function () {
    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('pcntl tidak tersedia.');
    }

    [$id, [$wd1, $wd3]] = kkSiapkan();

    kkJalankanSerentak($id, [[$wd1, 'setuju'], [$wd3, 'setuju']]);

    $p = Permohonan::findOrFail($id);
    $putusan = $p->persetujuanWd->pluck('putusan')->sort()->values()->all();

    expect($p->status)->toBe(StatusPermohonan::RekomendasiKasubag)
        ->and($putusan)->toBe([PersetujuanWd::DIABAIKAN, PersetujuanWd::SETUJU])
        ->and(RiwayatPermohonan::where('permohonan_id', $id)->where('ke_status', 'rekomendasi_kasubag')->count())->toBe(1);
});

it('mode salah_satu: setuju dan tolak serentak selalu berakhir pada rekomendasi kasubag', function () {
    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('pcntl tidak tersedia.');
    }

    [$id, [$wd1, $wd3]] = kkSiapkan();

    kkJalankanSerentak($id, [[$wd1, 'setuju'], [$wd3, 'tolak']]);

    $p = Permohonan::findOrFail($id);

    expect($p->status)->toBe(StatusPermohonan::RekomendasiKasubag)
        ->and($p->persetujuanWd->where('putusan', PersetujuanWd::SETUJU))->toHaveCount(1)
        ->and($p->persetujuanWd->where('putusan', PersetujuanWd::MENUNGGU))->toHaveCount(0)
        ->and(RiwayatPermohonan::where('permohonan_id', $id)->where('ke_status', 'ditolak')->count())->toBe(0);
});
