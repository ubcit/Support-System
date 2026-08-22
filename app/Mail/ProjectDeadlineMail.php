<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Modules\Employees\Models\Employee;

class ProjectDeadlineMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Employee $employee,
        public Collection $projects,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Project Deadline Approaching - ' . $this->projects->first()?->name,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.project-deadline');
    }
}
