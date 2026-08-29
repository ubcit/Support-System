<?php

namespace Modules\Communication\Services;

use App\Helpers\InboxNav;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Modules\Communication\Enums\ConversationSessionStatus;
use Modules\Communication\Enums\MessageChannel;
use Modules\Communication\Enums\MessageDirection;
use Modules\Communication\Enums\MessageStatus;
use Modules\Communication\Jobs\SendOutboundMessage;
use Modules\Communication\Models\ConversationSession;
use Modules\Communication\Models\Message;
use Modules\Communication\Support\CustomerAutoReplyMessages;
use Modules\Communication\Support\CustomerLocale;
use Modules\Employees\Models\Employee;
use Modules\MultiTenancy\Models\Workspace;
use Modules\Notifications\Models\NotificationLog;
use Modules\Notifications\Services\EmailNotificationService;
use Modules\Notifications\Services\NotificationService;
use Modules\Projects\Models\Project;
use Modules\Projects\Services\ProjectService;
use Modules\Tasks\Models\Task;

class SessionReadyNotificationService
{
    public function __construct(
        protected WhatsAppCloudService $whatsApp
    ) {}

    public function notifyAfterSessionComplete(ConversationSession $session, bool $isBoss = false): void
    {
        if ($isBoss) {
            return;
        }

        $session->refresh();
        $session->loadMissing(['conversation.customer', 'conversation.workspace', 'workspace', 'project']);

        if ($session->status === ConversationSessionStatus::AwaitingVerification) {
            return;
        }

        if ($session->status === ConversationSessionStatus::Done) {
            $isActionable = (bool) ($session->analysis['is_actionable'] ?? $session->metadata['is_actionable'] ?? true);
            if (! $isActionable || $session->taskIdList() === []) {
                return;
            }
        }

        $workspace = $session->conversation?->workspace ?? $session->workspace ?? Workspace::first();
        $settings = $workspace?->settings ?? [];

        if (($settings['auto_reply_on_processing'] ?? true) !== false) {
            $this->sendCustomerProcessingReply($session);
        }

        if (($settings['staff_notify_on_session'] ?? true) !== false) {
            $this->notifyStaff($session);
        }
    }

    protected function sendCustomerProcessingReply(ConversationSession $session): void
    {
        $conversation = $session->conversation;
        $customer = $conversation?->customer;

        if (! $conversation || ! $customer) {
            return;
        }

        $recipient = $customer->whatsapp_id ?? $customer->phone;
        if (! $recipient) {
            return;
        }

        $alreadySent = Message::query()
            ->where('conversation_session_id', $session->id)
            ->where('direction', MessageDirection::Outbound->value)
            ->where('metadata->auto_reply', true)
            ->where('metadata->trigger', 'session_processing')
            ->exists();

        if ($alreadySent) {
            return;
        }

        $body = CustomerAutoReplyMessages::body(
            'session_processing',
            CustomerLocale::forReply($session, $customer),
        );

        $message = Message::create([
            'workspace_id' => $conversation->workspace_id,
            'channel' => MessageChannel::WhatsApp,
            'direction' => MessageDirection::Outbound,
            'sender_identifier' => config('services.whatsapp.phone_number_id'),
            'sender_name' => config('app.name', 'Support'),
            'recipient_identifier' => $recipient,
            'body' => $body,
            'conversation_id' => $conversation->id,
            'conversation_session_id' => $session->id,
            'customer_id' => $customer->id,
            'status' => MessageStatus::Processing,
            'metadata' => [
                'auto_reply' => true,
                'trigger' => 'session_processing',
            ],
        ]);

        SendOutboundMessage::dispatch($message);
    }

    protected function notifyStaff(ConversationSession $session): void
    {
        $project = $this->resolveProject($session);
        $recipients = $this->staffRecipients($project);

        if ($recipients->isEmpty()) {
            Log::info("SessionReady: No staff recipients for session {$session->uuid}");

            return;
        }

        $customer = $session->conversation?->customer;
        $customerName = $customer?->name ?? 'Customer';
        $projectName = $project?->name ?? 'Unassigned project';
        $title = $session->title ?: 'Customer request';
        $summary = trim((string) ($session->summary ?? ''));
        $url = InboxNav::url(null, (int) $session->conversation_id, null, (int) $session->id);

        $whatsAppBody = "English: New customer request ready.\n"
            ."Customer: {$customerName}\n"
            ."Project: {$projectName}\n"
            ."Title: {$title}"
            .($summary !== '' ? "\nSummary: {$summary}" : '')
            ."\n\nArabic: طلب عميل جديد جاهز للمتابعة.\n"
            ."العميل: {$customerName}\n"
            ."المشروع: {$projectName}\n"
            ."العنوان: {$title}";

        foreach ($recipients as $employee) {
            $this->sendStaffInApp($employee, $session, $customerName, $title, $url);
            $this->sendStaffEmail($employee, $session, $customer, $project);
            $this->sendStaffWhatsApp($employee, $whatsAppBody, $session);
        }

        Log::info("SessionReady: Notified {$recipients->count()} staff for session {$session->uuid}", [
            'project_id' => $project?->id,
            'inbox_url' => $url,
        ]);
    }

    protected function resolveProject(ConversationSession $session): ?Project
    {
        if ($session->project_id) {
            return $session->project ?? Project::find($session->project_id);
        }

        $taskProjectId = Task::query()
            ->whereIn('id', $session->taskIdList())
            ->whereNotNull('project_id')
            ->value('project_id');

        if ($taskProjectId) {
            return Project::find($taskProjectId);
        }

        $aiProject = $session->analysis['project'] ?? null;
        if ($aiProject && $session->conversation?->customer_id) {
            return app(ProjectService::class)
                ->matchForCustomer((int) $session->conversation->customer_id, $aiProject);
        }

        return null;
    }

    /**
     * @return Collection<int, Employee>
     */
    protected function staffRecipients(?Project $project): Collection
    {
        if ($project) {
            $project->loadMissing('employees');
            $members = $project->employees
                ->filter(fn (Employee $employee) => filled($employee->phone) || filled($employee->email))
                ->values();

            if ($members->isNotEmpty()) {
                return $members;
            }
        }

        return Employee::query()
            ->whereNull('deleted_at')
            ->where(function ($query) {
                $query->where(function ($inner) {
                    $inner->whereNotNull('phone')->where('phone', '!=', '');
                })->orWhere(function ($inner) {
                    $inner->whereNotNull('email')->where('email', '!=', '');
                });
            })
            ->get();
    }

    protected function sendStaffInApp(Employee $employee, ConversationSession $session, string $customerName, string $title, string $url): void
    {
        app(NotificationService::class)->send(
            title: 'Conversation needs attention',
            body: "{$customerName}: {$title}",
            type: 'conversation_needs_human',
            employee: $employee,
            userId: $employee->user_id,
            actionUrl: $url,
            metadata: [
                'conversation_session_id' => $session->id,
                'conversation_id' => $session->conversation_id,
            ],
        );
    }

    protected function sendStaffEmail(Employee $employee, ConversationSession $session, $customer, ?Project $project): void
    {
        if (empty($employee->email)) {
            return;
        }

        $meta = $employee->metadata ?? [];
        if (isset($meta['email_notifications_enabled']) && $meta['email_notifications_enabled'] === false) {
            return;
        }

        // Same digest path: send SMTP in-process (no queue re-find).
        app(EmailNotificationService::class)->sendConversationNeedsHuman($session, $employee);
    }

    protected function sendStaffWhatsApp(Employee $employee, string $body, ConversationSession $session): void
    {
        $to = $employee->phone;
        if (! filled($to)) {
            return;
        }

        $response = $this->whatsApp->sendText($to, $body);

        NotificationLog::create([
            'channel' => 'whatsapp',
            'recipient' => $to,
            'subject' => 'Session ready',
            'body' => 'session_ready',
            'status' => $response ? 'sent' : 'failed',
            'notifiable_type' => Employee::class,
            'notifiable_id' => $employee->id,
            'sent_at' => $response ? now() : null,
            'metadata' => [
                'type' => 'session_ready',
                'conversation_session_id' => $session->id,
                'error' => $response ? null : $this->whatsApp->lastError(),
            ],
        ]);
    }
}
