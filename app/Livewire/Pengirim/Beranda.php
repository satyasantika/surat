<?php

namespace App\Livewire\Pengirim;

use App\Actions\Kiriman\TarikKirimanEksternal;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Support\Pelacakan\Linimasa;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** Ruang pengirim eksternal: kiriman milik sendiri beserta tahap dan kode lacaknya, dan penarikan selama belum diterima. */
#[Layout('layouts.app')]
class Beranda extends Component
{
    public function mount(): void
    {
        abort_unless($this->pengguna()->can('kiriman.ajukan'), 403);
    }

    public function tarik(string $id): void
    {
        abort_unless($this->pengguna()->can('kiriman.ajukan'), 403);
        // Hanya kiriman milik sendiri; id milik orang lain berakhir 404 (tidak membocorkan keberadaannya).
        $surat = SuratMasuk::where('pengirim_user_id', $this->pengguna()->getKey())->findOrFail($id);

        try {
            app(TarikKirimanEksternal::class)->jalankan($surat, $this->pengguna());
            session()->flash('status', 'Kiriman ditarik.');
        } catch (ValidationException $e) {
            $this->addError('umum', (string) collect($e->errors())->flatten()->first());
        }
    }

    public function render(Linimasa $linimasa): View
    {
        $daftar = SuratMasuk::where('pengirim_user_id', $this->pengguna()->getKey())->latest()->get();

        return view('livewire.pengirim.beranda', [
            'daftar' => $daftar,
            'status' => $daftar->mapWithKeys(fn (SuratMasuk $s) => [$s->id => $linimasa->statusSuratMasuk($s)]),
        ])->title('Kiriman Saya');
    }

    private function pengguna(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
