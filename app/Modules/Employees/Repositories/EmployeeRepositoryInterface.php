<?php

namespace Modules\Employees\Repositories;

use App\Core\Contracts\RepositoryInterface;

interface EmployeeRepositoryInterface extends RepositoryInterface
{
    public function findByUserId(int $userId);
    public function getAvailable();
    public function getByRole(string $role);
    public function getByDepartment(string $department);
    public function getWithCapacity();
}
