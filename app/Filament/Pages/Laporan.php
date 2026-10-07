<?php

namespace App\Filament\Pages;

use App\Jobs\EksporLaporan;
use App\Services\Laporan\DaftarLaporan;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

/**
 * @property Schema $form
 */
class Laporan extends Page
{
    protected string $view = 'filament.pages.laporan';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Laporan';

    protected static ?string $title = 'Laporan';

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?string $slug = 'laporan';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public bool $ditampilkan = false;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('laporan.lihat');
    }

    public function mount(): void
    {
        $this->form->fill(['jenis' => array_key_first(DaftarLaporan::semua()), 'dari' => now()->startOfYear()->toDateString(), 'sampai' => now()->toDateString()]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->columns(3)->components([
            Select::make('jenis')->label('Laporan')->options(fn () => collect(DaftarLaporan::semua())->map(fn ($l) => $l->judul())->all())->required()->native(false),
            DatePicker::make('dari')->label('Dari tanggal')->required()->maxDate(now()),
            DatePicker::make('sampai')->label('Sampai tanggal')->required()->afterOrEqual('dari'),
        ]);
    }

    public function tampilkan(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->form->getState();
        $this->ditampilkan = true;
    }

    /** @return array{judul: string, deskripsi: string, tabel: list<array{judul: string, kolom: list<string>, baris: list<list<mixed>>, terpotong: bool}>}|null */
    public function hasil(): ?array
    {
        if (! $this->ditampilkan) {
            return null;
        }

        [$laporan, $dari, $sampai] = $this->parameter();

        return [
            'judul' => $laporan->judul(), 'deskripsi' => $laporan->deskripsi(),
            'tabel' => array_map(fn ($t) => [...$t, 'terpotong' => count($t['baris']) > 300, 'baris' => array_slice($t['baris'], 0, 300)], $laporan->susun($dari, $sampai)),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ekspor')->label('Ekspor XLSX')->icon(Heroicon::OutlinedArrowDownTray)->action(function () {
                abort_unless(static::canAccess(), 403);
                [$laporan, $dari, $sampai] = $this->parameter();

                EksporLaporan::dispatch($laporan->kode(), $dari->toDateString(), $sampai->toDateString(), (string) auth()->id());
                Notification::make()->title('Ekspor diproses')->body('Tautan unduh akan muncul di notifikasi saat laporan siap.')->success()->send();
            }),
        ];
    }

    /** @return array{0: \App\Services\Laporan\Laporan, 1: CarbonImmutable, 2: CarbonImmutable} */
    private function parameter(): array
    {
        $data = $this->form->getState();
        $laporan = DaftarLaporan::cari((string) ($data['jenis'] ?? ''));

        if ($laporan === null) {
            throw ValidationException::withMessages(['data.jenis' => 'Laporan tidak dikenal.']);
        }

        $dari = CarbonImmutable::parse($data['dari'])->startOfDay();
        $sampai = CarbonImmutable::parse($data['sampai'])->endOfDay();

        if ($sampai->lt($dari) || $dari->diffInDays($sampai) > 1100) {
            throw ValidationException::withMessages(['data.sampai' => 'Rentang periode tidak sah (maksimum tiga tahun).']);
        }

        return [$laporan, $dari, $sampai];
    }
}
