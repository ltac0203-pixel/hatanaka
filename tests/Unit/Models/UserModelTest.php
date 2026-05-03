<?php

namespace Tests\Unit\Models;

use App\Enums\SubscriptionStatus;
use App\Models\FincodeCard;
use App\Models\FincodeCustomer;
use App\Models\Subscription;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserModelTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private FincodeCustomer $fincodeCustomer;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 2, 14, 10, 0, 0));

        $this->user = User::factory()->create();

        $this->fincodeCustomer = FincodeCustomer::create([
            'user_id' => $this->user->id,
            'fincode_customer_id' => 'cus_test_user',
            'name' => $this->user->name,
            'email' => $this->user->email,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_has_active_subscription_returns_true(): void
    {
        FincodeCard::create([
            'user_id' => $this->user->id,
            'fincode_customer_id' => 'cus_test_user',
            'fincode_card_id' => 'card_test_user',
            'brand' => 'Visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'holder_name' => 'TEST USER',
            'is_default' => true,
        ]);

        Subscription::create([
            'user_id' => $this->user->id,
            'fincode_plan_id' => 'pl_test_active',
            'plan_name' => 'Test Plan',
            'plan_amount' => 1000,
            'plan_interval' => 'monthly',
            'plan_interval_count' => 1,
            'plan_snapshot' => ['name' => 'Test Plan'],
            'fincode_subscription_id' => 'sub_test_active',
            'fincode_customer_id' => 'cus_test_user',
            'fincode_card_id' => 'card_test_user',
            'status' => 'active',
            'start_date' => now()->toDateString(),
        ]);

        $this->assertTrue($this->user->hasActiveSubscription());
    }

    public function test_has_active_subscription_returns_false(): void
    {
        $this->assertFalse($this->user->hasActiveSubscription());
    }

    public function test_get_default_card_returns_card(): void
    {
        $card = FincodeCard::create([
            'user_id' => $this->user->id,
            'fincode_customer_id' => 'cus_test_user',
            'fincode_card_id' => 'card_default',
            'brand' => 'Visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'holder_name' => 'TEST USER',
            'is_default' => true,
        ]);

        $defaultCard = $this->user->getDefaultCard();

        $this->assertInstanceOf(FincodeCard::class, $defaultCard);
        $this->assertSame($card->id, $defaultCard->id);
        $this->assertTrue($defaultCard->is_default);
    }

    public function test_get_default_card_returns_null_when_no_default(): void
    {
        FincodeCard::create([
            'user_id' => $this->user->id,
            'fincode_customer_id' => 'cus_test_user',
            'fincode_card_id' => 'card_non_default',
            'brand' => 'Visa',
            'last4' => '1234',
            'exp_month' => 12,
            'exp_year' => 2030,
            'holder_name' => 'TEST USER',
            'is_default' => false,
        ]);

        $defaultCard = $this->user->getDefaultCard();

        $this->assertNull($defaultCard);
    }

    public function test_fincode_customer_relationship(): void
    {
        $customer = $this->user->fincodeCustomer()->first();

        $this->assertInstanceOf(FincodeCustomer::class, $customer);
        $this->assertSame('cus_test_user', $customer->fincode_customer_id);
    }

    public function test_fincode_cards_relationship(): void
    {
        FincodeCard::create([
            'user_id' => $this->user->id,
            'fincode_customer_id' => 'cus_test_user',
            'fincode_card_id' => 'card_rel_001',
            'brand' => 'Visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'holder_name' => 'TEST USER',
            'is_default' => true,
        ]);

        FincodeCard::create([
            'user_id' => $this->user->id,
            'fincode_customer_id' => 'cus_test_user',
            'fincode_card_id' => 'card_rel_002',
            'brand' => 'Mastercard',
            'last4' => '5555',
            'exp_month' => 6,
            'exp_year' => 2028,
            'holder_name' => 'TEST USER',
            'is_default' => false,
        ]);

        $cards = $this->user->fincodeCards()->get();

        $this->assertCount(2, $cards);
        $this->assertInstanceOf(FincodeCard::class, $cards->first());
    }

    public function test_subscriptions_relationship(): void
    {
        FincodeCard::create([
            'user_id' => $this->user->id,
            'fincode_customer_id' => 'cus_test_user',
            'fincode_card_id' => 'card_test_user',
            'brand' => 'Visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'holder_name' => 'TEST USER',
            'is_default' => true,
        ]);

        Subscription::create([
            'user_id' => $this->user->id,
            'fincode_plan_id' => 'pl_rel_001',
            'plan_name' => 'Plan 1',
            'plan_amount' => 1000,
            'plan_interval' => 'monthly',
            'plan_interval_count' => 1,
            'plan_snapshot' => ['name' => 'Plan 1'],
            'fincode_subscription_id' => 'sub_rel_001',
            'fincode_customer_id' => 'cus_test_user',
            'fincode_card_id' => 'card_test_user',
            'status' => 'active',
            'start_date' => now()->toDateString(),
        ]);

        Subscription::create([
            'user_id' => $this->user->id,
            'fincode_plan_id' => 'pl_rel_002',
            'plan_name' => 'Plan 2',
            'plan_amount' => 2000,
            'plan_interval' => 'yearly',
            'plan_interval_count' => 1,
            'plan_snapshot' => ['name' => 'Plan 2'],
            'fincode_subscription_id' => 'sub_rel_002',
            'fincode_customer_id' => 'cus_test_user',
            'fincode_card_id' => 'card_test_user',
            'status' => 'canceled',
            'start_date' => now()->subMonth()->toDateString(),
        ]);

        $subscriptions = $this->user->subscriptions()->get();

        $this->assertCount(2, $subscriptions);
        $this->assertInstanceOf(Subscription::class, $subscriptions->first());
    }

    public function test_active_subscription_relationship(): void
    {
        FincodeCard::create([
            'user_id' => $this->user->id,
            'fincode_customer_id' => 'cus_test_user',
            'fincode_card_id' => 'card_test_user',
            'brand' => 'Visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'holder_name' => 'TEST USER',
            'is_default' => true,
        ]);

        Subscription::create([
            'user_id' => $this->user->id,
            'fincode_plan_id' => 'pl_active_rel',
            'plan_name' => 'Active Plan',
            'plan_amount' => 1000,
            'plan_interval' => 'monthly',
            'plan_interval_count' => 1,
            'plan_snapshot' => ['name' => 'Active Plan'],
            'fincode_subscription_id' => 'sub_active_rel',
            'fincode_customer_id' => 'cus_test_user',
            'fincode_card_id' => 'card_test_user',
            'status' => 'active',
            'start_date' => now()->toDateString(),
        ]);

        Subscription::create([
            'user_id' => $this->user->id,
            'fincode_plan_id' => 'pl_canceled_rel',
            'plan_name' => 'Canceled Plan',
            'plan_amount' => 2000,
            'plan_interval' => 'monthly',
            'plan_interval_count' => 1,
            'plan_snapshot' => ['name' => 'Canceled Plan'],
            'fincode_subscription_id' => 'sub_canceled_rel',
            'fincode_customer_id' => 'cus_test_user',
            'fincode_card_id' => 'card_test_user',
            'status' => 'canceled',
            'start_date' => now()->subMonth()->toDateString(),
        ]);

        $activeSubscription = $this->user->activeSubscription()->with('card')->first();

        $this->assertInstanceOf(Subscription::class, $activeSubscription);
        $this->assertSame(SubscriptionStatus::Active, $activeSubscription->status);
        $this->assertSame('Active Plan', $activeSubscription->plan_name);
        $this->assertTrue($activeSubscription->relationLoaded('card'));
        $this->assertInstanceOf(FincodeCard::class, $activeSubscription->card);
        $this->assertSame('card_test_user', $activeSubscription->card->fincode_card_id);
    }
}
