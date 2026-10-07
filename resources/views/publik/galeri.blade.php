<x-layouts.publik judul="Galeri">
    <h1 class="mb-4 text-xl font-bold text-blue-900">Galeri</h1>
    @foreach (['Foto' => $foto, 'Video' => $video, 'Instagram' => $instagram] as $judulBagian => $daftar)
        @if ($daftar->isNotEmpty())
            <section class="mb-8" aria-label="{{ $judulBagian }}">
                <h2 class="mb-3 text-lg font-semibold">{{ $judulBagian }}</h2>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">@foreach ($daftar as $g)<x-publik.item-galeri :item="$g" />@endforeach</div>
            </section>
        @endif
    @endforeach
    @if ($foto->isEmpty() && $video->isEmpty() && $instagram->isEmpty())<p class="text-sm text-slate-500">Belum ada galeri.</p>@endif
</x-layouts.publik>
