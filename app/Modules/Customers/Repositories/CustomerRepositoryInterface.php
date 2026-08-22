<?php

namespace Modules\Customers\Repositories;

use App\Core\Contracts\RepositoryInterface;

interface CustomerRepositoryInterface extends RepositoryInterface
{
    public function findByWhatsAppId(string $whatsappId);
    public function findByPhone(string $phone);
    public function findByEmail(string $email);
    public function getActive();
}
