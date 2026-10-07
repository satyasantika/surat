<?php

namespace App\Filament\Resources\Ormawas;

use App\Filament\RelationManagers\TautanBerkasRelationManager;

class OrmawaTautanRelationManager extends TautanBerkasRelationManager
{
    protected static array $jenis = ['logo'];

    protected static ?string $title = 'Logo';
}
