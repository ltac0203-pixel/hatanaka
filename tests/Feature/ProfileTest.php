<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Events\SubscriptionCanceled;
use App\Events\SubscriptionStatusChanged;
use App\Exceptions\FincodeApiException;
use App\Models\FincodeCard;
use App\Models\FincodeCustomer;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Fincode\SubscriptionService as FincodeSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }

    public function test_user_with_active_subscription_can_delete_account(): void
    {
        Event::fake();

        $user = User::factory()->create();
        $customer = FincodeCustomer::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'c_test_123',
            'name' => $user->name,
            'email' => $user->email,
        ]);
        $card = FincodeCard::create([
            'user_id' => $user->id,
            'fincode_customer_id' => $customer->fincode_customer_id,
            'fincode_card_id' => 'cs_test_123',
            'brand' => 'Visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
        ]);
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'fincode_plan_id' => 'pl_test_123',
            'plan_name' => 'Test Plan',
            'plan_amount' => 1000,
            'plan_interval' => 'monthly',
            'plan_interval_count' => 1,
            'fincode_subscription_id' => 'sb_test_123',
            'fincode_customer_id' => $customer->fincode_customer_id,
            'fincode_card_id' => $card->fincode_card_id,
            'status' => 'active',
            'start_date' => now()->toDateString(),
        ]);
        $mockSubscriptionService = Mockery::mock(FincodeSubscriptionService::class);
        $mockSubscriptionService->shouldReceive('cancel')
            ->once()
            ->with('sb_test_123', 'subscription.cancel:'.hash('sha256', 'sb_test_123'))
            ->andReturn([]);
        $this->app->instance(FincodeSubscriptionService::class, $mockSubscriptionService);

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
        $this->assertDatabaseMissing('subscriptions', [
            'id' => $subscription->id,
        ]);
        Event::assertDispatched(SubscriptionCanceled::class);
        Event::assertDispatched(SubscriptionStatusChanged::class);
    }

    public function test_user_is_not_deleted_when_subscription_cancellation_fails(): void
    {
        Event::fake();

        $user = User::factory()->create();
        $customer = FincodeCustomer::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'c_test_123',
            'name' => $user->name,
            'email' => $user->email,
        ]);
        $card = FincodeCard::create([
            'user_id' => $user->id,
            'fincode_customer_id' => $customer->fincode_customer_id,
            'fincode_card_id' => 'cs_test_123',
            'brand' => 'Visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
        ]);
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'fincode_plan_id' => 'pl_test_123',
            'plan_name' => 'Test Plan',
            'plan_amount' => 1000,
            'plan_interval' => 'monthly',
            'plan_interval_count' => 1,
            'fincode_subscription_id' => 'sb_test_123',
            'fincode_customer_id' => $customer->fincode_customer_id,
            'fincode_card_id' => $card->fincode_card_id,
            'status' => 'active',
            'start_date' => now()->toDateString(),
        ]);
        $mockSubscriptionService = Mockery::mock(FincodeSubscriptionService::class);
        $mockSubscriptionService->shouldReceive('cancel')
            ->once()
            ->with('sb_test_123', 'subscription.cancel:'.hash('sha256', 'sb_test_123'))
            ->andThrow(new FincodeApiException('Cancellation failed', 500));
        $this->app->instance(FincodeSubscriptionService::class, $mockSubscriptionService);

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHas('error', '退会処理に失敗しました。時間をおいて再試行してください。')
            ->assertRedirect('/profile');

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh());
        $this->assertSame(SubscriptionStatus::Active, $subscription->fresh()->status);
        Event::assertNotDispatched(SubscriptionCanceled::class);
        Event::assertNotDispatched(SubscriptionStatusChanged::class);
    }
}
