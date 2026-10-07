<div class="space-y-4">
    @if ($daftar->count() > 1)
        <div class="flex flex-wrap gap-2 text-sm" role="tablist">
            @foreach ($daftar as $o)
                <button type="button" wire:click="pilih('{{ $o->id }}')" wire:key="o-{{ $o->id }}"
                        class="rounded-full px-4 py-2 {{ $ormawa?->id === $o->id ? 'bg-blue-900 text-white' : 'bg-white shadow' }}">{{ $o->singkatan ?: $o->nama }}</button>
            @endforeach
        </div>
    @endif

    @if ($ormawa === null)
        <div class="rounded-lg bg-white p-6 shadow" role="status">
            <p class="font-medium">Anda belum terhubung ke ormawa aktif.</p>
            <p class="mt-1 text-sm text-slate-600">
                @if ($adaKeanggotaan)
                    Keanggotaan Anda belum aktif: pastikan SK kepengurusan ormawa masih berlaku dan masa jabatan Anda belum berakhir.
                @else
                    Minta ketua/sekretaris ormawa atau admin fakultas menautkan akun Anda (berdasarkan NIM {{ auth()->user()->nip_nim ?: '— belum diisi' }}).
                @endif
            </p>
        </div>
    @else
        <section class="rounded-lg bg-white p-4 shadow">
            <h2 class="text-lg font-semibold">{{ $ormawa->nama }}</h2>
            <p class="text-sm text-slate-600">
                @if ($sk) SK {{ $sk->nomor_sk }} · periode {{ $sk->periode_mulai->translatedFormat('d M Y') }} – {{ $sk->periode_selesai->translatedFormat('d M Y') }} @endif
            </p>
            <p class="mt-2 text-sm">
                <a class="text-blue-800 underline" href="{{ route('ormawa.profil', $ormawa) }}">{{ $bolehKelola ? 'Ubah profil dan pengurus' : 'Lihat profil dan pengurus' }}</a>
            </p>
        </section>

        <section class="rounded-lg bg-white p-4 shadow" aria-label="Permohonan">
            <h3 class="font-medium">Permohonan</h3>
            <p class="text-sm text-slate-500">{{ $jumlahPermohonan > 0 ? $jumlahPermohonan.' permohonan tercatat.' : 'Belum ada permohonan.' }}</p>
            <p class="mt-2 flex gap-4 text-sm">
                <a class="text-blue-800 underline" href="{{ route('ormawa.permohonan', $ormawa) }}">Progres</a>
                <a class="text-blue-800 underline" href="{{ route('ormawa.permohonan.baru', $ormawa) }}">Ajukan baru</a>
            </p>
        </section>

        <section class="rounded-lg bg-white p-4 shadow" aria-label="Kabar">
            <h3 class="font-medium">Kabar</h3>
            <p class="mt-1 text-sm"><a class="text-blue-800 underline" href="{{ route('ormawa.kabar', $ormawa) }}">Tulis dan kelola kabar ormawa</a></p>
        </section>

        <section class="rounded-lg bg-white p-4 shadow" aria-label="LPJ jatuh tempo">
            <h3 class="font-medium">LPJ jatuh tempo</h3>
            @forelse ($lpjDaftar as $l)
                <p class="text-sm" wire:key="lpj-{{ $l->id }}">
                    <a class="text-blue-800 underline" href="{{ route('ormawa.lpj', ['ormawa' => $ormawa->id, 'lpj' => $l->id]) }}">{{ $l->permohonan->nama_kegiatan }}</a>
                    · batas {{ $l->batas_waktu->translatedFormat('d M Y') }}
                    @if ($l->terlambat())<span class="rounded bg-red-100 px-1.5 text-xs text-red-800">Terlambat</span>@endif
                </p>
            @empty
                <p class="text-sm text-slate-500">Tidak ada LPJ yang jatuh tempo.</p>
            @endforelse
        </section>
    @endif
</div>
