<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold">Kiriman saya</h2>
        <a href="{{ route('pengirim.kiriman.baru') }}" class="rounded bg-blue-900 px-3 py-2 text-sm text-white">Ajukan surat</a>
    </div>
    <p class="text-sm text-slate-600">Satu baris untuk tiap tujuan. Surat ke fakultas diterima petugas, diberi nomor agenda, lalu diproses; selama belum diterima, kiriman dapat ditarik kembali. Kiriman ke ormawa atau program studi/laboratorium dipilah perantara tersebut; selama masih menunggu, kiriman dapat dibatalkan.</p>

    @if (session('status'))<p class="rounded bg-green-50 p-3 text-sm text-green-800" role="status">{{ session('status') }}</p>@endif
    @error('umum')<p class="rounded bg-red-50 p-3 text-sm text-red-800" role="alert">{{ $message }}</p>@enderror

    @forelse ($daftar as $k)
        <article class="space-y-1 rounded-lg bg-white p-4 shadow" wire:key="{{ $k['kunci'] }}">
            <p class="text-xs text-slate-500">Kepada {{ $k['tujuan'] }} · {{ $k['waktu']->translatedFormat('d M Y H:i') }}</p>
            <p class="font-medium">{{ $k['perihal'] }}</p>
            @if ($k['uraian'])<p class="text-sm text-slate-700">{{ $k['uraian'] }}</p>@endif
            <p class="text-xs"><span class="rounded bg-slate-100 px-2 py-0.5">{{ $k['status'] }}</span>
                @if ($k['keterangan']) <span class="text-slate-500">· {{ $k['keterangan'] }}</span>@endif</p>
            @if ($k['catatan'])<p class="text-sm text-slate-700">Catatan perantara: {{ $k['catatan'] }}</p>@endif
            <p class="text-xs text-slate-500">Kode lacak: <a href="{{ route('lacak.tampil', $k['kode']) }}" class="font-mono text-blue-800 underline">{{ $k['kode'] }}</a></p>
            @if ($k['aksi'] === 'tarik')
                <button type="button" wire:click="tarik('{{ $k['id'] }}')" wire:confirm="Tarik kiriman ini?" class="text-sm text-red-700 underline">Tarik kiriman</button>
            @elseif ($k['aksi'] === 'batalkan')
                <button type="button" wire:click="batalkan('{{ $k['id'] }}')" wire:confirm="Batalkan kiriman ini?" class="text-sm text-red-700 underline">Batalkan</button>
            @endif
        </article>
    @empty
        <p class="rounded bg-white p-6 text-center text-slate-500 shadow">Belum ada kiriman.</p>
    @endforelse
</div>
