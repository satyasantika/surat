{{-- Pratinjau impor tempel pengguna (ListUsers::pratinjau). Gaya ditulis sendiri (bukan utilitas Tailwind) karena CSS
     Filament hanya memuat kelas yang dipakainya; mode gelap lewat .dark. --}}
@php
    /** @var array{keadaan: string, baru: list<array<string, mixed>>, jumlah_baru: int, dilewati: list<string>, galat: list<array{baris: ?int, pesan: string}>} $pratinjau */
    $keadaan = $pratinjau['keadaan'];
    $jumlahGalat = count($pratinjau['galat']);
    $jumlahLewati = count($pratinjau['dilewati']);
@endphp

<style>
    .sr-pt { display: flex; flex-direction: column; gap: .875rem; font-size: .875rem; color: #0f172a; }
    .dark .sr-pt { color: #e2e8f0; }
    .sr-pt-judul { font-size: .875rem; font-weight: 600; }

    .sr-pt-ringkas { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .625rem; }
    .sr-pt-angka { border-radius: .625rem; padding: .625rem .875rem; border: 1px solid #e2e8f0; background: #f8fafc; }
    .sr-pt-angka b { display: block; font-size: 1.375rem; line-height: 1.75rem; font-weight: 700; font-variant-numeric: tabular-nums; }
    .sr-pt-angka span { font-size: .75rem; color: #64748b; }
    .sr-pt-angka.baru { border-color: #bfdbfe; background: #eff6ff; } .sr-pt-angka.baru b { color: #1e3a8a; }
    .sr-pt-angka.galat { border-color: #fecaca; background: #fef2f2; } .sr-pt-angka.galat b { color: #b91c1c; }
    .sr-pt-angka.nol b { color: #94a3b8; }
    .dark .sr-pt-angka { border-color: rgba(255,255,255,.1); background: rgba(255,255,255,.03); }
    .dark .sr-pt-angka span { color: #94a3b8; }
    .dark .sr-pt-angka.baru { border-color: rgba(96,165,250,.35); background: rgba(59,130,246,.1); } .dark .sr-pt-angka.baru b { color: #93c5fd; }
    .dark .sr-pt-angka.galat { border-color: rgba(248,113,113,.35); background: rgba(248,113,113,.1); } .dark .sr-pt-angka.galat b { color: #fca5a5; }
    .dark .sr-pt-angka.nol b { color: #64748b; }

    .sr-pt-status { display: flex; align-items: flex-start; gap: .5rem; border-radius: .5rem; padding: .625rem .875rem; line-height: 1.35rem; }
    .sr-pt-status svg { width: 1.125rem; height: 1.125rem; flex-shrink: 0; margin-top: .1rem; }
    .sr-pt-status.siap { background: #f0fdf4; color: #166534; box-shadow: inset 0 0 0 1px rgba(22,163,74,.2); }
    .sr-pt-status.galat { background: #fef2f2; color: #991b1b; box-shadow: inset 0 0 0 1px rgba(220,38,38,.2); }
    .sr-pt-status.kosong { background: #f8fafc; color: #475569; box-shadow: inset 0 0 0 1px #e2e8f0; }
    .dark .sr-pt-status.siap { background: rgba(74,222,128,.1); color: #86efac; }
    .dark .sr-pt-status.galat { background: rgba(248,113,113,.1); color: #fca5a5; }
    .dark .sr-pt-status.kosong { background: rgba(255,255,255,.03); color: #94a3b8; box-shadow: inset 0 0 0 1px rgba(255,255,255,.1); }

    .sr-pt-wadah { max-height: 22rem; overflow: auto; border: 1px solid #e2e8f0; border-radius: .625rem; }
    .dark .sr-pt-wadah { border-color: rgba(255,255,255,.1); }
    .sr-pt table { width: 100%; border-collapse: collapse; }
    .sr-pt th { position: sticky; top: 0; z-index: 1; background: #f1f5f9; text-align: left; font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #475569; padding: .5rem .75rem; white-space: nowrap; }
    .sr-pt td { padding: .5rem .75rem; border-top: 1px solid #f1f5f9; vertical-align: top; }
    .sr-pt tbody tr:hover td { background: #f8fafc; }
    .dark .sr-pt th { background: #1e293b; color: #94a3b8; }
    .dark .sr-pt td { border-top-color: rgba(255,255,255,.06); }
    .dark .sr-pt tbody tr:hover td { background: rgba(255,255,255,.03); }
    .sr-pt .no { width: 1%; white-space: nowrap; color: #94a3b8; font-variant-numeric: tabular-nums; }
    .sr-pt .surel { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .8125rem; word-break: break-all; }
    .sr-pt .redup { color: #94a3b8; }
    .sr-pt .tak-wrap { white-space: nowrap; font-variant-numeric: tabular-nums; }
    .sr-pt-peran { display: inline-block; margin: 0 .25rem .25rem 0; padding: .0625rem .5rem; border-radius: 999px; font-size: .75rem; font-weight: 500; background: #e0e7ff; color: #1e3a8a; white-space: nowrap; }
    .dark .sr-pt-peran { background: rgba(99,102,241,.2); color: #c7d2fe; }
    .sr-pt-galat td.no { color: #b91c1c; font-weight: 600; }
    .dark .sr-pt-galat td.no { color: #fca5a5; }

    .sr-pt-contoh { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); gap: 1px; border-radius: .5rem; overflow: hidden; background: #e2e8f0; font-size: .75rem; }
    .sr-pt-contoh div { background: #fff; padding: .375rem .5rem; }
    .sr-pt-contoh .kepala { background: #f1f5f9; font-weight: 600; color: #475569; }
    .sr-pt-contoh .wajib::after { content: " *"; color: #dc2626; }
    .dark .sr-pt-contoh { background: rgba(255,255,255,.1); }
    .dark .sr-pt-contoh div { background: #0f172a; }
    .dark .sr-pt-contoh .kepala { background: #1e293b; color: #94a3b8; }

    .sr-pt details summary { cursor: pointer; color: #475569; font-size: .8125rem; }
    .sr-pt details ul { margin: .5rem 0 0; padding-left: 1.25rem; columns: 2; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .8125rem; color: #64748b; }
    .dark .sr-pt details summary, .dark .sr-pt details ul { color: #94a3b8; }
    .sr-pt-catatan { font-size: .75rem; color: #64748b; margin: 0; }
    @media (max-width: 640px) { .sr-pt-ringkas { grid-template-columns: 1fr; } .sr-pt details ul { columns: 1; } }
</style>

<div class="sr-pt" aria-live="polite">
    <div class="sr-pt-judul">Pratinjau</div>

    @if ($keadaan === 'kosong')
        <div class="sr-pt-status kosong">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/></svg>
            <span>Tempel data di atas untuk melihat pratinjau. Urutan kolom (bertanda * wajib):</span>
        </div>
        <div class="sr-pt-contoh" aria-label="Urutan kolom">
            <div class="kepala wajib">Nama</div><div class="kepala wajib">Surel</div><div class="kepala">Peran</div><div class="kepala">NIP/NIM</div><div class="kepala">Telepon</div>
            <div>Budi Santoso</div><div>budi@unsil.ac.id</div><div>pegawai</div><div>19800101…</div><div>0812…</div>
            <div>Siti Aminah</div><div>siti@student.unsil.ac.id</div><div>pengurus-ormawa|mahasiswa</div><div>221010001</div><div class="redup">—</div>
        </div>
    @else
        <div class="sr-pt-ringkas">
            <div @class(['sr-pt-angka', 'baru' => $pratinjau['jumlah_baru'] > 0, 'nol' => $pratinjau['jumlah_baru'] === 0])><b>{{ $pratinjau['jumlah_baru'] }}</b><span>akun baru</span></div>
            <div @class(['sr-pt-angka', 'nol' => $jumlahLewati === 0])><b>{{ $jumlahLewati }}</b><span>dilewati (sudah terdaftar)</span></div>
            <div @class(['sr-pt-angka', 'galat' => $jumlahGalat > 0, 'nol' => $jumlahGalat === 0])><b>{{ $jumlahGalat }}</b><span>baris bermasalah</span></div>
        </div>

        @if ($keadaan === 'galat')
            <div class="sr-pt-status galat" role="alert">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
                <span>Perbaiki baris berikut di Excel lalu tempel ulang. Selama masih ada galat, tidak ada akun yang dibuat.</span>
            </div>
            <div class="sr-pt-wadah">
                <table class="sr-pt-galat">
                    <thead><tr><th>Baris</th><th>Masalah</th></tr></thead>
                    <tbody>
                        @foreach ($pratinjau['galat'] as $g)
                            <tr><td class="no">{{ $g['baris'] ?? '—' }}</td><td>{{ $g['pesan'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @elseif ($pratinjau['jumlah_baru'] === 0)
            <div class="sr-pt-status kosong">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/></svg>
                <span>Semua surel sudah terdaftar; tidak ada akun baru yang akan dibuat.</span>
            </div>
        @else
            <div class="sr-pt-status siap">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
                <span>Siap diimpor. Tekan <b>Impor</b> untuk membuat {{ $pratinjau['jumlah_baru'] }} akun.</span>
            </div>
            <div class="sr-pt-wadah">
                <table>
                    <thead><tr><th>Baris</th><th>Nama</th><th>Surel</th><th>Peran</th><th>NIP/NIM</th><th>Telepon</th></tr></thead>
                    <tbody>
                        @foreach ($pratinjau['baru'] as $akun)
                            <tr>
                                <td class="no">{{ $akun['baris'] }}</td>
                                <td>{{ $akun['name'] }}</td>
                                <td class="surel">{{ $akun['email'] }}</td>
                                <td>
                                    @forelse ($akun['peran'] as $peran)
                                        <span class="sr-pt-peran">{{ $peran }}</span>
                                    @empty
                                        <span class="redup">tanpa peran</span>
                                    @endforelse
                                </td>
                                <td class="tak-wrap">{{ $akun['nip_nim'] ?? '—' }}</td>
                                <td class="tak-wrap">{{ $akun['telepon'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if ($pratinjau['jumlah_baru'] > count($pratinjau['baru']))
                <p class="sr-pt-catatan">Menampilkan {{ count($pratinjau['baru']) }} dari {{ $pratinjau['jumlah_baru'] }} akun; semuanya tetap diimpor.</p>
            @endif
        @endif

        @if ($jumlahLewati > 0)
            <details>
                <summary>{{ $jumlahLewati }} surel dilewati karena sudah terdaftar (tidak diubah)</summary>
                <ul>
                    @foreach ($pratinjau['dilewati'] as $surel)
                        <li>{{ $surel }}</li>
                    @endforeach
                </ul>
            </details>
        @endif
    @endif
</div>
