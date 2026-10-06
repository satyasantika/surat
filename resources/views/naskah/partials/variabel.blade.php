@php($defs = collect($k['variabel'])->reject(fn ($v) => $v['kunci'] === 'lampiran'))
@if ($defs->isNotEmpty())
    <table class="meta">
        @foreach ($defs as $v)
            @php($nilai = $k['data'][$v['kunci']] ?? null)
            @if (filled($nilai))
                <tr>
                    <td>{{ $v['label'] }}</td><td>:</td>
                    <td>@if (($v['tipe'] ?? '') === 'date'){{ \Carbon\CarbonImmutable::parse($nilai)->locale('id')->translatedFormat('d F Y') }}@elseif (($v['tipe'] ?? '') === 'select'){{ $v['opsi'][$nilai] ?? $nilai }}@else{{ $nilai }}@endif</td>
                </tr>
            @endif
        @endforeach
    </table>
@endif
