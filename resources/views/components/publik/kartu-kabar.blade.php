@props(['kabar'])
<article class="overflow-hidden rounded-lg bg-white shadow">
    @if ($kabar->sampulUrl())
        <img src="{{ $kabar->sampulUrl() }}" alt="" loading="lazy" referrerpolicy="no-referrer" class="h-40 w-full object-cover">
    @endif
    <div class="p-4">
        <h3 class="font-semibold"><a href="{{ route('kabar.tampil', $kabar->slug) }}" class="hover:underline">{{ $kabar->judul }}</a></h3>
        <p class="mt-1 text-xs text-slate-500">{{ $kabar->ormawa?->nama ?? 'Fakultas' }} · {{ $kabar->terbit_pada?->locale('id')->translatedFormat('d F Y') }}</p>
        @if ($kabar->subjudul)<p class="mt-2 text-sm text-slate-600">{{ $kabar->subjudul }}</p>@endif
    </div>
</article>
