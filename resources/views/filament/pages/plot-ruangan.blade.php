@php($data = $this->dataKalender())
<x-filament-panels::page>
    @if ($data['galat'])
        <div class="rounded-lg bg-danger-50 p-4 text-sm text-danger-700" role="alert">Layanan ruangan sedang tidak tersedia; kalender tidak dapat ditampilkan.</div>
    @else
        <div class="flex flex-wrap items-center gap-3">
            <select wire:model.live="kode" class="rounded border px-3 py-2 text-sm" aria-label="Ruangan">
                @foreach ($data['ruangan'] as $r)
                    <option value="{{ $r['kode'] }}">{{ $r['nama'] }}</option>
                @endforeach
            </select>
            <div class="flex items-center gap-2 text-sm">
                <button type="button" wire:click="geser(-1)" class="rounded border px-3 py-1" aria-label="Bulan sebelumnya">&larr;</button>
                <strong>{{ \Carbon\CarbonImmutable::createFromFormat('Y-m-d', $bulan.'-01')->locale('id')->translatedFormat('F Y') }}</strong>
                <button type="button" wire:click="geser(1)" class="rounded border px-3 py-1" aria-label="Bulan berikutnya">&rarr;</button>
            </div>
            <p class="flex gap-3 text-xs">
                <span class="rounded bg-amber-100 px-2 py-0.5 text-amber-900">Ditahan</span>
                <span class="rounded bg-green-100 px-2 py-0.5 text-green-900">Dikonfirmasi</span>
                <span class="rounded bg-slate-200 px-2 py-0.5 text-slate-800">Pemakaian lain</span>
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full table-fixed border-collapse text-xs">
                <thead><tr>@foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $h)<th class="border p-1">{{ $h }}</th>@endforeach</tr></thead>
                <tbody>
                @foreach ($data['minggu'] as $pekan)
                    <tr>
                        @foreach ($pekan as $hari)
                            <td class="h-24 border p-1 align-top">
                                @if ($hari)
                                    <div class="font-semibold">{{ $hari['hari'] }}</div>
                                    @foreach ($hari['entri'] as $e)
                                        @php($warna = ['ditahan' => 'bg-amber-100 text-amber-900', 'dikonfirmasi' => 'bg-green-100 text-green-900'][$e['jenis']] ?? 'bg-slate-200 text-slate-800')
                                        @if ($e['permohonan_id'])
                                            <button type="button" wire:click="pilihPermohonan('{{ $e['permohonan_id'] }}')" class="mb-0.5 block w-full rounded px-1 text-left {{ $warna }}" title="{{ $e['label'] }}">{{ $e['sesi'] }}</button>
                                        @else
                                            <span class="mb-0.5 block rounded px-1 {{ $warna }}" title="{{ $e['label'] }}">{{ $e['sesi'] }}</span>
                                        @endif
                                    @endforeach
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>

        @if ($permohonanId)
            @php($rincian = $this->rincian())
            <section class="rounded-lg border p-4 text-sm" aria-live="polite">
                @if ($rincian)
                    <p class="font-semibold">{{ $rincian->nomor }} — {{ $rincian->nama_kegiatan }}</p>
                    <p>{{ $rincian->ormawa->nama }} · {{ $rincian->status->label() }} · {{ $rincian->tanggal_mulai->translatedFormat('d M Y') }}</p>
                    <a class="text-primary-600 underline" href="{{ \App\Filament\Resources\Permohonans\PermohonanResource::getUrl('view', ['record' => $rincian]) }}">Buka permohonan</a>
                @else
                    <p>Sesi ini terisi. Rincian tidak tersedia untuk akun Anda.</p>
                @endif
            </section>
        @endif

        @if ($this->modeLokal())
            <section class="space-y-3 rounded-lg border p-4 text-sm">
                <h3 class="font-semibold">Catat pemakaian manual (non-ormawa)</h3>
                @error('tanggalManual')<p class="text-danger-600" role="alert">{{ $message }}</p>@enderror
                @error('sesiManual')<p class="text-danger-600" role="alert">{{ $message }}</p>@enderror
                <div class="grid gap-2 sm:grid-cols-4">
                    <input type="date" wire:model="tanggalManual" class="rounded border px-2 py-1">
                    <select wire:model="sesiManual" class="rounded border px-2 py-1">@foreach ($this->sesiKode() as $s)<option value="{{ $s }}">{{ $s }}</option>@endforeach</select>
                    <input wire:model="keteranganManual" placeholder="Keterangan" class="rounded border px-2 py-1">
                    <button type="button" wire:click="catatManual" class="rounded bg-primary-600 px-3 py-1 text-white">Catat</button>
                </div>
                <ul class="divide-y">
                    @foreach ($this->pemakaianManual() as $p)
                        <li class="flex items-center justify-between py-1" wire:key="m-{{ $p->id }}">
                            <span>{{ $p->tanggal->translatedFormat('d M Y') }} · {{ $p->sesi }} · {{ $p->keterangan }}</span>
                            <button type="button" wire:click="hapusManual('{{ $p->id }}')" wire:confirm="Hapus pemakaian ini?" class="text-danger-600 underline">Hapus</button>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endif
</x-filament-panels::page>
