<?php

namespace App\Models;

use App\Jobs\PeriksaTautanBerkas;
use App\Models\Concerns\MenyegarkanCachePublik;
use App\Models\Concerns\TercatatAktivitas;
use App\Support\UrlBerkas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

#[Fillable(['pemilik_type', 'pemilik_id', 'jenis', 'label', 'url', 'ditambahkan_oleh'])]
class TautanBerkas extends Model
{
    use HasUuids, MenyegarkanCachePublik, SoftDeletes, TercatatAktivitas;

    protected $table = 'tautan_berkas';

    protected $attributes = ['status_cek' => 'belum'];

    protected function casts(): array
    {
        return ['dicek_pada' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $tautan) {
            if (! in_array($tautan->jenis, config('berkas.jenis'), true)) {
                throw ValidationException::withMessages(['jenis' => "Jenis tautan tidak dikenal: {$tautan->jenis}"]);
            }

            if ($tautan->isDirty('url') || ! $tautan->exists) {
                $urai = UrlBerkas::urai($tautan->url);

                if ($urai === null || UrlBerkas::hostPemendek($urai['host']) || ! UrlBerkas::hostDiizinkan($urai['host'])) {
                    throw ValidationException::withMessages(['url' => 'Tautan tidak memenuhi kebijakan berkas.']);
                }

                $tautan->penyedia = UrlBerkas::penyedia($urai['host']);
                $tautan->drive_file_id = UrlBerkas::driveFileId($tautan->url);
                $tautan->status_cek = 'belum';
                $tautan->dicek_pada = null;
            }
        });

        $periksa = fn (self $tautan) => PeriksaTautanBerkas::dispatch($tautan)->afterCommit();

        static::created($periksa);
        static::updated(fn (self $tautan) => $tautan->wasChanged('url') ? $periksa($tautan) : null);
    }

    /** URL gambar untuk halaman publik: Drive lewat lh3, selain itu hanya domain unsil.ac.id; jenis tertutup tidak pernah. */
    public function gambarUrl(): ?string
    {
        if ($this->tertutup()) {
            return null;
        }

        if ($this->drive_file_id) {
            return 'https://lh3.googleusercontent.com/d/'.$this->drive_file_id;
        }

        return $this->penyedia === 'unsil' && UrlBerkas::urai($this->url) !== null ? $this->url : null;
    }

    /** Jenis yang tidak pernah dirender sebagai URL ke klien. */
    public function tertutup(): bool
    {
        return in_array($this->jenis, config('berkas.jenis_tertutup'), true);
    }

    /** @return MorphTo<Model, $this> */
    public function pemilik(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function penambah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditambahkan_oleh');
    }
}
