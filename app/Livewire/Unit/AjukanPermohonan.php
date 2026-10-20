<?php

namespace App\Livewire\Unit;

use App\Actions\Permohonan\AjukanPermohonan as AksiAjukan;
use App\Livewire\Ormawa\AjukanPermohonan as FormulirOrmawa;
use App\Models\JenisPermohonan;
use App\Models\Permohonan;
use App\Models\UnitKerja;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;

/** Formulir pengajuan atas nama program studi/laboratorium; formulir dan aturan sama dengan pengajuan ormawa. */
#[Layout('layouts.app')]
class AjukanPermohonan extends FormulirOrmawa
{
    #[Locked]
    public string $unitId = '';

    public function mount(string $unit): void
    {
        $model = UnitKerja::findOrFail($unit);
        Gate::forUser($this->pengguna())->authorize('ajukanPermohonan', $model);
        $this->unitId = $model->getKey();
        $this->jenis = (string) JenisPermohonan::where('aktif', true)->orderBy('nama')->value('id');
    }

    protected function ajukan(JenisPermohonan $jenis, array $data): Permohonan
    {
        return app(AksiAjukan::class)->jalankanUnit($this->pengguna(), UnitKerja::findOrFail($this->unitId), $jenis, $data);
    }

    protected function urlSetelahKirim(): string
    {
        return route('unit.permohonan', ['unit' => $this->unitId]);
    }

    protected function infoPengaju(): array
    {
        $unit = UnitKerja::findOrFail($this->unitId);
        Gate::forUser($this->pengguna())->authorize('ajukanPermohonan', $unit);

        return ['nama' => $unit->nama, 'kembali' => route('unit'), 'label_nomor' => 'Nomor surat pengantar unit'];
    }
}
