<?php

namespace Modules\Customers\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Customers\Models\Customer;
use Modules\Customers\Repositories\CustomerRepositoryInterface;

class CustomerService
{
    public function __construct(
        protected CustomerRepositoryInterface $repository
    ) {}

    public function list(array $filters = [], ?string $search = null, ?string $sortBy = null, string $direction = 'desc', int $perPage = 15): LengthAwarePaginator
    {
        $query = Customer::query()
            ->filter($filters)
            ->search($search)
            ->sort($sortBy, $direction);

        return $query->paginate($perPage);
    }

    public function findByUuid(string $uuid): Customer
    {
        return $this->repository->findByUuidOrFail($uuid);
    }

    public function create(array $data): Customer
    {
        return $this->repository->create($data);
    }

    public function update(string $uuid, array $data): Customer
    {
        $customer = $this->repository->findByUuidOrFail($uuid);

        return $this->repository->update($customer->id, $data);
    }

    public function delete(string $uuid): bool
    {
        $customer = $this->repository->findByUuidOrFail($uuid);

        return $this->repository->delete($customer->id);
    }

    public function findByPhone(string $phone): ?Customer
    {
        return $this->repository->findByPhone($phone);
    }

    public function findByWhatsAppId(string $whatsappId): ?Customer
    {
        return $this->repository->findByWhatsAppId($whatsappId);
    }

    public function findOrCreateByPhone(string $phone, array $data = []): Customer
    {
        $customer = $this->repository->findByPhone($phone);

        if ($customer) {
            return $customer;
        }

        return $this->repository->create(array_merge(['phone' => $phone], $data));
    }

    public function findByEmail(string $email): ?Customer
    {
        return $this->repository->findByEmail($email);
    }

    public function findOrCreateByEmail(string $email, array $data = []): Customer
    {
        $customer = $this->findByEmail($email);

        if ($customer) {
            return $customer;
        }

        return $this->repository->create(array_merge(['email' => $email], $data));
    }
}
