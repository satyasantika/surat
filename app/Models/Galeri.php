<?php

namespace App\Models;

use App\Models\Concerns\MenyegarkanCachePublik;
use App\Models\Concerns\TercatatAktivitas;
use App\Rules\TautanGaleriValid;
use App\Support\UrlBerkas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Validator;

/** Item galeri berbasis tautan (foto/video/instagram); hanya yang aktif tampil publik. */
#[Fillable(['ormawa_id', 'permohonan_id', 'judul', 'tipe', 'url', 'aktif', 'urutan'])]
class Galeri extends Model
{
    use HasUuids, MenyegarkanCachePublik, TercatatAktivitas;

    public const TIPE = ['foto' => 'Foto', 'video' => 'Video', 'instagram' => 'Instagram'];

    protected $table = 'galeri';

    protected $attributes = ['aktif' => false, 'urutan' => 0];

    protected function casts(): array
    {
        return ['aktif' => 'boolean', 'urutan' => 'integer'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $galeri) {
            Validator::make($galeri->only(['tipe', 'url']), [
                'tipe' => ['required', 'in:foto,video,instagram'],
                'url' => ['required', 'string', new TautanGaleriValid($galeri->tipe)],
            ])->validate();
        });
    }

    /** @param  Builder<self>  $query */
    public function scopeAktif(Builder $query): void
    {
        $query->where('aktif', true)->orderBy('urutan')->orderByDesc('created_at');
    }

    /** URL gambar foto (Drive lewat lh3); null bila bukan foto atau tidak dapat ditampilkan. */
    public function gambarUrl(): ?string
    {
        if ($this->tipe !== 'foto') {
            return null;
        }

        $id = UrlBerkas::driveFileId($this->url);

        if ($id !== null) {
            return 'https://lh3.googleusercontent.com/d/'.$id;
        }

        $urai = UrlBerkas::urai($this->url);

        return $urai !== null && str_ends_with($urai['host'], '.unsil.ac.id') ? $this->url : null;
    }

    /** URL embed video: YouTube (tanpa cookie) atau pratinjau Drive; instagram dan lainnya tidak di-embed. */
    public function embedUrl(): ?string
    {
        if ($this->tipe !== 'video') {
            return null;
        }

        $bagian = parse_url($this->url);
        $host = strtolower($bagian['host'] ?? '');
        parse_str($bagian['query'] ?? '', $query);
        $path = $bagian['path'] ?? '';

        $idYoutube = match (true) {
            $host === 'youtu.be' => ltrim($path, '/'),
            str_ends_with($host, 'youtube.com') && is_string($query['v'] ?? null) => $query['v'],
            str_ends_with($host, 'youtube.com') && preg_match('#^/(?:embed|shorts)/([^/?]+)#', $path, $m) === 1 => $m[1],
            default => null,
        };

        if ($idYoutube !== null && preg_match('/^[A-Za-z0-9_-]{6,20}$/', $idYoutube) === 1) {
            return 'https://www.youtube-nocookie.com/embed/'.$idYoutube;
        }

        $drive = UrlBerkas::driveFileId($this->url);

        return $drive !== null ? 'https://drive.google.com/file/d/'.$drive.'/preview' : null;
    }

    /** @return BelongsTo<Ormawa, $this> */
    public function ormawa(): BelongsTo
    {
        return $this->belongsTo(Ormawa::class);
    }
}
