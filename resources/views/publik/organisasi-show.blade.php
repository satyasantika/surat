<x-layouts.publik :judul="$ormawa->nama">
    <a href="{{ route('organisasi') }}" class="text-sm text-blue-800 underline">&larr; Semua organisasi</a>
    <header class="mt-2 flex items-center gap-4">
        @if ($ormawa->logoUrl())<img src="{{ $ormawa->logoUrl() }}" alt="Logo {{ $ormawa->nama }}" referrerpolicy="no-referrer" class="size-20 rounded object-cover">@endif
        <div>
            <h1 class="text-2xl font-bold text-blue-900">{{ $ormawa->nama }}</h1>
            <p class="text-sm text-slate-600">{{ \App\Models\Ormawa::TINGKAT[$ormawa->tingkat] ?? '' }}@if ($ormawa->akun_media) · Instagram {{ '@'.ltrim($ormawa->akun_media, '@') }}@endif</p>
        </div>
    </header>

    @if ($ormawa->visi)<section class="mt-6"><h2 class="font-semibold">Visi</h2><p class="mt-1 whitespace-pre-line text-slate-700">{{ $ormawa->visi }}</p></section>@endif
    @if ($ormawa->misi)<section class="mt-4"><h2 class="font-semibold">Misi</h2><p class="mt-1 whitespace-pre-line text-slate-700">{{ $ormawa->misi }}</p></section>@endif

    <section class="mt-6" aria-label="Pengurus">
        <h2 class="font-semibold">Pengurus</h2>
        <ul class="mt-2 divide-y rounded-lg bg-white shadow">
            @forelse ($pengurus as $p)
                <li class="flex justify-between gap-4 px-4 py-2 text-sm"><span>{{ $p->nama }}</span><span class="text-slate-500">{{ $p->jabatan_teks ?: \App\Models\PengurusOrmawa::JABATAN[$p->jabatan] }}</span></li>
            @empty
                <li class="px-4 py-2 text-sm text-slate-500">Belum ada pengurus yang ditampilkan.</li>
            @endforelse
        </ul>
    </section>
</x-layouts.publik>
