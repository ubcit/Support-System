<?php

namespace Modules\Issues\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Issues\Enums\IssueStatus;
use Modules\Issues\Models\Issue;
use Modules\Issues\Models\IssueTimeline;
use App\Jobs\SendNotificationEmailJob;
use Modules\Issues\Repositories\IssueRepositoryInterface;

class IssueService
{
    public function __construct(
        protected IssueRepositoryInterface $repository
    ) {}

    public function list(array $filters = [], ?string $search = null, ?string $sortBy = null, string $direction = 'desc', int $perPage = 15): LengthAwarePaginator
    {
        $query = Issue::query()
            ->with(['project', 'customer', 'reporter', 'assignee'])
            ->filter($filters)
            ->search($search)
            ->sort($sortBy, $direction);

        return $query->paginate($perPage);
    }

    public function findByUuid(string $uuid): Issue
    {
        return $this->repository->findByUuidOrFail($uuid);
    }

    public function create(array $data): Issue
    {
        $issue = $this->repository->create($data);

        // Record timeline entry. Null-safe: if the caller didn't pass an
        // explicit `status`, the in-memory model won't reflect the DB
        // column's default ('new') until it's refreshed, so fall back to it.
        $this->addTimelineEntry($issue, 'created', null, $issue->status?->value ?? IssueStatus::New->value);

        $loaded = $issue->load(['project', 'customer', 'reporter', 'assignee']);

        SendNotificationEmailJob::dispatch('new_issue', $issue->id);

        return $loaded;
    }

    public function update(string $uuid, array $data): Issue
    {
        $issue = $this->repository->findByUuidOrFail($uuid);

        $issue = $this->repository->update($issue->id, $data);

        return $issue->load(['project', 'customer', 'reporter', 'assignee']);
    }

    public function delete(string $uuid): bool
    {
        $issue = $this->repository->findByUuidOrFail($uuid);

        return $this->repository->delete($issue->id);
    }

    public function changeStatus(string $uuid, IssueStatus $newStatus, ?int $employeeId = null): Issue
    {
        $issue = $this->repository->findByUuidOrFail($uuid);
        $oldStatus = $issue->status;

        $updateData = ['status' => $newStatus->value];

        if ($newStatus === IssueStatus::Resolved) {
            $updateData['resolved_at'] = now();
        }

        if ($newStatus === IssueStatus::Closed) {
            $updateData['closed_at'] = now();
        }

        $issue = $this->repository->update($issue->id, $updateData);

        $this->addTimelineEntry($issue, 'status_changed', $oldStatus?->value ?? IssueStatus::New->value, $newStatus->value, $employeeId);

        return $issue;
    }

    public function assign(string $uuid, int $employeeId, ?int $assignedBy = null): Issue
    {
        $issue = $this->repository->findByUuidOrFail($uuid);
        $oldAssignee = $issue->assigned_to;

        $issue = $this->repository->update($issue->id, ['assigned_to' => $employeeId]);

        $this->addTimelineEntry($issue, 'assigned', (string) $oldAssignee, (string) $employeeId, $assignedBy);

        return $issue->load('assignee');
    }

    public function addComment(string $uuid, array $data): Issue
    {
        $issue = $this->repository->findByUuidOrFail($uuid);

        $issue->comments()->create($data);

        $this->addTimelineEntry($issue, 'commented', null, null, $data['employee_id'] ?? null);

        return $issue->load('comments');
    }

    protected function addTimelineEntry(Issue $issue, string $action, ?string $oldValue, ?string $newValue, ?int $employeeId = null): void
    {
        IssueTimeline::create([
            'issue_id' => $issue->id,
            'employee_id' => $employeeId,
            'action' => $action,
            'old_value' => $oldValue,
            'new_value' => $newValue,
        ]);
    }
}
