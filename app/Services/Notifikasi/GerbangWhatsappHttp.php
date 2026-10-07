<?php

namespace App\Services\Notifikasi;

use App\Contracts\GerbangWhatsapp;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/** Gerbang HTTP generik: POST JSON {target, message} dengan token Bearer dari .env (tidak pernah dicatat di log). */
class GerbangWhatsappHttp implements GerbangWhatsapp
{
    public function __construct(private readonly ?string $url, private readonly ?string $token, private readonly int $timeout = 10) {}

    public function tersedia(): bool
    {
        return filled($this->url) && filled($this->token);
    }

    public function kirim(string $nomor, string $pesan): bool
    {
        if (! $this->tersedia()) {
            return false;
        }

        try {
            return Http::timeout($this->timeout)->withToken((string) $this->token)->acceptJson()
                ->post((string) $this->url, ['target' => $nomor, 'message' => $pesan])->successful();
        } catch (Throwable $e) {
            Log::warning('Gerbang WhatsApp gagal: '.get_class($e));

            return false;
        }
    }
}
