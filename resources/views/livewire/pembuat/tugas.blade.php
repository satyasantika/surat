<div class="space-y-4">
    <h2 class="text-lg font-semibold">Tugas pembuat surat</h2>
    <p class="text-sm text-slate-600">Buat surat/hasil di luar sistem (cetak, paraf, tanda tangan), unggah ulang <strong>tautan</strong> hasilnya di sini, lalu kirim kepada pengirim.</p>

    @if (session('status'))<p class="rounded bg-green-50 p-3 text-sm text-green-800" role="status">{{ session('status') }}</p>@endif
    @if ($errors->any())
        <ul class="rounded bg-red-50 p-3 text-sm text-red-800" role="alert">
            @foreach ($errors->all() as $galat)<li>{{ $galat }}</li>@endforeach
        </ul>
    @endif

    <h3 class="font-medium">Permohonan ormawa</h3>
    @forelse ($permohonanDaftar as $p)
        @php($kunci = 'permohonan:'.$p->id)
        <article class="rounded-lg bg-white p-4 shadow" wire:key="{{ $kunci }}">
            <button type="button" wire:click="buka('{{ $kunci }}')" class="w-full text-left">
                <p class="text-xs text-slate-500">{{ $p->nomor }} · {{ $p->ormawa->nama }} · {{ $p->jenis->nama }}</p>
                <p class="font-medium">{{ $p->nama_kegiatan }}</p>
                <p class="mt-1 text-xs"><span class="rounded bg-slate-100 px-2 py-0.5">{{ $p->status->label() }}</span>
                    @if ($p->unitPembuat)<span class="text-slate-500">Unit {{ $p->unitPembuat->nama }}</span>@endif</p>
            </button>
            @if ($terbuka === $kunci)
                <div class="mt-3 space-y-3 border-t pt-3 text-sm">
                    <p>{{ $p->perihal }}</p>
                    @can('kirimHasil', $p)
                        @include('livewire.pembuat._form-hasil', ['aksi' => "kirimPermohonan('{$p->id}')"])
                    @else
                        <p class="text-slate-600">Surat izin sedang diproses melalui naskah (paraf/tanda tangan); permohonan selesai otomatis saat surat terbit.</p>
                    @endcan
                    @if (auth()->user()->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')))
                        <a href="{{ \App\Filament\Resources\Permohonans\PermohonanResource::getUrl('view', ['record' => $p]) }}" class="text-blue-800 underline">Buka rincian di panel (buat draf surat izin)</a>
                    @endif
                </div>
            @endif
        </article>
    @empty
        <p class="rounded bg-white p-4 text-center text-sm text-slate-500 shadow">Tidak ada permohonan yang menunggu Anda.</p>
    @endforelse

    <h3 class="font-medium">Surat masuk</h3>
    @forelse ($suratDaftar as $s)
        @php($kunci = 'surat:'.$s->id)
        <article class="rounded-lg bg-white p-4 shadow" wire:key="{{ $kunci }}">
            <button type="button" wire:click="buka('{{ $kunci }}')" class="w-full text-left">
                <p class="text-xs text-slate-500">{{ $s->nomor_agenda }} · {{ $s->tanggal_terima->translatedFormat('d M Y') }}</p>
                <p class="font-medium">{{ $s->perihalUntuk(auth()->user()) }}</p>
                <p class="text-sm text-slate-600">{{ $s->asal }}</p>
                <p class="mt-1 text-xs"><span class="rounded bg-slate-100 px-2 py-0.5">{{ $s->klasifikasi_keamanan->label() }}</span>
                    <span class="text-slate-500">Unit {{ $s->unitPembuat->nama }}</span></p>
            </button>
            @if ($terbuka === $kunci)
                <div class="mt-3 space-y-3 border-t pt-3 text-sm">
                    @can('view', $s)
                        @if ($s->ringkasan)<p>{{ $s->ringkasan }}</p>@endif
                        @foreach ($s->tautan as $t)<x-tautan-berkas :tautan="$t" />@endforeach
                    @endcan
                    @can('kirimHasil', $s)
                        @include('livewire.pembuat._form-hasil', ['aksi' => "kirimSurat('{$s->id}')"])
                    @endcan
                </div>
            @endif
        </article>
    @empty
        <p class="rounded bg-white p-4 text-center text-sm text-slate-500 shadow">Tidak ada surat masuk yang menunggu Anda.</p>
    @endforelse
</div>
