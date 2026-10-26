<div class="space-y-4">
    <a href="{{ $pengaju['kembali'] }}" class="text-sm text-blue-800 underline">&larr; Kembali</a>
    <h2 class="text-lg font-semibold">Usulan mahasiswa — {{ $pengaju['nama'] }}</h2>
    <p class="text-sm text-slate-600">Pilih satu atau beberapa usulan lalu jadikan dasar satu permohonan ke fakultas, atau kembalikan/tolak dengan catatan. Kiriman dari pihak luar (mis. undangan) juga dapat diarsipkan tanpa tindak lanjut.</p>

    @if (session('status'))<p class="rounded bg-green-50 p-3 text-sm text-green-800" role="status">{{ session('status') }}</p>@endif
    @error('dipilih')<p class="rounded bg-red-50 p-3 text-sm text-red-800" role="alert">{{ $message }}</p>@enderror

    @forelse ($daftar as $u)
        <article class="space-y-2 rounded-lg bg-white p-4 shadow" wire:key="u-{{ $u->id }}">
            <label class="flex items-start gap-3">
                <input type="checkbox" wire:model="dipilih" value="{{ $u->id }}" class="mt-1">
                <span>
                    <span class="block text-xs text-slate-500">
                        @if ($u->dariPihakLuar())<span class="rounded bg-amber-100 px-1.5 py-0.5 font-medium text-amber-900">dari pihak luar</span>@endif
                        {{ $u->mahasiswa->name }}@if ($u->dariPihakLuar() && $u->instansi) ({{ $u->instansi }})@endif · {{ $u->created_at->translatedFormat('d M Y') }}
                    </span>
                    <span class="block font-medium">{{ $u->perihal }}</span>
                    <span class="block text-sm text-slate-700">{{ $u->uraian }}</span>
                </span>
            </label>
            @foreach ($u->tautan as $t)<p class="text-sm"><x-tautan-berkas :tautan="$t" /></p>@endforeach

            <label class="block text-sm">Catatan <span class="text-slate-500">(wajib untuk mengembalikan atau menolak)</span>
                <textarea wire:model="catatan.{{ $u->id }}" rows="2" class="mt-1 w-full rounded border px-3 py-2"></textarea>
            </label>
            @error('catatan.'.$u->id)<p class="text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
            <div class="flex gap-2 text-sm">
                <button type="button" wire:click="kembalikan('{{ $u->id }}')" class="rounded bg-amber-500 px-3 py-2 text-blue-950">Kembalikan</button>
                <button type="button" wire:click="tolak('{{ $u->id }}')" wire:confirm="Tolak usulan ini?" class="rounded bg-red-700 px-3 py-2 text-white">Tolak</button>
                @if ($u->dariPihakLuar())
                    <button type="button" wire:click="arsipkan('{{ $u->id }}')" wire:confirm="Arsipkan kiriman ini tanpa tindak lanjut?" class="rounded bg-slate-600 px-3 py-2 text-white">Arsipkan</button>
                @endif
            </div>
        </article>
    @empty
        <p class="rounded bg-white p-6 text-center text-slate-500 shadow">Tidak ada usulan yang menunggu.</p>
    @endforelse

    @if ($daftar->isNotEmpty())
        <button type="button" wire:click="jadikanDasar" class="rounded bg-blue-900 px-4 py-2 text-white">Jadikan dasar permohonan</button>
    @endif
</div>
