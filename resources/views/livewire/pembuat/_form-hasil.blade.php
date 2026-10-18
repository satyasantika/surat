<div class="space-y-2">
    <label class="block">
        <span class="font-medium">Tautan hasil cetak</span>
        <input type="url" wire:model="url" maxlength="2048" placeholder="https://drive.google.com/file/d/..." class="mt-1 w-full rounded border px-3 py-2">
    </label>
    <p class="text-xs text-slate-500">Tautan satu berkas (Google Drive/Docs atau unsil.ac.id), bukan unggahan. Bagikan sebagai &ldquo;Siapa saja yang memiliki link&rdquo; agar pengirim eksternal dapat membukanya; bila memuat data pribadi, bagikan terbatas dan beri tahu pengirim.</p>
    <textarea wire:model="catatan" rows="2" maxlength="1000" class="w-full rounded border px-3 py-2" placeholder="Catatan untuk riwayat (opsional)"></textarea>
    <button type="button" wire:click="{{ $aksi }}" wire:confirm="Kirim hasil kepada pengirim dan tutup proses ini?" class="rounded bg-green-700 px-4 py-2 text-white">Kirim hasil ke pengirim</button>
</div>
