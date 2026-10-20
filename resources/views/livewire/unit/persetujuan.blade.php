<div class="space-y-4">
    <a href="{{ route('unit') }}" class="text-sm text-blue-800 underline">&larr; Kembali</a>
    <h2 class="text-lg font-semibold">Persetujuan permohonan unit</h2>

    @if (session('status'))<p class="rounded bg-green-50 p-3 text-sm text-green-800" role="status">{{ session('status') }}</p>@endif

    @forelse ($daftar as $p)
        <article class="space-y-2 rounded-lg bg-white p-4 shadow" wire:key="p-{{ $p->id }}">
            <p class="text-xs text-slate-500">{{ $p->nomor }} · {{ $p->jenis->nama }} · {{ $p->unitKerja->nama }}</p>
            <p class="font-medium">{{ $p->nama_kegiatan }}</p>
            <p class="text-sm text-slate-600">{{ $p->tanggal_mulai->translatedFormat('d M Y') }} – {{ $p->tanggal_selesai->translatedFormat('d M Y') }} · diajukan {{ $p->pengaju->name }}</p>
            <p class="text-sm">{{ $p->perihal }}</p>
            <p class="text-sm text-slate-700">{{ $p->deskripsi }}</p>
            <p class="text-sm text-slate-600">Ketua pelaksana: {{ $p->penanggung_jawab['ketua']['nama'] ?? '—' }}</p>

            <label class="block text-sm">Catatan <span class="text-slate-500">(wajib untuk mengembalikan atau menolak)</span>
                <textarea wire:model="catatan.{{ $p->id }}" rows="2" class="mt-1 w-full rounded border px-3 py-2"></textarea>
            </label>
            @error('catatan.'.$p->id)<p class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror

            <div class="flex flex-wrap gap-2 text-sm">
                <button type="button" wire:click="setujui('{{ $p->id }}')" wire:confirm="Setujui dan teruskan ke fakultas?" class="rounded bg-green-700 px-3 py-2 text-white">Setujui</button>
                <button type="button" wire:click="kembalikan('{{ $p->id }}')" class="rounded bg-amber-500 px-3 py-2 text-blue-950">Kembalikan</button>
                <button type="button" wire:click="tolak('{{ $p->id }}')" wire:confirm="Tolak permohonan ini?" class="rounded bg-red-700 px-3 py-2 text-white">Tolak</button>
            </div>
        </article>
    @empty
        <p class="rounded bg-white p-6 text-center text-slate-500 shadow">Tidak ada permohonan yang menunggu persetujuan.</p>
    @endforelse
</div>
