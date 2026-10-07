<?php

namespace App\Actions\Notifikasi;

use App\Models\User;
use App\Support\Notifikasi\Kategori;
use Illuminate\Support\Facades\Validator;

/** Pengguna mengatur kanal notifikasi per kategori dan nomor telepon WhatsApp miliknya sendiri. */
class SimpanPreferensiNotifikasi
{
    /**
     * @param  array<string, mixed>  $data  telepon (opsional) dan pref[kategori][mail|whatsapp]
     */
    public function jalankan(User $pengguna, array $data): User
    {
        $valid = Validator::make($data, [
            'telepon' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]+$/'],
            'pref' => ['nullable', 'array'],
            'pref.*' => ['array'],
            'pref.*.mail' => ['nullable', 'boolean'],
            'pref.*.whatsapp' => ['nullable', 'boolean'],
        ])->validate();

        $pref = [];

        foreach (array_keys(Kategori::SEMUA) as $kategori) {
            $baris = $valid['pref'][$kategori] ?? [];
            $pref[$kategori] = ['mail' => (bool) ($baris['mail'] ?? false), 'whatsapp' => (bool) ($baris['whatsapp'] ?? false)];
        }

        $pengguna->forceFill(['preferensi_notifikasi' => $pref, 'telepon' => ($valid['telepon'] ?? '') !== '' ? $valid['telepon'] : null])->save();

        return $pengguna;
    }
}
