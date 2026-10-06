<div class="space-y-4">
    <div class="flex gap-2 text-sm" role="tablist">
        @foreach (['perlu' => 'Perlu tindakan', 'naskah' => 'Paraf & tanda tangan', 'terkirim' => 'Terkirim', 'selesai' => 'Selesai'] as $kunci => $nama)
            <button type="button" wire:click="pilihTab('{{ $kunci }}')" role="tab"
                    class="rounded-full px-4 py-2 {{ $tab === $kunci ? 'bg-blue-900 text-white' : 'bg-white shadow' }}">{{ $nama }}</button>
        @endforeach
    </div>

    @if ($errors->any())
        <ul class="rounded bg-red-50 p-3 text-sm text-red-800" role="alert">
            @foreach ($errors->all() as $galat)
                <li>{{ $galat }}</li>
            @endforeach
        </ul>
    @endif

    {{-- Surat yang menunggu disposisi dekan --}}
    @foreach ($surat as $s)
        @php($kunci = 'surat:'.$s->id)
        <article class="rounded-lg bg-white p-4 shadow" wire:key="{{ $kunci }}">
            <button type="button" wire:click="buka('{{ $kunci }}')" class="w-full text-left">
                <p class="text-xs text-slate-500">{{ $s->nomor_agenda }} · {{ $s->tanggal_terima->translatedFormat('d M Y') }}</p>
                <p class="font-medium">{{ $s->perihalUntuk(auth()->user()) }}</p>
                <p class="text-sm text-slate-600">{{ $s->asal }}</p>
                <p class="mt-1 flex gap-2 text-xs">
                    <span class="rounded bg-slate-100 px-2 py-0.5">{{ $s->klasifikasi_keamanan->label() }}</span>
                    <span class="rounded bg-amber-100 px-2 py-0.5">{{ $s->derajat_kecepatan->label() }}</span>
                </p>
            </button>
            @if ($terbuka === $kunci)
                <div class="mt-3 space-y-3 border-t pt-3 text-sm">
                    @can('view', $s)
                        @if ($s->ringkasan)<p>{{ $s->ringkasan }}</p>@endif
                        @foreach ($s->tautan as $t)<x-tautan-berkas :tautan="$t" />@endforeach
                    @endcan
                    @if ($mode === 'disposisi')
                        @include('livewire.pimpinan._form-disposisi', ['aksi' => "disposisikan('{$s->id}')"])
                    @else
                        <button type="button" wire:click="mulai('disposisi')" class="rounded bg-blue-900 px-4 py-2 text-white">Disposisikan</button>
                    @endif
                </div>
            @endif
        </article>
    @endforeach

    {{-- Disposisi yang saya terima --}}
    @foreach ($penerimaDaftar as $p)
        @php($kunci = 'penerima:'.$p->id)
        @php($d = $p->disposisi)
        <article class="rounded-lg bg-white p-4 shadow" wire:key="{{ $kunci }}">
            <button type="button" wire:click="buka('{{ $kunci }}')" class="w-full text-left">
                <p class="text-xs text-slate-500">{{ $d->suratMasuk?->nomor_agenda }} · dari {{ $d->dari->name }}</p>
                <p class="font-medium">{{ $d->suratMasuk?->perihalUntuk(auth()->user()) }}</p>
                <p class="mt-1 flex flex-wrap gap-2 text-xs">
                    <span class="rounded bg-slate-100 px-2 py-0.5">{{ $p->status->label() }}</span>
                    <span class="rounded bg-amber-100 px-2 py-0.5">Batas {{ $d->batas_waktu->translatedFormat('d M Y H:i') }}</span>
                    @if ($p->sudahLewatBatas())<span class="rounded bg-red-100 px-2 py-0.5 text-red-800">Terlambat</span>@endif
                </p>
            </button>
            @if ($terbuka === $kunci)
                <div class="mt-3 space-y-3 border-t pt-3 text-sm">
                    <p><strong>Instruksi:</strong> {{ collect($d->instruksi)->map(fn ($i) => $instruksiPilihan[$i] ?? $i)->implode(', ') ?: '—' }}</p>
                    @if ($d->catatan)<p><strong>Catatan:</strong> {{ $d->catatan }}</p>@endif
                    @if ($d->suratMasuk)
                        @can('view', $d->suratMasuk)
                            @if ($d->suratMasuk->ringkasan)<p>{{ $d->suratMasuk->ringkasan }}</p>@endif
                            @foreach ($d->suratMasuk->tautan as $t)<x-tautan-berkas :tautan="$t" />@endforeach
                        @endcan
                    @endif
                    @if ($p->laporan_tindak_lanjut)<p><strong>Laporan:</strong> {{ $p->laporan_tindak_lanjut }}</p>@endif

                    @if ($p->status !== \App\Enums\StatusDisposisiPenerima::Selesai)
                        @if ($mode === 'lapor')
                            <textarea wire:model="laporan" rows="3" class="w-full rounded border px-3 py-2" placeholder="Laporan tindak lanjut"></textarea>
                            <button type="button" wire:click="lapor('{{ $p->id }}')" class="rounded bg-blue-900 px-4 py-2 text-white">Kirim laporan</button>
                        @elseif ($mode === 'teruskan')
                            @include('livewire.pimpinan._form-disposisi', ['aksi' => "teruskan('{$p->id}')"])
                        @else
                            <div class="flex flex-wrap gap-2">
                                <button type="button" wire:click="mulai('lapor')" class="rounded bg-blue-900 px-4 py-2 text-white">Lapor tindak lanjut</button>
                                @if ($bolehTeruskan)
                                    <button type="button" wire:click="mulai('teruskan')" class="rounded border border-blue-900 px-4 py-2 text-blue-900">Teruskan</button>
                                @endif
                                @if ($p->status === \App\Enums\StatusDisposisiPenerima::Ditindaklanjuti)
                                    <button type="button" wire:click="selesai('{{ $p->id }}')" class="rounded bg-green-700 px-4 py-2 text-white">Selesaikan</button>
                                @endif
                            </div>
                        @endif
                    @endif
                </div>
            @endif
        </article>
    @endforeach

    {{-- Naskah menunggu paraf / tanda tangan --}}
    @foreach ($naskahDaftar as $n)
        @php($kunci = 'naskah:'.$n->id)
        <article class="rounded-lg bg-white p-4 shadow" wire:key="{{ $kunci }}">
            <button type="button" wire:click="buka('{{ $kunci }}')" class="w-full text-left">
                <p class="text-xs text-slate-500">{{ $n->jenis->nama }} · dari {{ $n->penyusun->name }}</p>
                <p class="font-medium">{{ $n->perihal }}</p>
                <p class="mt-1 text-xs"><span class="rounded bg-slate-100 px-2 py-0.5">{{ $n->status->label() }}</span></p>
            </button>
            @if ($terbuka === $kunci)
                <div class="mt-3 space-y-3 border-t pt-3 text-sm">
                    <a href="{{ route('naskah.pratinjau', $n) }}" target="_blank" rel="noopener noreferrer" class="text-blue-800 underline">Pratinjau naskah</a>
                    <textarea wire:model="catatanNaskah" rows="2" class="w-full rounded border px-3 py-2" placeholder="Catatan (wajib bila dikembalikan)"></textarea>
                    <div class="flex flex-wrap gap-2">
                        @if ($n->status === \App\Enums\StatusNaskah::Paraf)
                            <button type="button" wire:click="parafi('{{ $n->id }}')" class="rounded bg-blue-900 px-4 py-2 text-white">Paraf</button>
                        @endif
                        <button type="button" wire:click="kembalikanNaskah('{{ $n->id }}')" class="rounded border border-red-700 px-4 py-2 text-red-700">Kembalikan</button>
                    </div>
                </div>
            @endif
        </article>
    @endforeach

    {{-- Terkirim --}}
    @foreach ($terkirim as $d)
        <article class="rounded-lg bg-white p-4 shadow" wire:key="terkirim:{{ $d->id }}">
            <p class="text-xs text-slate-500">{{ $d->suratMasuk?->nomor_agenda }} · {{ $d->created_at->translatedFormat('d M Y H:i') }}</p>
            <p class="font-medium">{{ $d->suratMasuk?->perihalUntuk(auth()->user()) }}</p>
            <ul class="mt-2 space-y-1 text-sm">
                @foreach ($d->penerima as $p)
                    <li>{{ $p->user->name }} — <span class="text-slate-600">{{ $p->status->label() }}</span>
                        @if ($p->sudahLewatBatas()) <span class="text-red-700">(terlambat)</span>@endif</li>
                @endforeach
            </ul>
        </article>
    @endforeach

    @if ($surat->isEmpty() && $penerimaDaftar->isEmpty() && $terkirim->isEmpty() && $naskahDaftar->isEmpty())
        <p class="rounded bg-white p-6 text-center text-slate-500 shadow">Tidak ada item.</p>
    @endif
</div>
