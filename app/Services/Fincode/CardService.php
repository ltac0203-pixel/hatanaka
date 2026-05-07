<?php

declare(strict_types=1);

namespace App\Services\Fincode;

final class CardService
{
    public function __construct(private readonly FincodeClient $client) {}

    public function create(string $customerId, string $token, bool $defaultFlag = false, ?string $idempotencyKey = null): array
    {
        return $this->client->post("/v1/customers/{$customerId}/cards", [
            'token' => $token,
            'default_flag' => $defaultFlag ? '1' : '0',
        ], $idempotencyKey);
    }

    public function getCard(string $customerId, string $cardId): array
    {
        return $this->client->get("/v1/customers/{$customerId}/cards/{$cardId}");
    }

    public function deleteCard(string $customerId, string $cardId, ?string $idempotencyKey = null): array
    {
        return $this->client->delete("/v1/customers/{$customerId}/cards/{$cardId}", [], $idempotencyKey);
    }
}
