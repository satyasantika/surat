<?php

namespace App\Http\Controllers;

use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * GET /api/health (STANDAR-TEKNIS §1b, Profil Ringan). 503 hanya bila DB (atau Gotenberg, bila diaktifkan lewat ADR)
 * gagal; antrean dan detak penjadwal bersifat informatif agar healthcheck kontainer tidak memicu restart.
 */
class KesehatanController extends Controller
{
    private const DETAK_MAKS_MENIT = 5;

    public function __invoke(): JsonResponse
    {
        $status = [
            'app' => config('app.name'),
            'versi' => config('app.version'),
            'db' => $this->periksa(fn () => DB::select('select 1')),
            'job_tertunda' => $this->hitung(fn () => DB::table('jobs')
                ->whereNull('reserved_at')
                ->where('available_at', '<=', now()->subMinutes(10)->getTimestamp())
                ->count()),
            'job_gagal_24j' => $this->hitung(fn () => DB::table('failed_jobs')
                ->where('failed_at', '>=', now()->subDay())
                ->count()),
            'detak_penjadwal' => $this->detakPenjadwal(),
        ];

        if (config('pdf.driver') === 'gotenberg') {
            $status['gotenberg'] = $this->periksa(fn () => Http::timeout(3)->get(rtrim((string) config('pdf.gotenberg_url'), '/').'/health')->throw());
        }

        $sehat = ! in_array('gagal', [$status['db'], $status['gotenberg'] ?? 'ok'], true);

        return response()->json($status, $sehat ? 200 : 503);
    }

    private function detakPenjadwal(): string
    {
        $detak = Cache::get(config('app.kode').':detak-penjadwal');

        if (! $detak instanceof CarbonInterface) {
            return 'belum-ada';
        }

        return $detak->diffInMinutes(now(), true) > self::DETAK_MAKS_MENIT ? 'terlambat' : 'ok';
    }

    private function hitung(callable $hitung): int|string
    {
        try {
            return (int) $hitung();
        } catch (Throwable) {
            return 'gagal';
        }
    }

    private function periksa(callable $cek): string
    {
        try {
            $cek();

            return 'ok';
        } catch (Throwable) {
            return 'gagal';
        }
    }
}
