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
            // PUT /v1/subscriptions/{id} も決済種別が必須 (ES003023001 決済種別が指定されていません)。
            'pay_type' => 'Card',
            'status' => FincodeApiStatus::CANCELED,
        ]);
    }

    public function getResults(string $subscriptionId): array
    {
        return $this->client->get("/v1/subscriptions/{$subscriptionId}/results");
    }
}
