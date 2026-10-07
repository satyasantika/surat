<div class="space-y-4">
    <a href="{{ route('ormawa', ['o' => $ormawa->id]) }}" class="text-sm text-blue-800 underline">&larr; Kembali</a>
    <h2 class="text-lg font-semibold">LPJ — {{ $lpj->permohonan->nama_kegiatan }}</h2>
    <p class="text-sm text-slate-600">
        {{ $lpj->permohonan->nomor }} · batas {{ $lpj->batas_waktu->translatedFormat('d M Y') }}
        <span class="ml-1 rounded bg-slate-100 px-2 py-0.5">{{ ucfirst($lpj->status) }}</span>
        @if ($lpj->terlambat())<span class="ml-1 rounded bg-red-100 px-2 py-0.5 text-red-800">Terlambat</span>@endif
    </p>

    @if ($errors->any())
        <ul class="rounded bg-red-50 p-3 text-sm text-red-800" role="alert">@foreach ($errors->all() as $galat)<li>{{ $galat }}</li>@endforeach</ul>
    @endif

    <section class="space-y-3 rounded-lg bg-white p-4 shadow">
        @php($atr = $bisaIsi ? '' : 'disabled')
        <label class="block text-sm">Tanggal pelaksanaan <input type="date" wire:model="tanggal_pelaksanaan" {{ $atr }} class="mt-1 w-full rounded border px-3 py-2"></label>
        <label class="block text-sm">Jumlah peserta <input type="number" min="0" wire:model="jumlah_peserta" {{ $atr }} class="mt-1 w-full rounded border px-3 py-2"></label>
        <label class="block text-sm">Ringkasan kegiatan <textarea wire:model="ringkasan" rows="4" {{ $atr }} class="mt-1 w-full rounded border px-3 py-2"></textarea></label>
        <label class="block text-sm">Kendala <textarea wire:model="kendala" rows="2" {{ $atr }} class="mt-1 w-full rounded border px-3 py-2"></textarea></label>
        <label class="block text-sm">Solusi <textarea wire:model="solusi" rows="2" {{ $atr }} class="mt-1 w-full rounded border px-3 py-2"></textarea></label>
        <label class="block text-sm">Rekomendasi <textarea wire:model="rekomendasi" rows="2" {{ $atr }} class="mt-1 w-full rounded border px-3 py-2"></textarea></label>
        <label class="block text-sm">Tautan Instagram <input type="url" wire:model="tautan_instagram" {{ $atr }} class="mt-1 w-full rounded border px-3 py-2" placeholder="https://www.instagram.com/..."></label>
        <label class="block text-sm">Tautan video <input type="url" wire:model="tautan_video" {{ $atr }} class="mt-1 w-full rounded border px-3 py-2" placeholder="https://www.youtube.com/..."></label>
        <label class="block text-sm">Tautan berkas LPJ (Drive/unsil.ac.id) <input type="url" wire:model="berkas_lpj" {{ $atr }} class="mt-1 w-full rounded border px-3 py-2" placeholder="https://"></label>
        @if ($bisaIsi)
            <button type="button" wire:click="kirim" class="w-full rounded bg-blue-900 px-4 py-2 text-white">Ajukan LPJ</button>
        @else
            <p class="text-sm text-slate-500">LPJ sudah diajukan dan tidak dapat diubah.</p>
        @endif
    </section>

    @if ($lpj->nilai_akhir !== null)
        <section class="rounded-lg bg-white p-4 shadow"><h3 class="font-medium">Nilai akhir</h3><p class="text-2xl font-semibold">{{ $lpj->nilai_akhir }}</p></section>
    @endif
</div>
