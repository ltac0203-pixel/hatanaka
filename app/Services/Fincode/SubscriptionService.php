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
        // PUT /v1/subscriptions/{id} は課金開始済みのサブスクを変更不可 (ESC03194031) で、
        // 同日に作成したサブスクをユーザが当日解約できなくなる。
        // DELETE /v1/subscriptions/{id} は課金開始日と同日でも受理されるため、解約はこちらに寄せる。
        // pay_type は query で必須 (ES002023001 決済種別が指定されていません)。
        return $this->client->delete("/v1/subscriptions/{$subscriptionId}", [
            'pay_type' => 'Card',
        ]);
    }

    public function getResults(string $subscriptionId): array
    {
        return $this->client->get("/v1/subscriptions/{$subscriptionId}/results");
    }
}
