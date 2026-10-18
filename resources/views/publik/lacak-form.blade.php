<x-layouts.publik judul="Lacak surat" :noindex="true">
    <section class="mx-auto max-w-xl">
        <h1 class="text-xl font-bold text-blue-900">Lacak surat atau permohonan</h1>
        <p class="mt-2 text-sm text-slate-600">Masukkan kode lacak yang Anda terima dari fakultas (contoh: <span class="font-mono">K7M2-9QXD-4WPT</span>). Tidak perlu masuk.</p>
        <form method="GET" action="{{ route('lacak.form') }}" class="mt-4 space-y-3">
            <label class="block text-sm">Kode lacak
                <input type="text" name="kode" required maxlength="40" autocomplete="off" class="mt-1 w-full rounded border px-3 py-2 font-mono uppercase">
            </label>
            @if ($galat)<p class="text-sm text-red-700" role="alert">{{ $galat }}</p>@endif
            <button type="submit" class="rounded bg-blue-900 px-4 py-2 text-white">Lacak</button>
        </form>
    </section>
</x-layouts.publik>
