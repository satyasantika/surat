<?php

namespace App\Contracts;

/** Gerbang pengiriman WhatsApp (kanal opsional BR-21). Isi pesan hanya ringkasan + tautan, tanpa data rahasia. */
interface GerbangWhatsapp
{
    public function tersedia(): bool;

    /** @param  string  $nomor  format internasional tanpa plus, mis. 62812xxxxxxx */
    public function kirim(string $nomor, string $pesan): bool;
}
