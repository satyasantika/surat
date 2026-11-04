{{-- Layout halaman masuk panel (App\Filament\Pages\Auth\Masuk). Gaya ditulis sendiri (bukan utilitas Tailwind)
     karena CSS Filament hanya memuat kelas yang dipakainya. Warna mengikuti primer panel #1e3a8a. --}}
@php
    use Filament\Support\Facades\FilamentView;
    use Filament\View\PanelsRenderHook;

    $livewire ??= null;
    $renderHookScopes = $livewire?->getRenderHookScopes();
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    <style>
        .sr-masuk { display: grid; grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr); min-height: 100vh; background: #f8fafc; color: #0f172a; }
        .dark .sr-masuk { background: #0b1120; color: #e2e8f0; }

        /* ===== Panel merek (kiri) ===== */
        .sr-merek-panel {
            position: relative; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between;
            padding: 3rem 3.5rem; color: #dbeafe;
            background: radial-gradient(900px 500px at 15% 0%, rgba(96, 165, 250, .28), transparent 60%),
                        radial-gradient(700px 500px at 100% 100%, rgba(37, 99, 235, .3), transparent 60%),
                        linear-gradient(160deg, #1e3a8a 0%, #172554 55%, #0b1430 100%);
        }
        .sr-merek-panel::after {
            content: ""; position: absolute; inset: 0; pointer-events: none; opacity: .5;
            background-image: linear-gradient(rgba(191, 219, 254, .07) 1px, transparent 1px), linear-gradient(90deg, rgba(191, 219, 254, .07) 1px, transparent 1px);
            background-size: 44px 44px; mask-image: radial-gradient(circle at 30% 40%, #000, transparent 75%);
        }
        .sr-merek-panel > * { position: relative; z-index: 1; }
        .sr-merek { display: flex; align-items: center; gap: .75rem; font-weight: 700; font-size: 1.15rem; letter-spacing: .01em; color: #fff; text-decoration: none; }
        .sr-merek-tanda {
            display: flex; align-items: center; justify-content: center; width: 2.25rem; height: 2.25rem; border-radius: .6rem;
            background: #fff; color: #1e3a8a; font-size: 1rem; font-weight: 800;
        }
        .sr-merek-panel h2 { font-size: 2.1rem; line-height: 1.2; font-weight: 800; color: #fff; max-width: 26rem; margin: 0 0 1rem; }
        .sr-merek-panel h2 em { font-style: normal; color: #93c5fd; }
        .sr-merek-panel p.sr-lead { max-width: 26rem; color: #bfdbfe; line-height: 1.65; margin: 0 0 1.75rem; }
        .sr-poin { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .9rem; max-width: 27rem; }
        .sr-poin li { display: flex; gap: .75rem; align-items: flex-start; font-size: .95rem; line-height: 1.5; color: #dbeafe; }
        .sr-poin svg { flex-shrink: 0; width: 1.25rem; height: 1.25rem; margin-top: .1rem; color: #93c5fd; }
        .sr-hiasan { position: absolute !important; right: -2rem; bottom: 3rem; width: 15rem; height: 15rem; opacity: .2; z-index: 0 !important; }
        .sr-kaki-merek { font-size: .8rem; color: #93c5fd; }

        /* ===== Panel form (kanan) ===== */
        .sr-masuk-pane { display: flex; flex-direction: column; min-height: 100vh; padding: 1.25rem 2rem 1rem; }
        .sr-masuk-bar { display: flex; align-items: center; min-height: 2.75rem; }
        .sr-merek-ponsel { display: none; color: #1e3a8a; }
        .sr-merek-ponsel .sr-merek-tanda { background: #1e3a8a; color: #fff; }
        .dark .sr-merek-ponsel { color: #e2e8f0; }
        .sr-masuk-isi { flex: 1; display: flex; align-items: center; justify-content: center; padding: 1.5rem 0; }
        .sr-masuk-kartu { width: 100%; max-width: 26rem; }
        .sr-masuk-kartu .fi-simple-header { align-items: flex-start; text-align: left; margin-bottom: 1.75rem; }
        .sr-masuk-kartu .fi-simple-header-heading { font-size: 1.75rem; line-height: 2.1rem; font-weight: 800; letter-spacing: -.01em; text-align: left; }
        .sr-masuk-kartu .fi-simple-header-subheading { text-align: left; margin-top: .4rem; }
        .sr-masuk-kartu .fi-simple-page-content { row-gap: 0; }
        .sr-masuk-kaki { text-align: center; font-size: .85rem; color: #64748b; }
        .sr-masuk-kaki a { color: #1e3a8a; font-weight: 600; text-decoration: none; }
        .sr-masuk-kaki a:hover { text-decoration: underline; }
        .dark .sr-masuk-kaki { color: #94a3b8; }
        .dark .sr-masuk-kaki a { color: #93c5fd; }
        /* Hook FOOTER panel (versi + panduan) memakai utilitas Tailwind yang tidak ada di CSS Filament. */
        .sr-masuk-versi { margin-top: .35rem; text-align: center; font-size: .75rem; line-height: 1.1rem; color: #64748b; }
        .sr-masuk-versi a { color: inherit; text-decoration: underline; }
        .dark .sr-masuk-versi { color: #94a3b8; }

        @media (max-width: 960px) {
            .sr-masuk { grid-template-columns: 1fr; }
            .sr-merek-panel { display: none; }
            .sr-merek-ponsel { display: flex; }
            .sr-masuk-pane { padding: 1rem 1.25rem 1rem; }
        }
    </style>

    <div class="sr-masuk">
        <aside class="sr-merek-panel" aria-label="Tentang {{ config('app.name') }}">
            {{-- Hiasan: lembar naskah dengan kotak QR verifikasi. --}}
            <svg class="sr-hiasan" viewBox="0 0 200 200" fill="none" stroke="#bfdbfe" stroke-width="2" aria-hidden="true">
                <rect x="48" y="20" width="110" height="150" rx="8"/>
                <rect x="34" y="34" width="110" height="150" rx="8" fill="rgba(147,197,253,.18)"/>
                <path d="M54 66h70M54 84h70M54 102h50"/>
                <rect x="100" y="136" width="30" height="30" rx="3"/>
                <path d="M108 144h6v6h-6zM118 154h4v4h-4z" fill="#bfdbfe"/>
            </svg>

            <a href="{{ route('beranda') }}" class="sr-merek"><span class="sr-merek-tanda">F</span> {{ config('app.name') }}</a>

            <div>
                <h2>Naskah dinas FKIP, <em>tertib dan terlacak.</em></h2>
                <p class="sr-lead">Surat masuk, disposisi, dan naskah keluar fakultas dalam satu tempat, sesuai tata naskah dinas.</p>
                <ul class="sr-poin">
                    <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg><span>Nomor surat otomatis dan QR verifikasi pada setiap naskah keluar.</span></li>
                    <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg><span>Disposisi surat masuk terpantau dari pimpinan hingga pelaksana.</span></li>
                    <li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg><span>Permohonan ormawa dan unit diproses dan dilacak tanpa berkas fisik.</span></li>
                </ul>
            </div>

            <div class="sr-kaki-merek">&copy; {{ date('Y') }} Fakultas Keguruan dan Ilmu Pendidikan, Universitas Siliwangi</div>
        </aside>

        <div class="sr-masuk-pane">
            <div class="sr-masuk-bar">
                <a href="{{ route('beranda') }}" class="sr-merek sr-merek-ponsel"><span class="sr-merek-tanda">F</span> {{ config('app.name') }}</a>
            </div>

            <div class="sr-masuk-isi">
                <main id="fi-main-content" tabindex="-1" class="sr-masuk-kartu">
                    {{ $slot }}
                </main>
            </div>

            <p class="sr-masuk-kaki"><a href="{{ route('beranda') }}">&larr; Kembali ke beranda</a></p>

            <div class="sr-masuk-versi">
                {{ FilamentView::renderHook(PanelsRenderHook::FOOTER, scopes: $renderHookScopes) }}
            </div>
        </div>
    </div>

    {{ FilamentView::renderHook(PanelsRenderHook::SIMPLE_LAYOUT_END, scopes: $renderHookScopes) }}
</x-filament-panels::layout.base>
