<x-layouts.publik>
    <section class="mb-12 overflow-hidden rounded-2xl bg-gradient-to-br from-blue-900 to-blue-700 px-6 py-14 text-white sm:px-12">
        <p class="text-sm font-semibold uppercase tracking-widest text-blue-200">FKIP Universitas Siliwangi</p>
        <h1 class="mt-3 max-w-2xl text-3xl font-bold leading-tight sm:text-4xl">Layanan Persuratan &amp; Organisasi Mahasiswa, Lebih Cepat dan Transparan</h1>
        <p class="mt-4 max-w-xl text-blue-100">Pengajuan surat, disposisi, arsip naskah, hingga profil kegiatan ormawa — satu sistem terpadu untuk sivitas akademika FKIP.</p>
        <div class="mt-8 flex flex-wrap gap-3">
            <a href="{{ route('verifikasi.form') }}" class="rounded-md bg-white px-5 py-2.5 text-sm font-semibold text-blue-900 hover:bg-blue-50">Verifikasi keaslian surat</a>
            <a href="{{ asset('panduan/index.html') }}" class="rounded-md border border-white/40 px-5 py-2.5 text-sm font-semibold text-white hover:bg-white/10">Lihat panduan pengguna</a>
            <a href="{{ route('login') }}" class="rounded-md border border-white/40 px-5 py-2.5 text-sm font-semibold text-white hover:bg-white/10">Masuk ke sistem</a>
        </div>
    </section>

    <section class="mb-12 grid gap-4 sm:grid-cols-3">
        <a href="{{ route('kabar') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow-md">
            <h2 class="font-semibold text-blue-900">Kabar kegiatan</h2>
            <p class="mt-1 text-sm text-slate-600">Informasi dan dokumentasi kegiatan fakultas serta organisasi mahasiswa.</p>
        </a>
        <a href="{{ route('organisasi') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow-md">
            <h2 class="font-semibold text-blue-900">Organisasi mahasiswa</h2>
            <p class="mt-1 text-sm text-slate-600">Profil, visi-misi, dan kepengurusan ormawa aktif di lingkungan FKIP.</p>
        </a>
        <a href="{{ route('verifikasi.form') }}" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:shadow-md">
            <h2 class="font-semibold text-blue-900">Verifikasi surat</h2>
            <p class="mt-1 text-sm text-slate-600">Pastikan keaslian naskah dinas yang diterbitkan melalui sistem ini.</p>
        </a>
    </section>

    <section class="mb-12" aria-label="Kabar terbaru">
        <div class="mb-4 flex items-baseline justify-between"><h2 class="text-xl font-bold text-slate-900">Kabar terbaru</h2><a href="{{ route('kabar') }}" class="text-sm font-medium text-blue-800 hover:underline">Semua kabar →</a></div>
        <div class="grid gap-4 sm:grid-cols-3">
            @forelse ($kabar as $k)<x-publik.kartu-kabar :kabar="$k" />@empty<p class="text-sm text-slate-500">Belum ada kabar terbit.</p>@endforelse
        </div>
    </section>

    <section class="mb-12" aria-label="Galeri">
        <div class="mb-4 flex items-baseline justify-between"><h2 class="text-xl font-bold text-slate-900">Galeri</h2><a href="{{ route('galeri') }}" class="text-sm font-medium text-blue-800 hover:underline">Semua galeri →</a></div>
        <div class="grid gap-4 sm:grid-cols-3">
            @forelse ($galeri as $g)<x-publik.item-galeri :item="$g" />@empty<p class="text-sm text-slate-500">Belum ada galeri.</p>@endforelse
        </div>
    </section>

    <section class="rounded-xl border border-blue-100 bg-blue-50 p-6 text-sm text-blue-900">
        <p class="mb-2">Butuh bantuan memakai aplikasi? Buka <a href="{{ asset('panduan/index.html') }}" class="font-medium underline">Panduan Pengguna</a> per peran lengkap dengan tangkapan layar.</p>
        <p>Memeriksa keaslian surat? Gunakan <a href="{{ route('verifikasi.form') }}" class="font-medium underline">Verifikasi surat</a>.</p>
    </section>
</x-layouts.publik>
