<?php

use App\Actions\Nomor\AmbilNomorBerikutnya;
use App\Models\NomorTerpakai;
use App\Models\RegisterNomor;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Tanpa transaksi pembungkus: proses anak harus melihat data yang sudah di-commit.
uses(DatabaseTruncation::class);

afterEach(function () {
    // DatabaseTruncation tidak membatalkan data; bersihkan agar tidak mencemari tes lain.
    Schema::disableForeignKeyConstraints();
    foreach (['nomor_terpakai', 'register_nomor', 'activity_log', 'users'] as $tabel) {
        DB::table($tabel)->truncate();
    }
    Schema::enableForeignKeyConstraints();
});

it('tidak menghasilkan nomor ganda pada 20 proses paralel', function () {
    if (! function_exists('pcntl_fork')) {
        $this->markTestSkipped('pcntl tidak tersedia.');
    }

    $register = RegisterNomor::create(['kode' => 'paralel', 'nama' => 'Paralel', 'pola' => '{urut}/{tahun}']);
    $pemilik = User::factory()->create();
    $pids = [];

    for ($i = 0; $i < 20; $i++) {
        $pid = pcntl_fork();

        if ($pid === 0) {
            // Anak memakai koneksi baru agar tidak menutup koneksi milik induk.
            config(['database.connections.anak' => config('database.connections.'.config('database.default'))]);
            DB::setDefaultConnection('anak');

            try {
                app(AmbilNomorBerikutnya::class)->jalankan($register, $pemilik);
            } finally {
                posix_kill(posix_getpid(), SIGKILL);
            }
        }

        $pids[] = $pid;
    }

    foreach ($pids as $pid) {
        pcntl_waitpid($pid, $status);
    }

    $urut = NomorTerpakai::where('register_nomor_id', $register->id)->orderBy('urut')->pluck('urut')->all();

    expect($urut)->toBe(range(1, 20));
});
