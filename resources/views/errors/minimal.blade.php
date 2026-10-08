<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') — @yield('title')</title>
    <meta name="robots" content="noindex">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @else
        <style>
            *,:after,:before{box-sizing:border-box}
            body{margin:0;font-family:ui-sans-serif,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f8fafc;color:#1e293b}
        </style>
    @endif
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
<header class="bg-blue-900 text-white">
    <nav class="mx-auto flex max-w-3xl items-center justify-between px-4 py-3 text-sm">
        <span class="font-semibold">{{ config('app.name') }}</span>
        <span class="text-blue-200">FKIP Universitas Siliwangi</span>
    </nav>
</header>
<main class="mx-auto flex min-h-[70vh] max-w-3xl flex-col items-center justify-center px-4 py-16 text-center">
    <p class="text-sm font-semibold uppercase tracking-widest text-blue-700">Kode @yield('code')</p>
    <h1 class="mt-2 text-3xl font-bold text-slate-900 sm:text-4xl">@yield('title')</h1>
    <p class="mx-auto mt-4 max-w-md text-slate-600">@yield('message')</p>
    <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
        <button type="button" onclick="history.length > 1 ? history.back() : (window.location.href = {{ Js::from(url('/')) }})"
                class="rounded-md border border-blue-900 px-4 py-2 text-sm font-medium text-blue-900 hover:bg-blue-50">
            ← Kembali
        </button>
        <a href="{{ route('beranda') }}" class="rounded-md bg-blue-900 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800">
            Ke beranda
        </a>
    </div>
</main>
<footer class="mx-auto max-w-3xl px-4 py-6 text-center text-xs text-slate-400">FKIP Universitas Siliwangi</footer>
</body>
</html>
