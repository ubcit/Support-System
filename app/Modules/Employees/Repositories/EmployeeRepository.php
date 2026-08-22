<?php

namespace Modules\Employees\Repositories;

use App\Core\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Modules\Employees\Models\Employee;

class EmployeeRepository extends BaseRepository implements EmployeeRepositoryInterface
{
    public function __construct(Employee $model)
    {
        parent::__construct($model);
    }

    public function findByUserId(int $userId): ?Employee
    {
        return $this->newQuery()->where('user_id', $userId)->first();
    }

    public function getAvailable(): Collection
    {
        return $this->newQuery()->where('is_available', true)->get();
    }

    public function getByRole(string $role): Collection
    {
        return $this->newQuery()->where('role', $role)->get();
    }

    public function getByDepartment(string $department): Collection
    {
        return $this->newQuery()->where('department', $department)->get();
    }

    public function getWithCapacity(): Collection
    {
        return $this->getAvailable()->filter(function (Employee $employee) {
            return $employee->hasCapacity();
        });
    }
}
