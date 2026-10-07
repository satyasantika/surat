<x-layouts.publik>
    <section class="mb-8">
        <h1 class="text-2xl font-bold text-blue-900">Layanan Persuratan &amp; Ormawa FKIP</h1>
        <p class="mt-2 text-slate-600">Kabar kegiatan, galeri, dan profil organisasi mahasiswa Fakultas Keguruan dan Ilmu Pendidikan.</p>
    </section>

    <section class="mb-8" aria-label="Kabar terbaru">
        <div class="mb-3 flex items-baseline justify-between"><h2 class="text-lg font-semibold">Kabar terbaru</h2><a href="{{ route('kabar') }}" class="text-sm text-blue-800 underline">Semua kabar</a></div>
        <div class="grid gap-4 sm:grid-cols-3">
            @forelse ($kabar as $k)<x-publik.kartu-kabar :kabar="$k" />@empty<p class="text-sm text-slate-500">Belum ada kabar.</p>@endforelse
        </div>
    </section>

    <section class="mb-8" aria-label="Galeri">
        <div class="mb-3 flex items-baseline justify-between"><h2 class="text-lg font-semibold">Galeri</h2><a href="{{ route('galeri') }}" class="text-sm text-blue-800 underline">Semua galeri</a></div>
        <div class="grid gap-4 sm:grid-cols-3">
            @forelse ($galeri as $g)<x-publik.item-galeri :item="$g" />@empty<p class="text-sm text-slate-500">Belum ada galeri.</p>@endforelse
        </div>
    </section>

    <p class="mb-2 text-sm">Butuh bantuan memakai aplikasi? Buka <a href="{{ asset('panduan/index.html') }}" class="text-blue-800 underline">Panduan Pengguna</a> per peran lengkap dengan tangkapan layar.</p>

    <p class="text-sm">Memeriksa keaslian surat? <a href="{{ route('verifikasi.form') }}" class="text-blue-800 underline">Verifikasi surat</a></p>
</x-layouts.publik>
