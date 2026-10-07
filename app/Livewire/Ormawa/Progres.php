<?php

namespace App\Livewire\Ormawa;

use App\Models\Ormawa;
use App\Models\Permohonan;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Daftar permohonan ormawa beserta linimasa dari riwayat_permohonan. */
#[Layout('layouts.app')]
class Progres extends Component
{
    #[Locked]
    public string $ormawaId = '';

    #[Url(as: 'p')]
    public ?string $terpilih = null;

    public function mount(string $ormawa): void
    {
        $model = Ormawa::findOrFail($ormawa);
        Gate::forUser($this->pengguna())->authorize('view', $model);
        $this->ormawaId = $model->getKey();
    }

    public function pilih(string $id): void
    {
        $permohonan = Permohonan::where('ormawa_id', $this->ormawaId)->findOrFail($id);
        Gate::forUser($this->pengguna())->authorize('view', $permohonan);
        $this->terpilih = $permohonan->getKey();
    }

    public function render(): View
    {
        $daftar = Permohonan::with('jenis')->where('ormawa_id', $this->ormawaId)->latest('diajukan_pada')->get();
        $detail = $this->terpilih ? $daftar->firstWhere('id', $this->terpilih) : null;

        if ($detail !== null) {
            $detail->load(['riwayat.pelaku', 'ruangan', 'pengaju']);
        }

        return view('livewire.ormawa.progres', [
            'ormawa' => Ormawa::findOrFail($this->ormawaId),
            'daftar' => $daftar,
            'detail' => $detail,
            'bolehPribadi' => $detail !== null && $this->pengguna()->can('lihatDataPribadi', $detail),
        ])->title('Progres permohonan');
    }

    private function pengguna(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
