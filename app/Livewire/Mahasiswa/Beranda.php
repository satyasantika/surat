<?php

namespace App\Livewire\Mahasiswa;

use App\Actions\Usulan\BatalkanUsulan;
use App\Models\User;
use App\Models\UsulanMahasiswa;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Ruang mahasiswa: usulan yang diajukan beserta statusnya, dan pembatalan selama belum dipakai. */
#[Layout('layouts.app')]
class Beranda extends Component
{
    public function mount(): void
    {
        abort_unless($this->pengguna()->can('usulan.ajukan'), 403);
    }

    public function batalkan(string $id): void
    {
        $usulan = UsulanMahasiswa::where('mahasiswa_id', $this->pengguna()->getKey())->findOrFail($id);

        try {
            app(BatalkanUsulan::class)->jalankan($usulan, $this->pengguna());
            session()->flash('status', 'Usulan dibatalkan.');
        } catch (ValidationException $e) {
            $this->addError('umum', (string) collect($e->errors())->flatten()->first());
        }
    }

    public function render(): View
    {
        return view('livewire.mahasiswa.beranda', [
            'daftar' => UsulanMahasiswa::with(['ormawa', 'unitKerja', 'permohonan'])->where('mahasiswa_id', $this->pengguna()->getKey())->latest()->get(),
        ])->title('Ruang Mahasiswa');
    }

    private function pengguna(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
