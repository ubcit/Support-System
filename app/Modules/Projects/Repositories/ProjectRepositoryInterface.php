<?php

namespace Modules\Projects\Repositories;

use App\Core\Contracts\RepositoryInterface;

interface ProjectRepositoryInterface extends RepositoryInterface
{
    public function findByAlias(string $alias);
    public function getByCustomer(int $customerId);
    public function getByStatus(string $status);
}
