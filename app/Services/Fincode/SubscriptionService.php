<?php

declare(strict_types=1);

namespace App\Services\Fincode;

class SubscriptionService
{
    public function __construct(private FincodeClient $client) {}

    public function create(array $data, ?string $idempotencyKey = null): array
    {
        return $this->client->post('/v1/subscriptions', $data, $idempotencyKey);
    }

    public function getSubscription(string $subscriptionId): array
    {
        return $this->client->get("/v1/subscriptions/{$subscriptionId}");
    }

    public function update(string $subscriptionId, array $data): array
    {
        return $this->client->put("/v1/subscriptions/{$subscriptionId}", $data);
    }

    public function cancel(string $subscriptionId): array
    {
        return $this->client->put("/v1/subscriptions/{$subscriptionId}", [
            'status' => FincodeApiStatus::CANCELED,
        ]);
    }

    public function getResults(string $subscriptionId): array
    {
        return $this->client->get("/v1/subscriptions/{$subscriptionId}/results");
    }
}
