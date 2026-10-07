<?php

namespace App\Support\Migrasi;

use App\Models\ImporLog;

/** Keadaan satu kali impor: batch, pemetaan, peta id lama → baru, dan penghitung laporan. */
class KonteksImpor
{
    /** @var array<string, array<string, string>> sheet => id lama => id baru (atau kode) */
    public array $peta = [];

    /** @var array<string, array<string, int>> sheet => status => jumlah */
    public array $hitung = [];

    /** @var list<array{sheet: string, id: string, status: string, pesan: ?string}> */
    public array $catatan = [];

    public function __construct(
        public readonly string $batch,
        public readonly Pemetaan $pemetaan,
        public readonly string $modeRuangan,
    ) {}

    public function catat(string $sheet, string $idLama, string $status, ?string $pesan = null, ?string $tabel = null, ?string $idBaru = null): void
    {
        ImporLog::create([
            'batch' => $this->batch, 'sheet' => $sheet, 'id_lama' => mb_substr($idLama, 0, 100),
            'tabel_baru' => $tabel, 'id_baru' => $idBaru, 'status' => $status, 'pesan' => $pesan,
        ]);

        $this->hitung[$sheet][$status] = ($this->hitung[$sheet][$status] ?? 0) + 1;

        if ($status !== ImporLog::OK) {
            $this->catatan[] = ['sheet' => $sheet, 'id' => $idLama, 'status' => $status, 'pesan' => $pesan];
        }

        if ($idBaru !== null) {
            $this->peta[$sheet][$idLama] = $idBaru;
        }
    }

    /** id baru dari impor sebelumnya (idempotensi); null bila baris belum pernah berhasil diimpor. */
    public function sebelumnya(string $sheet, string $idLama): ?string
    {
        return ImporLog::where('sheet', $sheet)->where('id_lama', mb_substr($idLama, 0, 100))
            ->whereNotNull('id_baru')->whereIn('status', [ImporLog::OK, ImporLog::PERINGATAN, ImporLog::LEWATI])
            ->latest('created_at')->latest('id')->value('id_baru');
    }

    public function jumlah(string $sheet, string $status): int
    {
        return $this->hitung[$sheet][$status] ?? 0;
    }
}
