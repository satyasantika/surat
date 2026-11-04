{{-- Gaya bersama halaman masuk dua kolom: panel Filament (/admin/login) dan halaman publik (layouts.masuk).
     Ditulis sendiri (bukan utilitas Tailwind) karena CSS Filament hanya memuat kelas yang dipakainya.
     Mode gelap lewat kelas .dark pada <html> (Filament dan public/js/tema.js memakai kunci tema yang sama). --}}
<style>
    .sr-masuk { display: grid; grid-template-columns: minmax(0, 1.05fr) minmax(0, 1fr); min-height: 100vh; background: #f8fafc; color: #0f172a; }
    .dark .sr-masuk { background: #0b1220; color: #e8eef8; }

    /* ===== Panel merek (kiri), senada hero beranda ===== */
    .sr-merek-panel {
        position: relative; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between;
        padding: 3rem 3.5rem; color: #dbeafe;
        background: radial-gradient(ellipse 60% 80% at 100% 0%, rgba(96, 165, 250, .4), transparent 70%),
                    linear-gradient(150deg, #1e3a8a 0%, #1d4ed8 60%, #1e40af 100%);
    }
    .sr-merek-panel::after {
        content: ""; position: absolute; inset: 0; pointer-events: none;
        background-image: linear-gradient(rgba(255, 255, 255, .07) 1px, transparent 1px), linear-gradient(90deg, rgba(255, 255, 255, .07) 1px, transparent 1px);
        background-size: 44px 44px; mask-image: radial-gradient(ellipse 60% 70% at 90% 10%, #000, transparent 75%);
    }
    .sr-merek-panel > * { position: relative; z-index: 1; }
    .sr-merek { display: flex; align-items: center; gap: .65rem; font-weight: 800; font-size: 1.1rem; letter-spacing: -.02em; color: #fff; text-decoration: none; }
    .sr-merek i { display: grid; place-items: center; width: 2rem; height: 2rem; border-radius: 50%; background: #fff; flex-shrink: 0; }
    .sr-merek svg { width: 1.05rem; height: 1.05rem; }
    .sr-label { display: inline-flex; gap: .5rem; align-items: center; font-size: .8rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: #bfdbfe; }
    .sr-label::before { content: ""; width: 1.75rem; height: 2px; background: currentColor; }
    .sr-merek-panel h2 { font-size: 2.3rem; line-height: 1.12; font-weight: 700; letter-spacing: -.03em; color: #fff; max-width: 15ch; margin: 1rem 0 1rem; }
    .sr-merek-panel h2 em { font-style: normal; color: #bfdbfe; }
    .sr-merek-panel p.sr-lead { max-width: 28rem; color: #dbeafe; line-height: 1.65; margin: 0 0 1.75rem; }
    .sr-poin { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: .9rem; max-width: 28rem; }
    .sr-poin li { display: flex; gap: .75rem; align-items: flex-start; font-size: .95rem; line-height: 1.5; color: #eff6ff; }
    .sr-poin svg { flex-shrink: 0; width: 1.25rem; height: 1.25rem; margin-top: .1rem; color: #bfdbfe; }
    .sr-hiasan { position: absolute !important; right: -2rem; bottom: 3rem; width: 15rem; height: 15rem; opacity: .2; z-index: 0 !important; }
    .sr-kaki-merek { font-size: .8rem; color: #bfdbfe; }

    /* ===== Panel form (kanan) ===== */
    .sr-masuk-pane { display: flex; flex-direction: column; min-height: 100vh; padding: 1.25rem 2rem 1rem; }
    .sr-masuk-bar { display: flex; align-items: center; gap: .75rem; min-height: 2.75rem; }
    .sr-merek-ponsel { display: none; color: #0f172a; }
    .sr-merek-ponsel i { background: #1e3a8a; }
    .dark .sr-merek-ponsel { color: #e8eef8; }
    .sr-masuk-isi { flex: 1; display: flex; align-items: center; justify-content: center; padding: 1.5rem 0; }
    .sr-masuk-kartu { width: 100%; max-width: 26rem; }
    .sr-masuk-kartu .fi-simple-header { align-items: flex-start; text-align: left; margin-bottom: 1.75rem; }
    .sr-masuk-kartu .fi-simple-header-heading { font-size: 1.75rem; line-height: 2.1rem; font-weight: 800; letter-spacing: -.01em; text-align: left; }
    .sr-masuk-kartu .fi-simple-header-subheading { text-align: left; margin-top: .4rem; }
    .sr-masuk-kartu .fi-simple-page-content { row-gap: 0; }
    /* Tombol utama form panel disamakan dengan /masuk; Filament memilih gradasi 400 (pucat) untuk #1e3a8a. */
    .sr-masuk-kartu .fi-btn.fi-color-primary { background: #1e3a8a; color: #fff; box-shadow: 0 6px 16px -8px rgba(30, 58, 138, .7); }
    .sr-masuk-kartu .fi-btn.fi-color-primary:hover { background: #1d4ed8; color: #fff; }
    .dark .sr-masuk-kartu .fi-btn.fi-color-primary { background: #3b82f6; }
    .sr-masuk-kaki { text-align: center; font-size: .85rem; color: #64748b; margin: 0; }
    .sr-masuk-kaki a { color: #1e3a8a; font-weight: 600; text-decoration: none; }
    .sr-masuk-kaki a:hover { text-decoration: underline; }
    .dark .sr-masuk-kaki { color: #94a3b8; }
    .dark .sr-masuk-kaki a { color: #93c5fd; }
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
