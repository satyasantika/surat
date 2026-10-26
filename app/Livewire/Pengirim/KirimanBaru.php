<?php

namespace App\Livewire\Pengirim;

use App\Actions\Kiriman\AjukanKirimanEksternal;
use App\Models\Ormawa;
use App\Models\User;
use App\Support\Pengaturan;
use App\Support\TujuanUsulan;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Formulir kiriman pengirim eksternal: tujuan dicentang (fakultas dan/atau ormawa/prodi/lab aktif, berjalan paralel),
 * nama dan surel dari akun Google sebagai nilai awal. Hanya prodi/lab yang punya pengelola usulan yang ditawarkan.
 */
#[Layout('layouts.app')]
class KirimanBaru extends Component
{
    /** @var list<string> */
    public array $tujuan = [AjukanKirimanEksternal::TUJUAN_FAKULTAS];

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
                'tujuan' => array_values(array_filter($this->tujuan, 'is_string')),
                'nama' => $this->nama, 'instansi' => $this->instansi, 'perihal' => $this->perihal, 'uraian' => $this->uraian,
                'surel' => $this->surel, 'wa' => $this->wa, 'lampiran' => $this->lampiran ?: null,
            ]);
        } catch (TooManyRequestsHttpException) {
            $this->addError('umum', 'Batas pengiriman harian tercapai. Coba lagi besok.');

            return null;
        }

        session()->flash('status', 'Kiriman terkirim. Pantau status tiap tujuan di daftar ini.');

        return $this->redirectRoute('pengirim', navigate: false);
    }

    public function render(): View
    {
        return view('livewire.pengirim.kiriman-baru', [
            'ormawaDaftar' => Ormawa::where('aktif', true)->orderBy('nama')->get(['id', 'nama']),
            'unitDaftar' => TujuanUsulan::unitDapatDipilih()->orderBy('nama')->get(['id', 'nama']),
            'batasTujuan' => max(1, (int) Pengaturan::get('batas_tujuan_kiriman', 5)),
        ])->title('Kirim surat');
    }

    private function pengguna(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
