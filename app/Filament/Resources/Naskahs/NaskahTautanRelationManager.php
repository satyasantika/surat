<?php

namespace App\Filament\Resources\Naskahs;

use App\Filament\RelationManagers\TautanBerkasRelationManager;

class NaskahTautanRelationManager extends TautanBerkasRelationManager
{
    protected static array $jenis = ['lampiran', 'naskah_basah'];
}
