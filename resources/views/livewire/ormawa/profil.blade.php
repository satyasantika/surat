<div class="space-y-4">
    <a href="{{ route('ormawa', ['o' => $ormawa->id]) }}" class="text-sm text-blue-800 underline">&larr; Kembali</a>
    <h2 class="text-lg font-semibold">{{ $ormawa->nama }}</h2>

    @if (session('status'))<p class="rounded bg-green-50 p-3 text-sm text-green-800" role="status">{{ session('status') }}</p>@endif
    @if ($errors->any())
        <ul class="rounded bg-red-50 p-3 text-sm text-red-800" role="alert">@foreach ($errors->all() as $galat)<li>{{ $galat }}</li>@endforeach</ul>
    @endif

    <section class="space-y-3 rounded-lg bg-white p-4 shadow">
        <h3 class="font-medium">Profil</h3>
        @if ($bolehUbah)
            <label class="block text-sm">Singkatan <input wire:model="singkatan" class="mt-1 w-full rounded border px-3 py-2"></label>
            <label class="block text-sm">Akun media <input wire:model="akun_media" class="mt-1 w-full rounded border px-3 py-2" placeholder="@akun"></label>
            <label class="block text-sm">Surel organisasi <input wire:model="surel_organisasi" type="email" class="mt-1 w-full rounded border px-3 py-2"></label>
            <label class="block text-sm">Visi <textarea wire:model="visi" rows="3" class="mt-1 w-full rounded border px-3 py-2"></textarea></label>
            <label class="block text-sm">Misi <textarea wire:model="misi" rows="4" class="mt-1 w-full rounded border px-3 py-2"></textarea></label>
            <button type="button" wire:click="simpanProfil" class="rounded bg-blue-900 px-4 py-2 text-white">Simpan profil</button>
        @else
            <dl class="space-y-1 text-sm">
                <div><dt class="inline text-slate-500">Singkatan:</dt> <dd class="inline">{{ $ormawa->singkatan ?: '—' }}</dd></div>
                <div><dt class="inline text-slate-500">Akun media:</dt> <dd class="inline">{{ $ormawa->akun_media ?: '—' }}</dd></div>
                <div><dt class="inline text-slate-500">Visi:</dt> <dd class="inline">{{ $ormawa->visi ?: '—' }}</dd></div>
                <div><dt class="inline text-slate-500">Misi:</dt> <dd class="inline">{{ $ormawa->misi ?: '—' }}</dd></div>
            </dl>
        @endif
    </section>

    <section class="space-y-3 rounded-lg bg-white p-4 shadow">
        <h3 class="font-medium">Pengurus</h3>
        <ul class="divide-y text-sm">
            @forelse ($pengurus as $p)
                @can('lihatDataPribadi', $p) @php($pribadi = true) @else @php($pribadi = false) @endcan
                <li class="py-2" wire:key="p-{{ $p->id }}">
                    <p class="font-medium">{{ $p->nama }}
                        <span class="text-slate-500">· {{ $p->jabatan_teks ?: ($jabatanPilihan[$p->jabatan] ?? $p->jabatan) }}</span>
                        @if ($p->narahubung)<span class="ml-1 rounded bg-blue-100 px-1.5 text-xs">Narahubung</span>@endif
                        @if (! $p->user_id)<span class="ml-1 rounded bg-amber-100 px-1.5 text-xs">Belum bertaut akun</span>@endif
                    </p>
                    @if ($pribadi)
                        <p class="text-slate-600">NIM {{ $p->nim ?: '—' }} · Telp {{ $p->telepon ?: '—' }}</p>
                    @endif
                    @if ($bolehUbah)
                        <p class="mt-1 flex gap-3">
                            <button type="button" wire:click="ubah('{{ $p->id }}')" class="text-blue-800 underline">Ubah</button>
                            <button type="button" wire:click="hapus('{{ $p->id }}')" wire:confirm="Hapus pengurus ini?" class="text-red-700 underline">Hapus</button>
                        </p>
                    @endif
                </li>
            @empty
                <li class="py-2 text-slate-500">Belum ada pengurus.</li>
            @endforelse
        </ul>

        @if ($bolehUbah)
            <div class="space-y-2 rounded border bg-slate-50 p-3">
                <h4 class="font-medium">{{ $pengurusId ? 'Ubah pengurus' : 'Tambah pengurus' }}</h4>
                <input wire:model="form.nama" placeholder="Nama" class="w-full rounded border px-3 py-2">
                <select wire:model="form.jabatan" class="w-full rounded border px-3 py-2">
                    @foreach ($jabatanPilihan as $nilai => $label)<option value="{{ $nilai }}">{{ $label }}</option>@endforeach
                </select>
                <input wire:model="form.jabatan_teks" placeholder="Nama jabatan (bebas)" class="w-full rounded border px-3 py-2">
                <input wire:model="form.nim" placeholder="NIM" class="w-full rounded border px-3 py-2">
                <input wire:model="form.prodi" placeholder="Program studi" class="w-full rounded border px-3 py-2">
                <input wire:model="form.telepon" placeholder="Telepon" class="w-full rounded border px-3 py-2">
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="form.tampil_publik"> Tampil di halaman publik</label>
                <label class="flex items-center gap-2"><input type="checkbox" wire:model="form.narahubung"> Narahubung</label>
                <div class="flex gap-2">
                    <button type="button" wire:click="simpanPengurus" class="rounded bg-blue-900 px-4 py-2 text-white">Simpan</button>
                    @if ($pengurusId)<button type="button" wire:click="batal" class="rounded border px-4 py-2">Batal</button>@endif
                </div>
            </div>
        @endif
    </section>
</div>
