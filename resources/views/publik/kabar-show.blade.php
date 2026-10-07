<x-layouts.publik :judul="$kabar->judul" :deskripsi="$kabar->subjudul">
    <article class="mx-auto max-w-3xl">
        <a href="{{ route('kabar') }}" class="text-sm text-blue-800 underline">&larr; Semua kabar</a>
        <h1 class="mt-2 text-2xl font-bold text-blue-900">{{ $kabar->judul }}</h1>
        @if ($kabar->subjudul)<p class="mt-1 text-slate-600">{{ $kabar->subjudul }}</p>@endif
        <p class="mt-2 text-xs text-slate-500">
            @if ($kabar->ormawa)<a href="{{ route('organisasi.tampil', $kabar->ormawa->slug) }}" class="underline">{{ $kabar->ormawa->nama }}</a>@else Fakultas @endif
            · {{ $kabar->terbit_pada?->locale('id')->translatedFormat('d F Y') }}
        </p>
        @if ($kabar->sampulUrl())
            <img src="{{ $kabar->sampulUrl() }}" alt="" referrerpolicy="no-referrer" class="mt-4 w-full rounded-lg">
        @endif
        <div class="mt-4 space-y-3 leading-relaxed [&_a]:text-blue-800 [&_a]:underline [&_h2]:mt-4 [&_h2]:text-lg [&_h2]:font-semibold [&_ol]:list-decimal [&_ol]:pl-5 [&_ul]:list-disc [&_ul]:pl-5">
            <x-isi-aman :html="$kabar->isi" />
        </div>
        @if ($kabar->tag)
            <p class="mt-4 flex flex-wrap gap-2 text-xs">@foreach ($kabar->tag as $t)<span class="rounded bg-slate-200 px-2 py-0.5">{{ $t }}</span>@endforeach</p>
        @endif
    </article>
</x-layouts.publik>
