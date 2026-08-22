<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Communication\Models\ConversationSession;
use Modules\Customers\Models\Customer;
use Modules\Employees\Models\Employee;
use Modules\Projects\Models\Project;

/**
 * Sent synchronously so SessionReadyNotificationService can catch SMTP failures
 * without leaving orphaned failed_jobs entries during local simulator runs.
 */
class SessionReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Employee $employee,
        public ConversationSession $session,
        public ?Customer $customer = null,
        public ?Project $project = null,
    ) {}

    public function envelope(): Envelope
    {
        $customerName = $this->customer?->name ?? 'Customer';
        $title = $this->session->title ?: 'New request';

        return new Envelope(
            subject: "Session ready: {$customerName} — {$title}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.session-ready');
    }
}
