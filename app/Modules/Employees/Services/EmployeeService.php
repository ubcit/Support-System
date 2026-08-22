<?php

namespace Modules\Employees\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Employees\Models\Employee;
use Modules\Employees\Repositories\EmployeeRepositoryInterface;

class EmployeeService
{
    public function __construct(
        protected EmployeeRepositoryInterface $repository
    ) {}

    public function list(array $filters = [], ?string $search = null, ?string $sortBy = null, string $direction = 'desc', int $perPage = 15): LengthAwarePaginator
    {
        $query = Employee::query()
            ->filter($filters)
            ->search($search)
            ->sort($sortBy, $direction);

        return $query->paginate($perPage);
    }

    public function findByUuid(string $uuid): Employee
    {
        return $this->repository->findByUuidOrFail($uuid);
    }

    public function create(array $data): Employee
    {
        $employee = $this->repository->create($data);

        // Add skills if provided
        if (!empty($data['skills'])) {
            foreach ($data['skills'] as $skill) {
                $employee->skills()->create($skill);
            }
        }

        return $employee->load('skills');
    }

    public function update(string $uuid, array $data): Employee
    {
        $employee = $this->repository->findByUuidOrFail($uuid);

        $employee = $this->repository->update($employee->id, $data);

        // Sync skills if provided
        if (isset($data['skills'])) {
            $employee->skills()->delete();
            foreach ($data['skills'] as $skill) {
                $employee->skills()->create($skill);
            }
        }

        return $employee->load('skills');
    }

    public function delete(string $uuid): bool
    {
        $employee = $this->repository->findByUuidOrFail($uuid);

        return $this->repository->delete($employee->id);
    }

    public function getAvailableWithCapacity()
    {
        return $this->repository->getWithCapacity();
    }
}
