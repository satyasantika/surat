@extends('layouts.auth')
@section('judul', 'Masuk')
@section('isi')
<form method="POST" action="{{ route('login') }}" class="space-y-4">
    @csrf
    <label class="block text-sm">Surel
        <input type="email" name="email" value="{{ old('email') }}" required autofocus class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
    </label>
    <label class="block text-sm">Kata sandi
        <input type="password" name="password" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2">
    </label>
    <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1"> Ingat saya</label>
    <button type="submit" class="w-full rounded bg-blue-900 px-4 py-2 text-white">Masuk</button>
</form>
<p class="mt-4 text-sm"><a class="text-blue-800 underline" href="{{ route('password.request') }}">Lupa kata sandi?</a>
    · <a class="text-blue-800 underline" href="{{ route('daftar') }}">Daftar pengurus ormawa</a></p>
<p class="mt-2 text-sm text-slate-500">Pimpinan dan admin masuk melalui <a class="underline" href="{{ url('admin') }}">halaman admin</a>.</p>
@endsection
