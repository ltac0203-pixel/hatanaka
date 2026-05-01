<?php

namespace Tests\Feature;

use App\Models\FincodeCard;
use App\Models\FincodeCustomer;
use App\Models\Subscription;
use App\Models\User;
use App\Policies\CardPolicy;
use App\Policies\SubscriptionPolicy;
use App\Services\CardManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_delete_own_subscription(): void
    {
        $user = User::factory()->create();

        $subscription = new Subscription([
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $policy = new SubscriptionPolicy;

        $this->assertTrue($policy->delete($user, $subscription));
    }

    public function test_user_cannot_delete_other_users_subscription(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $subscription = new Subscription([
            'user_id' => $user1->id,
            'status' => 'active',
        ]);

        $policy = new SubscriptionPolicy;

        $this->assertFalse($policy->delete($user2, $subscription));
    }

    public function test_subscription_delete_enforced_in_api(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        FincodeCustomer::create([
            'user_id' => $user1->id,
            'fincode_customer_id' => 'cus_test_1',
            'name' => $user1->name,
            'email' => $user1->email,
        ]);

        FincodeCard::create([
            'user_id' => $user1->id,
            'fincode_customer_id' => 'cus_test_1',
            'fincode_card_id' => 'card_test',
            'brand' => 'Visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'holder_name' => 'TEST USER',
            'is_default' => true,
        ]);

        Subscription::create([
            'user_id' => $user1->id,
            'fincode_plan_id' => 'pl_test',
            'plan_name' => 'Test',
            'plan_amount' => 1000,
            'plan_interval' => 'monthly',
            'plan_interval_count' => 1,
            'plan_snapshot' => [],
            'fincode_subscription_id' => 'sub_test_123',
            'fincode_customer_id' => 'cus_test_1',
            'fincode_card_id' => 'card_test',
            'status' => 'active',
            'start_date' => now(),
        ]);

        // 他人の契約IDを推測しても操作対象に到達できないことを確認する。
        $response = $this->actingAs($user2)->deleteJson('/api/subscription');

        $response->assertStatus(404);
    }

    public function test_card_policy_allows_own_card_delete(): void
    {
        $user = User::factory()->create();

        $card = new FincodeCard([
            'user_id' => $user->id,
            'fincode_card_id' => 'card_test',
        ]);

        $policy = new CardPolicy;

        $this->assertTrue($policy->delete($user, $card));
    }

    public function test_card_policy_denies_other_users_card(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $card = new FincodeCard([
            'user_id' => $user1->id,
            'fincode_card_id' => 'card_test',
        ]);

        $policy = new CardPolicy;

        $this->assertFalse($policy->delete($user2, $card));
    }

    public function test_card_delete_enforced_in_api(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        FincodeCustomer::create([
            'user_id' => $user1->id,
            'fincode_customer_id' => 'cus_test_1',
            'name' => $user1->name,
            'email' => $user1->email,
        ]);

        $card = FincodeCard::create([
            'user_id' => $user1->id,
            'fincode_customer_id' => 'cus_test_1',
            'fincode_card_id' => 'card_test_123',
            'brand' => 'Visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'holder_name' => 'TEST USER',
            'is_default' => true,
        ]);

        // 権限拒否だけを検証したいので外部 API 呼び出しは事前に遮断する。
        $mockCardManager = Mockery::mock(CardManager::class);
        $mockCardManager->shouldReceive('delete')->never();
        $this->app->instance(CardManager::class, $mockCardManager);

        // 他人のカード削除要求が権限チェックで止まることを確認する。
        $response = $this->actingAs($user2)->deleteJson("/api/subscription/cards/{$card->id}");

        $response->assertStatus(403);
    }
}
