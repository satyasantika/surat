<?php

namespace App\Livewire\Ormawa;

use App\Models\Lpj;
use App\Models\Ormawa;
use App\Models\Permohonan;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Beranda pengurus ormawa: ringkasan ormawa aktif (kartu permohonan/LPJ diisi pada F7/F8). */
#[Layout('layouts.app')]
class Beranda extends Component
{
    #[Url(as: 'o')]
    public ?string $ormawaId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasRole('pengurus-ormawa'), 403);
    }

    public function pilih(string $id): void
    {
        abort_unless($this->daftar()->contains('id', $id), 403);
        $this->ormawaId = $id;
    }

    /** @return Collection<int, Ormawa> */
    private function daftar(): Collection
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->ormawaAktif();
    }

    public function render(): View
    {
        $daftar = $this->daftar();
        $terpilih = $daftar->firstWhere('id', $this->ormawaId) ?? $daftar->first();
        /** @var User $user */
        $user = auth()->user();

        return view('livewire.ormawa.beranda', [
            'daftar' => $daftar,
            'ormawa' => $terpilih,
            'sk' => $terpilih?->skBerlaku(),
            'lpjDaftar' => $terpilih ? Lpj::with('permohonan')->whereHas('permohonan', fn ($q) => $q->where('ormawa_id', $terpilih->getKey()))->where('status', 'draf')->orderBy('batas_waktu')->get() : collect(),
            'jumlahPermohonan' => $terpilih ? Permohonan::where('ormawa_id', $terpilih->getKey())->count() : 0,
            'bolehKelola' => $terpilih !== null && $user->dapatMengelolaOrmawa($terpilih),
            'adaKeanggotaan' => $user->keanggotaanOrmawa()->exists(),
        ])->title('Ruang Ormawa');
    }
}
