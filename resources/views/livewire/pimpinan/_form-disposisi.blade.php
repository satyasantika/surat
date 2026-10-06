<div class="space-y-3 rounded border bg-slate-50 p-3">
    <fieldset>
        <legend class="font-medium">Penerima</legend>
        @foreach ($calonPenerima as $u)
            <label class="flex items-center gap-2 py-1"><input type="checkbox" wire:model="penerima" value="{{ $u->id }}"> {{ $u->name }}</label>
        @endforeach
    </fieldset>
    <fieldset>
        <legend class="font-medium">Instruksi</legend>
        @foreach ($instruksiPilihan as $nilai => $label)
            <label class="flex items-center gap-2 py-1"><input type="checkbox" wire:model="instruksi" value="{{ $nilai }}"> {{ $label }}</label>
        @endforeach
    </fieldset>
    <textarea wire:model="catatan" rows="2" class="w-full rounded border px-3 py-2" placeholder="Catatan"></textarea>
    <label class="block">Batas waktu (opsional)
        <input type="datetime-local" wire:model="batas" class="mt-1 w-full rounded border px-3 py-2">
    </label>
    <button type="button" wire:click="{{ $aksi }}" class="w-full rounded bg-blue-900 px-4 py-2 text-white">Kirim disposisi</button>
</div>
