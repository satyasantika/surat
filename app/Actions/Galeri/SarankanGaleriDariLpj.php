<?php

namespace App\Actions\Galeri;

use App\Models\Galeri;
use App\Models\Lpj;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Menyalin tautan instagram/video dari LPJ yang sudah dinilai menjadi item galeri NONAKTIF; admin yang
 * mengaktifkannya. Idempoten per (permohonan, url); tautan lama yang tak lolos aturan dilewati.
 */
class SarankanGaleriDariLpj
{
    /** @return array{dibuat: int, dilewati: int} */
    public function jalankan(User $pelaku): array
    {
        Gate::forUser($pelaku)->authorize('create', Galeri::class);

        $hasil = ['dibuat' => 0, 'dilewati' => 0];

        Lpj::with('permohonan')->where('status', Lpj::DINILAI)
            ->where(fn ($q) => $q->whereNotNull('tautan_instagram')->orWhereNotNull('tautan_video'))
            ->orderBy('id')
            ->each(function (Lpj $lpj) use (&$hasil) {
                foreach (['instagram' => $lpj->tautan_instagram, 'video' => $lpj->tautan_video] as $tipe => $url) {
                    if (blank($url)) {
                        continue;
                    }

                    if (Galeri::where('permohonan_id', $lpj->permohonan_id)->where('url', $url)->exists()) {
                        $hasil['dilewati']++;

                        continue;
                    }

                    try {
                        Galeri::create([
                            'ormawa_id' => $lpj->permohonan->ormawa_id, 'permohonan_id' => $lpj->permohonan_id,
                            'judul' => $lpj->permohonan->nama_kegiatan, 'tipe' => $tipe, 'url' => $url, 'aktif' => false,
                        ]);
                        $hasil['dibuat']++;
                    } catch (ValidationException) {
                        $hasil['dilewati']++;
                    }
                }
            });

        return $hasil;
    }
}
