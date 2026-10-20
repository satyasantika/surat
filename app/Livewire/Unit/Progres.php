<?php

namespace App\Livewire\Unit;

use App\Livewire\Ormawa\Progres as ProgresOrmawa;
use App\Models\Permohonan;
use App\Models\UnitKerja;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;

/** Daftar permohonan program studi/laboratorium beserta linimasa; untuk pengaju dan kepala unit. */
#[Layout('layouts.app')]
class Progres extends ProgresOrmawa
{
    #[Locked]
    public string $unitId = '';

    public function mount(string $unit): void
    {
        $model = UnitKerja::findOrFail($unit);
        Gate::forUser($this->pengguna())->authorize('lihatPermohonan', $model);
        $this->unitId = $model->getKey();
    }

    protected function kueri(): Builder
    {
        return Permohonan::where('unit_kerja_id', $this->unitId);
    }

    protected function infoPengaju(): array
    {
        $unit = UnitKerja::findOrFail($this->unitId);
        $boleh = Gate::forUser($this->pengguna())->allows('ajukanPermohonan', $unit);

        return ['nama' => $unit->nama, 'kembali' => route('unit'), 'baru' => $boleh ? route('unit.permohonan.baru', $unit) : ''];
    }
}
