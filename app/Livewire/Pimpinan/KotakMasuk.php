<?php

namespace App\Livewire\Pimpinan;

use App\Actions\Disposisi\BuatDisposisi;
use App\Actions\Disposisi\LaporTindakLanjut;
use App\Actions\Disposisi\SelesaikanDisposisi;
use App\Actions\Disposisi\TandaiDibaca;
use App\Actions\Naskah\KembalikanNaskah;
use App\Actions\Naskah\ParafiNaskah;
use App\Actions\Naskah\TandaTangani;
use App\Actions\Permohonan\DisposisiPermohonan;
use App\Actions\Permohonan\PutusanWd;
use App\Actions\Permohonan\RekomendasiKasubag;
use App\Enums\StatusDisposisiPenerima;
use App\Enums\StatusNaskah;
use App\Enums\StatusPermohonan;
use App\Enums\StatusSuratMasuk;
use App\Models\Disposisi;
use App\Models\DisposisiPenerima;
use App\Models\Jabatan;
use App\Models\Naskah;
use App\Models\Permohonan;
use App\Models\SuratMasuk;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Kotak masuk disposisi untuk dekan, wakil dekan, kasubag, dan pegawai (ramah ponsel).
 * Seluruh aturan ada di Action; komponen hanya memanggilnya dengan pengguna terautentikasi.
 */
#[Layout('layouts.app')]
class KotakMasuk extends Component
{
    public const PERAN = ['dekan', 'wakil-dekan', 'kasubag', 'pegawai'];

    #[Url]
    public string $tab = 'perlu';

    /** Kartu yang sedang terbuka: "surat:{id}" atau "penerima:{id}". */
    #[Locked]
    public ?string $terbuka = null;

    public string $mode = '';

    /** @var list<string> */
    public array $penerima = [];

    /** @var list<string> */
    public array $instruksi = [];

    public string $catatan = '';

    public ?string $batas = null;

    public string $laporan = '';

    public string $catatanNaskah = '';

    public string $catatanPermohonan = '';

    /** @var list<string> */
    public array $jabatanWd = [];

    public function mount(): void
    {
        abort_unless($this->pengguna()->hasAnyRole([...self::PERAN, 'super-admin']), 403);
    }

    public function pilihTab(string $tab): void
    {
        $this->tab = in_array($tab, ['perlu', 'terkirim', 'selesai', 'naskah', 'permohonan'], true) ? $tab : 'perlu';
        $this->tutup();
    }

    public function buka(string $kunci): void
    {
        $this->terbuka = $kunci;
        $this->mode = '';
        $this->resetForm();

        if (str_starts_with($kunci, 'penerima:')) {
            $p = $this->penerimaMilikSaya(substr($kunci, 9));
            app(TandaiDibaca::class)->jalankan($p, $this->pengguna());
        } elseif (str_starts_with($kunci, 'surat:')) {
            abort_unless($this->surat(substr($kunci, 6)) !== null, 403);
        }
    }

    public function tutup(): void
    {
        $this->terbuka = null;
        $this->mode = '';
        $this->resetForm();
    }

    public function mulai(string $mode): void
    {
        abort_unless(in_array($mode, ['disposisi', 'teruskan', 'lapor'], true), 422);
        $this->mode = $mode;
        $this->resetErrorBag();
    }

    public function disposisikan(string $suratId): void
    {
        $surat = $this->surat($suratId);
        abort_unless($surat !== null, 403);

        app(BuatDisposisi::class)->jalankan($surat, $this->pengguna(), $this->penerima, $this->instruksi, $this->catatan ?: null, $this->batasWaktu());
        $this->tutup();
    }

    public function teruskan(string $penerimaId): void
    {
        $p = $this->penerimaMilikSaya($penerimaId);
        $surat = $p->disposisi->suratMasuk;
        abort_unless($surat !== null, 404);

        app(BuatDisposisi::class)->jalankan($surat, $this->pengguna(), $this->penerima, $this->instruksi, $this->catatan ?: null, $this->batasWaktu(), $p);
        $this->tutup();
    }

    public function lapor(string $penerimaId): void
    {
        app(LaporTindakLanjut::class)->jalankan($this->penerimaMilikSaya($penerimaId), $this->pengguna(), $this->laporan);
        $this->tutup();
    }

    public function selesai(string $penerimaId): void
    {
        app(SelesaikanDisposisi::class)->jalankan($this->penerimaMilikSaya($penerimaId), $this->pengguna());
        $this->tutup();
    }

    public function parafi(string $naskahId): void
    {
        app(ParafiNaskah::class)->jalankan(Naskah::findOrFail($naskahId), $this->pengguna(), $this->catatanNaskah ?: null);
        $this->tutup();
    }

    public function disposisikanPermohonan(string $id): void
    {
        app(DisposisiPermohonan::class)->jalankan(Permohonan::findOrFail($id), $this->pengguna(), $this->jabatanWd, $this->catatanPermohonan ?: null);
        $this->tutup();
    }

    public function tolakDekan(string $id): void
    {
        app(DisposisiPermohonan::class)->tolak(Permohonan::findOrFail($id), $this->pengguna(), $this->catatanPermohonan);
        $this->tutup();
    }

    public function putusWd(string $id, string $putusan): void
    {
        app(PutusanWd::class)->jalankan(Permohonan::findOrFail($id), $this->pengguna(), $putusan, $this->catatanPermohonan ?: null);
        $this->tutup();
    }

    public function rekomendasi(string $id): void
    {
        app(RekomendasiKasubag::class)->jalankan(Permohonan::findOrFail($id), $this->pengguna(), $this->catatanPermohonan ?: null);
        $this->tutup();
    }

    public function tolakKasubag(string $id): void
    {
        app(RekomendasiKasubag::class)->tolak(Permohonan::findOrFail($id), $this->pengguna(), $this->catatanPermohonan);
        $this->tutup();
    }

    public function tandatangani(string $naskahId): void
    {
        app(TandaTangani::class)->jalankan(Naskah::findOrFail($naskahId), $this->pengguna());
        $this->tutup();
    }

    public function kembalikanNaskah(string $naskahId): void
    {
        app(KembalikanNaskah::class)->jalankan(Naskah::findOrFail($naskahId), $this->pengguna(), $this->catatanNaskah);
        $this->tutup();
    }

    public function render(): View
    {
        $pengguna = $this->pengguna();

        return view('livewire.pimpinan.kotak-masuk', [
            'surat' => $this->tab === 'perlu' ? $this->suratBelumDidisposisikan() : collect(),
            'naskahDaftar' => $this->tab === 'naskah' ? $this->daftarNaskah() : collect(),
            'permohonanDaftar' => $this->tab === 'permohonan' ? $this->daftarPermohonan() : collect(),
            'wdPilihan' => $this->tab === 'permohonan' ? Jabatan::where('kode', 'like', 'wd-%')->orderBy('urutan')->get() : collect(),
            'penerimaDaftar' => in_array($this->tab, ['terkirim', 'naskah', 'permohonan'], true) ? collect() : $this->daftarPenerima(),
            'terkirim' => $this->tab === 'terkirim' ? $this->daftarTerkirim() : collect(),
            'calonPenerima' => $this->terbuka ? $this->calonPenerima() : collect(),
            'bolehDisposisi' => $pengguna->can('disposisi.buat') && $pengguna->hasAnyRole(['dekan', 'super-admin']),
            'bolehTeruskan' => $pengguna->can('disposisi.teruskan'),
            'instruksiPilihan' => Disposisi::INSTRUKSI,
        ])->title('Disposisi');
    }

    private function pengguna(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    private function batasWaktu(): ?CarbonImmutable
    {
        return filled($this->batas) ? CarbonImmutable::parse($this->batas) : null;
    }

    private function resetForm(): void
    {
        $this->reset('penerima', 'instruksi', 'catatan', 'batas', 'laporan', 'catatanNaskah', 'catatanPermohonan', 'jabatanWd');
        $this->resetErrorBag();
    }

    private function surat(string $id): ?SuratMasuk
    {
        $surat = SuratMasuk::find($id);

        return $surat && Gate::forUser($this->pengguna())->allows('lihatMetadata', $surat) && $this->bolehDisposisiAwal() ? $surat : null;
    }

    private function bolehDisposisiAwal(): bool
    {
        return $this->pengguna()->can('disposisi.buat') && $this->pengguna()->hasAnyRole(['dekan', 'super-admin']);
    }

    private function penerimaMilikSaya(string $id): DisposisiPenerima
    {
        return DisposisiPenerima::with('disposisi.suratMasuk')
            ->where('user_id', $this->pengguna()->getKey())
            ->findOrFail($id);
    }

    /** @return Collection<int, SuratMasuk> */
    private function suratBelumDidisposisikan(): Collection
    {
        if (! $this->bolehDisposisiAwal()) {
            return collect();
        }

        return SuratMasuk::where('status', StatusSuratMasuk::Diterima->value)
            ->orderByRaw("FIELD(derajat_kecepatan, 'sangat_segera', 'segera', 'biasa')")
            ->orderBy('tanggal_terima')
            ->get();
    }

    /** @return Collection<int, DisposisiPenerima> */
    private function daftarPenerima(): Collection
    {
        $status = $this->tab === 'selesai'
            ? [StatusDisposisiPenerima::Selesai->value]
            : [StatusDisposisiPenerima::Diterima->value, StatusDisposisiPenerima::Dibaca->value, StatusDisposisiPenerima::Ditindaklanjuti->value];

        return DisposisiPenerima::with(['disposisi.suratMasuk', 'disposisi.dari'])
            ->where('user_id', $this->pengguna()->getKey())
            ->whereIn('status', $status)
            ->latest()
            ->get();
    }

    /** @return Collection<int, Naskah> naskah yang menunggu paraf giliran pengguna atau tanda tangannya */
    private function daftarNaskah(): Collection
    {
        $pengguna = $this->pengguna();
        $jabatanIds = $pengguna->jabatanAktif()->pluck('id')->all();

        return Naskah::with(['jenis', 'penyusun', 'paraf'])
            ->whereIn('status', [StatusNaskah::Paraf->value, StatusNaskah::MenungguTandaTangan->value])
            ->get()
            ->filter(fn (Naskah $n) => $n->status === StatusNaskah::Paraf
                ? $n->paraf->firstWhere('status', 'menunggu')?->user_id === $pengguna->getKey()
                : in_array($n->penanda_tangan_jabatan_id, $jabatanIds, true))
            ->values();
    }

    /** @return Collection<int, Permohonan> permohonan ormawa yang menunggu putusan pengguna */
    private function daftarPermohonan(): Collection
    {
        $pengguna = $this->pengguna();

        return Permohonan::with(['ormawa', 'jenis', 'persetujuanWd.jabatan'])
            ->whereIn('status', [StatusPermohonan::DisposisiDekan->value, StatusPermohonan::PersetujuanWd->value, StatusPermohonan::RekomendasiKasubag->value])
            ->orderBy('diajukan_pada')->get()
            ->filter(fn (Permohonan $p) => $pengguna->can('disposisiDekan', $p) || $pengguna->can('putusWd', $p) || $pengguna->can('rekomendasiKasubag', $p))
            ->values();
    }

    /** @return Collection<int, Disposisi> */
    private function daftarTerkirim(): Collection
    {
        return Disposisi::with(['suratMasuk', 'penerima.user'])
            ->where('dari_user_id', $this->pengguna()->getKey())
            ->latest()
            ->get();
    }

    /** @return Collection<int, User> */
    private function calonPenerima(): Collection
    {
        return User::where('aktif', true)
            ->whereKeyNot($this->pengguna()->getKey())
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['wakil-dekan', 'kasubag', 'pegawai']))
            ->orderBy('name')
            ->get();
    }
}
