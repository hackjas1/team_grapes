<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $token;
    public ?\App\Models\User $user;
    public string $resetUrl;

    public function __construct(string $token, ?\App\Models\User $user = null, ?string $resetUrl = null)
    {
        $this->token = $token;
        $this->user = $user;
        $this->resetUrl = $resetUrl ?? (config('app.url') . ($user && $user->role === 'student' ? '/student#reset-password' : '/admin#reset-password') . '?token=' . urlencode($token) . '&email=' . urlencode($user ? $user->email : ''));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'BSIS Password Reset Request — Talibon Polytechnic College',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.password_reset',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
