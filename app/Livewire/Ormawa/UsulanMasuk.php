<?php

namespace App\Livewire\Ormawa;

use App\Actions\Usulan\ArsipkanUsulan;
use App\Actions\Usulan\PutusUsulan;
use App\Enums\StatusUsulan;
use App\Models\Ormawa;
use App\Models\User;
use App\Models\UsulanMahasiswa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Usulan mahasiswa dan kiriman pihak luar yang masuk ke ormawa: perantara memilah. Satu atau beberapa usulan dijadikan
 * dasar satu permohonan (membuka formulir pengajuan dengan dasar terpilih), atau dikembalikan/ditolak dengan catatan.
 * Kiriman pihak luar juga dapat diarsipkan tanpa tindak lanjut.
 */
#[Layout('layouts.app')]
class UsulanMasuk extends Component
{
    #[Locked]
    public string $ormawaId = '';

    /** @var list<string> */
    public array $dipilih = [];

    /** @var array<string, string> */
    public array $catatan = [];

    public function mount(string $ormawa): void
    {
        $model = Ormawa::findOrFail($ormawa);
        abort_unless($this->pengguna()->can('permohonan.ajukan') && $this->pengguna()->ormawaAktif()->contains('id', $model->getKey()), 403);
        $this->ormawaId = $model->getKey();
    }

    public function jadikanDasar(): mixed
    {
        $this->resetErrorBag();
        $sah = $this->kueri()->whereIn('id', $this->dipilih)->pluck('id')->all();

        if ($sah === []) {
            $this->addError('dipilih', 'Pilih minimal satu usulan.');

            return null;
        }

        return $this->redirect($this->urlPengajuan($sah), navigate: false);
    }

    public function kembalikan(string $id): void
    {
        $this->putus($id, fn (UsulanMahasiswa $u) => app(PutusUsulan::class)->kembalikan($u, $this->pengguna(), $this->catatan[$id] ?? ''), 'Usulan dikembalikan kepada pengusul.');
    }

    public function tolak(string $id): void
    {
        $this->putus($id, fn (UsulanMahasiswa $u) => app(PutusUsulan::class)->tolak($u, $this->pengguna(), $this->catatan[$id] ?? ''), 'Usulan ditolak.');
    }

    public function arsipkan(string $id): void
    {
        $this->putus($id, fn (UsulanMahasiswa $u) => app(ArsipkanUsulan::class)->jalankan($u, $this->pengguna()), 'Kiriman pihak luar diarsipkan.');
    }

    /** @param  callable(UsulanMahasiswa): mixed  $aksi */
    private function putus(string $id, callable $aksi, string $pesan): void
    {
        $this->resetErrorBag();
        $usulan = $this->kueri()->findOrFail($id);
        Gate::forUser($this->pengguna())->authorize('tangani', $usulan);

        try {
            $aksi($usulan);
        } catch (ValidationException $e) {
            $this->addError("catatan.{$id}", (string) collect($e->errors())->flatten()->first());

            return;
        }

        unset($this->catatan[$id]);
        $this->dipilih = array_values(array_diff($this->dipilih, [$id]));
        session()->flash('status', $pesan);
    }

    /** @return Builder<UsulanMahasiswa> */
    protected function kueri(): Builder
    {
        return UsulanMahasiswa::where('ormawa_id', $this->ormawaId)->where('status', StatusUsulan::Diajukan->value);
    }

    /** @param  list<string>  $ids */
    protected function urlPengajuan(array $ids): string
    {
        return route('ormawa.permohonan.baru', ['ormawa' => $this->ormawaId, 'dasar' => $ids]);
    }

    /** @return array{nama: string, kembali: string} */
    protected function infoPengaju(): array
    {
        $ormawa = Ormawa::findOrFail($this->ormawaId);

        return ['nama' => $ormawa->nama, 'kembali' => route('ormawa', ['o' => $ormawa->id])];
    }

    public function render(): View
    {
        return view('livewire.ormawa.usulan-masuk', [
            'pengaju' => $this->infoPengaju(),
            'daftar' => $this->kueri()->with(['mahasiswa', 'tautan'])->oldest()->get(),
        ])->title('Usulan masuk');
    }

    protected function pengguna(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
