<?php

declare(strict_types=1);

namespace App\Services\Fincode;

class CustomerService
{
    public function __construct(private FincodeClient $client) {}

    public function create(array $data): array
    {
        return $this->client->post('/v1/customers', $data);
    }

    public function getCustomer(string $customerId): array
    {
        return $this->client->get("/v1/customers/{$customerId}");
    }

    public function update(string $customerId, array $data): array
    {
        return $this->client->put("/v1/customers/{$customerId}", $data);
    }
}
