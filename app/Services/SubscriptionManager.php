<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Events\SubscriptionCanceled;
use App\Events\SubscriptionCreated;
use App\Events\SubscriptionStatusChanged;
use App\Exceptions\ActiveSubscriptionExistsException;
use App\Exceptions\ExpiredCardException;
use App\Exceptions\FincodeApiException;
use App\Models\FincodeCard;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Fincode\FincodePayType;
use App\Services\Fincode\SubscriptionService as FincodeSubscriptionService;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionManager
{
    private const ACTIVE_SUBSCRIPTION_UNIQUE_INDEX = 'subscriptions_active_user_id_unique';

    protected FincodeSubscriptionService $subscriptionService;

    /**
     * 契約前に顧客同期の前提を満たすために利用する。
     */
    protected CustomerSyncService $customerSyncService;

    /**
     * 現在のリクエスト由来コンテキストを監査イベントへ固定する。
     */
    protected RequestContextResolver $requestContextResolver;

    public function __construct(
        FincodeSubscriptionService $subscriptionService,
        CustomerSyncService $customerSyncService,
        RequestContextResolver $requestContextResolver
    ) {
        $this->subscriptionService = $subscriptionService;
        $this->customerSyncService = $customerSyncService;
        $this->requestContextResolver = $requestContextResolver;
    }

    public function create(User $user, array $planData, FincodeCard $card, string $startDate): Subscription
    {
        if ($card->isExpired()) {
            throw new ExpiredCardException;
        }

        $requestContext = $this->requestContextResolver->resolve();

        return DB::transaction(function () use ($user, $planData, $card, $startDate, $requestContext) {
            $this->lockUserForSubscriptionCreation($user);
            $this->assertNoActiveSubscription($user);

            // 顧客未作成のまま契約作成が走る不整合を防ぐため先に同期する。
            $customer = $this->customerSyncService->ensureCustomerExists($user);
            $idempotencyKey = $this->buildCreateIdempotencyKey(
                $user,
                (string) $planData['fincode_plan_id'],
                $card,
                $startDate
            );

            // 外部契約作成が失敗した場合にローカルだけ登録される状態を防ぐ。
            try {
                $response = $this->subscriptionService->create([
                    // Fincode のサブスク作成は決済種別が必須 (ES001023001)。本実装はカード決済のみを想定する。
                    'pay_type' => FincodePayType::CARD,
                    'plan_id' => $planData['fincode_plan_id'],
                    'customer_id' => $customer->fincode_customer_id,
                    'card_id' => $card->fincode_card_id,
                    // Fincode は課金開始日を Y/m/d 形式で要求する (ESC01196008 課金開始日の書式が正しくありません)。
                    'start_date' => Carbon::parse($startDate)->format('Y/m/d'),
                ], $idempotencyKey);
            } catch (FincodeApiException $e) {
                Log::error('Failed to create subscription on Fincode', [
                    'user_id' => $user->id,
                    'fincode_plan_id' => $planData['fincode_plan_id'],
                    'exception_class' => $e::class,
                    'status_code' => $e->getStatusCode(),
                ]);
                throw $e;
            }

            // 外部 API の日時形式差分を吸収し、DB 保存形式を安定させる。
            $parsedStartDate = $this->parseFincodeDate($response['start_date']);
            $parsedStopDate = $this->parseFincodeDate($response['stop_date'] ?? null);
            $parsedNextChargeDate = $this->parseFincodeDate($response['next_charge_date'] ?? null);

            // 画面表示と二重登録防止に使えるようローカルへ確定保存する。
            try {
                $subscription = new Subscription([
                    'fincode_plan_id' => $planData['fincode_plan_id'],
                    'plan_name' => $planData['name'] ?? '',
                    'plan_amount' => $planData['amount'] ?? 0,
                    'plan_interval' => $planData['interval'] ?? 'monthly',
                    'plan_interval_count' => $planData['interval_count'] ?? 1,
                    'plan_snapshot' => $planData,
                    'fincode_subscription_id' => $response['id'],
                    'fincode_customer_id' => $customer->fincode_customer_id,
                    'fincode_card_id' => $card->fincode_card_id,
                    'start_date' => $parsedStartDate,
                    'stop_date' => $parsedStopDate,
                    'next_charge_date' => $parsedNextChargeDate,
                ]);
                $subscription->user_id = $user->id;
                $resolvedStatus = SubscriptionStatus::tryFromApi($response['status'] ?? 'incomplete');
                if ($resolvedStatus === null) {
                    Log::warning('Unknown subscription status from Fincode API, falling back to incomplete', [
                        'raw_status' => $response['status'] ?? null,
                        'subscription_id' => $response['id'] ?? null,
                    ]);
                    $resolvedStatus = SubscriptionStatus::Incomplete;
                }
                $subscription->status = $resolvedStatus->value;
                $subscription->save();
            } catch (QueryException $e) {
                if ($this->isActiveSubscriptionUniqueConstraintViolation($e)) {
                    throw new ActiveSubscriptionExistsException(previous: $e);
                }

                throw $e;
            }

            $event = new SubscriptionCreated(
                $subscription,
                $user,
                [],
                $subscription->toArray(),
                [],
                $requestContext->ipAddress,
                $requestContext->userAgent
            );

            // 永続化が確定した後だけ副作用を配信する。
            DB::afterCommit(static fn () => event($event));

            $subscription->setRelation('card', $card);

            return $subscription;
        });
    }

    private function lockUserForSubscriptionCreation(User $user): void
    {
        User::query()
            ->whereKey($user->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function assertNoActiveSubscription(User $user): void
    {
        $hasActiveSubscription = Subscription::query()
            ->where('user_id', $user->id)
            ->where('status', SubscriptionStatus::Active->value)
            ->whereNull('deleted_at')
            ->exists();

        if ($hasActiveSubscription) {
            throw new ActiveSubscriptionExistsException;
        }
    }

    private function isActiveSubscriptionUniqueConstraintViolation(QueryException $e): bool
    {
        $sqlState = (string) $e->getCode();
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        if ($sqlState !== '23000' || $driverCode !== 1062) {
            return false;
        }

        return str_contains($e->getMessage(), self::ACTIVE_SUBSCRIPTION_UNIQUE_INDEX);
    }

    private function buildCreateIdempotencyKey(User $user, string $planId, FincodeCard $card, string $startDate): string
    {
        return 'subscription:create:'.hash('sha256', implode('|', [
            (string) $user->id,
            $planId,
            $card->fincode_card_id,
            $startDate,
        ]));
    }

    private function parseFincodeDate(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Carbon::parse($value)
            ->tz(config('app.timezone'))
            ->toDateString();
    }

    public function cancel(Subscription $subscription, ?User $actor = null): void
    {
        $requestContext = $this->requestContextResolver->resolve();

        DB::transaction(function () use ($subscription, $actor, $requestContext) {
            // 監査イベントの起点を残すため、呼び出し側が actor を渡さない場合だけ関連ユーザーを引く。
            $user = $actor ?? $subscription->user()->first();
            $oldValues = $subscription->toArray();

            // 外部課金が止まらないままローカルだけ解約される状態を防ぐ。
            try {
                $this->subscriptionService->cancel($subscription->fincode_subscription_id);
            } catch (FincodeApiException $e) {
                Log::error('Failed to cancel subscription on Fincode', [
                    'subscription_id' => $subscription->id,
                    'exception_class' => $e::class,
                    'status_code' => $e->getStatusCode(),
                ]);
                throw $e;
            }

            // 画面表示と内部判定が外部状態に追従するようローカルも更新する。
            $subscription->cancel();

            $newValues = $subscription->toArray();

            $subscriptionCanceled = new SubscriptionCanceled(
                $subscription,
                $user,
                $oldValues,
                $newValues,
                [],
                $requestContext->ipAddress,
                $requestContext->userAgent
            );

            $statusChanged = new SubscriptionStatusChanged(
                $subscription,
                $user,
                $oldValues,
                $newValues,
                (string) ($oldValues['status'] ?? ''),
                (string) ($newValues['status'] ?? ''),
                ['trigger' => 'subscription.cancel'],
                $requestContext->ipAddress,
                $requestContext->userAgent
            );

            // 解約が確定した後だけ関連イベントを配信する。
            DB::afterCommit(static function () use ($subscriptionCanceled, $statusChanged): void {
                event($subscriptionCanceled);
                event($statusChanged);
            });
        });
    }
}
