<?php

namespace Tests\Feature\Web;

use App\Exceptions\FincodeApiException;
use App\Models\FincodeCard;
use App\Models\FincodeCustomer;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Fincode\PlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PlanControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_renders_plan_list(): void
    {
        $user = User::factory()->create();

        $plans = [
            [
                'id' => 'pl_plan_1',
                'fincode_plan_id' => 'pl_plan_1',
                'name' => 'Basic Plan',
                'amount' => 500,
                'interval' => 'monthly',
                'interval_count' => 1,
                'status' => 'active',
                'features' => null,
                'price_display' => '¥500/月',
                'interval_label' => '月',
            ],
            [
                'id' => 'pl_plan_2',
                'fincode_plan_id' => 'pl_plan_2',
                'name' => 'Pro Plan',
                'amount' => 2000,
                'interval' => 'monthly',
                'interval_count' => 1,
                'status' => 'active',
                'features' => null,
                'price_display' => '¥2,000/月',
                'interval_label' => '月',
            ],
        ];

        $planService = Mockery::mock(PlanService::class);
        $planService->shouldReceive('listActivePlans')
            ->once()
            ->andReturn($plans);
        $this->app->instance(PlanService::class, $planService);

        $response = $this->actingAs($user)->get('/plans');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Plan/Index')
            ->has('plans', 2)
        );
    }

    public function test_index_handles_fincode_error(): void
    {
        $user = User::factory()->create();

        $planService = Mockery::mock(PlanService::class);
        $planService->shouldReceive('listActivePlans')
            ->once()
            ->andThrow(new FincodeApiException('Fincode API error', 503));
        $this->app->instance(PlanService::class, $planService);

        $response = $this->actingAs($user)->get('/plans');

        $response->assertStatus(503);
    }

    public function test_index_requires_authentication(): void
    {
        $response = $this->get('/plans');

        $response->assertRedirect('/login');
    }

    public function test_show_renders_plan_detail(): void
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

        $planService = Mockery::mock(PlanService::class);
        $planService->shouldReceive('findActivePlan')
            ->once()
            ->with('pl_test_plan')
            ->andReturn($plan);
        $this->app->instance(PlanService::class, $planService);

        $response = $this->actingAs($user)->get('/plans/pl_test_plan');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Plan/Show')
            ->has('plan')
            ->has('cards')
            ->has('hasActiveSubscription')
            ->where('minimumStartDate', today()->toDateString())
            ->where('hasActiveSubscription', false)
        );
    }

    public function test_show_renders_plan_detail_with_active_subscription(): void
    {
        $user = User::factory()->create();

        FincodeCustomer::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_test_123',
            'name' => $user->name,
            'email' => $user->email,
        ]);

        FincodeCard::create([
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

        Subscription::create([
            'user_id' => $user->id,
            'fincode_plan_id' => 'pl_test_plan',
            'plan_name' => 'Test Plan',
            'plan_amount' => 1000,
            'plan_interval' => 'monthly',
            'plan_interval_count' => 1,
            'plan_snapshot' => [],
            'fincode_subscription_id' => 'sub_test_123',
            'fincode_customer_id' => 'cus_test_123',
            'fincode_card_id' => 'card_test_123',
            'status' => 'active',
            'start_date' => now(),
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
        $planService->shouldReceive('findActivePlan')
            ->once()
            ->with('pl_test_plan')
            ->andReturn($plan);
        $this->app->instance(PlanService::class, $planService);

        $response = $this->actingAs($user)->get('/plans/pl_test_plan');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Plan/Show')
            ->where('hasActiveSubscription', true)
        );
    }

    public function test_show_redirects_when_plan_is_not_active(): void
    {
        $user = User::factory()->create();

        $planService = Mockery::mock(PlanService::class);
        $planService->shouldReceive('findActivePlan')
            ->once()
            ->with('pl_inactive')
            ->andReturn(null);
        $this->app->instance(PlanService::class, $planService);

        $response = $this->actingAs($user)->get('/plans/pl_inactive');

        $response->assertRedirect(route('plans.index'));
    }

    public function test_show_requires_authentication(): void
    {
        $response = $this->get('/plans/pl_test_plan');

        $response->assertRedirect('/login');
    }
}
