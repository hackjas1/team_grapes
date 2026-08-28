<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StudentOnboardingMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public User $user;
    public string $onboardingUrl;
    public string $downloadUrl;
    public string $studentHubUrl;

    public function __construct(User $user, string $onboardingUrl, ?string $downloadUrl = null, ?string $studentHubUrl = null)
    {
        $this->user = $user;
        $this->onboardingUrl = $onboardingUrl;
        $this->downloadUrl = $downloadUrl ?? (url("/download/app/{$user->onboardingTokens()->latest()->first()?->token}"));
        $this->studentHubUrl = $studentHubUrl ?? url('/student');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'BSIS Student Account Activation — Talibon Polytechnic College',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.onboarding',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
