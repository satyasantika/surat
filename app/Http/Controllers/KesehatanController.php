<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Throwable;

class KesehatanController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $status = [
            'app' => config('app.name'),
            'versi' => config('app.version'),
            'db' => $this->periksa(fn () => DB::select('select 1')),
            'redis' => $this->periksa(fn () => Redis::connection()->ping()),
            'gotenberg' => config('pdf.driver') === 'gotenberg'
                ? $this->periksa(fn () => Http::timeout(3)->get(rtrim((string) config('pdf.gotenberg_url'), '/').'/health')->throw())
                : 'nonaktif',
        ];

        $sehat = ! in_array('gagal', $status, true);

        return response()->json($status, $sehat ? 200 : 503);
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
