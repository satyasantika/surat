@props(['judul' => null, 'deskripsi' => null])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $judul ? $judul.' — ' : '' }}{{ config('app.name') }}</title>
    @if ($deskripsi)<meta name="description" content="{{ $deskripsi }}">@endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
<header class="bg-blue-900 text-white">
    <nav class="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm" aria-label="Menu utama">
        <a href="{{ route('beranda') }}" class="font-semibold">{{ config('app.name') }}</a>
        <div class="flex flex-wrap items-center gap-4">
            <a href="{{ route('kabar') }}" class="underline-offset-2 hover:underline">Kabar</a>
            <a href="{{ route('galeri') }}" class="underline-offset-2 hover:underline">Galeri</a>
            <a href="{{ route('organisasi') }}" class="underline-offset-2 hover:underline">Organisasi</a>
            <a href="{{ route('verifikasi.form') }}" class="underline-offset-2 hover:underline">Verifikasi surat</a>
            <a href="{{ route('login') }}" class="rounded bg-white px-3 py-1 text-blue-900">Masuk</a>
        </div>
    </nav>
</header>
<main class="mx-auto max-w-5xl px-4 py-6">
    {{ $slot }}
</main>
<footer class="mx-auto max-w-5xl px-4 py-6 text-xs text-slate-500">FKIP Universitas Siliwangi</footer>
</body>
</html>
