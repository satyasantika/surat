<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $k['perihal'] }}</title>
    <style>
        @page { size: A4; margin: 2.5cm 2.5cm 2.5cm 3cm; }
        body { font-family: "Times New Roman", "DejaVu Serif", serif; font-size: 12pt; line-height: 1.35; color: #000; }
        .kop { text-align: center; border-bottom: 3px double #000; padding-bottom: 6px; margin-bottom: 14px; }
        .kop p { margin: 0; font-weight: bold; text-transform: uppercase; }
        table.meta { border-collapse: collapse; margin-bottom: 12px; }
        table.meta td { padding: 1px 6px 1px 0; vertical-align: top; }
        .isi { margin: 12px 0; text-align: justify; }
        .isi table { border-collapse: collapse; width: 100%; }
        .isi td, .isi th { border: 1px solid #000; padding: 3px 5px; }
        .ttd { margin-top: 28px; margin-left: 55%; }
        .ttd .ruang { height: 70px; }
        .tembusan { margin-top: 24px; font-size: 11pt; }
        .qr { margin-top: 12px; }
        .qr img { width: 80px; height: 80px; }
        .draf { color: #b00; font-weight: bold; text-align: center; margin-bottom: 8px; }
    </style>
</head>
<body>
    @if (($k['status'] ?? null) === 'draf' || ($k['status'] ?? null) === 'dikembalikan')
        <p class="draf">DRAF — BELUM BERLAKU</p>
    @endif

    <div class="kop">
        @foreach ($k['kop'] as $baris)
            <p>{{ $baris }}</p>
        @endforeach
    </div>

    <table class="meta">
        <tr><td>Nomor</td><td>:</td><td>{{ $k['nomor'] ?: '(diberikan saat ditandatangani)' }}</td></tr>
        <tr><td>Sifat</td><td>:</td><td>{{ $k['sifat'] }}</td></tr>
        <tr><td>Lampiran</td><td>:</td><td>{{ $k['data']['lampiran'] ?? '-' }}</td></tr>
        <tr><td>Hal</td><td>:</td><td>{{ $k['perihal'] }}</td></tr>
    </table>

    @if (! empty($k['tujuan']))
        <p>Yth.<br>
            @foreach ($k['tujuan'] as $nama){{ $nama }}@if (! $loop->last)<br>@endif @endforeach
        </p>
    @endif

    @yield('badan')

    @if (filled($k['isi'] ?? null))
        <div class="isi"><x-isi-aman :html="$k['isi']" /></div>
    @endif

    <div class="ttd">
        @if (! empty($k['tanggal']))
            <p>{{ \Carbon\CarbonImmutable::parse($k['tanggal'])->locale('id')->translatedFormat('d F Y') }}</p>
        @endif
        @if (! empty($k['atas_nama']))
            <p>{{ ['an' => 'a.n.', 'ub' => 'u.b.', 'plt' => 'Plt.', 'plh' => 'Plh.'][$k['atas_nama']] ?? '' }} {{ $k['penanda_tangan']['jabatan_dasar'] ?? '' }}</p>
        @endif
        <p>{{ $k['penanda_tangan']['jabatan'] }},</p>
        <div class="ruang">
            @if (! empty($k['ttd_gambar']))
                <img src="{{ $k['ttd_gambar'] }}" alt="" style="height: 64px;">
            @endif
        </div>
        <p><strong>{{ $k['penanda_tangan']['nama'] ?? '________________' }}</strong>
            @if (! empty($k['penanda_tangan']['nip']))<br>NIP {{ $k['penanda_tangan']['nip'] }}@endif</p>
    </div>

    @if (! empty($k['qr']))
        <div class="qr"><img src="{{ $k['qr'] }}" alt="Kode QR verifikasi"></div>
    @endif

    @if (! empty($k['tembusan']))
        <div class="tembusan">
            <p>Tembusan:</p>
            <ol>
                @foreach ($k['tembusan'] as $nama)<li>{{ $nama }}</li>@endforeach
            </ol>
        </div>
    @endif
</body>
</html>
