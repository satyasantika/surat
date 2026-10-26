<div class="space-y-4">
    <a href="{{ route('pengirim') }}" class="text-sm text-blue-800 underline">&larr; Kembali</a>
    <h2 class="text-lg font-semibold">Kirim surat ke fakultas, ormawa, atau program studi/laboratorium</h2>
    <p class="text-sm text-slate-600">Pilih satu atau beberapa tujuan. Tiap tujuan menerima kiriman sendiri dan memprosesnya secara terpisah: fakultas (Dekan FKIP Universitas Siliwangi) meregistrasi dan memproses surat; ormawa atau program studi/laboratorium dapat menindaklanjuti, mengembalikan, atau mengarsipkannya. Kontak Anda hanya dipakai untuk mengabarkan hasilnya dan tidak ditampilkan publik.</p>

    @if ($errors->any())
        <ul class="rounded bg-red-50 p-3 text-sm text-red-800" role="alert">@foreach ($errors->all() as $galat)<li>{{ $galat }}</li>@endforeach</ul>
    @endif

    <form wire:submit="kirim" class="space-y-3 rounded-lg bg-white p-4 shadow">
        <fieldset class="space-y-2">
            <legend class="text-sm">Tujuan <span class="text-slate-500">(minimal satu; paling banyak {{ $batasTujuan }} ormawa/program studi/laboratorium)</span></legend>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="tujuan" value="fakultas"> Fakultas (Dekan FKIP)</label>
            @if ($ormawaDaftar->isNotEmpty())
                <p class="pt-1 text-xs font-medium text-slate-500">Ormawa</p>
                @foreach ($ormawaDaftar as $o)
                    <label class="flex items-center gap-2 text-sm" wire:key="t-o-{{ $o->id }}"><input type="checkbox" wire:model="tujuan" value="ormawa:{{ $o->id }}"> {{ $o->nama }}</label>
                @endforeach
            @endif
            @if ($unitDaftar->isNotEmpty())
                <p class="pt-1 text-xs font-medium text-slate-500">Program studi / laboratorium</p>
                @foreach ($unitDaftar as $un)
                    <label class="flex items-center gap-2 text-sm" wire:key="t-u-{{ $un->id }}"><input type="checkbox" wire:model="tujuan" value="unit:{{ $un->id }}"> {{ $un->nama }}</label>
                @endforeach
            @endif
        </fieldset>
        <label class="block text-sm">Nama pengirim <input wire:model="nama" class="mt-1 w-full rounded border px-3 py-2" maxlength="150"></label>
        <label class="block text-sm">Instansi / organisasi <span class="text-slate-500">(opsional)</span>
            <input wire:model="instansi" class="mt-1 w-full rounded border px-3 py-2" maxlength="255"></label>
        <label class="block text-sm">Perihal <input wire:model="perihal" class="mt-1 w-full rounded border px-3 py-2" maxlength="500"></label>
        <label class="block text-sm">Uraian singkat <textarea wire:model="uraian" rows="5" class="mt-1 w-full rounded border px-3 py-2"></textarea></label>
        <label class="block text-sm">Tautan berkas surat <span class="text-slate-500">(opsional; Google Drive atau tautan resmi lain)</span>
            <input wire:model="lampiran" class="mt-1 w-full rounded border px-3 py-2" placeholder="https://drive.google.com/…">
        </label>
        <fieldset class="space-y-2">
            <legend class="text-sm">Kontak <span class="text-slate-500">(isi minimal salah satu)</span></legend>
            <label class="block text-sm">Surel <input type="email" wire:model="surel" class="mt-1 w-full rounded border px-3 py-2" maxlength="150"></label>
            <label class="block text-sm">WhatsApp <input wire:model="wa" class="mt-1 w-full rounded border px-3 py-2" maxlength="20" placeholder="08…"></label>
        </fieldset>
        <button type="submit" class="rounded bg-blue-900 px-4 py-2 text-white">Kirim surat</button>
    </form>
</div>
