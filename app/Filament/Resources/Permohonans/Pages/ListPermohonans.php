<?php

namespace App\Filament\Resources\Permohonans\Pages;

use App\Enums\StatusPermohonan;
use App\Filament\Resources\Permohonans\PermohonanResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPermohonans extends ListRecords
{
    protected static string $resource = PermohonanResource::class;

    /** @return array<string, Tab> */
    public function getTabs(): array
    {
        $tab = ['semua' => Tab::make('Semua')];

        foreach ([
            StatusPermohonan::PersetujuanPembina, StatusPermohonan::ValidasiAdmin, StatusPermohonan::Dikembalikan,
            StatusPermohonan::DisposisiDekan, StatusPermohonan::PersetujuanWd, StatusPermohonan::RekomendasiKasubag,
            StatusPermohonan::Penerbitan, StatusPermohonan::Selesai, StatusPermohonan::Ditolak,
        ] as $status) {
            $tab[$status->value] = Tab::make($status->label())
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', $status->value));
        }

        return $tab;
    }
}
