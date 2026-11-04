{{-- Tombol masuk dengan Google; dipakai halaman masuk publik dan halaman masuk panel (render hook).
     Gaya ditulis sendiri (bukan utilitas Tailwind) karena CSS Filament hanya memuat kelas yang dipakainya.
     Tombol tetap tampil walau kunci Google belum diatur (DEPLOY.md 3a); $galat diisi panel untuk galat alur Google. --}}
<style>
    .sr-google-ekstra { display: flex; flex-direction: column; gap: 1rem; margin-top: 1.25rem; }
    .sr-google-galat {
        border-radius: .5rem; padding: .75rem 1rem; font-size: .875rem; line-height: 1.25rem;
        background: #fef2f2; color: #b91c1c; box-shadow: inset 0 0 0 1px rgba(220, 38, 38, .2);
    }
    .dark .sr-google-galat { background: rgba(248, 113, 113, .1); color: #f87171; box-shadow: inset 0 0 0 1px rgba(248, 113, 113, .3); }
    .sr-pemisah { display: flex; align-items: center; gap: .75rem; font-size: .8125rem; color: #64748b; }
    .sr-pemisah::before, .sr-pemisah::after { content: ""; flex: 1; height: 1px; background: #e2e8f0; }
    .dark .sr-pemisah { color: #94a3b8; }
    .dark .sr-pemisah::before, .dark .sr-pemisah::after { background: rgba(255, 255, 255, .1); }
    .sr-tombol-google {
        display: flex; align-items: center; justify-content: center; gap: .75rem; width: 100%;
        min-height: 2.75rem; padding: .5rem 1rem; border-radius: .5rem;
        background: #fff; color: #1f1f1f; border: 1px solid #747775;
        font-size: .875rem; font-weight: 500; letter-spacing: .01em; text-decoration: none;
        transition: background-color .15s, box-shadow .15s;
    }
    .sr-tombol-google:hover { background: #f8f9fa; box-shadow: 0 1px 2px rgba(60, 64, 67, .3), 0 1px 3px 1px rgba(60, 64, 67, .15); }
    .sr-tombol-google:focus-visible { outline: 2px solid #1e3a8a; outline-offset: 2px; }
    .dark .sr-tombol-google { background: #131314; color: #e3e3e3; border-color: #8e918f; }
    .dark .sr-tombol-google:hover { background: #1f1f20; }
    .sr-tombol-google svg { width: 1.25rem; height: 1.25rem; flex-shrink: 0; }
</style>

<div class="sr-google-ekstra">
    @if (filled($galat ?? null))
        <div role="alert" class="sr-google-galat">{{ $galat }}</div>
    @endif

    <div class="sr-pemisah" aria-hidden="true">atau</div>

    <a href="{{ $tautan }}" class="sr-tombol-google" rel="nofollow">
        <svg viewBox="0 0 48 48" aria-hidden="true" focusable="false">
            <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
            <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
            <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
            <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
        </svg>
        <span>Masuk dengan Google</span>
    </a>
</div>
