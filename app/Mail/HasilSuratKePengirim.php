<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Surel kepada pengirim tanpa akun: hasil siap, lengkap dengan tautan lacak publik. Tanpa perihal maupun isi surat. */
class HasilSuratKePengirim extends Mailable
{
    public function __construct(public readonly ?string $nama, public readonly string $tautanLacak) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Surat Anda telah selesai diproses');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.hasil-surat-pengirim');
    }
}
