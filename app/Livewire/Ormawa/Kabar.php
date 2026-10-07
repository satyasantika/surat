<?php

namespace App\Livewire\Ormawa;

use App\Actions\Kabar\AjukanKabar;
use App\Actions\Kabar\SimpanKabar;
use App\Models\Kabar as ModelKabar;
use App\Models\Ormawa;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Pengurus ormawa mengusulkan kabar (draf → diajukan). Aturan ada di Action; admin menyetujui di panel. */
#[Layout('layouts.app')]
class Kabar extends Component
{
    #[Locked]
    public string $ormawaId = '';

    #[Locked]
    public ?string $kabarId = null;

    public string $judul = '';

    public string $subjudul = '';

    public string $isi = '';

    public string $tag = '';

    public string $sampul = '';

    public function mount(string $ormawa): void
    {
        $model = Ormawa::findOrFail($ormawa);
        abort_unless($this->pengguna()->ormawaAktif()->contains('id', $model->getKey()), 403);
        abort_unless($this->pengguna()->can('kabar.usul'), 403);
        $this->ormawaId = $model->getKey();
    }

    public function ubah(string $id): void
    {
        $kabar = ModelKabar::where('ormawa_id', $this->ormawaId)->with('tautan')->findOrFail($id);
        Gate::forUser($this->pengguna())->authorize('update', $kabar);

        $this->kabarId = $kabar->getKey();
        $this->judul = $kabar->judul;
        $this->subjudul = (string) $kabar->subjudul;
        $this->isi = $kabar->isi;
        $this->tag = implode(', ', $kabar->tag ?? []);
        $this->sampul = (string) $kabar->tautan->firstWhere('jenis', 'foto')?->url;
    }

    public function simpan(): void
    {
        $this->simpanDraf();
        session()->flash('status', 'Draf kabar disimpan.');
        $this->kosongkan();
    }

    public function simpanDanAjukan(): void
    {
        $kabar = $this->simpanDraf();
        app(AjukanKabar::class)->jalankan($kabar, $this->pengguna());
        session()->flash('status', 'Kabar diajukan ke admin untuk disetujui.');
        $this->kosongkan();
    }

    public function ajukan(string $id): void
    {
        $kabar = ModelKabar::where('ormawa_id', $this->ormawaId)->findOrFail($id);
        app(AjukanKabar::class)->jalankan($kabar, $this->pengguna());
        session()->flash('status', 'Kabar diajukan ke admin untuk disetujui.');
    }

    public function batal(): void
    {
        $this->kosongkan();
    }

    public function render(): View
    {
        return view('livewire.ormawa.kabar', [
            'ormawa' => Ormawa::findOrFail($this->ormawaId),
            'daftar' => ModelKabar::where('ormawa_id', $this->ormawaId)->latest()->get(),
        ])->title('Kabar ormawa');
    }

    private function simpanDraf(): ModelKabar
    {
        $kabar = $this->kabarId ? ModelKabar::where('ormawa_id', $this->ormawaId)->findOrFail($this->kabarId) : null;

        return app(SimpanKabar::class)->jalankan($this->pengguna(), [
            'ormawa_id' => $this->ormawaId,
            'judul' => $this->judul,
            'subjudul' => $this->subjudul ?: null,
            'isi' => $this->isi,
            'tag' => array_values(array_filter(array_map('trim', explode(',', $this->tag)))),
            'sampul' => $this->sampul ?: null,
        ], $kabar);
    }

    private function kosongkan(): void
    {
        $this->reset(['kabarId', 'judul', 'subjudul', 'isi', 'tag', 'sampul']);
        $this->resetValidation();
    }

    private function pengguna(): User
    {
        /** @var User */
        return auth()->user();
    }
}
