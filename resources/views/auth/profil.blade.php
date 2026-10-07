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
<section class="mt-6 border-t pt-4" aria-label="Preferensi notifikasi">
    <h2 class="mb-2 font-medium">Notifikasi</h2>
    <p class="mb-2 text-sm"><a class="text-blue-800 underline" href="{{ route('notifikasi') }}">Lihat notifikasi ({{ $user->unreadNotifications()->count() }} belum dibaca)</a></p>
    <form method="POST" action="{{ route('profil.notifikasi') }}" class="space-y-2 text-sm">
        @csrf
        @method('PUT')
        <label class="block">Nomor WhatsApp (opsional) <input type="text" name="telepon" value="{{ old('telepon', $user->telepon) }}" maxlength="20" class="mt-1 w-full rounded border border-slate-300 px-3 py-2" placeholder="0812…"></label>
        <table class="w-full">
            <thead><tr class="text-left text-slate-500"><th class="py-1">Kategori</th><th>Surel</th><th>WhatsApp</th></tr></thead>
            <tbody>
            @foreach (\App\Support\Notifikasi\Kategori::SEMUA as $kode => $label)
                @php($p = $user->preferensiNotifikasi($kode))
                <tr class="border-t">
                    <td class="py-1">{{ $label }}</td>
                    <td><input type="checkbox" name="pref[{{ $kode }}][mail]" value="1" @checked($p['mail']) aria-label="Surel {{ $label }}"></td>
                    <td><input type="checkbox" name="pref[{{ $kode }}][whatsapp]" value="1" @checked($p['whatsapp']) aria-label="WhatsApp {{ $label }}"></td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <p class="text-xs text-slate-500">Notifikasi di aplikasi selalu aktif. Pesan WhatsApp hanya berisi ringkasan dan tautan.</p>
        <button type="submit" class="rounded border border-blue-900 px-4 py-2 text-blue-900">Simpan preferensi</button>
    </form>
</section>
@if ($user->hasAnyRole(['dekan', 'wakil-dekan', 'kasubag', 'pegawai']))
    <p class="mb-4 text-sm"><a class="text-blue-800 underline" href="{{ route('disposisi') }}">Buka kotak masuk disposisi</a></p>
@endif
<form method="POST" action="{{ route('logout') }}" class="mt-4">@csrf <button class="text-sm underline" type="submit">Keluar</button></form>
@endsection
