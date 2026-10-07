<?php

namespace App\Contracts;

use App\Exceptions\LayananRuanganTidakTersedia;
use App\Exceptions\RuanganBentrok;
use Carbon\CarbonInterface;

/** Sumber data ruangan dan pemakaiannya (BR-12): Aset (API) atau lokal. */
interface LayananRuangan
{
    /** @return list<array{kode: string, nama: string, gedung: ?string, kapasitas: ?int, fasilitas: ?string}> ruangan yang dapat dipesan */
    public function daftar(): array;

    /**
     * Pemakaian yang sudah tercatat pada rentang (inklusif).
     *
     * @return list<array{tanggal: string, sesi: string, keterangan: ?string}>
     *
     * @throws LayananRuanganTidakTersedia
     */
    public function jadwal(string $kode, CarbonInterface $dari, CarbonInterface $sampai): array;

    /**
     * Mencatat pemakaian. Idempoten untuk $referensi yang sama. Mengembalikan id pemakaian.
     *
     * @throws RuanganBentrok bila sesi sudah dipakai referensi lain
     * @throws LayananRuanganTidakTersedia
     */
    public function catatPemakaian(string $kode, string $tanggal, string $sesi, ?string $referensi = null, ?string $keterangan = null): string;
}
