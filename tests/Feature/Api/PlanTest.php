<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\Fincode\PlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_active_plans(): void
    {
        $user = User::factory()->create();

        $planService = Mockery::mock(PlanService::class);
        $planService->shouldReceive('listActivePlans')
            ->once()
            ->andReturn([
                [
                    'id' => 'pl_active',
                    'fincode_plan_id' => 'pl_active',
                    'name' => 'Active Plan',
                    'amount' => 1000,
                    'interval' => 'monthly',
                    'interval_count' => 1,
                    'status' => 'active',
                    'features' => null,
                    'price_display' => '¥1,000/月',
                    'interval_label' => '月',
                ],
            ]);
        $this->app->instance(PlanService::class, $planService);

        $response = $this->actingAs($user)->getJson('/api/subscription/plans');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Active Plan')
            ->assertJsonPath('data.0.price_display', '¥1,000/月');
    }

    public function test_plan_list_returns_503_when_fincode_is_unavailable(): void
    {
        $user = User::factory()->create();

        $planService = Mockery::mock(PlanService::class);
        $planService->shouldReceive('listActivePlans')
            ->once()
            ->andThrow(new \App\Exceptions\FincodeApiException('fincode error', 500));
        $this->app->instance(PlanService::class, $planService);

        $response = $this->actingAs($user)->getJson('/api/subscription/plans');

        $response->assertStatus(503)
            ->assertJsonPath('message', '決済サービスとの通信でエラーが発生しました。');
    }

    public function test_unauthenticated_user_cannot_list_plans(): void
    {
        $response = $this->getJson('/api/subscription/plans');

        $response->assertStatus(401);
    }
}
