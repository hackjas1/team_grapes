<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DeviceResetStatusMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public User $user;
    public string $status;
    public ?string $rejectionReason;

    public function __construct(User $user, string $status, ?string $rejectionReason = null)
    {
        $this->user = $user;
        $this->status = $status;
        $this->rejectionReason = $rejectionReason;
    }

    public function envelope(): Envelope
    {
        $subjectStatus = strtoupper($this->status);
        return new Envelope(
            subject: "BSIS Device Reset Request {$subjectStatus} — Talibon Polytechnic College",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.device_reset_status',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
