<?php

namespace Modules\Issues\Repositories;

use App\Core\Contracts\RepositoryInterface;

interface IssueRepositoryInterface extends RepositoryInterface
{
    public function getByProject(int $projectId);
    public function getByCustomer(int $customerId);
    public function getByStatus(string $status);
    public function getByAssignee(int $employeeId);
    public function getOverdue();
}
