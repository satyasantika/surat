<?php

namespace App\Models;

use App\Enums\StatusUsulan;
use App\Models\Concerns\TercatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Usulan mahasiswa kepada perantara (ormawa atau unit). Perantara meneruskannya sebagai dasar sebuah permohonan,
 * sendiri atau digabung dengan usulan lain (permohonan_id).
 *
 * @property StatusUsulan $status
 * @property ?string $ormawa_id
 * @property ?string $unit_kerja_id
 * @property ?string $permohonan_id
 */
#[Fillable(['mahasiswa_id', 'ormawa_id', 'unit_kerja_id', 'perihal', 'uraian'])]
class UsulanMahasiswa extends Model
{
    use HasUuids, TercatatAktivitas;

    protected $table = 'usulan_mahasiswa';

    protected $attributes = ['status' => 'diajukan'];

    protected function casts(): array
    {
        return ['status' => StatusUsulan::class];
    }

    protected function namaLog(): string
    {
        return 'usulan-mahasiswa';
    }

    /** @return BelongsTo<User, $this> */
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
