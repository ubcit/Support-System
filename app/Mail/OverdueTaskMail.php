<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Modules\Employees\Models\Employee;

class OverdueTaskMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Employee $employee,
        public Collection $tasks,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: count($this->tasks).' Overdue '.str('task')->plural(count($this->tasks))->title().' - Action Required',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.overdue-tasks');
    }
}
