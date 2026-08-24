<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Employees\Models\Employee;
use Modules\Projects\Models\Project;

class ProjectMemberAddedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Employee $employee,
        public Project $project,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Added to project: '.$this->project->name,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.project-member-added');
    }
}
