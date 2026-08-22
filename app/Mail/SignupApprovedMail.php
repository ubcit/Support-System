<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SignupApprovedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $roleName,
        public string $reviewerName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your account has been approved',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.signup-approved');
    }
}
