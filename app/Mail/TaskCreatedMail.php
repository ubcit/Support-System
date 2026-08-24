<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Employees\Models\Employee;
use Modules\Tasks\Models\Task;

class TaskCreatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Employee $employee,
        public Task $task,
        public ?string $creatorName = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New task in project: '.$this->task->title,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.task-created');
    }
}
