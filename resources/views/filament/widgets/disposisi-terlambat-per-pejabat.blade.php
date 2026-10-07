<x-filament-widgets::widget>
    <x-filament::section heading="Disposisi terlambat per pejabat">
        @forelse ($this->baris() as $b)
            <div class="flex items-center justify-between border-b border-gray-100 py-2 text-sm last:border-0 dark:border-white/10">
                <span>{{ $b['nama'] }} <span class="text-gray-500">{{ $b['jabatan'] }}</span></span>
                <span class="font-semibold text-danger-600">{{ $b['terlambat'] }}</span>
            </div>
        @empty
            <p class="text-sm text-gray-500">Tidak ada disposisi terlambat.</p>
        @endforelse
    </x-filament::section>
</x-filament-widgets::widget>
