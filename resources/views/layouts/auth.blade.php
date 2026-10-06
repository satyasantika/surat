<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('judul') — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
<main class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-10">
    <h1 class="mb-6 text-center text-xl font-semibold text-blue-900">{{ config('app.name') }}</h1>
    <div class="rounded-lg bg-white p-6 shadow">
        <h2 class="mb-4 text-lg font-medium">@yield('judul')</h2>
        @if (session('status'))
            <p class="mb-4 rounded bg-green-50 p-3 text-sm text-green-800">{{ session('status') }}</p>
        @endif
        @if ($errors->any())
            <ul class="mb-4 rounded bg-red-50 p-3 text-sm text-red-800">
                @foreach ($errors->all() as $galat)
                    <li>{{ $galat }}</li>
                @endforeach
            </ul>
        @endif
        @yield('isi')
    </div>
</main>
</body>
</html>
