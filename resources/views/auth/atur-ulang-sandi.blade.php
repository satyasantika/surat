@extends('layouts.auth')
@section('judul', 'Atur ulang kata sandi')
@section('isi')
<form method="POST" action="{{ route('password.store') }}" class="space-y-4">
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <label class="block text-sm">Surel <input type="email" name="email" value="{{ old('email', $email) }}" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2"></label>
    <label class="block text-sm">Kata sandi baru <input type="password" name="password" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2"></label>
    <label class="block text-sm">Ulangi kata sandi <input type="password" name="password_confirmation" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2"></label>
    <button type="submit" class="w-full rounded bg-blue-900 px-4 py-2 text-white">Simpan</button>
</form>
@endsection
