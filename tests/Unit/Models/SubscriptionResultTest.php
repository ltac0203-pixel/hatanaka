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

class SubscriptionResultTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 2, 14, 10, 0, 0));

        $this->user = User::factory()->create();

        FincodeCustomer::create([
            'user_id' => $this->user->id,
            'fincode_customer_id' => 'cus_test_result',
            'name' => $this->user->name,
            'email' => $this->user->email,
        ]);

        FincodeCard::create([
            'user_id' => $this->user->id,
            'fincode_customer_id' => 'cus_test_result',
            'fincode_card_id' => 'card_test_result',
            'brand' => 'Visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'holder_name' => 'TEST USER',
            'is_default' => true,
        ]);

        $this->subscription = Subscription::create([
            'user_id' => $this->user->id,
            'fincode_plan_id' => 'pl_test_result',
            'plan_name' => 'Test Plan',
            'plan_amount' => 1000,
            'plan_interval' => 'monthly',
            'plan_interval_count' => 1,
            'plan_snapshot' => ['name' => 'Test Plan', 'amount' => 1000],
            'fincode_subscription_id' => 'sub_test_result',
            'fincode_customer_id' => 'cus_test_result',
            'fincode_card_id' => 'card_test_result',
            'status' => 'active',
            'start_date' => now()->toDateString(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createResult(array $overrides = []): SubscriptionResult
    {
        return SubscriptionResult::create(array_merge([
            'subscription_id' => $this->subscription->id,
            'user_id' => $this->user->id,
            'fincode_subscription_id' => $this->subscription->fincode_subscription_id,
            'fincode_payment_id' => 'pay_test_001',
            'status' => 'success',
            'amount' => 1000,
            'tax' => 100,
            'charged_at_date' => now()->toDateString(),
            'charged_at' => now(),
        ], $overrides));
    }

    public function test_is_successful_returns_true_for_success_status(): void
    {
        $result = $this->createResult(['status' => 'success']);

        $this->assertTrue($result->isSuccessful());
    }

    public function test_is_successful_returns_false_for_failed_status(): void
    {
        $result = $this->createResult([
            'status' => 'failed',
            'error_code' => 'E001',
            'error_message' => 'Payment declined',
            'fincode_payment_id' => 'pay_test_fail',
        ]);

        $this->assertFalse($result->isSuccessful());
    }

    public function test_is_successful_returns_false_for_pending_status(): void
    {
        $result = $this->createResult([
            'status' => 'pending',
            'fincode_payment_id' => 'pay_test_pending',
        ]);

        $this->assertFalse($result->isSuccessful());
    }

    public function test_subscription_relationship(): void
    {
        $result = $this->createResult();

        $subscription = $result->subscription()->firstOrFail();

        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertSame($this->subscription->id, $subscription->id);
    }

    public function test_user_relationship(): void
    {
        $result = $this->createResult();

        $user = $result->user()->firstOrFail();

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame($this->user->id, $user->id);
    }
}
