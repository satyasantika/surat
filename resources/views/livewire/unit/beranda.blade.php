<div class="space-y-4">
    @if ($adaPenyetuju)
        <section class="rounded-lg bg-white p-4 shadow" aria-label="Persetujuan">
            <h3 class="font-medium">Menunggu persetujuan Anda</h3>
            <p class="mt-1 text-sm text-slate-600">{{ $menunggu > 0 ? $menunggu.' permohonan menunggu keputusan Anda.' : 'Tidak ada permohonan yang menunggu.' }}</p>
            <p class="mt-2 text-sm"><a class="text-blue-800 underline" href="{{ route('unit.persetujuan') }}">Buka halaman persetujuan</a></p>
        </section>
    @endif

    @forelse ($unit as $u)
        <section class="rounded-lg bg-white p-4 shadow" wire:key="u-{{ $u->id }}">
            <h2 class="text-lg font-semibold">{{ $u->nama }}</h2>
            <p class="text-sm text-slate-600">{{ $u->tipe->label() }} · {{ ($jumlah[$u->id] ?? 0) > 0 ? $jumlah[$u->id].' permohonan tercatat.' : 'Belum ada permohonan.' }}</p>
            <p class="mt-2 flex gap-4 text-sm">
                <a class="text-blue-800 underline" href="{{ route('unit.permohonan', $u) }}">Progres</a>
                @if (in_array($u->id, $bolehAjukan, true))<a class="text-blue-800 underline" href="{{ route('unit.permohonan.baru', $u) }}">Ajukan baru</a>@endif
            </p>
        </section>
    @empty
        <div class="rounded-lg bg-white p-6 shadow" role="status">
            <p class="font-medium">Anda belum ditugaskan pada program studi atau laboratorium aktif.</p>
            <p class="mt-1 text-sm text-slate-600">Minta admin fakultas menugaskan akun Anda di Master › Unit kerja.</p>
        </div>
    @endforelse
</div>
