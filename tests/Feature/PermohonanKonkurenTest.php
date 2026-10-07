<?php

use App\Actions\Permohonan\AjukanPermohonan;
use App\Models\JenisPermohonan;
use App\Models\Ormawa;
use App\Models\PengurusOrmawa;
use App\Models\Permohonan;
use App\Models\PermohonanRuangan;
use App\Models\RuanganLokal;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\JenisNaskahSeeder;
use Database\Seeders\JenisPermohonanSeeder;
use Database\Seeders\PengaturanSeeder;
use Database\Seeders\PeranDanIzinSeeder;
use Database\Seeders\RegisterNomorSeeder;
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
});

it('menyisakan satu pengajuan yang berhasil untuk sesi ruangan yang sama (pengajuan paralel)', function () {
    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('pcntl tidak tersedia.');
    }

    $this->seed([PeranDanIzinSeeder::class, PengaturanSeeder::class, RegisterNomorSeeder::class, JenisNaskahSeeder::class, JenisPermohonanSeeder::class]);
    CarbonImmutable::setTestNow('2026-06-01 09:00:00');
    RuanganLokal::create(['kode' => 'A101', 'nama' => 'Ruang A101']);
    $jenis = JenisPermohonan::firstWhere('kode', 'kegiatan-ruangan');

    $peserta = [];
    foreach (range(1, 8) as $i) {
        $o = Ormawa::create(['nama' => "Ormawa {$i}", 'tingkat' => 'ukm']);
        $o->sk()->create(['nomor_sk' => "SK/{$i}", 'tanggal_sk' => '2026-01-01', 'periode_mulai' => '2026-01-01', 'periode_selesai' => '2026-12-31']);
        $u = User::factory()->create(['nip_nim' => "50000{$i}"])->assignRole('pengurus-ormawa');
        PengurusOrmawa::create(['ormawa_id' => $o->id, 'user_id' => $u->id, 'nama' => $u->name, 'jabatan' => 'ketua']);
        $peserta[] = [$o->id, $u->id];
    }

    $data = [
        'nama_kegiatan' => 'Acara', 'perihal' => 'Izin', 'tanggal_mulai' => '2026-06-20', 'tanggal_selesai' => '2026-06-20', 'deskripsi' => 'Uji',
        'penanggung_jawab' => ['ketua' => ['nama' => 'Budi']],
        'berkas' => ['surat_permohonan' => 'https://drive.google.com/file/d/1AbCdEfGhIjKlMnOpQr/view', 'proposal' => 'https://drive.google.com/file/d/1ZyXwVuTsRqPoNmLkJi/view'],
        'ruangan' => [['kode' => 'A101', 'tanggal' => '2026-06-20', 'sesi' => 'pagi']],
    ];

    $pids = [];
    foreach ($peserta as [$ormawaId, $userId]) {
        $pid = pcntl_fork();

        if ($pid === 0) {
            // Anak memakai koneksi baru agar tidak menutup koneksi milik induk.
            config(['database.connections.anak' => config('database.connections.'.config('database.default'))]);
            DB::setDefaultConnection('anak');

            try {
                app(AjukanPermohonan::class)->jalankan(User::findOrFail($userId), Ormawa::findOrFail($ormawaId), JenisPermohonan::firstWhere('kode', 'kegiatan-ruangan'), $data);
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

    expect(Permohonan::count())->toBe(1)
        ->and(PermohonanRuangan::count())->toBe(1)
        ->and(Permohonan::first()->nomor)->toBe('PMH-2026-0001')
        ->and($jenis->exists)->toBeTrue();
    CarbonImmutable::setTestNow();
});
