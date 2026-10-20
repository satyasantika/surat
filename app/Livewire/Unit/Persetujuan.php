<?php

namespace App\Livewire\Unit;

use App\Actions\Permohonan\KembalikanPermohonan;
use App\Actions\Permohonan\SetujuiUnit;
use App\Actions\Permohonan\TolakPermohonan;
use App\Enums\StatusPermohonan;
use App\Models\Permohonan;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Kepala program studi/laboratorium memutus permohonan unitnya: setujui, kembalikan, atau tolak. Aturan ada di Action. */
#[Layout('layouts.app')]
class Persetujuan extends Component
{
    /** @var array<string, string> catatan per permohonan (wajib untuk kembalikan/tolak) */
    public array $catatan = [];

    public function mount(): void
    {
        abort_unless($this->pengguna()->can('permohonan.setujui-unit') && $this->pengguna()->unitDisetujui()->exists(), 403);
    }

    public function setujui(string $id): void
    {
        $this->putus($id, fn (Permohonan $p) => app(SetujuiUnit::class)->jalankan($p, $this->pengguna(), $this->catatan[$id] ?? null), 'Permohonan disetujui dan diteruskan ke fakultas.');
    }

    public function kembalikan(string $id): void
    {
        $this->putus($id, fn (Permohonan $p) => app(KembalikanPermohonan::class)->jalankan($p, $this->pengguna(), $this->catatan[$id] ?? ''), 'Permohonan dikembalikan kepada pengaju.');
    }

    public function tolak(string $id): void
    {
        $this->putus($id, fn (Permohonan $p) => app(TolakPermohonan::class)->jalankan($p, $this->pengguna(), $this->catatan[$id] ?? ''), 'Permohonan ditolak.');
    }

    /** @param  callable(Permohonan): mixed  $aksi */
    private function putus(string $id, callable $aksi, string $pesan): void
    {
        $this->resetErrorBag();
        $permohonan = $this->daftar()->firstWhere('id', $id);
        abort_if($permohonan === null, 404);
        Gate::forUser($this->pengguna())->authorize('setujuiUnit', $permohonan);

        try {
            $aksi($permohonan);
        } catch (ValidationException $e) {
            $this->addError("catatan.{$id}", (string) collect($e->errors())->flatten()->first());

            return;
        }

        unset($this->catatan[$id]);
        session()->flash('status', $pesan);
    }

    /** @return Collection<int, Permohonan> */
    private function daftar(): Collection
    {
        return Permohonan::with(['unitKerja', 'jenis', 'pengaju'])
            ->whereIn('unit_kerja_id', $this->pengguna()->unitDisetujui()->pluck('unit_kerja.id'))
            ->where('status', StatusPermohonan::PersetujuanUnit)->oldest('diajukan_pada')->get();
    }

    public function render(): View
    {
        return view('livewire.unit.persetujuan', ['daftar' => $this->daftar()])->title('Persetujuan permohonan unit');
    }

    private function pengguna(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
