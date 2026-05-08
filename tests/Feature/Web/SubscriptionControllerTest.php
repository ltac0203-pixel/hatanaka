<?php

namespace Tests\Feature\Web;

use App\Exceptions\ActiveSubscriptionExistsException;
use App\Exceptions\FincodeApiException;
use App\Exceptions\PlanUnavailableException;
use App\Models\FincodeCard;
use App\Models\FincodeCustomer;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Fincode\PlanService;
use App\Services\SubscriptionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SubscriptionControllerTest extends TestCase
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

        return [$user, $plan, $card];
    }

    private function createSubscription(User $user, array $plan, FincodeCard $card): Subscription
    {
        return Subscription::create([
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
            'start_date' => now(),
        ]);
    }

    public function test_index_renders_subscription_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/subscription');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Subscription/Index')
            ->has('subscription')
            ->has('cards')
        );
    }

    public function test_index_with_active_subscription(): void
    {
        [$user, $plan, $card] = $this->createFullSetup();
        $subscription = $this->createSubscription($user, $plan, $card);

        $response = $this->actingAs($user)->get('/subscription');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Subscription/Index')
            ->where('subscription.id', $subscription->id)
            ->where('subscription.card.last4', $card->last4)
        );
    }

    public function test_index_without_subscription(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/subscription');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Subscription/Index')
            ->where('subscription', null)
        );
    }

    public function test_index_exposes_flash_success_with_unique_key(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession(['success' => '完了しました。'])
            ->get('/subscription');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Subscription/Index')
            ->where('flash.success', '完了しました。')
            ->where('flash.error', null)
            ->where('flash.key', fn ($value) => is_string($value) && $value !== '')
        );
    }

    public function test_index_exposes_flash_error_with_unique_key(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession(['error' => '失敗しました。'])
            ->get('/subscription');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Subscription/Index')
            ->where('flash.success', null)
            ->where('flash.error', '失敗しました。')
            ->where('flash.key', fn ($value) => is_string($value) && $value !== '')
        );
    }

    public function test_index_maps_legacy_message_flash_to_error_with_unique_key(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession(['message' => 'ページの有効期限が切れました。再度お試しください。'])
            ->get('/subscription');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Subscription/Index')
            ->where('flash.success', null)
            ->where('flash.error', 'ページの有効期限が切れました。再度お試しください。')
            ->where('flash.key', fn ($value) => is_string($value) && $value !== '')
        );
    }

    public function test_index_exposes_null_flash_key_without_flash_message(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/subscription');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Subscription/Index')
            ->where('flash.key', null)
            ->where('flash.success', null)
            ->where('flash.error', null)
        );
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->get('/subscription');

        $response->assertRedirect('/login');
    }

    public function test_store_creates_subscription_successfully(): void
    {
        [$user, $plan, $card] = $this->createFullSetup();

        $mockSubscription = Subscription::make([
            'user_id' => $user->id,
            'fincode_plan_id' => $plan['fincode_plan_id'],
            'plan_name' => $plan['name'],
            'plan_amount' => $plan['amount'],
            'plan_interval' => $plan['interval'],
            'plan_interval_count' => $plan['interval_count'],
            'fincode_subscription_id' => 'sub_test_123',
            'fincode_customer_id' => 'cus_test_123',
            'fincode_card_id' => 'card_test_123',
            'status' => 'active',
            'start_date' => now()->format('Y-m-d'),
        ]);

        $subscriptionManager = Mockery::mock(SubscriptionManager::class);
        $subscriptionManager->shouldReceive('createForPlan')
            ->once()
            ->with(
                Mockery::on(fn ($u) => $u->id === $user->id),
                $plan['fincode_plan_id'],
                $card->id,
                now()->format('Y-m-d')
            )
            ->andReturn($mockSubscription);
        $this->app->instance(SubscriptionManager::class, $subscriptionManager);

        $response = $this->actingAs($user)->post('/subscription', [
            'fincode_plan_id' => $plan['fincode_plan_id'],
            'card_id' => $card->id,
            'start_date' => now()->format('Y-m-d'),
        ]);

        $response->assertRedirect(route('subscription.index'));
        $response->assertSessionHas('success', 'サブスクリプションを登録しました。');
    }

    public function test_store_prevents_duplicate_subscription(): void
    {
        [$user, $plan, $card] = $this->createFullSetup();
        $this->createSubscription($user, $plan, $card);

        $planService = Mockery::mock(PlanService::class);
        $planService->shouldReceive('findActivePlanOrFail')
            ->once()
            ->with($plan['fincode_plan_id'])
            ->andReturn($plan);
        $this->app->instance(PlanService::class, $planService);

        $response = $this->actingAs($user)->post('/subscription', [
            'fincode_plan_id' => $plan['fincode_plan_id'],
            'card_id' => $card->id,
            'start_date' => now()->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('fincode_plan_id');
    }

    public function test_store_prevents_other_users_card(): void
    {
        [$user, $plan, $card] = $this->createFullSetup();

        $otherUser = User::factory()->create();
        FincodeCustomer::create([
            'user_id' => $otherUser->id,
            'fincode_customer_id' => 'cus_other',
            'name' => $otherUser->name,
            'email' => $otherUser->email,
        ]);
        $otherCard = FincodeCard::create([
            'user_id' => $otherUser->id,
            'fincode_customer_id' => 'cus_other',
            'fincode_card_id' => 'card_other',
            'brand' => 'Visa',
            'last4' => '1234',
            'exp_month' => 12,
            'exp_year' => 2030,
            'holder_name' => 'OTHER USER',
            'is_default' => true,
        ]);

        $response = $this->actingAs($user)->post('/subscription', [
            'fincode_plan_id' => $plan['fincode_plan_id'],
            'card_id' => $otherCard->id,
            'start_date' => now()->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('card_id');
    }

    public function test_store_prevents_expired_card(): void
    {
        $user = User::factory()->create();

        FincodeCustomer::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_test_123',
            'name' => $user->name,
            'email' => $user->email,
        ]);

        $expiredCard = FincodeCard::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_test_123',
            'fincode_card_id' => 'card_expired',
            'brand' => 'Visa',
            'last4' => '9999',
            'exp_month' => 1,
            'exp_year' => 2020,
            'holder_name' => 'TEST USER',
            'is_default' => true,
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

        $planService = Mockery::mock(PlanService::class);
        $planService->shouldReceive('findActivePlanOrFail')
            ->once()
            ->with($plan['fincode_plan_id'])
            ->andReturn($plan);
        $this->app->instance(PlanService::class, $planService);

        $response = $this->actingAs($user)->post('/subscription', [
            'fincode_plan_id' => $plan['fincode_plan_id'],
            'card_id' => $expiredCard->id,
            'start_date' => now()->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('card_id');
    }

    public function test_store_prevents_inactive_plan(): void
    {
        [$user, $plan, $card] = $this->createFullSetup();

        $planService = Mockery::mock(PlanService::class);
        $planService->shouldReceive('findActivePlanOrFail')
            ->once()
            ->with($plan['fincode_plan_id'])
            ->andThrow(new PlanUnavailableException($plan['fincode_plan_id']));
        $this->app->instance(PlanService::class, $planService);

        $response = $this->actingAs($user)->post('/subscription', [
            'fincode_plan_id' => $plan['fincode_plan_id'],
            'card_id' => $card->id,
            'start_date' => now()->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('fincode_plan_id');
    }

    public function test_store_validates_required_fields(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/subscription', []);

        $response->assertSessionHasErrors(['fincode_plan_id', 'card_id', 'start_date']);
    }

    public function test_store_handles_race_condition_duplicate_subscription(): void
    {
        [$user, $plan, $card] = $this->createFullSetup();

        $subscriptionManager = Mockery::mock(SubscriptionManager::class);
        $subscriptionManager->shouldReceive('createForPlan')
            ->once()
            ->andThrow(new ActiveSubscriptionExistsException);
        $this->app->instance(SubscriptionManager::class, $subscriptionManager);

        $response = $this->actingAs($user)->post('/subscription', [
            'fincode_plan_id' => $plan['fincode_plan_id'],
            'card_id' => $card->id,
            'start_date' => now()->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('fincode_plan_id');
    }

    public function test_store_handles_fincode_api_error(): void
    {
        [$user, $plan, $card] = $this->createFullSetup();

        $planService = Mockery::mock(PlanService::class);
        $planService->shouldReceive('findActivePlanOrFail')
            ->once()
            ->with($plan['fincode_plan_id'])
            ->andThrow(new FincodeApiException('Fincode API error', 503));
        $this->app->instance(PlanService::class, $planService);

        $response = $this->actingAs($user)->post('/subscription', [
            'fincode_plan_id' => $plan['fincode_plan_id'],
            'card_id' => $card->id,
            'start_date' => now()->format('Y-m-d'),
        ]);

        $response->assertRedirect(route('subscription.index'));
        $response->assertSessionHasErrors('subscription');
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->post('/subscription', [
            'fincode_plan_id' => 'pl_test_plan',
            'card_id' => 1,
            'start_date' => now()->format('Y-m-d'),
        ]);

        $response->assertRedirect('/login');
    }

    public function test_destroy_cancels_subscription(): void
    {
        [$user, $plan, $card] = $this->createFullSetup();
        $subscription = $this->createSubscription($user, $plan, $card);

        $subscriptionManager = Mockery::mock(SubscriptionManager::class);
        $subscriptionManager->shouldReceive('cancel')
            ->once()
            ->with(Mockery::on(fn ($s) => $s->id === $subscription->id))
            ->andReturnNull();
        $this->app->instance(SubscriptionManager::class, $subscriptionManager);

        $response = $this->actingAs($user)->delete("/subscription/{$subscription->id}");

        $response->assertRedirect(route('subscription.index'));
        $response->assertSessionHas('success', 'サブスクリプションを解約しました。');
    }

    public function test_destroy_handles_fincode_api_error(): void
    {
        [$user, $plan, $card] = $this->createFullSetup();
        $subscription = $this->createSubscription($user, $plan, $card);

        $subscriptionManager = Mockery::mock(SubscriptionManager::class);
        $subscriptionManager->shouldReceive('cancel')
            ->once()
            ->with(Mockery::on(fn ($s) => $s->id === $subscription->id))
            ->andThrow(new FincodeApiException('Fincode API error', 503));
        $this->app->instance(SubscriptionManager::class, $subscriptionManager);

        $response = $this->actingAs($user)->delete("/subscription/{$subscription->id}");

        $response->assertRedirect(route('subscription.index'));
        $response->assertSessionHasErrors([
            'subscription' => 'サブスクリプションの解約に失敗しました。時間をおいて再試行してください。',
        ]);
    }

    public function test_destroy_rejects_other_users_subscription(): void
    {
        [$user, $plan, $card] = $this->createFullSetup();
        $subscription = $this->createSubscription($user, $plan, $card);

        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)->delete("/subscription/{$subscription->id}");

        $response->assertStatus(403);
    }

    public function test_destroy_requires_authentication(): void
    {
        [$user, $plan, $card] = $this->createFullSetup();
        $subscription = $this->createSubscription($user, $plan, $card);

        $response = $this->delete("/subscription/{$subscription->id}");

        $response->assertRedirect('/login');
    }
}
