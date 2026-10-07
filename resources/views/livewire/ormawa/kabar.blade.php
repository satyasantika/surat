<div class="space-y-4">
    <a href="{{ route('ormawa', ['o' => $ormawa->id]) }}" class="text-sm text-blue-800 underline">&larr; Kembali</a>
    <h2 class="text-lg font-semibold">Kabar — {{ $ormawa->nama }}</h2>

    @if (session('status'))
        <p class="rounded bg-green-50 p-3 text-sm text-green-800" role="status">{{ session('status') }}</p>
    @endif
    @if ($errors->any())
        <ul class="rounded bg-red-50 p-3 text-sm text-red-800" role="alert">@foreach ($errors->all() as $galat)<li>{{ $galat }}</li>@endforeach</ul>
    @endif

    <section class="space-y-3 rounded-lg bg-white p-4 shadow">
        <h3 class="font-medium">{{ $kabarId ? 'Ubah kabar' : 'Tulis kabar baru' }}</h3>
        <label class="block text-sm">Judul <input type="text" wire:model="judul" maxlength="200" class="mt-1 w-full rounded border px-3 py-2"></label>
        <label class="block text-sm">Subjudul <input type="text" wire:model="subjudul" maxlength="250" class="mt-1 w-full rounded border px-3 py-2"></label>
        <label class="block text-sm">Isi (HTML sederhana: paragraf, daftar, tautan) <textarea wire:model="isi" rows="8" class="mt-1 w-full rounded border px-3 py-2"></textarea></label>
        <label class="block text-sm">Tag (pisahkan dengan koma) <input type="text" wire:model="tag" class="mt-1 w-full rounded border px-3 py-2"></label>
        <label class="block text-sm">Tautan foto sampul (Drive/unsil.ac.id) <input type="url" wire:model="sampul" class="mt-1 w-full rounded border px-3 py-2" placeholder="https://"></label>
        <div class="flex gap-2">
            <button type="button" wire:click="simpan" class="rounded border border-blue-900 px-4 py-2 text-blue-900">Simpan draf</button>
            <button type="button" wire:click="simpanDanAjukan" class="rounded bg-blue-900 px-4 py-2 text-white">Simpan &amp; ajukan</button>
            @if ($kabarId)<button type="button" wire:click="batal" class="px-4 py-2 text-sm underline">Batal</button>@endif
        </div>
    </section>

    <section class="space-y-2" aria-label="Daftar kabar">
        @forelse ($daftar as $k)
            <article class="rounded-lg bg-white p-4 shadow" wire:key="kabar-{{ $k->id }}">
                <h3 class="font-medium">{{ $k->judul }}
                    <span class="ml-1 rounded bg-slate-100 px-2 py-0.5 text-xs">{{ \App\Models\Kabar::STATUS[$k->status] }}</span>
                </h3>
                @if ($k->status === \App\Models\Kabar::DITOLAK && $k->catatan_admin)
                    <p class="mt-1 text-sm text-red-800">Catatan admin: {{ $k->catatan_admin }}</p>
                @endif
                @if (in_array($k->status, [\App\Models\Kabar::DRAF, \App\Models\Kabar::DITOLAK], true))
                    <p class="mt-2 flex gap-4 text-sm">
                        <button type="button" wire:click="ubah('{{ $k->id }}')" class="text-blue-800 underline">Ubah</button>
                        <button type="button" wire:click="ajukan('{{ $k->id }}')" class="text-blue-800 underline">Ajukan</button>
                    </p>
                @endif
            </article>
        @empty
            <p class="text-sm text-slate-500">Belum ada kabar.</p>
        @endforelse
    </section>
</div>
