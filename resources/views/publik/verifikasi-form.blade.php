<x-layouts.publik judul="Verifikasi surat">
    <section class="mx-auto max-w-xl">
        <h1 class="text-xl font-bold text-blue-900">Verifikasi surat</h1>
        <p class="mt-2 text-sm text-slate-600">Pindai QR pada surat, atau tempel kode/tautan verifikasi yang tercetak pada surat.</p>
        <form method="GET" action="{{ route('verifikasi.form') }}" class="mt-4 space-y-3">
            <label class="block text-sm">Kode atau tautan verifikasi
                <input type="text" name="kode" required maxlength="300" class="mt-1 w-full rounded border px-3 py-2">
            </label>
            @if ($galat)<p class="text-sm text-red-700" role="alert">{{ $galat }}</p>@endif
            <button type="submit" class="rounded bg-blue-900 px-4 py-2 text-white">Periksa</button>
        </form>
    </section>
</x-layouts.publik>
