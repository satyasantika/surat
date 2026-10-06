<?php

namespace App\Filament\Resources\SuratMasuks;

use App\Filament\RelationManagers\TautanBerkasRelationManager;

class SuratMasukTautanRelationManager extends TautanBerkasRelationManager
{
    protected static array $jenis = ['pindaian', 'lampiran'];
}
