<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Undang akun hasil migrasi OrmawaHub: tautan atur kata sandi sekali pakai (kata sandi lama tidak dimigrasikan, S-02). */
class UndanganAturSandi extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);
        $menit = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('Akun Persuratan & Layanan FKIP Anda')
            ->greeting('Halo '.$notifiable->name)
            ->line('Akun Anda di sistem Persuratan & Layanan FKIP Unsil telah disiapkan. Kata sandi lama OrmawaHub tidak dipindahkan; silakan buat kata sandi baru.')
            ->action('Atur kata sandi', $url)
            ->line("Tautan berlaku {$menit} menit. Bila kedaluwarsa, gunakan \"Lupa kata sandi\" pada halaman masuk.");
    }
}
