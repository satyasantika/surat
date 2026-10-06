{{-- Templat awal; dirancang ulang pada F5.1. Semua keluaran lewat {{ }} (tanpa HTML mentah). --}}
<!DOCTYPE html>
<html lang="id">
<head><meta charset="utf-8"><title>{{ $perihal ?? '' }}</title></head>
<body>
    <h1>{{ $perihal ?? '' }}</h1>
    @foreach (($data ?? []) as $kunci => $nilai)
        <p><strong>{{ $kunci }}</strong>: {{ $nilai }}</p>
    @endforeach
    <div>{{ $isi ?? '' }}</div>
</body>
</html>
