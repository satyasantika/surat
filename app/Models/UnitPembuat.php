<?php

namespace App\Models;

use App\Models\Concerns\TercatatAktivitas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Unit pembuat surat (rektorat, surat tugas dosen, izin kegiatan, dst.) yang ditunjuk kasubag pada tahap pembuatan
 * (BR-26). Isinya sepenuhnya dikelola super-admin; tidak ada nilai baku yang dikunci di kode.
 */
#[Fillable(['kode', 'nama', 'deskripsi', 'aktif'])]
class UnitPembuat extends Model
{
    use HasUuids, TercatatAktivitas;

    protected $table = 'unit_pembuat';

    protected $attributes = ['aktif' => true];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    /** @return BelongsToMany<User, $this> */
    public function petugas(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'unit_pembuat_petugas', 'unit_pembuat_id', 'user_id')->withTimestamps();
    }

    /**
     * Petugas yang akunnya aktif.
     *
     * @return BelongsToMany<User, $this>
     */
    public function petugasAktif(): BelongsToMany
    {
        return $this->petugas()->where('users.aktif', true);
    }

    /** @return HasMany<JenisPermohonan, $this> */
    public function jenisBawaan(): HasMany
    {
        return $this->hasMany(JenisPermohonan::class, 'unit_pembuat_bawaan_id');
    }

    /** @return HasMany<Permohonan, $this> */
    public function permohonan(): HasMany
    {
        return $this->hasMany(Permohonan::class);
    }
}
