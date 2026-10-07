@props(['item'])
<figure class="overflow-hidden rounded-lg bg-white shadow">
    @if ($item->tipe === 'foto' && $item->gambarUrl())
        <img src="{{ $item->gambarUrl() }}" alt="{{ $item->judul }}" loading="lazy" referrerpolicy="no-referrer" class="h-48 w-full object-cover">
    @elseif ($item->tipe === 'video' && $item->embedUrl())
        <iframe src="{{ $item->embedUrl() }}" title="{{ $item->judul }}" loading="lazy" referrerpolicy="no-referrer"
                sandbox="allow-scripts allow-same-origin allow-presentation" allowfullscreen class="aspect-video w-full"></iframe>
    @else
        <a href="{{ $item->url }}" target="_blank" rel="noopener noreferrer nofollow" class="block p-4 text-blue-800 underline">Buka di {{ \App\Models\Galeri::TIPE[$item->tipe] ?? 'tautan' }}</a>
    @endif
    <figcaption class="p-3 text-sm">{{ $item->judul }}</figcaption>
</figure>
