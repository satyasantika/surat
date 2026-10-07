<?php

namespace App\Filament\Pages;

use App\Contracts\LayananRuangan;
use App\Exceptions\LayananRuanganTidakTersedia;
use App\Exceptions\RuanganBentrok;
use App\Models\PemakaianRuanganLokal;
use App\Models\Permohonan;
use App\Models\PermohonanRuangan;
use App\Services\Ruangan\KetersediaanRuangan;
use App\Services\Ruangan\LayananRuanganLokal;
use App\Support\Pengaturan;
use App\Support\SesiRuangan;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Kalender bulanan per ruangan × sesi: pemakaian layanan, permohonan ditahan, dan permohonan dikonfirmasi. */
class PlotRuangan extends Page
{
    protected string $view = 'filament.pages.plot-ruangan';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Plot ruangan';

    protected static ?string $title = 'Plot ruangan';

    protected static string|\UnitEnum|null $navigationGroup = 'Layanan Ormawa';

    protected static ?string $slug = 'plot-ruangan';

    public string $bulan = '';

    public ?string $kode = null;

    public ?string $permohonanId = null;

    public string $tanggalManual = '';

    public string $sesiManual = '';

    public string $keteranganManual = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('ruangan.kelola-jadwal');
    }

    public function mount(): void
    {
        $this->bulan = now()->format('Y-m');
        $this->sesiManual = SesiRuangan::kode()[0] ?? '';
    }

    public function geser(int $arah): void
    {
        $this->bulan = CarbonImmutable::createFromFormat('Y-m-d', $this->bulan.'-01')->addMonths($arah)->format('Y-m');
        $this->permohonanId = null;
    }

    public function pilihRuangan(?string $kode): void
    {
        $this->kode = $kode ?: null;
        $this->permohonanId = null;
    }

    public function pilihPermohonan(string $id): void
    {
        $this->permohonanId = $id;
    }

    /** Pemakaian manual non-ormawa (hanya mode lokal). */
    public function catatManual(): void
    {
        abort_unless(static::canAccess() && $this->modeLokal(), 403);

        $this->validate([
            'kode' => ['required', 'string'],
            'tanggalManual' => ['required', 'date_format:Y-m-d'],
            'sesiManual' => ['required', 'string'],
            'keteranganManual' => ['nullable', 'string', 'max:255'],
        ], [], ['kode' => 'ruangan', 'tanggalManual' => 'tanggal', 'sesiManual' => 'sesi']);

        if (! SesiRuangan::valid($this->sesiManual)) {
            throw ValidationException::withMessages(['sesiManual' => 'Sesi tidak dikenal.']);
        }

        try {
            // Satu definisi "terpakai": juga menolak sesi yang ditahan/dikonfirmasi permohonan.
            if (app(KetersediaanRuangan::class)->terpakai((string) $this->kode, $this->tanggalManual, $this->sesiManual)) {
                $this->addError('tanggalManual', 'Ruangan sudah dipakai atau ditahan pada tanggal dan sesi tersebut.');

                return;
            }

            (new LayananRuanganLokal)->catatPemakaian((string) $this->kode, $this->tanggalManual, $this->sesiManual, null, $this->keteranganManual ?: 'Pemakaian manual');
        } catch (RuanganBentrok $e) {
            $this->addError('tanggalManual', $e->getMessage());

            return;
        }

        $this->reset('tanggalManual', 'keteranganManual');
    }

    public function hapusManual(string $id): void
    {
        abort_unless(static::canAccess() && $this->modeLokal(), 403);

        $pemakaian = PemakaianRuanganLokal::whereNull('permohonan_id')->findOrFail($id);
        $pemakaian->delete();
    }

    public function modeLokal(): bool
    {
        /** @var LayananRuangan $layanan */
        $layanan = app()->make(LayananRuangan::class);

        return $layanan::class === LayananRuanganLokal::class;
    }

    /**
     * @return array{ruangan: list<array{kode: string, nama: string}>, galat: bool, minggu: list<list<?array{tanggal: string, hari: int, entri: list<array<string, mixed>>}>>}
     */
    public function dataKalender(): array
    {
        try {
            $ruangan = app(LayananRuangan::class)->daftar();
        } catch (LayananRuanganTidakTersedia) {
            return ['ruangan' => [], 'galat' => true, 'minggu' => []];
        }

        $ruangan = array_map(fn (array $r) => ['kode' => $r['kode'], 'nama' => $r['nama']], $ruangan);
        $kode = $this->kode ?? ($ruangan[0]['kode'] ?? null);

        if ($kode === null) {
            return ['ruangan' => $ruangan, 'galat' => false, 'minggu' => []];
        }

        $awal = CarbonImmutable::createFromFormat('Y-m-d', $this->bulan.'-01')->startOfDay();
        $akhir = $awal->endOfMonth()->startOfDay();

        $entri = [];

        $baris = PermohonanRuangan::with('permohonan.ormawa')->where('kode_ruangan', $kode)->menguasai()
            ->whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])->get();

        foreach ($baris as $r) {
            $entri[$r->tanggal->toDateString()][] = [
                'sesi' => $r->sesi, 'jenis' => $r->status, 'permohonan_id' => $r->permohonan_id,
                // Nama ormawa/kegiatan hanya untuk yang berhak melihat permohonannya (tooltip pun tidak membocorkannya).
                'label' => Gate::allows('view', $r->permohonan) ? $r->permohonan->ormawa->nama.' — '.$r->permohonan->nama_kegiatan : 'Terisi',
            ];
        }

        try {
            foreach (app(LayananRuangan::class)->jadwal($kode, $awal, $akhir) as $j) {
                // Pemakaian yang berasal dari permohonan dikonfirmasi sudah tampil sebagai baris permohonan.
                $duplikat = collect($entri[$j['tanggal']] ?? [])->contains(fn (array $e) => $e['jenis'] === PermohonanRuangan::DIKONFIRMASI && $e['sesi'] === $j['sesi']);

                if (! $duplikat) {
                    $entri[$j['tanggal']][] = ['sesi' => $j['sesi'], 'jenis' => 'lain', 'permohonan_id' => null, 'label' => $j['keterangan'] ?: 'Pemakaian lain'];
                }
            }
        } catch (LayananRuanganTidakTersedia) {
            return ['ruangan' => $ruangan, 'galat' => true, 'minggu' => []];
        }

        $minggu = [];
        $pekan = array_fill(0, $awal->dayOfWeekIso - 1, null);

        for ($hari = $awal; $hari->lte($akhir); $hari = $hari->addDay()) {
            $pekan[] = ['tanggal' => $hari->toDateString(), 'hari' => $hari->day, 'entri' => $entri[$hari->toDateString()] ?? []];

            if (count($pekan) === 7) {
                $minggu[] = $pekan;
                $pekan = [];
            }
        }

        if ($pekan !== []) {
            $minggu[] = array_pad($pekan, 7, null);
        }

        return ['ruangan' => $ruangan, 'galat' => false, 'minggu' => $minggu];
    }

    /** Rincian permohonan hanya bagi yang berhak melihatnya. */
    public function rincian(): ?Permohonan
    {
        if ($this->permohonanId === null) {
            return null;
        }

        $permohonan = Permohonan::with(['ormawa', 'jenis'])->find($this->permohonanId);

        return $permohonan && Gate::allows('view', $permohonan) ? $permohonan : null;
    }

    /** @return list<PemakaianRuanganLokal> pemakaian manual bulan ini untuk ruangan terpilih */
    public function pemakaianManual(): array
    {
        if (! $this->modeLokal() || ! $this->kode) {
            return [];
        }

        $awal = CarbonImmutable::createFromFormat('Y-m-d', $this->bulan.'-01');

        return PemakaianRuanganLokal::whereNull('permohonan_id')
            ->whereHas('ruangan', fn ($q) => $q->where('kode', $this->kode))
            ->whereBetween('tanggal', [$awal->toDateString(), $awal->endOfMonth()->toDateString()])
            ->orderBy('tanggal')->get()->all();
    }

    /** @return list<string> */
    public function sesiKode(): array
    {
        return SesiRuangan::kode();
    }

    public function minHariInfo(): int
    {
        return (int) Pengaturan::get('min_hari_sebelum_kegiatan');
    }
}
