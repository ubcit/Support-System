<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Employees\Models\Employee;
use Modules\Tasks\Models\Task;

class ReviewRequestedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Employee $employee,
        public Task $task,
        public string $submitterName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Ready for review: '.$this->task->title);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.review-requested');
    }
}
