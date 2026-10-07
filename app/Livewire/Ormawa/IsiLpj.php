<?php

namespace App\Livewire\Ormawa;

use App\Actions\Lpj\IsiLpj as AksiIsiLpj;
use App\Models\Lpj;
use App\Models\Ormawa;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Formulir LPJ untuk pengurus ormawa. Aturan ada di Action IsiLpj; setelah diajukan hanya dapat dibaca. */
#[Layout('layouts.app')]
class IsiLpj extends Component
{
    #[Locked]
    public string $ormawaId = '';

    #[Locked]
    public string $lpjId = '';

    public string $tanggal_pelaksanaan = '';

    public string $jumlah_peserta = '';

    public string $ringkasan = '';

    public string $kendala = '';

    public string $solusi = '';

    public string $rekomendasi = '';

    public string $tautan_instagram = '';

    public string $tautan_video = '';

    public string $berkas_lpj = '';

    public function mount(string $ormawa, string $lpj): void
    {
        $model = Lpj::with('permohonan.ormawa')->findOrFail($lpj);
        abort_unless($model->permohonan->ormawa_id === $ormawa, 404);
        Gate::forUser($this->pengguna())->authorize('view', $model);

        $this->ormawaId = $ormawa;
        $this->lpjId = $model->getKey();
        $this->tanggal_pelaksanaan = (string) $model->tanggal_pelaksanaan?->toDateString();
        $this->jumlah_peserta = (string) $model->jumlah_peserta;
        $this->ringkasan = (string) $model->ringkasan;
        $this->kendala = (string) $model->kendala;
        $this->solusi = (string) $model->solusi;
        $this->rekomendasi = (string) $model->rekomendasi;
        $this->tautan_instagram = (string) $model->tautan_instagram;
        $this->tautan_video = (string) $model->tautan_video;
        $this->berkas_lpj = (string) $model->tautan()->where('jenis', 'lpj')->value('url');
    }

    public function kirim(): mixed
    {
        $kosong = fn (string $v) => $v === '' ? null : $v;

        app(AksiIsiLpj::class)->jalankan($this->model(), $this->pengguna(), [
            'tanggal_pelaksanaan' => $kosong($this->tanggal_pelaksanaan), 'jumlah_peserta' => $kosong($this->jumlah_peserta),
            'ringkasan' => $kosong($this->ringkasan), 'kendala' => $kosong($this->kendala), 'solusi' => $kosong($this->solusi),
            'rekomendasi' => $kosong($this->rekomendasi), 'tautan_instagram' => $kosong($this->tautan_instagram),
            'tautan_video' => $kosong($this->tautan_video), 'berkas_lpj' => $kosong($this->berkas_lpj),
        ]);

        session()->flash('status', 'LPJ berhasil diajukan.');

        return $this->redirectRoute('ormawa', ['o' => $this->ormawaId]);
    }

    public function render(): View
    {
        $lpj = $this->model();

        return view('livewire.ormawa.isi-lpj', [
            'lpj' => $lpj,
            'ormawa' => Ormawa::findOrFail($this->ormawaId),
            'bisaIsi' => $this->pengguna()->can('isi', $lpj),
        ])->title('LPJ');
    }

    private function model(): Lpj
    {
        $lpj = Lpj::with(['permohonan', 'nilai.rubrik', 'nilai.jabatan'])->findOrFail($this->lpjId);
        Gate::forUser($this->pengguna())->authorize('view', $lpj);

        return $lpj;
    }

    private function pengguna(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
