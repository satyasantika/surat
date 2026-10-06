<?php

namespace App\Actions\Disposisi;

use App\Enums\StatusDisposisiPenerima;
use App\Enums\StatusSuratMasuk;
use App\Models\DisposisiPenerima;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SelesaikanDisposisi
{
    public function jalankan(DisposisiPenerima $penerima, User $pelaku): DisposisiPenerima
    {
        if ($penerima->user_id !== $pelaku->getKey()) {
            throw new AuthorizationException('Hanya penerima yang dapat menyelesaikan.');
        }

        if ($penerima->status !== StatusDisposisiPenerima::Ditindaklanjuti) {
            throw ValidationException::withMessages(['status' => 'Laporkan tindak lanjut terlebih dahulu sebelum menyelesaikan.']);
        }

        return DB::transaction(function () use ($penerima) {
            $penerima->update(['status' => StatusDisposisiPenerima::Selesai, 'selesai_pada' => now()]);

            $penerima->loadMissing('disposisi.suratMasuk');
            $surat = $penerima->disposisi->suratMasuk;

            // Surat selesai bila seluruh penerima (semua tingkat) sudah selesai.
            if ($surat && ! DisposisiPenerima::whereHas('disposisi', fn ($q) => $q->where('surat_masuk_id', $surat->getKey()))
                ->where('status', '!=', StatusDisposisiPenerima::Selesai->value)->exists()) {
                $surat->update(['status' => StatusSuratMasuk::Selesai]);
            }

            return $penerima;
        });
    }
}
