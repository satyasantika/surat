<x-layouts.publik judul="Kabar">
    <h1 class="mb-4 text-xl font-bold text-blue-900">Kabar</h1>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($daftar as $k)<x-publik.kartu-kabar :kabar="$k" />@empty<p class="text-sm text-slate-500">Belum ada kabar.</p>@endforelse
    </div>
    <div class="mt-6">{{ $daftar->links() }}</div>
</x-layouts.publik>
