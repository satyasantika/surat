@extends('layouts.auth')
@section('judul', 'Profil')
@section('isi')
<dl class="mb-6 space-y-1 text-sm">
    <div><dt class="inline text-slate-500">Nama:</dt> <dd class="inline">{{ $user->name }}</dd></div>
    <div><dt class="inline text-slate-500">Surel:</dt> <dd class="inline">{{ $user->email }}</dd></div>
</dl>
<form method="POST" action="{{ route('profil.sandi') }}" class="space-y-4">
    @csrf
    @method('PUT')
    <label class="block text-sm">Kata sandi saat ini <input type="password" name="current_password" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2"></label>
    <label class="block text-sm">Kata sandi baru <input type="password" name="password" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2"></label>
    <label class="block text-sm">Ulangi kata sandi baru <input type="password" name="password_confirmation" required class="mt-1 w-full rounded border border-slate-300 px-3 py-2"></label>
    <button type="submit" class="w-full rounded bg-blue-900 px-4 py-2 text-white">Ganti kata sandi</button>
</form>
<form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf <button class="text-sm underline" type="submit">Keluar</button></form>
@endsection
