<x-layouts.publik judul="Organisasi">
    <h1 class="mb-4 text-xl font-bold text-blue-900">Organisasi mahasiswa</h1>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($daftar as $o)
            <a href="{{ route('organisasi.tampil', $o->slug) }}" class="flex items-center gap-3 rounded-lg bg-white p-4 shadow hover:shadow-md">
                @if ($o->logoUrl())<img src="{{ $o->logoUrl() }}" alt="" loading="lazy" referrerpolicy="no-referrer" class="size-12 rounded object-cover">@endif
                <span><span class="block font-medium">{{ $o->nama }}</span><span class="text-xs text-slate-500">{{ \App\Models\Ormawa::TINGKAT[$o->tingkat] ?? '' }}</span></span>
            </a>
        @empty
            <p class="text-sm text-slate-500">Belum ada organisasi.</p>
        @endforelse
    </div>
</x-layouts.publik>
