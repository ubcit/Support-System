<?php

namespace Modules\Projects\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Modules\Customers\Models\Customer;
use Modules\Employees\Models\Employee;
use Modules\Notifications\Services\EmailNotificationService;
use Modules\Notifications\Services\NotificationService;
use Modules\Projects\Enums\ProjectStatus;
use Modules\Projects\Models\Project;
use Modules\Projects\Repositories\ProjectRepositoryInterface;
use Modules\Projects\Support\ProjectCodeGenerator;

class ProjectService
{
    public function __construct(
        protected ProjectRepositoryInterface $repository,
        protected ProjectCodeGenerator $codes,
    ) {}

    public function list(array $filters = [], ?string $search = null, ?string $sortBy = null, string $direction = 'desc', int $perPage = 15): LengthAwarePaginator
    {
        $query = Project::query()
            ->with(['customer', 'category'])
            ->filter($filters)
            ->search($search)
            ->sort($sortBy, $direction);

        return $query->paginate($perPage);
    }

    public function findByUuid(string $uuid): Project
    {
        return $this->repository->findByUuidOrFail($uuid);
    }

    public function create(array $data): Project
    {
        if (empty($data['code'])) {
            $data['code'] = $this->codes->generate($data['workspace_id'] ?? null);
        } else {
            $data['code'] = $this->codes->normalize((string) $data['code']);
        }

        $project = $this->repository->create($data);

        // Add aliases if provided
        if (! empty($data['aliases'])) {
            foreach ($data['aliases'] as $alias) {
                $project->aliases()->create(['alias' => $alias]);
            }
        }

        if (! empty($project->customer_id)) {
            $this->attachCustomer($project, Customer::find($project->customer_id));
        }

        return $project->load(['customer', 'category', 'aliases', 'customers']);
    }

    public function update(string $uuid, array $data): Project
    {
        $project = $this->repository->findByUuidOrFail($uuid);

        if (array_key_exists('code', $data) && filled($data['code'])) {
            $data['code'] = $this->codes->normalize((string) $data['code']);
        } else {
            unset($data['code']);
        }

        $project = $this->repository->update($project->id, $data);

        // Sync aliases if provided
        if (isset($data['aliases'])) {
            $project->aliases()->delete();
            foreach ($data['aliases'] as $alias) {
                $project->aliases()->create(['alias' => $alias]);
            }
        }

        if (! empty($project->customer_id)) {
            $this->attachCustomer($project, Customer::find($project->customer_id));
        }

        return $project->load(['customer', 'category', 'aliases', 'customers']);
    }

    public function delete(string $uuid): bool
    {
        $project = $this->repository->findByUuidOrFail($uuid);

        return $this->repository->delete($project->id);
    }

    public function findByAlias(string $alias): ?Project
    {
        return $this->repository->findByAlias($alias);
    }

    public function findByCode(string $code, ?int $workspaceId = null): ?Project
    {
        $normalized = $this->codes->normalize($code);
        if ($normalized === '') {
            return null;
        }

        $query = Project::query()->where('code', $normalized);
        if ($workspaceId !== null) {
            $query->where('workspace_id', $workspaceId);
        }

        return $query->first();
    }

    public function attachCustomer(Project $project, ?Customer $customer): void
    {
        if (! $customer) {
            return;
        }

        $project->customers()->syncWithoutDetaching([$customer->id]);
    }

    public function customerHasAnyProject(int $customerId): bool
    {
        return Project::query()
            ->where(function ($query) use ($customerId) {
                $query->where('customer_id', $customerId)
                    ->orWhereHas('customers', fn ($linked) => $linked->where('customers.id', $customerId));
            })
            ->exists();
    }

    public function assignEmployee(string $projectUuid, int $employeeId, ?string $role = null): void
    {
        $project = $this->repository->findByUuidOrFail($projectUuid);
        $alreadyMember = $project->employees()->where('employees.id', $employeeId)->exists();

        $project->employees()->syncWithoutDetaching([
            $employeeId => [
                'role' => $role,
                'assigned_at' => now(),
            ],
        ]);

        if (! $alreadyMember) {
            $this->notifyMembersAdded($project, [$employeeId]);
        }
    }

    /**
     * Replace a project's whole employee roster wholesale (used by the
     * ProjectHub create/edit modal, which manages membership as a single
     * multi-select rather than one assignEmployee() call at a time).
     *
     * @param  list<int>  $employeeIds
     */
    public function syncEmployees(Project $project, array $employeeIds): void
    {
        $employeeIds = array_values(array_unique(array_filter($employeeIds)));
        $oldIds = $project->employees()->pluck('employees.id')->map(fn ($id) => (int) $id)->all();

        $project->employees()->sync($employeeIds);

        $added = array_values(array_diff(
            array_map('intval', $employeeIds),
            $oldIds
        ));

        if ($added !== []) {
            $this->notifyMembersAdded($project, $added);
        }
    }

    /**
     * @param  list<int>  $employeeIds
     */
    protected function notifyMembersAdded(Project $project, array $employeeIds): void
    {
        $notifications = app(NotificationService::class);

        foreach ($employeeIds as $employeeId) {
            $employee = Employee::find($employeeId);
            if (! $employee) {
                continue;
            }

            $notifications->send(
                title: 'Added to project: '.$project->name,
                body: 'You were added to this project team.',
                type: 'project_member_added',
                employee: $employee,
                userId: $employee->user_id,
                actionUrl: route('project-hub'),
                metadata: ['project_id' => $project->id],
            );

            app(EmailNotificationService::class)->sendProjectMemberAdded($project, $employee);
        }
    }

    /**
     * Attach a task to one of this customer's active projects.
     *
     * Matching order: exact code, exact name, exact alias, unique partial name/alias.
     * If the customer has several projects and AI did not uniquely name one,
     * return null (do not guess, and never pick another customer's project).
     * A single active project is used as a fallback when AI names nothing.
     * Session-pinned project_id (if provided via match options) wins when set.
     */
    public function matchForCustomer(?int $customerId, mixed $aiProject, ?int $preferredProjectId = null): ?Project
    {
        if (! $customerId) {
            return null;
        }

        if ($preferredProjectId) {
            $preferred = $this->projectsForCustomer($customerId)
                ->firstWhere('id', $preferredProjectId);
            if ($preferred) {
                return $preferred;
            }
        }

        $projects = $this->projectsForCustomer($customerId);

        if ($projects->isEmpty()) {
            return null;
        }

        $needle = $this->normalizeProjectName($aiProject);
        $codeNeedle = $this->codes->normalize(is_string($aiProject) ? $aiProject : (is_array($aiProject) ? (string) ($aiProject['code'] ?? $aiProject['name'] ?? $aiProject['matched'] ?? '') : ''));

        if ($needle === '' && $codeNeedle === '') {
            return $projects->count() === 1 ? $projects->first() : null;
        }

        if ($codeNeedle !== '') {
            $codeHit = $projects->first(
                fn (Project $project) => $this->codes->normalize((string) $project->code) === $codeNeedle
            );
            if ($codeHit) {
                return $codeHit;
            }
        }

        if ($needle === '') {
            return $projects->count() === 1 ? $projects->first() : null;
        }

        $exact = $projects->first(fn (Project $project) => mb_strtolower(trim($project->name)) === $needle);
        if ($exact) {
            return $exact;
        }

        $aliasHit = $projects->first(function (Project $project) use ($needle) {
            return $project->aliases->contains(
                fn ($alias) => mb_strtolower(trim((string) $alias->alias)) === $needle
            );
        });
        if ($aliasHit) {
            return $aliasHit;
        }

        $partial = $projects->filter(function (Project $project) use ($needle) {
            $name = mb_strtolower($project->name);
            if (str_contains($name, $needle) || str_contains($needle, $name)) {
                return true;
            }

            return $project->aliases->contains(function ($alias) use ($needle) {
                $value = mb_strtolower(trim((string) $alias->alias));

                return $value !== '' && (str_contains($value, $needle) || str_contains($needle, $value));
            });
        });

        return $partial->count() === 1 ? $partial->first() : null;
    }

    /**
     * @return Collection<int, Project>
     */
    public function projectsForCustomer(int $customerId)
    {
        return Project::query()
            ->with('aliases')
            ->where('status', ProjectStatus::Active)
            ->where(function ($query) use ($customerId) {
                $query->where('customer_id', $customerId)
                    ->orWhereHas('customers', fn ($linked) => $linked->where('customers.id', $customerId));
            })
            ->get()
            ->unique('id')
            ->values();
    }

    protected function normalizeProjectName(mixed $aiProject): string
    {
        if (is_array($aiProject)) {
            $aiProject = $aiProject['matched'] ?? $aiProject['name'] ?? '';
        }

        return is_string($aiProject) ? mb_strtolower(trim($aiProject)) : '';
    }
}
