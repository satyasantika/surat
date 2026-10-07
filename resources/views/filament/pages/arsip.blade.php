<x-filament-panels::page>
    <div class="grid gap-3 sm:grid-cols-5">
        <label class="text-sm sm:col-span-2">Cari (nomor, perihal, asal/tujuan)
            <input type="search" wire:model.live.debounce.400ms="kata" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-white/20 dark:bg-transparent" placeholder="mis. 123/UN58.10 atau Dinas Pendidikan">
        </label>
        <label class="text-sm">Jenis
            <select wire:model.live="jenis" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-white/20 dark:bg-transparent">
                <option value="semua">Semua</option><option value="masuk">Surat masuk</option><option value="keluar">Surat keluar</option>
            </select>
        </label>
        <label class="text-sm">Klasifikasi arsip
            <select wire:model.live="klasifikasi" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-white/20 dark:bg-transparent">
                <option value="">Semua</option>
                @foreach ($this->pilihanKlasifikasi() as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
            </select>
        </label>
        <div class="grid grid-cols-2 gap-2 text-sm">
            <label>Dari <input type="date" wire:model.live="dari" class="mt-1 w-full rounded-lg border border-gray-300 px-2 py-2 dark:border-white/20 dark:bg-transparent"></label>
            <label>Sampai <input type="date" wire:model.live="sampai" class="mt-1 w-full rounded-lg border border-gray-300 px-2 py-2 dark:border-white/20 dark:bg-transparent"></label>
        </div>
    </div>

    @php($hasil = $this->hasil())
    @foreach (['masuk' => 'Surat masuk', 'keluar' => 'Surat keluar'] as $kunci => $judul)
        @continue($jenis !== 'semua' && $jenis !== $kunci)
        <section aria-label="{{ $judul }}" class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
            <p class="px-3 py-2 font-medium">{{ $judul }} <span class="text-sm font-normal text-gray-500">({{ count($hasil[$kunci]) }})</span></p>
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left dark:bg-white/5"><tr><th class="px-3 py-2">Nomor</th><th class="px-3 py-2">Tanggal</th><th class="px-3 py-2">{{ $kunci === 'masuk' ? 'Asal' : 'Tujuan' }}</th><th class="px-3 py-2">Perihal</th><th class="px-3 py-2">Klasifikasi arsip</th><th class="px-3 py-2">Keamanan</th><th class="px-3 py-2">Status</th></tr></thead>
                <tbody>
                @forelse ($hasil[$kunci] as $b)
                    <tr class="border-t border-gray-100 dark:border-white/10"><td class="px-3 py-1.5">{{ $b['nomor'] }}</td><td class="px-3 py-1.5">{{ $b['tanggal'] }}</td><td class="px-3 py-1.5">{{ $b['pihak'] }}</td><td class="px-3 py-1.5">{{ $b['perihal'] }}</td><td class="px-3 py-1.5">{{ $b['klasifikasi'] }}</td><td class="px-3 py-1.5">{{ $b['keamanan'] }}</td><td class="px-3 py-1.5">{{ $b['status'] }}</td></tr>
                @empty
                    <tr><td class="px-3 py-3 text-gray-500" colspan="7">Tidak ada arsip yang cocok.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>
    @endforeach
</x-filament-panels::page>
