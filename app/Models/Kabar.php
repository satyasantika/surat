<?php

namespace App\Models;

use App\Models\Concerns\TercatatAktivitas;
use App\Support\SanitasiHtml;
use App\Support\UrlBerkas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

/** Kabar ormawa/fakultas (BR-19): diusulkan pengurus atau dibuat admin; tampil publik hanya bila terbit. */
#[Fillable(['ormawa_id', 'judul', 'subjudul', 'isi', 'tag'])]
class Kabar extends Model
{
    use HasUuids, TercatatAktivitas;

    public const DRAF = 'draf';

    public const DIAJUKAN = 'diajukan';

    public const TERBIT = 'terbit';

    public const DITOLAK = 'ditolak';

    public const STATUS = [self::DRAF => 'Draf', self::DIAJUKAN => 'Diajukan', self::TERBIT => 'Terbit', self::DITOLAK => 'Ditolak'];

    protected $table = 'kabar';

    protected $attributes = ['status' => self::DRAF];

    protected function casts(): array
    {
        return ['tag' => 'array', 'terbit_pada' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $kabar) {
            $kabar->isi = SanitasiHtml::bersihkan($kabar->isi);

            if (blank($kabar->slug)) {
                $kabar->slug = self::slugUnik($kabar->judul);
            }
        });
    }

    public static function slugUnik(string $judul): string
    {
        $dasar = Str::limit(Str::slug($judul) ?: 'kabar', 150, '');
        $slug = $dasar;

        for ($i = 2; self::where('slug', $slug)->exists(); $i++) {
            $slug = "{$dasar}-{$i}";
        }

        return $slug;
    }

    /** @param  Builder<self>  $query */
    public function scopeTerbit(Builder $query): void
    {
        $query->where('status', self::TERBIT);
    }

    /** Foto sampul (tautan jenis foto) sebagai URL gambar; Drive lewat lh3, selain itu hanya domain unsil. */
    public function sampulUrl(): ?string
    {
        $tautan = $this->tautan->firstWhere('jenis', 'foto');

        if ($tautan === null) {
            return null;
        }

        if ($tautan->drive_file_id) {
            return 'https://lh3.googleusercontent.com/d/'.$tautan->drive_file_id;
        }

        return $tautan->penyedia === 'unsil' && UrlBerkas::urai($tautan->url) !== null ? $tautan->url : null;
    }

    /** @return BelongsTo<Ormawa, $this> */
    public function ormawa(): BelongsTo
    {
        return $this->belongsTo(Ormawa::class);
    }

    /** @return BelongsTo<User, $this> */
    public function penulis(): BelongsTo
    {
        return $this->belongsTo(User::class, 'penulis_id');
    }

    /** @return BelongsTo<User, $this> */
    public function penyetuju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    /** @return MorphMany<TautanBerkas, $this> */
    public function tautan(): MorphMany
    {
        return $this->morphMany(TautanBerkas::class, 'pemilik');
    }
}
