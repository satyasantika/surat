{{-- Panel merek kiri halaman masuk (panel dan publik); isi dan tanda merek senada beranda. --}}
<aside class="sr-merek-panel" aria-label="Tentang {{ config('app.name') }}">
    {{-- Hiasan: lembar naskah dengan kotak QR verifikasi. --}}
    <svg class="sr-hiasan" viewBox="0 0 200 200" fill="none" stroke="#dbeafe" stroke-width="2" aria-hidden="true">
        <rect x="48" y="20" width="110" height="150" rx="8"/>
        <rect x="34" y="34" width="110" height="150" rx="8" fill="rgba(191,219,254,.18)"/>
        <path d="M54 66h70M54 84h70M54 102h50"/>
        <rect x="100" y="136" width="30" height="30" rx="3"/>
        <path d="M108 144h6v6h-6zM118 154h4v4h-4z" fill="#dbeafe"/>
    </svg>

    @include('auth.partials.merek')

    <div>
        <span class="sr-label">FKIP Universitas Siliwangi</span>
        <h2>Setiap surat, <em>tercatat</em> dan terlacak.</h2>
        <p class="sr-lead">Pengajuan surat, disposisi pimpinan, naskah bernomor, hingga permohonan kegiatan ormawa dalam satu sistem.</p>
        <ul class="sr-poin">
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg><span>Naskah bernomor dengan QR verifikasi keaslian.</span></li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg><span>Disposisi berjenjang dari pimpinan hingga pelaksana.</span></li>
            <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg><span>Surat dan permohonan dapat dilacak dengan kode, tanpa akun.</span></li>
        </ul>
    </div>

    <div class="sr-kaki-merek">&copy; {{ date('Y') }} Fakultas Keguruan dan Ilmu Pendidikan, Universitas Siliwangi</div>
</aside>
