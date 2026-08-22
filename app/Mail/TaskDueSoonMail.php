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

class TaskDueSoonMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Employee $employee,
        public Collection $tasks,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: count($this->tasks) . ' ' . str('Task')->plural(count($this->tasks)) . ' Due Soon',
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.due-soon');
    }
}
