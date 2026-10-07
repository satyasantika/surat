<?php

namespace App\Notifications\Saluran;

use App\Contracts\GerbangWhatsapp;
use Illuminate\Notifications\Notification;

/** Saluran notifikasi WhatsApp: memakai toWhatsapp() notifikasi dan nomor telepon pengguna. */
class SaluranWhatsapp
{
    public function __construct(private readonly GerbangWhatsapp $gerbang) {}

    public function send(object $notifiable, Notification $notification): void
    {
        $nomor = self::normalisasi((string) ($notifiable->telepon ?? ''));

        if ($nomor === null || ! $this->gerbang->tersedia() || ! method_exists($notification, 'toWhatsapp')) {
            return;
        }

        $this->gerbang->kirim($nomor, (string) $notification->toWhatsapp($notifiable));
    }

    /** 0812-xxx / +62812xxx / 62812xxx → 62812xxx; selain itu null. */
    public static function normalisasi(string $telepon): ?string
    {
        $digit = preg_replace('/\D+/', '', $telepon) ?? '';

        $nomor = match (true) {
            str_starts_with($digit, '62') => $digit,
            str_starts_with($digit, '0') => '62'.substr($digit, 1),
            str_starts_with($digit, '8') => '62'.$digit,
            default => '',
        };

        return strlen($nomor) >= 10 && strlen($nomor) <= 15 ? $nomor : null;
    }
}
