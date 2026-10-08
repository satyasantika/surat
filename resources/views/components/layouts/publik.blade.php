@props(['judul' => null, 'deskripsi' => null])
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $judul ? $judul.' — ' : '' }}{{ config('app.name') }}</title>
    <meta name="description" content="{{ $deskripsi ?? 'Layanan persuratan digital dan profil organisasi mahasiswa Fakultas Keguruan dan Ilmu Pendidikan, Universitas Siliwangi.' }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
<header class="sticky top-0 z-10 bg-blue-900 text-white shadow">
    <nav class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-2 px-4 py-3 text-sm" aria-label="Menu utama">
        <a href="{{ route('beranda') }}" class="flex items-center gap-2 font-semibold">
            <span class="flex size-8 items-center justify-center rounded-full bg-white text-blue-900">F</span>
            {{ config('app.name') }}
        </a>
        <div class="flex flex-wrap items-center gap-4">
            <a href="{{ route('kabar') }}" class="underline-offset-2 hover:underline">Kabar</a>
            <a href="{{ route('galeri') }}" class="underline-offset-2 hover:underline">Galeri</a>
            <a href="{{ route('organisasi') }}" class="underline-offset-2 hover:underline">Organisasi</a>
            <a href="{{ route('verifikasi.form') }}" class="underline-offset-2 hover:underline">Verifikasi surat</a>
            <a href="{{ asset('panduan/index.html') }}" class="underline-offset-2 hover:underline">Panduan</a>
            <a href="{{ route('login') }}" class="rounded bg-white px-3 py-1 font-medium text-blue-900 hover:bg-blue-50">Masuk</a>
        </div>
    </nav>
</header>
<main class="mx-auto max-w-6xl px-4 py-6">
    {{ $slot }}
</main>
<footer class="border-t border-slate-200 bg-white">
    <div class="mx-auto max-w-6xl px-4 py-8 text-sm text-slate-500">
        <p class="font-semibold text-slate-700">{{ config('app.name') }}</p>
        <p class="mt-1">Fakultas Keguruan dan Ilmu Pendidikan, Universitas Siliwangi</p>
        <div class="mt-4 flex flex-wrap gap-x-6 gap-y-1 text-xs">
            <a href="{{ route('verifikasi.form') }}" class="hover:underline">Verifikasi surat</a>
            <a href="{{ asset('panduan/index.html') }}" class="hover:underline">Panduan pengguna</a>
            <a href="{{ route('login') }}" class="hover:underline">Masuk ke sistem</a>
        </div>
    </div>
</footer>
</body>
</html>
