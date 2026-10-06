@extends('layouts.auth')
@section('judul', 'Daftar pengurus ormawa')
@section('isi')
<form method="POST" action="{{ route('daftar') }}" class="space-y-4">
    @csrf
    <label class="block text-sm">Nama lengkap <input name="name" value="{{ old('name') }}" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2"></label>
    <label class="block text-sm">NIM <input name="nip_nim" value="{{ old('nip_nim') }}" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2"></label>
    <label class="block text-sm">Surel mahasiswa (@student.unsil.ac.id) <input type="email" name="email" value="{{ old('email') }}" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2"></label>
    <label class="block text-sm">Kata sandi (min. 10 karakter, huruf dan angka) <input type="password" name="password" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2"></label>
    <label class="block text-sm">Ulangi kata sandi <input type="password" name="password_confirmation" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2"></label>
    <button type="submit" class="w-full rounded bg-blue-900 px-4 py-2 text-white">Daftar</button>
</form>
<p class="mt-4 text-sm"><a class="text-blue-800 underline" href="{{ route('login') }}">Sudah punya akun?</a></p>
@endsection
