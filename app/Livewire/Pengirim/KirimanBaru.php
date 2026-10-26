<?php

namespace App\Livewire\Pengirim;

use App\Actions\Kiriman\AjukanKirimanEksternal;
use App\Models\User;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/** Formulir kiriman pengirim eksternal ke fakultas: nama dan surel dari akun Google sebagai nilai awal. */
#[Layout('layouts.app')]
class KirimanBaru extends Component
{
    public string $nama = '';

    public string $instansi = '';

    public string $perihal = '';

    public string $uraian = '';

    public string $lampiran = '';

    public string $surel = '';

    public string $wa = '';

    public function mount(): void
    {
        abort_unless($this->pengguna()->can('kiriman.ajukan'), 403);

        $this->nama = (string) $this->pengguna()->name;
        $this->surel = (string) $this->pengguna()->email;
    }

    public function kirim(): mixed
    {
        try {
            app(AjukanKirimanEksternal::class)->jalankan($this->pengguna(), [
                'tujuan' => [AjukanKirimanEksternal::TUJUAN_FAKULTAS],
                'nama' => $this->nama, 'instansi' => $this->instansi, 'perihal' => $this->perihal, 'uraian' => $this->uraian,
                'surel' => $this->surel, 'wa' => $this->wa, 'lampiran' => $this->lampiran ?: null,
            ]);
        } catch (TooManyRequestsHttpException) {
            $this->addError('umum', 'Batas pengiriman harian tercapai. Coba lagi besok.');

            return null;
        }

        session()->flash('status', 'Kiriman terkirim dan menunggu diterima fakultas.');

        return $this->redirectRoute('pengirim', navigate: false);
    }

    public function render(): View
    {
        return view('livewire.pengirim.kiriman-baru')->title('Kirim surat');
    }

    private function pengguna(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
