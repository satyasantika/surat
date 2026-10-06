@extends('layouts.auth')
@section('judul', 'Lupa kata sandi')
@section('isi')
<form method="POST" action="{{ route('password.email') }}" class="space-y-4">
    @csrf
    <label class="block text-sm">Surel <input type="email" name="email" value="{{ old('email') }}" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2"></label>
    <button type="submit" class="w-full rounded bg-blue-900 px-4 py-2 text-white">Kirim tautan</button>
</form>
@endsection
