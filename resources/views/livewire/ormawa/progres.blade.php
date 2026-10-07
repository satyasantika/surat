<div class="space-y-4">
    <a href="{{ route('ormawa', ['o' => $ormawa->id]) }}" class="text-sm text-blue-800 underline">&larr; Kembali</a>
    <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold">Permohonan — {{ $ormawa->nama }}</h2>
        <a href="{{ route('ormawa.permohonan.baru', $ormawa) }}" class="rounded bg-blue-900 px-3 py-2 text-sm text-white">Ajukan baru</a>
    </div>

    @if (session('status'))<p class="rounded bg-green-50 p-3 text-sm text-green-800" role="status">{{ session('status') }}</p>@endif

    @forelse ($daftar as $p)
        <article class="rounded-lg bg-white p-4 shadow" wire:key="p-{{ $p->id }}">
            <button type="button" wire:click="pilih('{{ $p->id }}')" class="w-full text-left">
                <p class="text-xs text-slate-500">{{ $p->nomor }} · {{ $p->jenis->nama }}</p>
                <p class="font-medium">{{ $p->nama_kegiatan }}</p>
                <p class="mt-1 text-xs"><span class="rounded bg-slate-100 px-2 py-0.5">{{ $p->status->label() }}</span>
                    <span class="text-slate-500">{{ $p->tanggal_mulai->translatedFormat('d M Y') }}</span></p>
            </button>

            @if ($detail && $detail->id === $p->id)
                <div class="mt-3 space-y-3 border-t pt-3 text-sm">
                    <p>{{ $detail->deskripsi }}</p>
                    @if ($detail->ruangan->isNotEmpty())
                        <ul class="list-disc pl-5">
                            @foreach ($detail->ruangan as $r)
                                <li>{{ $r->nama_ruangan }} · {{ $r->tanggal->translatedFormat('d M Y') }} · {{ $r->sesi }} <span class="text-slate-500">({{ $r->status }})</span></li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($bolehPribadi)
                        <p class="text-slate-600">Ketua pelaksana: {{ $detail->penanggung_jawab['ketua']['nama'] ?? '—' }} · HP {{ $detail->penanggung_jawab['ketua']['hp'] ?? '—' }}</p>
                    @endif

                    <ol class="space-y-2 border-l-2 border-blue-200 pl-4" aria-label="Linimasa">
                        @foreach ($detail->riwayat as $r)
                            <li wire:key="h-{{ $r->id }}">
                                <p class="font-medium">{{ \App\Enums\StatusPermohonan::from($r->ke_status)->label() }}</p>
                                <p class="text-xs text-slate-500">{{ $r->created_at->translatedFormat('d M Y H:i') }}@if ($r->pelaku) · {{ $r->pelaku->name }}@endif</p>
                                @if ($r->catatan)<p class="text-slate-700">{{ $r->catatan }}</p>@endif
                            </li>
                        @endforeach
                    </ol>
                </div>
            @endif
        </article>
    @empty
        <p class="rounded bg-white p-6 text-center text-slate-500 shadow">Belum ada permohonan.</p>
    @endforelse
</div>
