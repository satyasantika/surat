<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold">Kiriman saya</h2>
        <a href="{{ route('pengirim.kiriman.baru') }}" class="rounded bg-blue-900 px-3 py-2 text-sm text-white">Ajukan surat</a>
    </div>
    <p class="text-sm text-slate-600">Surat Anda diterima petugas fakultas, diberi nomor agenda, lalu diproses. Selama belum diterima, kiriman dapat ditarik kembali.</p>

    @if (session('status'))<p class="rounded bg-green-50 p-3 text-sm text-green-800" role="status">{{ session('status') }}</p>@endif
    @error('umum')<p class="rounded bg-red-50 p-3 text-sm text-red-800" role="alert">{{ $message }}</p>@enderror

    @forelse ($daftar as $s)
        @php($kode = \App\Support\KodeLacak::tampil($s->kode_lacak))
        <article class="space-y-1 rounded-lg bg-white p-4 shadow" wire:key="k-{{ $s->id }}">
            <p class="text-xs text-slate-500">Kepada Dekan FKIP · {{ $s->created_at->translatedFormat('d M Y H:i') }}</p>
            <p class="font-medium">{{ $s->perihal }}</p>
            @if ($s->ringkasan)<p class="text-sm text-slate-700">{{ $s->ringkasan }}</p>@endif
            <p class="text-xs"><span class="rounded bg-slate-100 px-2 py-0.5">{{ $status[$s->id] }}</span>
                @if ($s->nomor_agenda) <span class="text-slate-500">· nomor agenda {{ $s->nomor_agenda }}</span>@endif</p>
            <p class="text-xs text-slate-500">Kode lacak: <a href="{{ route('lacak.tampil', $kode) }}" class="font-mono text-blue-800 underline">{{ $kode }}</a></p>
            @if ($s->masihDiajukan())
                <button type="button" wire:click="tarik('{{ $s->id }}')" wire:confirm="Tarik kiriman ini?" class="text-sm text-red-700 underline">Tarik kiriman</button>
            @endif
        </article>
    @empty
        <p class="rounded bg-white p-6 text-center text-slate-500 shadow">Belum ada kiriman.</p>
    @endforelse
</div>
