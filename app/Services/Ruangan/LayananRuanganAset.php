<?php

namespace App\Services\Ruangan;

use App\Contracts\LayananRuangan;
use App\Exceptions\LayananRuanganTidakTersedia;
use App\Exceptions\RuanganBentrok;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Klien API Aset v1 (BR-12). Galat jaringan/5xx menjadi LayananRuanganTidakTersedia; tidak pernah
 * mengembalikan data palsu. Jadwal per tanggal di-cache 60 detik (kunci surat:ruangan:{kode}:{tanggal}).
 */
class LayananRuanganAset implements LayananRuangan
{
    public function __construct(private readonly string $url, private readonly string $token, private readonly int $timeout = 5, private readonly int $cacheDetik = 60) {}

    public function daftar(): array
    {
        $data = $this->kirim(fn (PendingRequest $h) => $h->get('/api/v1/ruangan'))->json('data');

        return collect(is_array($data) ? $data : [])->map(fn (array $r) => [
            'kode' => (string) $r['kode'], 'nama' => (string) $r['nama'], 'gedung' => $r['gedung'] ?? null,
            'kapasitas' => isset($r['kapasitas']) ? (int) $r['kapasitas'] : null, 'fasilitas' => $r['fasilitas'] ?? null,
        ])->values()->all();
    }

    public function jadwal(string $kode, CarbonInterface $dari, CarbonInterface $sampai): array
    {
        $hasil = [];

        for ($hari = $dari->copy()->startOfDay(); $hari->lte($sampai); $hari = $hari->addDay()) {
            $tanggal = $hari->toDateString();
            $hasil = [...$hasil, ...Cache::remember(
                "surat:ruangan:{$kode}:{$tanggal}",
                $this->cacheDetik,
                fn () => $this->jadwalHari($kode, $tanggal),
            )];
        }

        return $hasil;
    }

    public function catatPemakaian(string $kode, string $tanggal, string $sesi, ?string $referensi = null, ?string $keterangan = null): string
    {
        try {
            $respons = $this->klien()->post("/api/v1/ruangan/{$kode}/pemakaian", [
                'tanggal' => $tanggal, 'sesi' => $sesi, 'referensi' => $referensi, 'keterangan' => $keterangan,
            ]);
        } catch (ConnectionException $e) {
            throw new LayananRuanganTidakTersedia('API Aset tidak dapat dijangkau.', 0, $e);
        }

        if ($respons->status() === 409) {
            throw new RuanganBentrok("Ruangan {$kode} sudah dipakai pada {$tanggal} sesi {$sesi}.");
        }

        if ($respons->failed()) {
            throw new LayananRuanganTidakTersedia("API Aset menolak permintaan (HTTP {$respons->status()}).");
        }

        Cache::forget("surat:ruangan:{$kode}:{$tanggal}");

        return (string) ($respons->json('data.id') ?? $respons->json('id') ?? '');
    }

    /** @return list<array{tanggal: string, sesi: string, keterangan: ?string}> */
    private function jadwalHari(string $kode, string $tanggal): array
    {
        $respons = $this->kirim(fn (PendingRequest $h) => $h->get("/api/v1/ruangan/{$kode}/jadwal", ['dari' => $tanggal, 'sampai' => $tanggal]));
        $data = $respons->json('data');

        return collect(is_array($data) ? $data : [])->map(fn (array $j) => [
            'tanggal' => (string) $j['tanggal'], 'sesi' => (string) $j['sesi'], 'keterangan' => $j['keterangan'] ?? null,
        ])->values()->all();
    }

    private function klien(): PendingRequest
    {
        if ($this->url === '' || $this->token === '') {
            throw new LayananRuanganTidakTersedia('Integrasi Aset belum dikonfigurasi (ASET_API_URL/ASET_API_TOKEN).');
        }

        return Http::baseUrl(rtrim($this->url, '/'))->withToken($this->token)->acceptJson()->timeout($this->timeout);
    }

    /**
     * @param  callable(PendingRequest): Response  $panggil
     */
    private function kirim(callable $panggil): Response
    {
        try {
            return $panggil($this->klien())->throw();
        } catch (ConnectionException|RequestException $e) {
            throw new LayananRuanganTidakTersedia('Layanan ruangan (Aset) tidak tersedia: '.$e->getMessage(), 0, $e);
        }
    }
}
