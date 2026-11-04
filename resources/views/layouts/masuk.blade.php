{{-- Layout halaman masuk publik dua kolom (masuk, daftar, lupa/atur ulang sandi), senada /admin/login dan beranda.
     Halaman setelah login (profil, notifikasi) tetap memakai layouts.auth. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('judul') — {{ config('app.name') }}</title>
    <link rel="icon" href="{{ url('/favicon.ico') }}">
    <link rel="stylesheet" href="{{ url('/fonts/filament/filament/inter/index.css') }}">
    <script src="{{ url('/js/tema.js') }}"></script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @include('auth.partials.gaya-masuk')
    <style>
        :root { color-scheme: light; }
        :root.dark { color-scheme: dark; }
        body { margin: 0; font-family: "Inter Variable", "Inter", system-ui, -apple-system, "Segoe UI", sans-serif; -webkit-font-smoothing: antialiased; }

        .sakelar-tema { display: inline-grid; place-items: center; width: 38px; height: 38px; margin-left: auto; padding: 0; border-radius: 10px; border: 1.5px solid #dbe3ee; background: transparent; color: inherit; cursor: pointer; }
        .sakelar-tema:hover { background: #eff4fb; border-color: #1e3a8a; }
        .sakelar-tema:focus-visible { outline: 2px solid #1e3a8a; outline-offset: 2px; }
        .sakelar-tema svg { width: 19px; height: 19px; }
        .sakelar-tema .ikon-matahari { display: none; }
        .dark .sakelar-tema { border-color: #22304a; }
        .dark .sakelar-tema:hover { background: #111b2e; }
        .dark .sakelar-tema .ikon-matahari { display: block; }
        .dark .sakelar-tema .ikon-bulan { display: none; }

        /* Judul dan isian, senada form Filament di /admin/login. Menimpa utilitas Tailwind pada tampilan halaman. */
        .sr-judul { font-size: 1.75rem; line-height: 2.1rem; font-weight: 800; letter-spacing: -.01em; margin: 0 0 1.5rem; }
        .sr-pesan { margin: 0 0 1rem; border-radius: .5rem; padding: .75rem 1rem; font-size: .875rem; line-height: 1.25rem; list-style: none; }
        .sr-pesan.ok { background: #f0fdf4; color: #166534; box-shadow: inset 0 0 0 1px rgba(22, 163, 74, .2); }
        .sr-pesan.galat { background: #fef2f2; color: #b91c1c; box-shadow: inset 0 0 0 1px rgba(220, 38, 38, .2); }
        .dark .sr-pesan.ok { background: rgba(74, 222, 128, .1); color: #4ade80; }
        .dark .sr-pesan.galat { background: rgba(248, 113, 113, .1); color: #f87171; }
        .sr-masuk-kartu label, .sr-masuk-kartu fieldset { font-size: .875rem; font-weight: 500; }
        .sr-masuk-kartu input:is([type=email], [type=password], [type=text], :not([type])) {
            display: block; width: 100%; margin-top: .4rem; padding: .55rem .75rem; border-radius: .5rem;
            border: 1px solid #d4d4d8; background: #fff; color: #0f172a; font-size: .9rem; font-weight: 400;
            box-shadow: 0 1px 2px rgba(0, 0, 0, .04);
        }
        .sr-masuk-kartu input:focus { outline: 2px solid #1e3a8a; outline-offset: 0; border-color: transparent; }
        .dark .sr-masuk-kartu input:is([type=email], [type=password], [type=text], :not([type])) { background: rgba(255, 255, 255, .05); border-color: rgba(255, 255, 255, .1); color: #e8eef8; }
        .dark .sr-masuk-kartu input:focus { outline-color: #60a5fa; }
        .sr-masuk-kartu input[type=checkbox], .sr-masuk-kartu input[type=radio] { accent-color: #1e3a8a; }
        .sr-masuk-kartu button[type=submit] {
            width: 100%; min-height: 2.6rem; padding: .5rem 1rem; border: 0; border-radius: .5rem; cursor: pointer;
            background: #1e3a8a; color: #fff; font-size: .9rem; font-weight: 600; box-shadow: 0 6px 16px -8px rgba(30, 58, 138, .7);
        }
        .sr-masuk-kartu button[type=submit]:hover { background: #1d4ed8; }
        .dark .sr-masuk-kartu button[type=submit] { background: #3b82f6; }
        .sr-masuk-kartu .text-blue-800, .sr-masuk-kartu .text-slate-500 a { color: #1e3a8a; font-weight: 600; }
        .dark .sr-masuk-kartu .text-blue-800, .dark .sr-masuk-kartu .text-slate-500 a { color: #93c5fd; }
        .dark .sr-masuk-kartu .text-slate-500 { color: #94a3b8; }
    </style>
</head>
<body>
<x-banner-impersonasi />
<div class="sr-masuk">
    @include('auth.partials.panel-merek')

    <div class="sr-masuk-pane">
        <div class="sr-masuk-bar">
            @include('auth.partials.merek', ['ponsel' => true])
            <button type="button" class="sakelar-tema" data-sakelar-tema aria-label="Ganti tema terang/gelap" title="Ganti tema terang/gelap">
                <svg class="ikon-bulan" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
                <svg class="ikon-matahari" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
            </button>
        </div>

        <div class="sr-masuk-isi">
            <main class="sr-masuk-kartu">
                <h1 class="sr-judul">@yield('judul')</h1>
                @if (session('status'))
                    <p class="sr-pesan ok" role="status">{{ session('status') }}</p>
                @endif
                @if ($errors->any())
                    <ul class="sr-pesan galat" role="alert">
                        @foreach ($errors->all() as $galat)
                            <li>{{ $galat }}</li>
                        @endforeach
                    </ul>
                @endif
                @yield('isi')
            </main>
        </div>

        <p class="sr-masuk-kaki"><a href="{{ route('beranda') }}">&larr; Kembali ke beranda</a></p>
        <p class="sr-masuk-versi"><a href="{{ asset('panduan/index.html') }}">Panduan pengguna</a></p>
    </div>
</div>
</body>
</html>
