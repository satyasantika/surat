<?php

namespace App\Notifications;

use App\Notifications\Saluran\SaluranWhatsapp;
use App\Support\Pengaturan;
use Filament\Actions\Action;
use Filament\Notifications\Notification as NotifikasiFilament;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi perpindahan tahap (BR-21): database (selalu), surel, dan WhatsApp (opsional) menurut preferensi.
 * Isi sudah disusun pemanggil TANPA data rahasia (perihal surat rahasia tidak pernah disertakan).
 */
class NotifikasiTahap extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $kategori,
        public readonly string $judul,
        public readonly string $ringkas,
        public readonly ?string $url = null,
    ) {
        $this->onQueue('notifikasi');
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        $kanal = ['database'];
        $pref = method_exists($notifiable, 'preferensiNotifikasi') ? $notifiable->preferensiNotifikasi($this->kategori) : ['mail' => true, 'whatsapp' => false];

        if ($pref['mail'] && filled($notifiable->email ?? null)) {
            $kanal[] = 'mail';
        }

        if ($pref['whatsapp'] && Pengaturan::get('wa_aktif') && filled($notifiable->telepon ?? null) && SaluranWhatsapp::normalisasi((string) $notifiable->telepon) !== null) {
            $kanal[] = SaluranWhatsapp::class;
        }

        return $kanal;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $pesan = (new MailMessage)->subject($this->judul)->greeting('Halo '.($notifiable->name ?? ''))->line($this->ringkas);

        return $this->url !== null ? $pesan->action('Buka di aplikasi', $this->url) : $pesan;
    }

    /** @return array<string, mixed> format notifikasi basis data Filament + kategori */
    public function toDatabase(object $notifiable): array
    {
        $n = NotifikasiFilament::make()->title($this->judul)->body($this->ringkas);

        if ($this->url !== null) {
            $n->actions([Action::make('buka')->label('Buka')->url($this->url)->markAsRead()]);
        }

        return [...$n->getDatabaseMessage(), 'kategori' => $this->kategori, 'url' => $this->url];
    }

    public function toWhatsapp(object $notifiable): string
    {
        return trim("{$this->judul}\n{$this->ringkas}".($this->url !== null ? "\n{$this->url}" : ''));
    }
}
