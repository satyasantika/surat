<?php

namespace App\Livewire\Ormawa;

use App\Actions\Permohonan\AjukanPermohonan as AksiAjukan;
use App\Contracts\LayananRuangan;
use App\Exceptions\LayananRuanganTidakTersedia;
use App\Models\JenisPermohonan;
use App\Models\Ormawa;
use App\Models\User;
use App\Services\Ruangan\KetersediaanRuangan;
use App\Support\Pengaturan;
use App\Support\SesiRuangan;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/** Formulir pengajuan bertahap: kegiatan → ruangan → penanggung jawab → berkas. Aturan ada di AjukanPermohonan. */
#[Layout('layouts.app')]
class AjukanPermohonan extends Component
{
    #[Locked]
    public string $ormawaId = '';

    public int $langkah = 1;

    public string $jenis = '';

    public string $nama_kegiatan = '';

    public string $perihal = '';

    public string $nomor_surat_ormawa = '';

    public string $tanggal_mulai = '';

    public string $tanggal_selesai = '';

    public string $jam_mulai = '';

    public string $jam_selesai = '';

    public string $tempat_lain = '';

    public string $deskripsi = '';

    public string $alasan_mendesak = '';

    /** @var array<string, string> kunci "kode|tanggal" => sesi (kosong = tidak dipilih) */
    public array $pilihanRuangan = [];

    /** @var array<string, array<string, string>> */
    public array $pj = ['ketua' => ['nama' => '', 'nim' => '', 'hp' => '', 'prodi' => '', 'email' => ''], 'wakil' => ['nama' => ''], 'sekretaris' => ['nama' => '']];

    /** @var list<array{nama: string, jumlah: string, keterangan: string}> */
    public array $fasilitas = [];

    /** @var array<string, string> */
    public array $berkas = [];

    public function mount(string $ormawa): void
    {
        $model = Ormawa::findOrFail($ormawa);
        abort_unless($this->pengguna()->ormawaAktif()->contains('id', $model->getKey()), 403);
        $this->ormawaId = $model->getKey();
        $this->jenis = (string) JenisPermohonan::where('aktif', true)->orderBy('nama')->value('id');
    }

    public function lanjut(): void
    {
        $this->langkah = min(4, $this->langkah + 1);
        $this->resetErrorBag();
    }

    public function kembali(): void
    {
        $this->langkah = max(1, $this->langkah - 1);
    }

    public function tambahFasilitas(): void
    {
        $this->fasilitas[] = ['nama' => '', 'jumlah' => '1', 'keterangan' => ''];
    }

    public function hapusFasilitas(int $i): void
    {
        unset($this->fasilitas[$i]);
        $this->fasilitas = array_values($this->fasilitas);
    }

    public function kirim(): mixed
    {
        $jenis = JenisPermohonan::findOrFail($this->jenis);
        $ruangan = [];

        foreach ($this->pilihanRuangan as $kunci => $sesi) {
            if ($sesi !== '' && str_contains($kunci, '|')) {
                [$kode, $tanggal] = explode('|', $kunci, 2);
                $ruangan[] = ['kode' => $kode, 'tanggal' => $tanggal, 'sesi' => $sesi];
            }
        }

        $kosongKeNull = fn (array $a) => array_map(fn ($v) => $v === '' ? null : $v, $a);

        try {
            $permohonan = app(AksiAjukan::class)->jalankan($this->pengguna(), $this->ormawa(), $jenis, [
                'nama_kegiatan' => $this->nama_kegiatan, 'perihal' => $this->perihal,
                'nomor_surat_ormawa' => $this->nomor_surat_ormawa ?: null,
                'tanggal_mulai' => $this->tanggal_mulai, 'tanggal_selesai' => $this->tanggal_selesai,
                'jam_mulai' => $this->jam_mulai ?: null, 'jam_selesai' => $this->jam_selesai ?: null,
                'tempat_lain' => $this->tempat_lain ?: null, 'deskripsi' => $this->deskripsi, 'alasan_mendesak' => $this->alasan_mendesak ?: null,
                'penanggung_jawab' => ['ketua' => $kosongKeNull($this->pj['ketua']), 'wakil' => $kosongKeNull($this->pj['wakil']), 'sekretaris' => $kosongKeNull($this->pj['sekretaris'])],
                'fasilitas_rektorat' => $jenis->butuh_fasilitas_rektorat ? array_map(fn ($f) => $kosongKeNull($f), $this->fasilitas) : null,
                'berkas' => array_filter($this->berkas, fn ($v) => $v !== ''),
                'ruangan' => $jenis->butuh_ruangan ? $ruangan : [],
            ]);
        } catch (TooManyRequestsHttpException) {
            $this->addError('umum', 'Terlalu banyak percobaan pengajuan. Coba lagi dalam beberapa menit.');

            return null;
        } catch (LayananRuanganTidakTersedia) {
            $this->addError('umum', 'Layanan ruangan sedang tidak tersedia. Coba lagi nanti; permohonan Anda belum tersimpan.');

            return null;
        }

        session()->flash('status', "Permohonan {$permohonan->nomor} berhasil diajukan.");

        return $this->redirectRoute('ormawa.permohonan', ['ormawa' => $this->ormawaId], navigate: false);
    }

    public function render(): View
    {
        $jenis = $this->jenis !== '' ? JenisPermohonan::find($this->jenis) : null;
        $sisa = null;
        $terlambat = false;

        if ($this->tanggal_mulai !== '') {
            $sisa = (int) now()->startOfDay()->diffInDays(CarbonImmutable::parse($this->tanggal_mulai)->startOfDay(), false);
            $terlambat = $sisa < (int) Pengaturan::get('min_hari_sebelum_kegiatan');
        }

        [$ketersediaan, $layananGalat] = $this->langkah === 2 && $jenis?->butuh_ruangan ? $this->hitungKetersediaan() : [[], false];

        return view('livewire.ormawa.ajukan-permohonan', [
            'ormawa' => $this->ormawa(),
            'jenisDaftar' => JenisPermohonan::where('aktif', true)->orderBy('nama')->get(),
            'jenisTerpilih' => $jenis,
            'terlambat' => $terlambat,
            'minHari' => (int) Pengaturan::get('min_hari_sebelum_kegiatan'),
            'ketersediaan' => $ketersediaan,
            'layananGalat' => $layananGalat,
            'sesiPilihan' => SesiRuangan::kode(),
        ])->title('Ajukan permohonan');
    }

    /**
     * @return array{0: list<array{kode: string, nama: string, tanggal: string, sesi: array<string, bool>}>, 1: bool}
     */
    private function hitungKetersediaan(): array
    {
        if ($this->tanggal_mulai === '' || $this->tanggal_selesai === '' || $this->tanggal_selesai < $this->tanggal_mulai) {
            return [[], false];
        }

        $mulai = CarbonImmutable::parse($this->tanggal_mulai);
        $selesai = CarbonImmutable::parse($this->tanggal_selesai);

        if ($mulai->diffInDays($selesai) > 14) {
            return [[], false];
        }

        $ketersediaan = app(KetersediaanRuangan::class);
        $hasil = [];

        try {
            foreach (app(LayananRuangan::class)->daftar() as $ruangan) {
                for ($hari = $mulai; $hari->lte($selesai); $hari = $hari->addDay()) {
                    $tanggal = $hari->toDateString();
                    $hasil[] = [
                        'kode' => $ruangan['kode'], 'nama' => $ruangan['nama'], 'tanggal' => $tanggal,
                        'sesi' => collect(SesiRuangan::kode())->mapWithKeys(fn (string $s) => [$s => ! $ketersediaan->terpakai($ruangan['kode'], $tanggal, $s)])->all(),
                    ];
                }
            }
        } catch (LayananRuanganTidakTersedia) {
            return [[], true];
        }

        return [$hasil, false];
    }

    private function pengguna(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    private function ormawa(): Ormawa
    {
        $ormawa = Ormawa::findOrFail($this->ormawaId);
        Gate::forUser($this->pengguna())->authorize('view', $ormawa);

        return $ormawa;
    }
}
