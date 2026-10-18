<?php

namespace App\Livewire\Pembuat;

use App\Actions\Masuk\KirimHasilSuratMasuk;
use App\Actions\Permohonan\KirimHasilPermohonan;
use App\Enums\StatusPermohonan;
use App\Enums\StatusSuratMasuk;
use App\Models\Permohonan;
use App\Models\SuratMasuk;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Tugas pembuat surat untuk petugas unit pembuat (BR-26, BR-28), termasuk pegawai yang tidak punya akses panel admin:
 * melihat permohonan dan surat masuk yang ditugaskan ke unitnya, mengunggah tautan hasil cetak, lalu mengirimkannya
 * kepada pengirim. Aturan ada di Action; komponen hanya memanggilnya dengan pengguna terautentikasi.
 */
#[Layout('layouts.app')]
class Tugas extends Component
{
    /** Kartu terbuka: "permohonan:{id}" atau "surat:{id}". */
    #[Locked]
    public ?string $terbuka = null;

    public string $url = '';

    public string $catatan = '';

    public function mount(): void
    {
        abort_unless($this->pengguna()->unitPembuat()->exists() || $this->pengguna()->hasRole('super-admin'), 403);
    }

    public function buka(string $kunci): void
    {
        $this->terbuka = $this->terbuka === $kunci ? null : $kunci;
        $this->reset('url', 'catatan');
        $this->resetErrorBag();
    }

    public function kirimPermohonan(string $id): void
    {
        app(KirimHasilPermohonan::class)->jalankan(Permohonan::findOrFail($id), $this->pengguna(), $this->url, $this->catatan ?: null);
        session()->flash('status', 'Hasil dikirim kepada pengirim; permohonan selesai.');
        $this->tutup();
    }

    public function kirimSurat(string $id): void
    {
        app(KirimHasilSuratMasuk::class)->jalankan(SuratMasuk::findOrFail($id), $this->pengguna(), $this->url, $this->catatan ?: null);
        session()->flash('status', 'Hasil dikirim kepada pengirim; surat selesai.');
        $this->tutup();
    }

    public function render(): View
    {
        return view('livewire.pembuat.tugas', [
            'permohonanDaftar' => $this->daftarPermohonan(),
            'suratDaftar' => $this->daftarSurat(),
        ])->title('Tugas pembuat surat');
    }

    private function tutup(): void
    {
        $this->terbuka = null;
        $this->reset('url', 'catatan');
        $this->resetErrorBag();
    }

    private function pengguna(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    /** @return list<string> */
    private function unitSaya(): array
    {
        return $this->pengguna()->unitPembuat()->pluck('unit_pembuat.id')->all();
    }

    /** @return Collection<int, Permohonan> */
    private function daftarPermohonan(): Collection
    {
        return Permohonan::with(['ormawa', 'jenis', 'unitPembuat'])
            ->whereIn('status', [StatusPermohonan::Pembuatan->value, StatusPermohonan::Penerbitan->value])
            ->when(! $this->pengguna()->hasRole('super-admin'), fn (Builder $q) => $q->whereIn('unit_pembuat_id', $this->unitSaya()))
            ->orderBy('diajukan_pada')->get();
    }

    /** @return Collection<int, SuratMasuk> */
    private function daftarSurat(): Collection
    {
        return SuratMasuk::with(['unitPembuat', 'tautan'])
            ->where('status', StatusSuratMasuk::Didisposisikan->value)
            ->whereNotNull('unit_pembuat_id')
            ->when(! $this->pengguna()->hasRole('super-admin'), fn (Builder $q) => $q->whereIn('unit_pembuat_id', $this->unitSaya()))
            ->orderBy('tanggal_terima')->get();
    }
}
