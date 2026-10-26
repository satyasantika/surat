<?php

namespace App\Livewire\Pengirim;

use App\Actions\Kiriman\TarikKirimanEksternal;
use App\Actions\Usulan\BatalkanUsulan;
use App\Enums\StatusUsulan;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Models\UsulanMahasiswa;
use App\Support\KodeLacak;
use App\Support\Pelacakan\Linimasa;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Ruang pengirim eksternal: kiriman milik sendiri, satu baris per tujuan (fakultas atau ormawa/prodi/lab), beserta
 * status dan kode lacaknya. Kiriman ke fakultas dapat ditarik selama belum diterima; kiriman ke perantara dapat
 * dibatalkan selama masih terbuka.
 */
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

    public function batalkan(string $id): void
    {
        abort_unless($this->pengguna()->can('kiriman.ajukan'), 403);
        // Hanya kiriman pihak luar milik sendiri; usulan mahasiswa atau milik pengirim lain berakhir 404.
        $usulan = UsulanMahasiswa::where('mahasiswa_id', $this->pengguna()->getKey())->where('asal', UsulanMahasiswa::ASAL_EKSTERNAL)->findOrFail($id);

        try {
            app(BatalkanUsulan::class)->jalankan($usulan, $this->pengguna());
            session()->flash('status', 'Kiriman dibatalkan.');
        } catch (ValidationException $e) {
            $this->addError('umum', (string) collect($e->errors())->flatten()->first());
        }
    }

    public function render(Linimasa $linimasa): View
    {
        $milik = $this->pengguna()->getKey();

        $surat = SuratMasuk::where('pengirim_user_id', $milik)->get()->map(fn (SuratMasuk $s) => [
            'kunci' => 's-'.$s->id,
            'id' => $s->id,
            'tujuan' => 'Dekan FKIP',
            'waktu' => $s->created_at ?? Carbon::parse($s->tanggal_terima),
            'perihal' => $s->perihal,
            'uraian' => $s->ringkasan,
            'status' => $linimasa->statusSuratMasuk($s),
            'keterangan' => $s->nomor_agenda ? "nomor agenda {$s->nomor_agenda}" : null,
            'catatan' => null,
            'kode' => KodeLacak::tampil($s->kode_lacak),
            'aksi' => $s->masihDiajukan() ? 'tarik' : null,
        ]);

        $usulan = UsulanMahasiswa::with(['ormawa', 'unitKerja'])->where('mahasiswa_id', $milik)->where('asal', UsulanMahasiswa::ASAL_EKSTERNAL)->get()
            ->map(fn (UsulanMahasiswa $u) => [
                'kunci' => 'u-'.$u->id,
                'id' => $u->id,
                'tujuan' => $u->namaTujuan(),
                'waktu' => $u->created_at,
                'perihal' => $u->perihal,
                'uraian' => $u->uraian,
                'status' => $u->status->label(),
                'keterangan' => null,
                // Catatan perantara hanya untuk keputusan yang ditujukan kepada pengirim.
                'catatan' => in_array($u->status, [StatusUsulan::Dikembalikan, StatusUsulan::Ditolak], true) ? $u->catatan : null,
                'kode' => KodeLacak::tampil($u->kode_lacak),
                'aksi' => $u->status->terbuka() ? 'batalkan' : null,
            ]);

        return view('livewire.pengirim.beranda', [
            'daftar' => $surat->toBase()->merge($usulan->toBase())->sortByDesc('waktu')->values(),
        ])->title('Kiriman Saya');
    }

    private function pengguna(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
