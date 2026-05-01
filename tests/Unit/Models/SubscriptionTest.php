<?php

namespace Tests\Unit\Models;

use App\Models\FincodeCard;
use App\Models\FincodeCustomer;
use App\Models\Subscription;
use App\Models\SubscriptionResult;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private FincodeCustomer $fincodeCustomer;

    private FincodeCard $card;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 2, 14, 10, 0, 0));

        $this->user = User::factory()->create();

        $this->fincodeCustomer = FincodeCustomer::create([
            'user_id' => $this->user->id,
            'fincode_customer_id' => 'cus_test_sub',
            'name' => $this->user->name,
            'email' => $this->user->email,
        ]);

        $this->card = FincodeCard::create([
            'user_id' => $this->user->id,
            'fincode_customer_id' => 'cus_test_sub',
            'fincode_card_id' => 'card_test_sub',
            'brand' => 'Visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'holder_name' => 'TEST USER',
            'is_default' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createSubscription(array $overrides = []): Subscription
    {
        return Subscription::create(array_merge([
            'user_id' => $this->user->id,
            'fincode_plan_id' => 'pl_test_001',
            'plan_name' => 'Test Plan',
            'plan_amount' => 1000,
            'plan_interval' => 'monthly',
            'plan_interval_count' => 1,
            'plan_snapshot' => ['name' => 'Test Plan', 'amount' => 1000],
            'fincode_subscription_id' => 'sub_test_001',
            'fincode_customer_id' => 'cus_test_sub',
            'fincode_card_id' => 'card_test_sub',
            'status' => 'active',
            'start_date' => now()->toDateString(),
        ], $overrides));
    }

    public function test_is_active_returns_true_for_active_subscription(): void
    {
        $subscription = $this->createSubscription(['status' => 'active']);

        $this->assertTrue($subscription->isActive());
    }

    public function test_is_active_returns_false_for_canceled_subscription(): void
    {
        $subscription = $this->createSubscription(['status' => 'canceled']);

        $this->assertFalse($subscription->isActive());
    }

    public function test_is_canceled_returns_true_for_canceled_subscription(): void
    {
        $subscription = $this->createSubscription(['status' => 'canceled']);

        $this->assertTrue($subscription->isCanceled());
    }

    public function test_is_canceled_returns_false_for_active_subscription(): void
    {
        $subscription = $this->createSubscription(['status' => 'active']);

        $this->assertFalse($subscription->isCanceled());
    }

    public function test_cancel_method_updates_status_and_dates(): void
    {
        $subscription = $this->createSubscription(['status' => 'active']);

        $subscription->cancel();

        $subscription->refresh();

        $this->assertSame('canceled', $subscription->status);
        $this->assertNotNull($subscription->canceled_at);
        $this->assertSame(now()->toDateString(), $subscription->stop_date->toDateString());
    }

    public function test_scope_active_filters_correctly(): void
    {
        $this->createSubscription([
            'status' => 'active',
            'fincode_subscription_id' => 'sub_active_001',
        ]);

        $this->createSubscription([
            'status' => 'canceled',
            'fincode_subscription_id' => 'sub_canceled_001',
        ]);

        $otherUser = User::factory()->create();
        FincodeCustomer::create([
            'user_id' => $otherUser->id,
            'fincode_customer_id' => 'cus_test_sub_other',
            'name' => $otherUser->name,
            'email' => $otherUser->email,
        ]);
        FincodeCard::create([
            'user_id' => $otherUser->id,
            'fincode_customer_id' => 'cus_test_sub_other',
            'fincode_card_id' => 'card_test_sub_other',
            'brand' => 'Mastercard',
            'last4' => '5555',
            'exp_month' => 11,
            'exp_year' => 2031,
            'holder_name' => 'OTHER USER',
            'is_default' => true,
        ]);
        $this->createSubscription([
            'user_id' => $otherUser->id,
            'fincode_customer_id' => 'cus_test_sub_other',
            'fincode_card_id' => 'card_test_sub_other',
            'status' => 'active',
            'fincode_subscription_id' => 'sub_active_002',
        ]);

        $activeSubscriptions = Subscription::active()->get();

        $this->assertCount(2, $activeSubscriptions);
        $activeSubscriptions->each(function ($subscription) {
            $this->assertSame('active', $subscription->status);
        });
    }

    public function test_scope_active_excludes_soft_deleted_subscriptions_when_with_trashed_is_used(): void
    {
        $deletedActiveSubscription = $this->createSubscription([
            'fincode_subscription_id' => 'sub_active_deleted',
        ]);

        $deletedActiveSubscription->delete();

        $activeSubscription = $this->createSubscription([
            'fincode_subscription_id' => 'sub_active_visible',
        ]);

        $activeSubscriptions = Subscription::withTrashed()->active()->get();

        $this->assertCount(1, $activeSubscriptions);
        $this->assertSame($activeSubscription->id, $activeSubscriptions->sole()->id);
        $this->assertNull($activeSubscriptions->sole()->deleted_at);
    }

    public function test_user_relationship(): void
    {
        $subscription = $this->createSubscription();

        $user = $subscription->user()->firstOrFail();

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame($this->user->id, $user->id);
    }

    public function test_card_relationship(): void
    {
        $subscription = $this->createSubscription();

        $this->assertInstanceOf(FincodeCard::class, $subscription->card);
        $this->assertSame('card_test_sub', $subscription->card->fincode_card_id);
    }

    public function test_card_is_not_eager_loaded_by_default(): void
    {
        $subscription = $this->createSubscription();

        $loadedSubscription = Subscription::query()->findOrFail($subscription->id);

        $this->assertFalse($loadedSubscription->relationLoaded('card'));
    }

    public function test_results_relationship(): void
    {
        $subscription = $this->createSubscription();

        SubscriptionResult::create([
            'subscription_id' => $subscription->id,
            'user_id' => $this->user->id,
            'fincode_subscription_id' => $subscription->fincode_subscription_id,
            'fincode_payment_id' => 'pay_test_001',
            'status' => 'success',
            'amount' => 1000,
            'tax' => 100,
            'charged_at_date' => now()->toDateString(),
            'charged_at' => now(),
        ]);

        SubscriptionResult::create([
            'subscription_id' => $subscription->id,
            'user_id' => $this->user->id,
            'fincode_subscription_id' => $subscription->fincode_subscription_id,
            'fincode_payment_id' => 'pay_test_002',
            'status' => 'failed',
            'amount' => 1000,
            'tax' => 100,
            'charged_at_date' => now()->addMonth()->toDateString(),
            'charged_at' => now()->addMonth(),
        ]);

        $results = $subscription->results()->get();

        $this->assertCount(2, $results);
        $this->assertInstanceOf(SubscriptionResult::class, $results->first());
    }

    public function test_soft_delete(): void
    {
        $subscription = $this->createSubscription();

        $subscription->delete();

        $this->assertSoftDeleted('subscriptions', ['id' => $subscription->id]);
        $this->assertNull(Subscription::find($subscription->id));
        $this->assertNotNull(Subscription::withTrashed()->find($subscription->id));
    }

    public function test_plan_snapshot_is_cast_to_array(): void
    {
        $planSnapshot = [
            'name' => 'Premium Plan',
            'amount' => 2000,
            'interval' => 'monthly',
            'features' => ['feature1', 'feature2'],
        ];

        $subscription = $this->createSubscription([
            'plan_snapshot' => $planSnapshot,
        ]);

        $subscription->refresh();

        $this->assertIsArray($subscription->plan_snapshot);
        $this->assertSame('Premium Plan', $subscription->plan_snapshot['name']);
        $this->assertSame(2000, $subscription->plan_snapshot['amount']);
        $this->assertSame(['feature1', 'feature2'], $subscription->plan_snapshot['features']);
    }
}
