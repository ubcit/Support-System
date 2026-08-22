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

class DailyDigestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Employee $employee,
        public array $stats,
        public Collection $overdueTasks,
        public Collection $upcomingDeadlines,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Daily Digest - ' . now()->format('M d, Y'),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.daily-digest');
    }
}
