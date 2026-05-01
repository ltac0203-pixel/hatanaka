<?php

namespace Tests\Feature\Api;

use App\Models\FincodeCard;
use App\Models\FincodeCustomer;
use App\Models\Subscription;
use App\Models\SubscriptionResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function createSubscriptionForUser(User $user): Subscription
    {
        $customerId = 'cus_'.uniqid();

        FincodeCustomer::firstOrCreate(
            ['user_id' => $user->id],
            [
                'fincode_customer_id' => $customerId,
                'name' => $user->name,
                'email' => $user->email,
            ]
        );

        $customer = FincodeCustomer::where('user_id', $user->id)->first();

        FincodeCard::firstOrCreate(
            ['fincode_card_id' => 'card_test'],
            [
                'user_id' => $user->id,
                'fincode_customer_id' => $customer->fincode_customer_id,
                'brand' => 'Visa',
                'last4' => '4242',
                'exp_month' => 12,
                'exp_year' => 2030,
                'holder_name' => 'TEST USER',
                'is_default' => true,
            ]
        );

        return Subscription::create([
            'user_id' => $user->id,
            'fincode_plan_id' => 'pl_test',
            'plan_name' => 'Test',
            'plan_amount' => 1000,
            'plan_interval' => 'monthly',
            'plan_interval_count' => 1,
            'plan_snapshot' => [],
            'fincode_subscription_id' => 'sub_'.uniqid(),
            'fincode_customer_id' => $customer->fincode_customer_id,
            'fincode_card_id' => 'card_test',
            'status' => 'active',
            'start_date' => now(),
        ]);
    }

    private function createResult(int $userId, int $subscriptionId, string $fincodeSubscriptionId, array $overrides = []): SubscriptionResult
    {
        return SubscriptionResult::create(array_merge([
            'subscription_id' => $subscriptionId,
            'user_id' => $userId,
            'fincode_subscription_id' => $fincodeSubscriptionId,
            'fincode_payment_id' => 'pay_'.uniqid(),
            'status' => 'success',
            'amount' => 1000,
            'tax' => 100,
            'charged_at_date' => now()->toDateString(),
            'charged_at' => now(),
        ], $overrides));
    }

    public function test_history_returns_paginated_results(): void
    {
        $user = User::factory()->create();
        $subscription = $this->createSubscriptionForUser($user);

        for ($i = 0; $i < 25; $i++) {
            $this->createResult($user->id, $subscription->id, $subscription->fincode_subscription_id, [
                'charged_at' => now()->subDays($i),
                'charged_at_date' => now()->subDays($i)->toDateString(),
            ]);
        }

        $response = $this->actingAs($user)->getJson('/api/subscription/history');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(20, $data);

        // 無限取得を防ぐ UI が組めるよう件数メタデータも保証する。
        $response->assertJsonPath('meta.total', 25);
        $response->assertJsonPath('meta.per_page', 20);

        // 一覧画面が必要とする最低限の項目が欠けていないことを保証する。
        $firstResult = $data[0];
        $this->assertArrayHasKey('id', $firstResult);
        $this->assertArrayHasKey('subscription_id', $firstResult);
        $this->assertArrayHasKey('status', $firstResult);
        $this->assertArrayHasKey('amount', $firstResult);
        $this->assertArrayHasKey('tax', $firstResult);
        $this->assertArrayHasKey('charged_at', $firstResult);
    }

    public function test_history_returns_only_own_results(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $sub1 = $this->createSubscriptionForUser($user1);
        $sub2 = $this->createSubscriptionForUser($user2);

        $this->createResult($user1->id, $sub1->id, $sub1->fincode_subscription_id);
        $this->createResult($user1->id, $sub1->id, $sub1->fincode_subscription_id);
        $this->createResult($user2->id, $sub2->id, $sub2->fincode_subscription_id);

        $response = $this->actingAs($user1)->getJson('/api/subscription/history');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(2, $data);
    }

    public function test_history_returns_empty_when_no_results(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->getJson('/api/subscription/history');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(0, $data);
    }

    public function test_history_requires_authentication(): void
    {
        $response = $this->getJson('/api/subscription/history');

        $response->assertStatus(401);
    }

    public function test_history_ordered_by_charged_at_desc(): void
    {
        $user = User::factory()->create();
        $subscription = $this->createSubscriptionForUser($user);

        $oldest = $this->createResult($user->id, $subscription->id, $subscription->fincode_subscription_id, [
            'charged_at' => now()->subDays(10),
            'charged_at_date' => now()->subDays(10)->toDateString(),
        ]);

        $newest = $this->createResult($user->id, $subscription->id, $subscription->fincode_subscription_id, [
            'charged_at' => now(),
            'charged_at_date' => now()->toDateString(),
        ]);

        $middle = $this->createResult($user->id, $subscription->id, $subscription->fincode_subscription_id, [
            'charged_at' => now()->subDays(5),
            'charged_at_date' => now()->subDays(5)->toDateString(),
        ]);

        $response = $this->actingAs($user)->getJson('/api/subscription/history');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(3, $data);

        // 利用者が直近の請求結果をすぐ確認できるよう降順を保証する。
        $this->assertEquals($newest->id, $data[0]['id']);
        $this->assertEquals($middle->id, $data[1]['id']);
        $this->assertEquals($oldest->id, $data[2]['id']);
    }
}
