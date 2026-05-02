<?php

namespace Tests\Feature\Api;

use App\Exceptions\ActiveSubscriptionExistsException;
use App\Models\FincodeCard;
use App\Models\FincodeCustomer;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Fincode\PlanService;
use App\Services\SubscriptionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private function createFullSetup(): array
    {
        $user = User::factory()->create();

        FincodeCustomer::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_test_123',
            'name' => $user->name,
            'email' => $user->email,
        ]);

        $plan = [
            'id' => 'pl_test_plan',
            'fincode_plan_id' => 'pl_test_plan',
            'name' => 'Test Plan',
            'amount' => 1000,
            'interval' => 'monthly',
            'interval_count' => 1,
            'status' => 'active',
            'features' => null,
            'price_display' => '¥1,000/月',
            'interval_label' => '月',
        ];

        $card = FincodeCard::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_test_123',
            'fincode_card_id' => 'card_test_123',
            'brand' => 'Visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'holder_name' => 'TEST USER',
            'is_default' => true,
        ]);

        return [$user, $plan, $card];
    }

    public function test_show_returns_null_when_no_active_subscription(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/subscription');

        $response->assertOk()
            ->assertJsonPath('data', null);
    }

    public function test_show_returns_active_subscription(): void
    {
        [$user, $plan, $card] = $this->createFullSetup();

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'fincode_plan_id' => $plan['fincode_plan_id'],
            'plan_name' => $plan['name'],
            'plan_amount' => $plan['amount'],
            'plan_interval' => $plan['interval'],
            'plan_interval_count' => $plan['interval_count'],
            'plan_snapshot' => $plan,
            'fincode_subscription_id' => 'sub_test_123',
            'fincode_customer_id' => 'cus_test_123',
            'fincode_card_id' => 'card_test_123',
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'next_charge_date' => now()->addMonth()->toDateString(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/subscription');

        $response->assertOk()
            ->assertJsonPath('data.id', $subscription->id)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.plan.name', 'Test Plan')
            ->assertJsonPath('data.card.id', $card->id);
    }

    public function test_store_creates_subscription(): void
    {
        [$user, $plan, $card] = $this->createFullSetup();
        $startDate = now()->addDay()->toDateString();

        $planService = Mockery::mock(PlanService::class);
        $planService->shouldReceive('findActivePlanOrFail')
            ->once()
            ->with($plan['fincode_plan_id'])
            ->andReturn($plan);
        $this->app->instance(PlanService::class, $planService);

        $mockSubscription = new Subscription([
            'user_id' => $user->id,
            'fincode_plan_id' => $plan['fincode_plan_id'],
            'plan_name' => $plan['name'],
            'plan_amount' => $plan['amount'],
            'plan_interval' => $plan['interval'],
            'plan_interval_count' => $plan['interval_count'],
            'plan_snapshot' => $plan,
            'fincode_subscription_id' => 'sub_new',
            'fincode_customer_id' => 'cus_test_123',
            'fincode_card_id' => 'card_test_123',
            'status' => 'active',
            'start_date' => $startDate,
        ]);
        $mockSubscription->id = 1;
        $mockSubscription->setRelation('card', $card);

        $manager = Mockery::mock(SubscriptionManager::class);
        $manager->shouldReceive('create')
            ->once()
            ->andReturn($mockSubscription);
        $this->app->instance(SubscriptionManager::class, $manager);

        $response = $this->actingAs($user)->postJson('/api/subscription', [
            'fincode_plan_id' => $plan['fincode_plan_id'],
            'card_id' => $card->id,
            'start_date' => $startDate,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.card.id', $card->id);
    }

    public function test_store_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/subscription', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fincode_plan_id', 'card_id', 'start_date']);
    }

    public function test_store_handles_race_condition_duplicate_subscription(): void
    {
        [$user, $plan, $card] = $this->createFullSetup();
        $startDate = now()->addDay()->toDateString();

        $planService = Mockery::mock(PlanService::class);
        $planService->shouldReceive('findActivePlanOrFail')
            ->once()
            ->with($plan['fincode_plan_id'])
            ->andReturn($plan);
        $this->app->instance(PlanService::class, $planService);

        $manager = Mockery::mock(SubscriptionManager::class);
        $manager->shouldReceive('create')
            ->once()
            ->andThrow(new ActiveSubscriptionExistsException);
        $this->app->instance(SubscriptionManager::class, $manager);

        $response = $this->actingAs($user)->postJson('/api/subscription', [
            'fincode_plan_id' => $plan['fincode_plan_id'],
            'card_id' => $card->id,
            'start_date' => $startDate,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', '既にアクティブなサブスクリプションがあります。')
            ->assertJsonPath('errors.fincode_plan_id.0', '既にアクティブなサブスクリプションがあります。');
    }

    public function test_store_rejects_other_users_card(): void
    {
        [$user1, $plan, $card] = $this->createFullSetup();
        $user2 = User::factory()->create();

        $response = $this->actingAs($user2)->postJson('/api/subscription', [
            'fincode_plan_id' => $plan['fincode_plan_id'],
            'card_id' => $card->id,
            'start_date' => now()->addDay()->toDateString(),
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('errors.card_id.0', '選択されたcard idは無効です。');
    }

    public function test_destroy_cancels_subscription(): void
    {
        [$user, $plan, $card] = $this->createFullSetup();

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'fincode_plan_id' => $plan['fincode_plan_id'],
            'plan_name' => $plan['name'],
            'plan_amount' => $plan['amount'],
            'plan_interval' => $plan['interval'],
            'plan_interval_count' => $plan['interval_count'],
            'plan_snapshot' => $plan,
            'fincode_subscription_id' => 'sub_test_123',
            'fincode_customer_id' => 'cus_test_123',
            'fincode_card_id' => 'card_test_123',
            'status' => 'active',
            'start_date' => now()->toDateString(),
        ]);

        $manager = Mockery::mock(SubscriptionManager::class);
        $manager->shouldReceive('cancel')->once();
        $this->app->instance(SubscriptionManager::class, $manager);

        $response = $this->actingAs($user)->deleteJson('/api/subscription');

        $response->assertOk()
            ->assertJsonPath('message', 'サブスクリプションを解約しました。');
    }

    public function test_destroy_returns_404_when_no_active_subscription(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->deleteJson('/api/subscription');

        $response->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_access_subscription(): void
    {
        $response = $this->getJson('/api/subscription');

        $response->assertStatus(401);
    }
}
