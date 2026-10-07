<?php

namespace App\Services\Notifikasi;

use App\Models\User;
use App\Notifications\NotifikasiTahap;
use App\Support\Notifikasi\Kategori;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Throwable;

/** Satu pintu pengiriman notifikasi tahap: pengguna aktif unik, tanpa pelaku sendiri; kegagalan tidak menggagalkan alur bisnis. */
class PengirimNotifikasi
{
    /**
     * @param  iterable<User|null>  $penerima
     * @return int jumlah penerima
     */
    public function kirim(iterable $penerima, string $kategori, string $judul, string $ringkas, ?string $url = null, ?User $kecuali = null): int
    {
        if (! Kategori::sah($kategori)) {
            throw new InvalidArgumentException("Kategori notifikasi tidak dikenal: {$kategori}");
        }

        $daftar = [];

        foreach ($penerima as $u) {
            if ($u instanceof User && $u->aktif && ! ($kecuali && $u->is($kecuali))) {
                $daftar[$u->getKey()] = $u;
            }
        }

        if ($daftar === []) {
            return 0;
        }

        try {
            Notification::send(array_values($daftar), new NotifikasiTahap($kategori, $judul, $ringkas, $url));
        } catch (Throwable $e) {
            Log::error('Notifikasi gagal dikirim: '.$e->getMessage());

            return 0;
        }

        return count($daftar);
    }
}
