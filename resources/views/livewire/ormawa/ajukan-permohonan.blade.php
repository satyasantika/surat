<div class="space-y-4">
    <a href="{{ route('ormawa', ['o' => $ormawa->id]) }}" class="text-sm text-blue-800 underline">&larr; Kembali</a>
    <h2 class="text-lg font-semibold">Ajukan permohonan — {{ $ormawa->nama }}</h2>
    <p class="text-sm text-slate-600" aria-live="polite">Langkah {{ $langkah }} dari 4</p>

    @if ($errors->any())
        <ul class="rounded bg-red-50 p-3 text-sm text-red-800" role="alert">@foreach ($errors->all() as $galat)<li>{{ $galat }}</li>@endforeach</ul>
    @endif

    @if ($langkah === 1)
        <section class="space-y-3 rounded-lg bg-white p-4 shadow">
            <label class="block text-sm">Jenis permohonan
                <select wire:model.live="jenis" class="mt-1 w-full rounded border px-3 py-2">
                    @foreach ($jenisDaftar as $j)<option value="{{ $j->id }}">{{ $j->nama }}</option>@endforeach
                </select>
            </label>
            <label class="block text-sm">Nama kegiatan <input wire:model="nama_kegiatan" class="mt-1 w-full rounded border px-3 py-2"></label>
            <label class="block text-sm">Perihal <input wire:model="perihal" class="mt-1 w-full rounded border px-3 py-2"></label>
            <label class="block text-sm">Nomor surat ormawa <input wire:model="nomor_surat_ormawa" class="mt-1 w-full rounded border px-3 py-2"></label>
            <div class="grid grid-cols-2 gap-3">
                <label class="block text-sm">Tanggal mulai <input type="date" wire:model.live="tanggal_mulai" class="mt-1 w-full rounded border px-3 py-2"></label>
                <label class="block text-sm">Tanggal selesai <input type="date" wire:model.live="tanggal_selesai" class="mt-1 w-full rounded border px-3 py-2"></label>
                <label class="block text-sm">Jam mulai <input type="time" wire:model="jam_mulai" class="mt-1 w-full rounded border px-3 py-2"></label>
                <label class="block text-sm">Jam selesai <input type="time" wire:model="jam_selesai" class="mt-1 w-full rounded border px-3 py-2"></label>
            </div>
            @if ($terlambat)
                <p class="rounded bg-amber-50 p-3 text-sm text-amber-900" role="status">Permohonan sebaiknya diajukan paling lambat {{ $minHari }} hari sebelum kegiatan. Isi alasan mendesak di bawah.</p>
                <label class="block text-sm">Alasan mendesak <textarea wire:model="alasan_mendesak" rows="2" class="mt-1 w-full rounded border px-3 py-2"></textarea></label>
            @endif
            <label class="block text-sm">Tempat lain (di luar ruangan FKIP) <input wire:model="tempat_lain" class="mt-1 w-full rounded border px-3 py-2"></label>
            <label class="block text-sm">Deskripsi kegiatan <textarea wire:model="deskripsi" rows="4" class="mt-1 w-full rounded border px-3 py-2"></textarea></label>
        </section>
    @elseif ($langkah === 2)
        <section class="space-y-3 rounded-lg bg-white p-4 shadow">
            @if (! $jenisTerpilih?->butuh_ruangan)
                <p class="text-sm text-slate-600">Jenis permohonan ini tidak memakai ruangan FKIP.</p>
            @elseif ($layananGalat)
                <p class="rounded bg-red-50 p-3 text-sm text-red-800" role="alert">Layanan ruangan sedang tidak tersedia. Ketersediaan tidak dapat ditampilkan; coba lagi nanti.</p>
            @elseif (empty($ketersediaan))
                <p class="text-sm text-slate-600">Isi tanggal kegiatan (maks. 15 hari) pada langkah 1 untuk melihat ketersediaan.</p>
            @else
                <p class="text-sm text-slate-600">Pilih sesi per ruangan dan tanggal. Sesi yang terpakai tidak dapat dipilih.</p>
                @foreach ($ketersediaan as $baris)
                    <div class="flex flex-wrap items-center gap-2 border-b py-2 text-sm" wire:key="r-{{ $baris['kode'] }}-{{ $baris['tanggal'] }}">
                        <span class="w-full font-medium sm:w-auto">{{ $baris['nama'] }} · {{ \Carbon\CarbonImmutable::parse($baris['tanggal'])->translatedFormat('d M Y') }}</span>
                        <select wire:model="pilihanRuangan.{{ $baris['kode'].'|'.$baris['tanggal'] }}" class="rounded border px-2 py-1">
                            <option value="">— tidak dipakai —</option>
                            @foreach ($baris['sesi'] as $kodeSesi => $kosong)
                                <option value="{{ $kodeSesi }}" @disabled(! $kosong)>{{ $kodeSesi }}{{ $kosong ? '' : ' (terpakai)' }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            @endif
        </section>
    @elseif ($langkah === 3)
        <section class="space-y-3 rounded-lg bg-white p-4 shadow">
            <h3 class="font-medium">Penanggung jawab</h3>
            <input wire:model="pj.ketua.nama" placeholder="Nama ketua pelaksana" class="w-full rounded border px-3 py-2">
            <div class="grid grid-cols-2 gap-3">
                <input wire:model="pj.ketua.nim" placeholder="NIM" class="rounded border px-3 py-2">
                <input wire:model="pj.ketua.hp" placeholder="No. HP" class="rounded border px-3 py-2">
                <input wire:model="pj.ketua.prodi" placeholder="Program studi" class="rounded border px-3 py-2">
                <input wire:model="pj.ketua.email" placeholder="Surel" class="rounded border px-3 py-2">
            </div>
            <input wire:model="pj.wakil.nama" placeholder="Nama wakil (opsional)" class="w-full rounded border px-3 py-2">
            <input wire:model="pj.sekretaris.nama" placeholder="Nama sekretaris (opsional)" class="w-full rounded border px-3 py-2">

            @if ($jenisTerpilih?->butuh_fasilitas_rektorat)
                <h3 class="font-medium">Fasilitas rektorat</h3>
                @foreach ($fasilitas as $i => $f)
                    <div class="grid grid-cols-6 gap-2" wire:key="f-{{ $i }}">
                        <input wire:model="fasilitas.{{ $i }}.nama" placeholder="Fasilitas" class="col-span-3 rounded border px-2 py-1">
                        <input wire:model="fasilitas.{{ $i }}.jumlah" type="number" min="1" class="col-span-1 rounded border px-2 py-1">
                        <button type="button" wire:click="hapusFasilitas({{ $i }})" class="col-span-2 text-red-700 underline">Hapus</button>
                    </div>
                @endforeach
                <button type="button" wire:click="tambahFasilitas" class="rounded border px-3 py-1 text-sm">Tambah fasilitas</button>
            @endif
        </section>
    @else
        <section class="space-y-3 rounded-lg bg-white p-4 shadow">
            <h3 class="font-medium">Berkas (tautan)</h3>
            <p class="text-sm text-slate-600">Tempel tautan berkas di Google Drive/OneDrive/domain unsil.ac.id. Dokumen berisi data pribadi: bagikan terbatas ke domain unsil.ac.id.</p>
            @foreach ($jenisTerpilih?->berkas_wajib ?? [] as $kunci)
                <label class="block text-sm">{{ str_replace('_', ' ', ucfirst($kunci)) }}
                    <input wire:model="berkas.{{ $kunci }}" type="url" placeholder="https://" class="mt-1 w-full rounded border px-3 py-2">
                </label>
            @endforeach
            <button type="button" wire:click="kirim" wire:loading.attr="disabled" class="w-full rounded bg-blue-900 px-4 py-2 text-white">Kirim permohonan</button>
        </section>
    @endif

    <div class="flex justify-between">
        @if ($langkah > 1)<button type="button" wire:click="kembali" class="rounded border px-4 py-2">Sebelumnya</button>@else<span></span>@endif
        @if ($langkah < 4)<button type="button" wire:click="lanjut" class="rounded bg-blue-900 px-4 py-2 text-white">Lanjut</button>@endif
    </div>
</div>
