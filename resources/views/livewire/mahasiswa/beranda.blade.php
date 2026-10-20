<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold">Usulan saya</h2>
        <a href="{{ route('mahasiswa.usulan.baru') }}" class="rounded bg-blue-900 px-3 py-2 text-sm text-white">Ajukan usulan</a>
    </div>
    <p class="text-sm text-slate-600">Usulan Anda diterima ormawa atau program studi tujuan, yang memilah dan meneruskannya ke fakultas bila layak.</p>

    @if (session('status'))<p class="rounded bg-green-50 p-3 text-sm text-green-800" role="status">{{ session('status') }}</p>@endif
    @error('umum')<p class="rounded bg-red-50 p-3 text-sm text-red-800" role="alert">{{ $message }}</p>@enderror

    @forelse ($daftar as $u)
        <article class="space-y-1 rounded-lg bg-white p-4 shadow" wire:key="u-{{ $u->id }}">
            <p class="text-xs text-slate-500">{{ $u->namaTujuan() }} · {{ $u->created_at->translatedFormat('d M Y') }}</p>
            <p class="font-medium">{{ $u->perihal }}</p>
            <p class="text-sm text-slate-700">{{ $u->uraian }}</p>
            <p class="text-xs"><span class="rounded bg-slate-100 px-2 py-0.5">{{ $u->status->label() }}</span>
                @if ($u->permohonan) <span class="text-slate-500">· permohonan {{ $u->permohonan->nomor }}: {{ $u->permohonan->status->labelPublik() }}</span>@endif</p>
            @if ($u->catatan && in_array($u->status->value, ['dikembalikan', 'ditolak'], true))<p class="text-sm text-slate-700">Catatan perantara: {{ $u->catatan }}</p>@endif
            @if ($u->status->terbuka())
                <button type="button" wire:click="batalkan('{{ $u->id }}')" wire:confirm="Batalkan usulan ini?" class="text-sm text-red-700 underline">Batalkan</button>
            @endif
        </article>
    @empty
        <p class="rounded bg-white p-6 text-center text-slate-500 shadow">Belum ada usulan.</p>
    @endforelse
</div>
