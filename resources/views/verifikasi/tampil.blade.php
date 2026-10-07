<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Verifikasi naskah — {{ config('app.name') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f8fafc; color: #1e293b; margin: 0; padding: 16px; }
        main { max-width: 640px; margin: 0 auto; }
        h1 { font-size: 1.1rem; color: #1e3a8a; }
        .kartu { background: #fff; border-radius: 8px; padding: 16px; box-shadow: 0 1px 3px rgba(0,0,0,.15); margin-bottom: 12px; }
        .status { display: inline-block; padding: 4px 12px; border-radius: 999px; font-weight: 600; }
        .berlaku { background: #dcfce7; color: #166534; }
        .batal { background: #fee2e2; color: #991b1b; }
        dl { margin: 12px 0 0; } dt { font-size: .75rem; color: #64748b; margin-top: 10px; } dd { margin: 0; word-break: break-word; }
        .hash { font-family: ui-monospace, monospace; font-size: .8rem; }
        input[type=text] { width: 100%; box-sizing: border-box; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px; }
        #hasil { margin-top: 8px; font-weight: 600; }
    </style>
</head>
<body>
<main>
    <h1>{{ config('app.name') }} — Verifikasi Naskah</h1>

    <section class="kartu" aria-live="polite">
        @if ($dibatalkan)
            <span class="status batal">DIBATALKAN</span>
            <p>Naskah ini telah dibatalkan{{ $tanggalBatal ? ' pada '.$tanggalBatal : '' }}.@if ($alasanBatal) Alasan: {{ $alasanBatal }}@endif</p>
        @else
            <span class="status berlaku">BERLAKU</span>
        @endif

        <dl>
            <dt>Nomor</dt><dd>{{ $nomor }}</dd>
            <dt>Tanggal</dt><dd>{{ $tanggal }}</dd>
            <dt>Jenis naskah</dt><dd>{{ $jenis }}</dd>
            <dt>Perihal</dt><dd>{{ $perihal ?? 'Tidak ditampilkan (klasifikasi terbatas)' }}</dd>
            <dt>Penanda tangan</dt>
            <dd>{{ $penandaTangan }}<br>@if ($jabatanDasar)a.n. {{ $jabatanDasar }}, @endif{{ $jabatan }}</dd>
            <dt>Mode tanda tangan</dt><dd>{{ $mode }}</dd>
            @if ($hash)
                <dt>SHA-256 dokumen PDF</dt><dd class="hash" id="hash-tercatat">{{ $hash }}</dd>
            @elseif ($migrasi)
                <dt>Keterangan</dt><dd>Naskah migrasi dari sistem lama: tidak memiliki hash dokumen.</dd>
            @endif
        </dl>
    </section>

    @if ($hash)
        <section class="kartu">
            <h2 style="font-size:1rem;margin-top:0">Cocokkan dokumen</h2>
            <p>Tempel hash SHA-256 dokumen Anda, atau pilih berkas PDF. Berkas dihitung di peramban dan <strong>tidak diunggah</strong>.</p>
            <label for="hash-input">Hash SHA-256</label>
            <input type="text" id="hash-input" autocomplete="off" spellcheck="false" placeholder="64 karakter heksadesimal">
            <p><label for="berkas">atau pilih berkas</label><br><input type="file" id="berkas" accept="application/pdf"></p>
            <p id="hasil" role="status"></p>
        </section>

        <script nonce="{{ $nonce }}">
            (function () {
                var tercatat = document.getElementById('hash-tercatat').textContent.trim().toLowerCase();
                var hasil = document.getElementById('hasil');
                function tampil(cocok) {
                    hasil.textContent = cocok ? 'COCOK — dokumen sesuai dengan yang tercatat.' : 'TIDAK COCOK — dokumen berbeda dari yang tercatat.';
                    hasil.style.color = cocok ? '#166534' : '#991b1b';
                }
                document.getElementById('hash-input').addEventListener('input', function (e) {
                    var v = e.target.value.trim().toLowerCase();
                    if (v.length === 64) { tampil(v === tercatat); } else { hasil.textContent = ''; }
                });
                document.getElementById('berkas').addEventListener('change', function (e) {
                    var f = e.target.files[0];
                    if (!f) { return; }
                    f.arrayBuffer().then(function (buf) { return crypto.subtle.digest('SHA-256', buf); }).then(function (digest) {
                        var hex = Array.prototype.map.call(new Uint8Array(digest), function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
                        tampil(hex === tercatat);
                    });
                });
            })();
        </script>
    @endif
</main>
</body>
</html>
