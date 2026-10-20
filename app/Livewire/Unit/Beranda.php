<?php

namespace App\Livewire\Unit;

use App\Enums\StatusPermohonan;
use App\Models\Permohonan;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Beranda pengaju dan kepala program studi/laboratorium: unit yang ditugaskan beserta ringkasan permohonannya. */
#[Layout('layouts.app')]
class Beranda extends Component
{
    public function mount(): void
    {
        abort_unless($this->pengguna()->hasAnyRole(['pengaju-unit', 'kaprodi', 'kalab']), 403);
    }

    public function render(): View
    {
        $pengguna = $this->pengguna();
        $diajukan = $pengguna->can('permohonan.ajukan-unit') ? $pengguna->unitDiajukan()->orderBy('nama')->get() : new Collection;
        $disetujui = $pengguna->can('permohonan.setujui-unit') ? $pengguna->unitDisetujui()->orderBy('nama')->get() : new Collection;
        $unitIds = $diajukan->pluck('id')->merge($disetujui->pluck('id'))->unique();

        return view('livewire.unit.beranda', [
            'unit' => $diajukan->concat($disetujui)->unique('id')->sortBy('nama')->values(),
            'bolehAjukan' => $diajukan->pluck('id')->all(),
            'jumlah' => Permohonan::whereIn('unit_kerja_id', $unitIds)->selectRaw('unit_kerja_id, count(*) as total')->groupBy('unit_kerja_id')->pluck('total', 'unit_kerja_id')->all(),
            'menunggu' => $disetujui->isEmpty() ? 0 : Permohonan::whereIn('unit_kerja_id', $disetujui->pluck('id'))->where('status', StatusPermohonan::PersetujuanUnit)->count(),
            'adaPenyetuju' => $disetujui->isNotEmpty(),
        ])->title('Ruang Unit');
    }

    private function pengguna(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
