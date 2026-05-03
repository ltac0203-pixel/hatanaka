<?php

declare(strict_types=1);

namespace App\Services\Fincode;

use App\Exceptions\FincodeApiException;
use App\Exceptions\PlanUnavailableException;
use Illuminate\Support\Facades\Cache;

class PlanService
{
    /**
     * プラン情報を保持する標準キャッシュ TTL (秒)。
     * Fincode 管理画面でのプラン編集が利用画面に遅くとも 5 分で反映されるよう短めに保つ。
     */
    private const ACTIVE_PLAN_CACHE_TTL_SECONDS = 300;

    /**
     * 不在プランの negative cache TTL (秒)。
     * 削除済み扱いになったプランが Fincode 側で再有効化されたときに早く再取得できるよう、
     * 通常キャッシュより大幅に短くしている。
     */
    private const PLAN_NOT_FOUND_CACHE_TTL_SECONDS = 60;

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
        return Cache::remember('fincode.plans.list', self::ACTIVE_PLAN_CACHE_TTL_SECONDS, fn () => $this->fetchActivePlans());
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
        // null は Cache::remember 上で「未保存」と区別不能のため、not-found は false センチネルで保存する。
        // not-found 結果のキャッシュ TTL を短くして、Fincode 側で再有効化されたプランを早く再取得できるようにする。
        $cacheKey = "fincode.plans.{$fincodePlanId}";
        $cached = Cache::get($cacheKey);

        if ($cached === false) {
            return null;
        }

        if (is_array($cached)) {
            return $cached;
        }

        $result = $this->fetchActivePlan($fincodePlanId);

        if ($result === null) {
            // not-found は短時間のみ negative cache し、誤って削除済みプランが永続的に隠れないようにする。
            Cache::put($cacheKey, false, self::PLAN_NOT_FOUND_CACHE_TTL_SECONDS);

            return null;
        }

        Cache::put($cacheKey, $result, self::ACTIVE_PLAN_CACHE_TTL_SECONDS);

        return $result;
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
