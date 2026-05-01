<?php

declare(strict_types=1);

namespace App\Services\Fincode;

use App\Exceptions\FincodeApiException;
use App\Exceptions\PlanUnavailableException;
use Illuminate\Support\Facades\Cache;

class PlanService
{
    public function __construct(
        private FincodeClient $client,
        private PlanNormalizer $normalizer
    ) {}

    /**
     * 契約可能なプランだけを一覧化し、画面でそのまま使える形へ正規化する。
     *
     * @throws FincodeApiException
     */
    public function listActivePlans(): array
    {
        return Cache::remember('fincode.plans.list', 300, fn () => $this->fetchActivePlans());
    }

    /**
     * 指定プランが契約可能であることを保証しつつ詳細を返す。
     *
     * @throws FincodeApiException
     * @throws PlanUnavailableException
     */
    public function findActivePlanOrFail(string $fincodePlanId): array
    {
        $plan = $this->findActivePlan($fincodePlanId);

        if ($plan === null) {
            throw new PlanUnavailableException($fincodePlanId);
        }

        return $plan;
    }

    /**
     * 指定プランがまだ契約可能かを判定しつつ詳細を取り出す。
     *
     * @throws FincodeApiException
     */
    public function findActivePlan(string $fincodePlanId): ?array
    {
        $cached = Cache::remember(
            "fincode.plans.{$fincodePlanId}",
            300,
            function () use ($fincodePlanId) {
                $result = $this->fetchActivePlan($fincodePlanId);

                return $result ?? false;
            }
        );

        return $cached === false ? null : $cached;
    }

    /**
     * @throws FincodeApiException
     */
    private function fetchActivePlans(): array
    {
        $response = $this->client->get('/v1/plans');
        $plans = $this->normalizer->extractPlans($response);

        return array_values(array_filter(
            array_map(fn (array $plan): array => $this->normalizer->normalizePlan($plan), $plans),
            fn (array $plan): bool => $plan['status'] === 'active'
        ));
    }

    /**
     * @throws FincodeApiException
     */
    private function fetchActivePlan(string $fincodePlanId): ?array
    {
        try {
            $response = $this->client->get("/v1/plans/{$fincodePlanId}");
        } catch (FincodeApiException $e) {
            if ($e->getStatusCode() === 404) {
                return null;
            }

            throw $e;
        }

        $plan = $this->normalizer->normalizePlan($this->normalizer->extractSinglePlan($response));

        if ($plan['fincode_plan_id'] === '' || $plan['status'] !== 'active') {
            return null;
        }

        return $plan;
    }
}
