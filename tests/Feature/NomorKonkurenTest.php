<?php

use App\Actions\Nomor\AmbilNomorBerikutnya;
use App\Models\NomorTerpakai;
use App\Models\RegisterNomor;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Tanpa transaksi pembungkus: proses anak harus melihat data yang sudah di-commit.
uses(DatabaseTruncation::class);

afterEach(function () {
    // DatabaseTruncation tidak membatalkan data; bersihkan agar tidak mencemari tes lain.
    Schema::disableForeignKeyConstraints();
    foreach (['nomor_terpakai', 'register_nomor', 'activity_log', 'users', 'cache_locks'] as $tabel) {
        DB::table($tabel)->truncate();
    }
    Schema::enableForeignKeyConstraints();
});

it('tidak menghasilkan nomor ganda atau loncat pada 20 proses paralel', function () {
    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('pcntl tidak tersedia.');
    }

    $register = RegisterNomor::create(['kode' => 'paralel', 'nama' => 'Paralel', 'pola' => '{urut}/{tahun}']);
    $pemilik = User::factory()->create();
    $pids = [];
    // Setiap anak menulis satu baris hasil: "ok" atau kelas galatnya beserta jumlah percobaan insert.
    $catatan = tempnam(sys_get_temp_dir(), 'nomor-anak-');
    // Kunci Cache::lock harus berlaku antar-proses seperti produksi (CACHE_STORE=database); store `array` bawaan
    // phpunit.xml hanya mengunci per proses sehingga lapisan kunci tidak ikut teruji.
    config(['cache.default' => 'database']);

    for ($i = 0; $i < 20; $i++) {
        $pid = pcntl_fork();

        if ($pid === 0) {
            // Anak memakai koneksi baru agar tidak menutup koneksi milik induk.
            config(['database.connections.anak' => config('database.connections.'.config('database.default'))]);
            DB::setDefaultConnection('anak');
            $percobaan = 0;
            DB::listen(function ($q) use (&$percobaan): void {
                if (str_starts_with($q->sql, 'insert into `nomor_terpakai`')) {
                    $percobaan++;
                }
            });

            try {
                app(AmbilNomorBerikutnya::class)->jalankan($register, $pemilik);
                $hasil = 'ok';
            } catch (Throwable $e) {
                $hasil = get_class($e)." percobaan_insert={$percobaan}: ".mb_substr($e->getMessage(), 0, 200);
            } finally {
                file_put_contents($catatan, ($hasil ?? 'tanpa hasil').PHP_EOL, FILE_APPEND | LOCK_EX);
                posix_kill(posix_getpid(), SIGKILL);
            }
        }

        $pids[] = $pid;
    }

    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
    }

    $urut = NomorTerpakai::where('register_nomor_id', $register->id)->orderBy('urut')->pluck('urut')->all();
    $hasil = file($catatan, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    unlink($catatan);
    $gagal = array_values(array_filter($hasil, fn (string $h): bool => $h !== 'ok'));
    $rincian = 'Hasil anak: '.implode(' | ', $gagal);

    // Jaminan BR-03/BR-07: nomor yang terbit unik dan berurutan tanpa loncat, apa pun jumlah anak yang kebagian.
    expect($urut)->not->toBeEmpty()
        ->and($urut)->toBe(range(1, count($urut)), $rincian)
        ->and($hasil)->toHaveCount(20, $rincian)
        ->and(count($hasil) - count($gagal))->toBe(count($urut), $rincian);

    // Di bawah perebutan berat, anak yang kalah hanya boleh gagal karena menunggu kunci terlalu lama (Cache::lock
    // ->block(5) mencoba tiap 250 ms) tanpa pernah menyentuh tabel; galat lain, terutama bentrok, menggagalkan uji.
    foreach ($gagal as $g) {
        expect($g)->toStartWith(LockTimeoutException::class.' percobaan_insert=0:', $rincian);
    }
});
