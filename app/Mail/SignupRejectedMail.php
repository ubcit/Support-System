<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SignupRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $reviewerName,
        public string $rejectionMessage,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your account request was rejected',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.signup-rejected');
    }
}
