<?php

namespace App\Livewire\Unit;

use App\Enums\StatusUsulan;
use App\Livewire\Ormawa\UsulanMasuk as UsulanMasukOrmawa;
use App\Models\UnitKerja;
use App\Models\UsulanMahasiswa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;

/** Usulan mahasiswa yang masuk ke program studi/laboratorium; dipilah pengaju unit. */
#[Layout('layouts.app')]
class UsulanMasuk extends UsulanMasukOrmawa
{
    #[Locked]
    public string $unitId = '';

    public function mount(string $unit): void
    {
        $model = UnitKerja::findOrFail($unit);
        Gate::forUser($this->pengguna())->authorize('ajukanPermohonan', $model);
        $this->unitId = $model->getKey();
    }

    protected function kueri(): Builder
    {
        return UsulanMahasiswa::where('unit_kerja_id', $this->unitId)->where('status', StatusUsulan::Diajukan->value);
    }

    protected function urlPengajuan(array $ids): string
    {
        return route('unit.permohonan.baru', ['unit' => $this->unitId, 'dasar' => $ids]);
    }

    protected function infoPengaju(): array
    {
        return ['nama' => UnitKerja::findOrFail($this->unitId)->nama, 'kembali' => route('unit')];
    }
}
