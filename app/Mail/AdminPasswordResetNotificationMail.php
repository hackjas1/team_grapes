<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminPasswordResetNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $newPassword;
    public ?User $admin;

    public function __construct(User $user, string $newPassword, ?User $admin = null)
    {
        $this->user = $user;
        $this->newPassword = $newPassword;
        $this->admin = $admin;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Account Password Reset by Administrator — Talibon Polytechnic College',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin_password_reset',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
