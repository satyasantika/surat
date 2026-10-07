<?php

namespace App\Filament\Pages;

use App\Enums\KlasifikasiKeamanan;
use App\Enums\StatusNaskah;
use App\Models\KlasifikasiArsip;
use App\Models\Naskah;
use App\Models\SuratMasuk;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pencarian arsip: nomor, perihal, asal/tujuan, klasifikasi. Menghormati klasifikasi keamanan: perihal surat
 * tertutup tidak dapat dicari maupun ditampilkan kepada yang tidak berhak (tak ada oracle pencarian).
 */
class Arsip extends Page
{
    private const BATAS = 100;

    protected string $view = 'filament.pages.arsip';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?string $navigationLabel = 'Arsip';

    protected static ?string $title = 'Arsip';

    protected static string|\UnitEnum|null $navigationGroup = 'Register & arsip';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'arsip';

    public string $kata = '';

    public string $jenis = 'semua';

    public string $klasifikasi = '';

    public string $dari = '';

    public string $sampai = '';

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('arsip.lihat');
    }

    /** @return array<string, string> */
    public function pilihanKlasifikasi(): array
    {
        return KlasifikasiArsip::untukPilihan();
    }

    /** @return array{masuk: list<array<string, mixed>>, keluar: list<array<string, mixed>>} */
    public function hasil(): array
    {
        abort_unless(static::canAccess(), 403);
        /** @var User $u */
        $u = auth()->user();
        $kata = trim($this->kata);
        $kode = $this->klasifikasi !== '' ? KlasifikasiArsip::find($this->klasifikasi)?->kode : null;

        return [
            'masuk' => $this->jenis === 'keluar' ? [] : $this->cariMasuk($u, $kata, $kode),
            'keluar' => $this->jenis === 'masuk' ? [] : $this->cariKeluar($u, $kata, $kode),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function cariMasuk(User $u, string $kata, ?string $kode): array
    {
        return SuratMasuk::query()->with('klasifikasiArsip')
            ->when($kata !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('nomor_agenda', 'like', "%{$kata}%")->orWhere('nomor_surat', 'like', "%{$kata}%")->orWhere('asal', 'like', "%{$kata}%")
                // perihal hanya dicari pada surat yang bukan tertutup: perihal rahasia tidak boleh bocor lewat pencarian
                ->orWhere(fn (Builder $p) => $p->whereNotIn('klasifikasi_keamanan', self::tertutup())->where('perihal', 'like', "%{$kata}%"))))
            ->when($kode, fn (Builder $q) => $q->whereHas('klasifikasiArsip', fn (Builder $k) => $k->where('kode', 'like', "{$kode}%")))
            ->when($this->dari !== '', fn (Builder $q) => $q->whereDate('tanggal_terima', '>=', $this->dari))
            ->when($this->sampai !== '', fn (Builder $q) => $q->whereDate('tanggal_terima', '<=', $this->sampai))
            ->orderByDesc('tanggal_terima')->limit(self::BATAS * 3)->get()
            ->filter(fn (SuratMasuk $s) => $u->can('lihatMetadata', $s))->take(self::BATAS)
            ->map(fn (SuratMasuk $s) => [
                'nomor' => $s->nomor_agenda, 'tanggal' => $s->tanggal_terima->format('d M Y'), 'pihak' => $s->asal, 'perihal' => $s->perihalUntuk($u),
                'klasifikasi' => $s->klasifikasiArsip ? "{$s->klasifikasiArsip->kode} {$s->klasifikasiArsip->nama}" : '—', 'keamanan' => $s->klasifikasi_keamanan->label(), 'status' => $s->status->label(),
            ])->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function cariKeluar(User $u, string $kata, ?string $kode): array
    {
        return Naskah::query()->whereNotNull('nomor')
            ->whereIn('status', [StatusNaskah::Ditandatangani->value, StatusNaskah::Terbit->value, StatusNaskah::Dibatalkan->value])
            ->with(['klasifikasiArsip', 'tujuan'])
            ->when($kata !== '', fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('nomor', 'like', "%{$kata}%")->orWhereHas('tujuan', fn (Builder $t) => $t->where('nama', 'like', "%{$kata}%"))
                ->orWhere(fn (Builder $p) => $p->where('klasifikasi_keamanan', KlasifikasiKeamanan::Biasa->value)->where('perihal', 'like', "%{$kata}%"))))
            ->when($kode, fn (Builder $q) => $q->whereHas('klasifikasiArsip', fn (Builder $k) => $k->where('kode', 'like', "{$kode}%")))
            ->when($this->dari !== '', fn (Builder $q) => $q->whereDate('tanggal_naskah', '>=', $this->dari))
            ->when($this->sampai !== '', fn (Builder $q) => $q->whereDate('tanggal_naskah', '<=', $this->sampai))
            ->orderByDesc('tanggal_naskah')->limit(self::BATAS)->get()
            ->map(fn (Naskah $n) => [
                'nomor' => $n->nomor, 'tanggal' => $n->tanggal_naskah?->format('d M Y'), 'pihak' => $n->tujuan->pluck('nama')->implode('; '),
                'perihal' => $n->klasifikasi_keamanan === KlasifikasiKeamanan::Biasa || $u->can('view', $n) ? $n->perihal : '[DIBATASI]',
                'klasifikasi' => $n->klasifikasiArsip ? "{$n->klasifikasiArsip->kode} {$n->klasifikasiArsip->nama}" : '—', 'keamanan' => $n->klasifikasi_keamanan->label(), 'status' => $n->status->label(),
            ])->all();
    }

    /** @return list<string> */
    private static function tertutup(): array
    {
        return array_values(array_map(fn (KlasifikasiKeamanan $k) => $k->value, array_filter(KlasifikasiKeamanan::cases(), fn (KlasifikasiKeamanan $k) => $k->tertutup())));
    }
}
