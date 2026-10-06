<?php

namespace App\Services\Berkas;

use App\Contracts\PenyimpananBerkas;
use App\Models\TautanBerkas;
use App\Models\User;
use App\Support\UrlBerkas;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class TautanEksternal implements PenyimpananBerkas
{
    public function simpan(Model $pemilik, string $jenis, string $url, ?string $label, ?User $oleh = null): TautanBerkas
    {
        return TautanBerkas::create([
            'pemilik_type' => $pemilik->getMorphClass(),
            'pemilik_id' => $pemilik->getKey(),
            'jenis' => $jenis,
            'label' => $label,
            'url' => $url,
            'ditambahkan_oleh' => $oleh?->getKey() ?? auth()->id(),
        ]);
    }

    public function daftar(Model $pemilik, ?string $jenis = null): Collection
    {
        return TautanBerkas::query()
            ->where('pemilik_type', $pemilik->getMorphClass())
            ->where('pemilik_id', $pemilik->getKey())
            ->when($jenis, fn ($q) => $q->where('jenis', $jenis))
            ->orderBy('created_at')
            ->get();
    }

    public function urlAkses(TautanBerkas $tautan): ?string
    {
        if ($tautan->tertutup()) {
            return null;
        }

        // Daftar putih diperiksa ulang saat akses: konfigurasi bisa berubah setelah tautan disimpan.
        $urai = UrlBerkas::urai($tautan->url);

        return $urai && UrlBerkas::hostDiizinkan($urai['host']) && ! UrlBerkas::hostPemendek($urai['host'])
            ? $tautan->url
            : null;
    }

    public function hapus(TautanBerkas $tautan): void
    {
        $tautan->delete();
    }
}
