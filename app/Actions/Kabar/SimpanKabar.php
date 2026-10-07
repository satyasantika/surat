<?php

namespace App\Actions\Kabar;

use App\Contracts\PenyimpananBerkas;
use App\Models\Kabar;
use App\Models\TautanBerkas;
use App\Models\User;
use App\Rules\TautanBerkasValid;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Membuat atau mengubah kabar (draf). Pengurus hanya untuk ormawa aktifnya; ormawa kabar tidak dapat dipindah olehnya. */
class SimpanKabar
{
    public function __construct(private readonly PenyimpananBerkas $berkas) {}

    /** @param  array<string, mixed>  $data */
    public function jalankan(User $pelaku, array $data, ?Kabar $kabar = null): Kabar
    {
        $kabar ? Gate::forUser($pelaku)->authorize('update', $kabar) : Gate::forUser($pelaku)->authorize('create', Kabar::class);

        $valid = Validator::make($data, [
            'ormawa_id' => ['nullable', 'uuid', 'exists:ormawa,id'],
            'judul' => ['required', 'string', 'max:200'],
            'subjudul' => ['nullable', 'string', 'max:250'],
            'isi' => ['required', 'string', 'max:100000'],
            'tag' => ['nullable', 'array', 'max:10'],
            'tag.*' => ['string', 'max:30'],
            'sampul' => ['nullable', 'string', new TautanBerkasValid],
        ])->validate();

        $kelola = $pelaku->can('kabar.kelola');
        $ormawaId = $kabar ? ($kelola ? ($valid['ormawa_id'] ?? null) : $kabar->ormawa_id) : ($valid['ormawa_id'] ?? null);

        if (! $kelola && ! $pelaku->ormawaAktif()->contains('id', $ormawaId)) {
            throw ValidationException::withMessages(['ormawa_id' => 'Anda hanya dapat menulis kabar untuk ormawa tempat Anda aktif.']);
        }

        return DB::transaction(function () use ($kabar, $pelaku, $valid, $ormawaId) {
            $kabar ??= (new Kabar)->forceFill(['penulis_id' => $pelaku->getKey()]);
            $kabar->fill([
                'ormawa_id' => $ormawaId,
                'judul' => $valid['judul'],
                'subjudul' => $valid['subjudul'] ?? null,
                'isi' => $valid['isi'],
                'tag' => array_values(array_unique(array_filter($valid['tag'] ?? []))) ?: null,
            ])->save();

            $this->simpanSampul($kabar, $pelaku, $valid['sampul'] ?? null);

            return $kabar;
        });
    }

    private function simpanSampul(Kabar $kabar, User $pelaku, ?string $url): void
    {
        $ada = TautanBerkas::where('pemilik_type', $kabar->getMorphClass())->where('pemilik_id', $kabar->getKey())->where('jenis', 'foto')->first();

        match (true) {
            blank($url) && $ada !== null => $this->berkas->hapus($ada),
            blank($url) => null,
            $ada !== null => $ada->update(['url' => $url]),
            default => $this->berkas->simpan($kabar, 'foto', $url, 'Foto sampul', $pelaku),
        };

        $kabar->unsetRelation('tautan');
    }
}
