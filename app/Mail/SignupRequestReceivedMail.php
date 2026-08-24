<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Employees\Models\Employee;

class SignupRequestReceivedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $pendingUser,
        public Employee $recipient,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New signup request pending approval',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.signup-request-received');
    }
}
