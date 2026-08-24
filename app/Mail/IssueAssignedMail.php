<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Employees\Models\Employee;
use Modules\Issues\Models\Issue;

class IssueAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Employee $employee,
        public Issue $issue,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Issue assigned: '.$this->issue->title,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.issue-assigned');
    }
}
