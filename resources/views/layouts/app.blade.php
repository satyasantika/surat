<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }} — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
<x-banner-impersonasi />
<header class="bg-blue-900 text-white">
    <nav class="mx-auto flex max-w-3xl items-center justify-between px-4 py-3 text-sm">
        <a href="{{ \App\Support\Beranda::url(auth()->user()) }}" class="font-semibold">{{ config('app.name') }}</a>
        <div class="flex items-center gap-4">
            <a href="{{ route('notifikasi') }}" class="underline">Notifikasi @if (($n = auth()->user()->unreadNotifications()->count()) > 0)<span class="rounded-full bg-amber-400 px-1.5 text-xs text-blue-950">{{ $n }}</span>@endif</a>
            <a href="{{ asset('panduan/index.html') }}" class="underline">Panduan</a>
            <a href="{{ route('profil') }}" class="underline">Profil</a>
            <form method="POST" action="{{ route('logout') }}">@csrf <button type="submit" class="underline">Keluar</button></form>
        </div>
    </nav>
</header>
<main class="mx-auto max-w-3xl px-4 py-4">
    {{ $slot }}
</main>
@livewireScripts
</body>
</html>
