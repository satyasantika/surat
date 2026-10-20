<div class="space-y-4">
    <a href="{{ route('mahasiswa') }}" class="text-sm text-blue-800 underline">&larr; Kembali</a>
    <h2 class="text-lg font-semibold">Ajukan usulan</h2>

    @if ($errors->any())
        <ul class="rounded bg-red-50 p-3 text-sm text-red-800" role="alert">@foreach ($errors->all() as $galat)<li>{{ $galat }}</li>@endforeach</ul>
    @endif

    <form wire:submit="kirim" class="space-y-3 rounded-lg bg-white p-4 shadow">
        <label class="block text-sm">Ditujukan kepada
            <select wire:model="tujuan" class="mt-1 w-full rounded border px-3 py-2">
                <option value="">— pilih —</option>
                <optgroup label="Organisasi mahasiswa">
                    @foreach ($ormawaDaftar as $o)<option value="ormawa:{{ $o->id }}">{{ $o->nama }}</option>@endforeach
                </optgroup>
                <optgroup label="Program studi / laboratorium">
                    @foreach ($unitDaftar as $u)<option value="unit:{{ $u->id }}">{{ $u->nama }}</option>@endforeach
                </optgroup>
            </select>
        </label>
        <label class="block text-sm">Perihal <input wire:model="perihal" class="mt-1 w-full rounded border px-3 py-2"></label>
        <label class="block text-sm">Uraian <textarea wire:model="uraian" rows="5" class="mt-1 w-full rounded border px-3 py-2"></textarea></label>
        <label class="block text-sm">Tautan lampiran <span class="text-slate-500">(opsional; Google Drive atau tautan resmi lain)</span>
            <input wire:model="lampiran" class="mt-1 w-full rounded border px-3 py-2" placeholder="https://drive.google.com/…">
        </label>
        <button type="submit" class="rounded bg-blue-900 px-4 py-2 text-white">Kirim usulan</button>
    </form>
</div>
