<?php

namespace App\Livewire\Ormawa;

use App\Actions\Ormawa\SimpanPengurus;
use App\Actions\Ormawa\SimpanProfilOrmawa;
use App\Models\Ormawa;
use App\Models\PengurusOrmawa;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Profil ormawa dan pengurusnya. Mengubah hanya untuk ketua/sekretaris; data pribadi hanya bagi yang berhak (BR-18). */
#[Layout('layouts.app')]
class Profil extends Component
{
    #[Locked]
    public string $ormawaId = '';

    public string $singkatan = '';

    public string $akun_media = '';

    public string $surel_organisasi = '';

    public string $visi = '';

    public string $misi = '';

    #[Locked]
    public ?string $pengurusId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    public function mount(string $ormawa): void
    {
        $model = $this->ormawa($ormawa);
        $this->ormawaId = $model->getKey();
        $this->singkatan = (string) $model->singkatan;
        $this->akun_media = (string) $model->akun_media;
        $this->surel_organisasi = (string) $model->surel_organisasi;
        $this->visi = (string) $model->visi;
        $this->misi = (string) $model->misi;
        $this->kosongkanForm();
    }

    public function simpanProfil(): void
    {
        app(SimpanProfilOrmawa::class)->jalankan($this->model(), $this->pengguna(), [
            'singkatan' => $this->singkatan ?: null, 'akun_media' => $this->akun_media ?: null,
            'surel_organisasi' => $this->surel_organisasi ?: null, 'visi' => $this->visi ?: null, 'misi' => $this->misi ?: null,
        ]);
        session()->flash('status', 'Profil disimpan.');
    }

    public function ubah(string $id): void
    {
        $p = $this->model()->pengurus()->findOrFail($id);
        Gate::forUser($this->pengguna())->authorize('update', $p);

        $this->pengurusId = $p->getKey();
        $this->form = [
            'nama' => $p->nama, 'jabatan' => $p->jabatan, 'jabatan_teks' => (string) $p->jabatan_teks, 'nim' => (string) $p->nim,
            'prodi' => (string) $p->prodi, 'telepon' => (string) $p->telepon, 'tampil_publik' => $p->tampil_publik, 'narahubung' => $p->narahubung,
            'mulai' => $p->mulai?->toDateString() ?? '', 'selesai' => $p->selesai?->toDateString() ?? '',
        ];
    }

    public function simpanPengurus(): void
    {
        $ormawa = $this->model();
        $pengurus = $this->pengurusId ? $ormawa->pengurus()->findOrFail($this->pengurusId) : null;

        $data = array_map(fn ($v) => $v === '' ? null : $v, $this->form);
        $data['tampil_publik'] = (bool) ($this->form['tampil_publik'] ?? true);
        $data['narahubung'] = (bool) ($this->form['narahubung'] ?? false);

        app(SimpanPengurus::class)->jalankan($ormawa, $pengurus, $this->pengguna(), $data);
        $this->kosongkanForm();
        session()->flash('status', 'Pengurus disimpan.');
    }

    public function hapus(string $id): void
    {
        $ormawa = $this->model();
        app(SimpanPengurus::class)->hapus($ormawa, $ormawa->pengurus()->findOrFail($id), $this->pengguna());
        $this->kosongkanForm();
    }

    public function batal(): void
    {
        $this->kosongkanForm();
    }

    public function render(): View
    {
        $ormawa = $this->model();
        $pengguna = $this->pengguna();

        return view('livewire.ormawa.profil', [
            'ormawa' => $ormawa,
            'bolehUbah' => $pengguna->can('update', $ormawa),
            'pengurus' => $ormawa->pengurus()->with('ormawa')->orderBy('jabatan')->orderBy('nama')->get(),
            'jabatanPilihan' => PengurusOrmawa::JABATAN,
        ])->title('Profil ormawa');
    }

    private function kosongkanForm(): void
    {
        $this->pengurusId = null;
        $this->form = ['nama' => '', 'jabatan' => 'anggota', 'jabatan_teks' => '', 'nim' => '', 'prodi' => '', 'telepon' => '',
            'tampil_publik' => true, 'narahubung' => false, 'mulai' => '', 'selesai' => ''];
        $this->resetErrorBag();
    }

    private function pengguna(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    private function model(): Ormawa
    {
        return $this->ormawa($this->ormawaId);
    }

    private function ormawa(string $id): Ormawa
    {
        $ormawa = Ormawa::findOrFail($id);
        Gate::forUser($this->pengguna())->authorize('view', $ormawa);

        return $ormawa;
    }
}
