<x-filament-panels::page>
    <form wire:submit="tampilkan" class="space-y-4">
        {{ $this->form }}
        <x-filament::button type="submit">Tampilkan</x-filament::button>
    </form>

    @if ($hasil = $this->hasil())
        <section class="space-y-6" aria-label="Hasil laporan">
            <div>
                <h2 class="text-lg font-semibold">{{ $hasil['judul'] }}</h2>
                <p class="text-sm text-gray-500">{{ $hasil['deskripsi'] }}</p>
            </div>
            @foreach ($hasil['tabel'] as $tabel)
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <p class="px-3 py-2 font-medium">{{ $tabel['judul'] }} <span class="text-sm font-normal text-gray-500">({{ count($tabel['baris']) }} baris{{ $tabel['terpotong'] ? ', 300 pertama — ekspor untuk lengkapnya' : '' }})</span></p>
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left dark:bg-white/5"><tr>@foreach ($tabel['kolom'] as $k)<th class="px-3 py-2">{{ $k }}</th>@endforeach</tr></thead>
                        <tbody>
                        @forelse ($tabel['baris'] as $baris)
                            <tr class="border-t border-gray-100 dark:border-white/10">@foreach ($baris as $sel)<td class="px-3 py-1.5">{{ $sel }}</td>@endforeach</tr>
                        @empty
                            <tr><td class="px-3 py-3 text-gray-500" colspan="{{ count($tabel['kolom']) }}">Tidak ada data pada periode ini.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            @endforeach
        </section>
    @endif
</x-filament-panels::page>
