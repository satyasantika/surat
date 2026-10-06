<?php

namespace App\Actions\Disposisi;

use App\Enums\StatusDisposisiPenerima;
use App\Models\DisposisiPenerima;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class TandaiDibaca
{
    public function jalankan(DisposisiPenerima $penerima, User $pelaku): DisposisiPenerima
    {
        if ($penerima->user_id !== $pelaku->getKey()) {
            throw new AuthorizationException('Hanya penerima yang dapat menandai dibaca.');
        }

        if ($penerima->status === StatusDisposisiPenerima::Diterima) {
            $penerima->update(['status' => StatusDisposisiPenerima::Dibaca, 'dibaca_pada' => now()]);
        }

        return $penerima;
    }
}
