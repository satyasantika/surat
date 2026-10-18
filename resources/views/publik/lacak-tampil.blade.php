<x-layouts.publik judul="Lacak surat" :noindex="true">
    <section class="mx-auto max-w-xl space-y-4">
        <div>
            <p class="text-xs text-slate-500">Kode lacak <span class="font-mono">{{ $kode }}</span></p>
            <h1 class="text-xl font-bold text-blue-900">{{ $layanan }}</h1>
            @if ($perihal)
                <p class="mt-1 text-slate-700">{{ $perihal }}</p>
            @else
                <p class="mt-1 text-sm text-slate-600">Perihal tidak ditampilkan untuk surat yang dirahasiakan.</p>
            @endif
            <p class="mt-2"><span class="rounded {{ $selesai ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-900' }} px-2 py-1 text-sm font-medium">{{ $status }}</span></p>
        </div>

        @if ($hasilUrl)
            <p class="rounded bg-green-50 p-3 text-sm text-green-900">Hasil sudah tersedia:
                <a href="{{ $hasilUrl }}" target="_blank" rel="noopener noreferrer" class="font-medium underline">Buka hasil</a></p>
        @elseif ($selesai && $tertutup)
            <p class="rounded bg-slate-100 p-3 text-sm text-slate-700">Surat telah selesai diproses. Hubungi fakultas untuk pengambilan hasil.</p>
        @endif
        @if ($verifikasiUrl)
            <p class="text-sm"><a href="{{ $verifikasiUrl }}" class="text-blue-800 underline">Periksa keaslian surat yang terbit</a></p>
        @endif

        <ol class="space-y-3 border-l-2 border-blue-200 pl-4" aria-label="Linimasa">
            @foreach ($tahap as $t)
                <li>
                    <p class="font-medium">{{ $t['tahap'] }}</p>
                    <p class="text-xs text-slate-500">{{ $t['waktu']->translatedFormat('d M Y H:i') }}@if ($t['pelaku']) · {{ $t['pelaku'] }}@endif</p>
                </li>
            @endforeach
        </ol>
        <p class="text-xs text-slate-500">Halaman ini hanya menampilkan perkembangan umum. Simpan kode lacak Anda; siapa pun yang memilikinya dapat melihat halaman ini.</p>
    </section>
</x-layouts.publik>
