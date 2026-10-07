<?php

namespace App\Filament\Resources\Users;

use App\Filament\RelationManagers\TautanBerkasRelationManager;
use Illuminate\Database\Eloquent\Model;

/** Gambar tanda tangan visual pejabat (hanya dibaca server; tidak pernah ditampilkan sebagai URL). */
class PenggunaTautanRelationManager extends TautanBerkasRelationManager
{
    protected static array $jenis = ['ttd_visual'];

    protected static ?string $title = 'Tanda tangan visual';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('update', $ownerRecord) ?? false;
    }
}
