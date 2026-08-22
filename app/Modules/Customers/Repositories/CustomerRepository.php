<?php

namespace Modules\Customers\Repositories;

use App\Core\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Modules\Customers\Models\Customer;

class CustomerRepository extends BaseRepository implements CustomerRepositoryInterface
{
    public function __construct(Customer $model)
    {
        parent::__construct($model);
    }

    public function findByWhatsAppId(string $whatsappId): ?Customer
    {
        return $this->newQuery()->where('whatsapp_id', $whatsappId)->first();
    }

    public function findByPhone(string $phone): ?Customer
    {
        return $this->newQuery()->where('phone', $phone)->first();
    }

    public function findByEmail(string $email): ?Customer
    {
        return $this->newQuery()->where('email', $email)->first();
    }

    public function getActive(): Collection
    {
        return $this->newQuery()->where('is_active', true)->get();
    }
}
