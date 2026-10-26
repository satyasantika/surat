<?php

namespace App\Models;

use App\Enums\StatusUsulan;
use App\Models\Concerns\MemilikiKodeLacak;
use App\Models\Concerns\TercatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Usulan kepada perantara (ormawa atau unit). Pengusulnya mahasiswa (asal = mahasiswa) atau pengirim pihak luar yang
 * memilih ormawa/prodi/lab sebagai tujuan kiriman (asal = eksternal). Perantara meneruskannya sebagai dasar sebuah
 * permohonan, sendiri atau digabung dengan usulan lain (permohonan_id); kiriman pihak luar juga boleh diarsipkan.
 * Tiap baris berkode lacak publik sendiri.
 *
 * @property string $mahasiswa_id pengusul (mahasiswa atau pengirim pihak luar); nama kolom dipertahankan
 * @property string $asal mahasiswa|eksternal
 * @property ?string $instansi instansi pengirim pihak luar
 * @property string $kode_lacak
 * @property StatusUsulan $status
 * @property ?string $ormawa_id
 * @property ?string $unit_kerja_id
 * @property ?string $permohonan_id
 */
#[Fillable(['mahasiswa_id', 'asal', 'instansi', 'ormawa_id', 'unit_kerja_id', 'perihal', 'uraian'])]
class UsulanMahasiswa extends Model
{
    use HasUuids, MemilikiKodeLacak, TercatatAktivitas;

    public const ASAL_MAHASISWA = 'mahasiswa';

    public const ASAL_EKSTERNAL = 'eksternal';

    protected $table = 'usulan_mahasiswa';

    protected $hidden = ['kode_lacak'];

    protected $attributes = ['status' => 'diajukan'];

    protected function casts(): array
    {
        return ['status' => StatusUsulan::class];
    }

    protected function namaLog(): string
    {
        return 'usulan-mahasiswa';
    }

    /** Kiriman pengirim pihak luar (bukan usulan mahasiswa). */
    public function dariPihakLuar(): bool
    {
        return $this->asal === self::ASAL_EKSTERNAL;
    }

    /**
     * Pengusul: mahasiswa atau pengirim pihak luar (kolom mahasiswa_id).
     *
     * @return BelongsTo<User, $this>
     */
    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mahasiswa_id');
    }

    /** @return BelongsTo<Ormawa, $this> */
    public function ormawa(): BelongsTo
    {
        return $this->belongsTo(Ormawa::class);
    }

    /** @return BelongsTo<UnitKerja, $this> */
    public function unitKerja(): BelongsTo
    {
        return $this->belongsTo(UnitKerja::class);
    }

    /** @return BelongsTo<Permohonan, $this> */
    public function permohonan(): BelongsTo
    {
        return $this->belongsTo(Permohonan::class);
    }

    /** @return MorphMany<TautanBerkas, $this> */
    public function tautan(): MorphMany
    {
        return $this->morphMany(TautanBerkas::class, 'pemilik');
    }

    /** Nama perantara tujuan: ormawa atau unit kerja. */
    public function namaTujuan(): string
    {
        return (string) ($this->ormawa_id !== null ? $this->ormawa?->nama : $this->unitKerja?->nama);
    }
}
