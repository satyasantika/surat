<?php

namespace App\Actions\Nomor;

use App\Models\NomorTerpakai;
use App\Models\RegisterNomor;
use App\Support\PolaNomor;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Satu-satunya jalan mengeluarkan nomor agenda/naskah/permohonan (BR-03, BR-07).
 * Lapisan pengaman: Cache::lock per register+tahun → transaksi → MAX(urut) FOR UPDATE →
 * UNIQUE(register, tahun, urut) sebagai jaring terakhir (dicoba ulang bila bentrok).
 */
class AmbilNomorBerikutnya
{
    private const PERCOBAAN = 15;

    /** Galat basis data yang aman diulang: bentrok UNIQUE, deadlock, atau tunggu kunci. */
    private const GALAT_ULANG = ['Duplicate entry', 'Deadlock', 'Lock wait timeout'];

    /**
     * @param  array<string, string>  $konteks  klasifikasi, kode_jenis, kode_unit (opsional)
     */
    public function jalankan(RegisterNomor $register, Model $pemilik, array $konteks = [], ?CarbonInterface $tanggal = null): NomorTerpakai
    {
        $tanggal ??= now();

        if (! $register->aktif) {
            throw new RuntimeException("Register {$register->kode} tidak aktif.");
        }

        $tahun = $register->reset === 'tahunan' ? $tanggal->year : 0;

        for ($percobaan = 1; ; $percobaan++) {
            try {
                return Cache::lock("nomor:{$register->kode}:{$tahun}", 10)->block(5, fn () => DB::transaction(function () use ($register, $pemilik, $konteks, $tanggal, $tahun) {
                    $urut = 1 + (int) NomorTerpakai::query()
                        ->where('register_nomor_id', $register->id)
                        ->where('tahun', $tahun)
                        ->lockForUpdate()
                        ->max('urut');

                    return NomorTerpakai::create([
                        'register_nomor_id' => $register->id,
                        'tahun' => $tahun,
                        'urut' => $urut,
                        'nomor_lengkap' => PolaNomor::render($register->pola, $urut, $tanggal, $konteks),
                        'pemilik_type' => $pemilik->getMorphClass(),
                        'pemilik_id' => $pemilik->getKey(),
                        'dibuat_oleh' => auth()->id(),
                    ]);
                }));
            } catch (QueryException $e) {
                // Dua proses membaca MAX yang sama saat deret masih kosong → bentrok UNIQUE atau deadlock gap-lock.
                if ($percobaan >= self::PERCOBAAN || ! Str::contains($e->getMessage(), self::GALAT_ULANG)) {
                    throw $e;
                }

                usleep(random_int(5_000, 50_000));
            }
        }
    }
}
