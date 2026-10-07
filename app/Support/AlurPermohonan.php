<?php

namespace App\Support;

use App\Enums\StatusPermohonan as S;
use App\Exceptions\TransisiTidakSah;

/** Peta transisi status permohonan yang sah (BR-14); dipakai semua Action. */
class AlurPermohonan
{
    /** @return array<string, list<S>> */
    public static function peta(): array
    {
        return [
            S::Diajukan->value => [S::PersetujuanPembina, S::ValidasiAdmin],
            S::PersetujuanPembina->value => [S::ValidasiAdmin, S::Dikembalikan, S::Ditolak, S::Dibatalkan],
            S::ValidasiAdmin->value => [S::DisposisiDekan, S::Dikembalikan, S::Ditolak, S::Dibatalkan],
            S::Dikembalikan->value => [S::ValidasiAdmin, S::Dibatalkan],
            S::DisposisiDekan->value => [S::PersetujuanWd, S::Ditolak, S::Dibatalkan],
            S::PersetujuanWd->value => [S::RekomendasiKasubag, S::Ditolak, S::Dibatalkan],
            S::RekomendasiKasubag->value => [S::Penerbitan, S::Ditolak, S::Dibatalkan],
            S::Penerbitan->value => [S::Selesai, S::Dibatalkan],
            S::Selesai->value => [],
            S::Ditolak->value => [],
            S::Dibatalkan->value => [],
        ];
    }

    /** @return list<S> */
    public static function tujuan(S $dari): array
    {
        return self::peta()[$dari->value];
    }

    public static function boleh(S $dari, S $ke): bool
    {
        return in_array($ke, self::tujuan($dari), true);
    }

    public static function pastikan(S $dari, S $ke): void
    {
        if (! self::boleh($dari, $ke)) {
            throw new TransisiTidakSah("Transisi {$dari->value} → {$ke->value} tidak sah.");
        }
    }

    /** Status yang masih menahan ruangan. */
    public static function menahanRuangan(S $status): bool
    {
        return ! in_array($status, [S::Ditolak, S::Dibatalkan], true);
    }
}
