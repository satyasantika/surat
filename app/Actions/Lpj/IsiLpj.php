<?php

namespace App\Actions\Lpj;

use App\Contracts\PenyimpananBerkas;
use App\Models\Lpj;
use App\Models\TautanBerkas;
use App\Models\User;
use App\Rules\TautanBerkasValid;
use App\Rules\TautanMediaValid;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/** Mengisi dan mengajukan LPJ (draf → diajukan); tautan berkas LPJ wajib. LPJ diajukan tidak dapat diubah. */
class IsiLpj
{
    public function __construct(private readonly PenyimpananBerkas $berkas) {}

    /** @param  array<string, mixed>  $data */
    public function jalankan(Lpj $lpj, User $pelaku, array $data): Lpj
    {
        $lpj->loadMissing('permohonan');
        Gate::forUser($pelaku)->authorize('isi', $lpj);

        $permohonan = $lpj->permohonan;

        $valid = Validator::make($data, [
            'tanggal_pelaksanaan' => ['required', 'date', 'after_or_equal:'.$permohonan->tanggal_mulai->toDateString(), 'before_or_equal:today'],
            'jumlah_peserta' => ['required', 'integer', 'min:0', 'max:1000000'],
            'ringkasan' => ['required', 'string', 'max:10000'],
            'kendala' => ['nullable', 'string', 'max:5000'],
            'solusi' => ['nullable', 'string', 'max:5000'],
            'rekomendasi' => ['nullable', 'string', 'max:5000'],
            'tautan_instagram' => ['nullable', 'string', new TautanMediaValid],
            'tautan_video' => ['nullable', 'string', new TautanMediaValid],
            'berkas_lpj' => ['required', 'string', new TautanBerkasValid],
        ], [
            'berkas_lpj.required' => 'Tautan berkas LPJ wajib diisi.',
            'tanggal_pelaksanaan.after_or_equal' => 'Tanggal pelaksanaan tidak boleh sebelum tanggal mulai kegiatan.',
            'tanggal_pelaksanaan.before_or_equal' => 'Tanggal pelaksanaan tidak boleh di masa depan.',
        ])->validate();

        return DB::transaction(function () use ($lpj, $pelaku, $valid) {
            $lpj->update(collect($valid)->except('berkas_lpj')->all());

            $tautan = TautanBerkas::where('pemilik_type', $lpj->getMorphClass())->where('pemilik_id', $lpj->getKey())->where('jenis', 'lpj')->first();
            $tautan ? $tautan->update(['url' => $valid['berkas_lpj']]) : $this->berkas->simpan($lpj, 'lpj', $valid['berkas_lpj'], 'Berkas LPJ', $pelaku);

            $lpj->forceFill(['status' => Lpj::DIAJUKAN, 'diajukan_pada' => now()])->save();

            return $lpj;
        });
    }
}
