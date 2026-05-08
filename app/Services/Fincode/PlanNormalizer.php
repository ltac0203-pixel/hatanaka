<?php

declare(strict_types=1);

namespace App\Services\Fincode;

use App\Enums\PlanInterval;

final class PlanNormalizer
{
    public function extractPlans(array $response): array
    {
        if (isset($response['list']) && is_array($response['list'])) {
            return $response['list'];
        }

        if (isset($response['plans']) && is_array($response['plans'])) {
            return $response['plans'];
        }

        if (isset($response['data']) && is_array($response['data'])) {
            if (array_is_list($response['data'])) {
                return $response['data'];
            }

            if (isset($response['data']['list']) && is_array($response['data']['list'])) {
                return $response['data']['list'];
            }

            if (isset($response['data']['plans']) && is_array($response['data']['plans'])) {
                return $response['data']['plans'];
            }
        }

        return array_is_list($response) ? $response : [];
    }

    public function extractSinglePlan(array $response): array
    {
        if (isset($response['data']) && is_array($response['data']) && ! array_is_list($response['data'])) {
            return $response['data'];
        }

        return $response;
    }

    public function normalizePlan(array $plan): array
    {
        $fincodePlanId = (string) ($plan['id'] ?? $plan['plan_id'] ?? $plan['fincode_plan_id'] ?? '');
        $interval = PlanInterval::fromApi((string) ($plan['interval'] ?? $plan['cycle'] ?? $plan['billing_cycle'] ?? 'monthly'));
        $intervalCount = max((int) ($plan['interval_count'] ?? $plan['cycle_count'] ?? 1), 1);
        $amount = (int) ($plan['amount'] ?? $plan['price'] ?? $plan['unit_amount'] ?? 0);
        $status = $this->normalizeStatus((string) ($plan['status'] ?? 'active'));
        if (((string) ($plan['delete_flag'] ?? '0')) === '1') {
            $status = 'archived';
        }
        $features = $this->normalizeFeatures($plan['features'] ?? null);

        return [
            'id' => $fincodePlanId,
            'fincode_plan_id' => $fincodePlanId,
            'name' => (string) ($plan['name'] ?? $plan['plan_name'] ?? ''),
            'description' => isset($plan['description']) ? (string) $plan['description'] : null,
            'amount' => $amount,
            'interval' => $interval->value,
            'interval_count' => $intervalCount,
            'status' => $status,
            'features' => $features,
            'price_display' => $interval->priceDisplay($amount, $intervalCount),
            'interval_label' => $interval->label($intervalCount),
            'metadata' => is_array($plan['metadata'] ?? null) ? $plan['metadata'] : null,
        ];
    }

    private function normalizeStatus(string $status): string
    {
        return match (strtolower($status)) {
            'active', 'enabled', 'public' => 'active',
            'inactive', 'disabled' => 'inactive',
            'archived', 'deleted' => 'archived',
            default => 'inactive',
        };
    }

    private function normalizeFeatures(mixed $features): ?array
    {
        if (! is_array($features)) {
            return null;
        }

        return array_values(array_map(
            fn ($feature): string => (string) $feature,
            $features
        ));
    }
}
