<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Lembar Disposisi {{ $surat->nomor_agenda }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        .kop { text-align: center; border-bottom: 2px solid #111; padding-bottom: 6px; margin-bottom: 10px; }
        .kop p { margin: 0; font-weight: bold; text-transform: uppercase; }
        h1 { font-size: 14px; text-align: center; margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        td, th { border: 1px solid #333; padding: 4px 6px; vertical-align: top; text-align: left; }
        th { background: #eee; width: 28%; }
        .instruksi span { display: inline-block; width: 48%; }
    </style>
</head>
<body>
    <div class="kop">
        @foreach ($kop as $baris)
            <p>{{ $baris }}</p>
        @endforeach
    </div>

    <h1>LEMBAR DISPOSISI</h1>

    <table>
        <tr><th>Nomor agenda</th><td>{{ $surat->nomor_agenda }}</td></tr>
        <tr><th>Tanggal diterima</th><td>{{ $surat->tanggal_terima->translatedFormat('d F Y H:i') }}</td></tr>
        <tr><th>Nomor surat</th><td>{{ $surat->nomor_surat }}</td></tr>
        <tr><th>Tanggal surat</th><td>{{ $surat->tanggal_surat->translatedFormat('d F Y') }}</td></tr>
        <tr><th>Asal</th><td>{{ $asal }}</td></tr>
        <tr><th>Perihal</th><td>{{ $perihal }}</td></tr>
        @if ($ringkasan)
            <tr><th>Isi ringkas</th><td>{{ $ringkasan }}</td></tr>
        @endif
        @if ($surat->lampiran)
            <tr><th>Lampiran</th><td>{{ $surat->lampiran }}</td></tr>
        @endif
        <tr><th>Klasifikasi keamanan</th><td>{{ $surat->klasifikasi_keamanan->label() }}</td></tr>
        <tr><th>Derajat kecepatan</th><td>{{ $surat->derajat_kecepatan->label() }}</td></tr>
    </table>

    <table>
        <tr><th>Instruksi</th>
            <td class="instruksi">
                @foreach ($instruksiPilihan as $kunci => $label)
                    <span>{{ in_array($kunci, $instruksiTercentang, true) ? '[x]' : '[ ]' }} {{ $label }}</span>
                @endforeach
            </td>
        </tr>
    </table>

    @forelse ($disposisi as $d)
        <table style="margin-left: {{ ($kedalaman[$d->id] ?? 0) * 18 }}px; width: auto; min-width: 70%;">
            <tr><th>{{ ($kedalaman[$d->id] ?? 0) === 0 ? 'Disposisi' : 'Disposisi lanjutan' }}</th>
                <td>Dari {{ $d->dari->name }}@if ($d->dariJabatan) ({{ $d->dariJabatan->nama }})@endif,
                    {{ $d->created_at->translatedFormat('d F Y H:i') }}</td></tr>
            <tr><th>Kepada</th>
                <td>
                    @foreach ($d->penerima as $p)
                        {{ $p->user->name }}@if ($p->jabatan) ({{ $p->jabatan->nama }})@endif — {{ $p->status->label() }}@if (! $loop->last)<br>@endif
                    @endforeach
                </td></tr>
            <tr><th>Instruksi</th><td>{{ collect($d->instruksi)->map(fn ($i) => $instruksiPilihan[$i] ?? $i)->implode(', ') ?: '—' }}</td></tr>
            @if ($d->catatan)<tr><th>Catatan</th><td>{{ $d->catatan }}</td></tr>@endif
            <tr><th>Batas waktu</th><td>{{ $d->batas_waktu->translatedFormat('d F Y H:i') }}</td></tr>
        </table>
    @empty
        <p>Belum ada disposisi.</p>
    @endforelse
</body>
</html>
