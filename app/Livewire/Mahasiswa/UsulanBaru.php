<?php

namespace App\Livewire\Mahasiswa;

use App\Actions\Usulan\AjukanUsulan;
use App\Enums\TipeUnitKerja;
use App\Models\Ormawa;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/** Formulir ringkas usulan mahasiswa: tujuan (ormawa atau program studi/laboratorium), perihal, uraian, lampiran. */
#[Layout('layouts.app')]
class UsulanBaru extends Component
{
    public string $tujuan = '';

    public string $perihal = '';

    public string $uraian = '';

    public string $lampiran = '';

    public function mount(): void
    {
        abort_unless($this->pengguna()->can('usulan.ajukan'), 403);
    }

    public function kirim(): mixed
    {
        try {
            app(AjukanUsulan::class)->jalankan($this->pengguna(), [
                'tujuan' => $this->tujuan, 'perihal' => $this->perihal, 'uraian' => $this->uraian, 'lampiran' => $this->lampiran ?: null,
            ]);
        } catch (TooManyRequestsHttpException) {
            $this->addError('umum', 'Terlalu banyak usulan. Coba lagi dalam beberapa menit.');

            return null;
        }

        session()->flash('status', 'Usulan diajukan dan menunggu perantara.');

        return $this->redirectRoute('mahasiswa', navigate: false);
    }

    public function render(): View
    {
        return view('livewire.mahasiswa.usulan-baru', [
            'ormawaDaftar' => Ormawa::where('aktif', true)->orderBy('nama')->get(['id', 'nama']),
            'unitDaftar' => UnitKerja::where('aktif', true)->whereIn('tipe', [TipeUnitKerja::Prodi->value, TipeUnitKerja::Laboratorium->value])->orderBy('nama')->get(['id', 'nama']),
        ])->title('Ajukan usulan');
    }

    private function pengguna(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
