<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Modules\Employees\Models\Employee;
use Modules\Tasks\Models\TaskComment;

class CommentMentionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Employee $employee,
        public TaskComment $comment,
    ) {}

    public function envelope(): Envelope
    {
        $taskTitle = $this->comment->task?->title ?? 'a task';

        return new Envelope(
            subject: "You were mentioned in: {$taskTitle}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.comment-mention',
        );
    }
}
