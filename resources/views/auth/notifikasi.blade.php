@extends('layouts.auth')
@section('judul', 'Notifikasi')
@section('isi')
@if (session('status'))<p class="mb-3 rounded bg-green-50 p-3 text-sm text-green-800" role="status">{{ session('status') }}</p>@endif
<div class="mb-3 flex items-center justify-between text-sm">
    <span>{{ $belumDibaca }} belum dibaca</span>
    @if ($belumDibaca > 0)
        <form method="POST" action="{{ route('notifikasi.baca-semua') }}">@csrf <button type="submit" class="text-blue-800 underline">Tandai semua dibaca</button></form>
    @endif
</div>
<ul class="space-y-2" aria-label="Daftar notifikasi">
    @forelse ($daftar as $n)
        <li class="rounded border p-3 text-sm {{ $n->read_at ? 'bg-white' : 'bg-blue-50' }}">
            <p class="font-medium">{{ $n->data['title'] ?? 'Notifikasi' }}</p>
            @if (! empty($n->data['body']))<p class="text-slate-600">{{ $n->data['body'] }}</p>@endif
            <p class="mt-1 flex gap-3 text-xs text-slate-500">
                <span>{{ $n->created_at->locale('id')->diffForHumans() }}</span>
                <a href="{{ route('notifikasi.buka', $n->id) }}" class="text-blue-800 underline">Buka</a>
            </p>
        </li>
    @empty
        <li class="text-sm text-slate-500">Belum ada notifikasi.</li>
    @endforelse
</ul>
<p class="mt-4 text-sm"><a href="{{ route('profil') }}" class="text-blue-800 underline">Pengaturan notifikasi</a></p>
@endsection
