<?php

namespace App\Actions\Disposisi;

use App\Models\Disposisi;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Support\Pengaturan;
use Illuminate\Support\Facades\Gate;

/** HTML lembar disposisi; perihal dan ringkasan disamarkan bila pelaku tidak berhak membuka isi surat rahasia. */
class HtmlLembarDisposisi
{
    public function jalankan(SuratMasuk $surat, User $pelaku): string
    {
        $bolehIsi = Gate::forUser($pelaku)->allows('view', $surat);

        $disposisi = Disposisi::with(['dari', 'dariJabatan', 'penerima.user', 'penerima.jabatan'])
            ->where('surat_masuk_id', $surat->getKey())
            ->orderBy('created_at')->orderBy('id')
            ->get();

        // Kedalaman rantai: disposisi awal = 0, lanjutan = kedalaman induk + 1.
        $kedalaman = [];
        $penerimaKeDisposisi = [];
        foreach ($disposisi as $d) {
            foreach ($d->penerima as $p) {
                $penerimaKeDisposisi[$p->getKey()] = $d->getKey();
            }
        }

        foreach ($disposisi as $d) {
            $kedalaman[$d->getKey()] = $d->induk_penerima_id
                ? ($kedalaman[$penerimaKeDisposisi[$d->induk_penerima_id] ?? ''] ?? 0) + 1
                : 0;
        }

        return view('pdf.lembar-disposisi', [
            'kop' => Pengaturan::get('kop_surat'),
            'surat' => $surat,
            'perihal' => $bolehIsi || ! $surat->klasifikasi_keamanan->tertutup() ? $surat->perihal : SuratMasuk::TOPENGAN,
            'ringkasan' => $bolehIsi ? $surat->ringkasan : null,
            'asal' => $surat->asal,
            'disposisi' => $disposisi,
            'kedalaman' => $kedalaman,
            'instruksiTercentang' => $disposisi->flatMap(fn (Disposisi $d) => $d->instruksi)->unique()->all(),
            'instruksiPilihan' => Disposisi::INSTRUKSI,
        ])->render();
    }
}
