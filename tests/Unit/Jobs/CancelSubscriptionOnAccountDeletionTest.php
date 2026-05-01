<?php

namespace Tests\Unit\Jobs;

use App\Jobs\CancelSubscriptionOnAccountDeletion;
use App\Models\FincodeCard;
use App\Models\FincodeCustomer;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class CancelSubscriptionOnAccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function createSubscription(User $user, string $status = 'active'): Subscription
    {
        FincodeCustomer::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_job_test',
            'name' => $user->name,
            'email' => $user->email,
        ]);

        FincodeCard::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_job_test',
            'fincode_card_id' => 'card_job_test',
            'brand' => 'Visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'is_default' => true,
        ]);

        return Subscription::create([
            'user_id' => $user->id,
            'fincode_plan_id' => 'pl_job_test',
            'plan_name' => 'Job Test Plan',
            'plan_amount' => 1000,
            'plan_interval' => 'monthly',
            'plan_interval_count' => 1,
            'fincode_subscription_id' => 'sub_job_test',
            'fincode_customer_id' => 'cus_job_test',
            'fincode_card_id' => 'card_job_test',
            'status' => $status,
            'start_date' => now()->toDateString(),
        ]);
    }

    public function test_handle_skips_already_canceled_subscription(): void
    {
        $user = User::factory()->create();
        $subscription = $this->createSubscription($user, 'canceled');

        $mockManager = Mockery::mock(SubscriptionManager::class);
        $mockManager->shouldNotReceive('cancel');

        $job = new CancelSubscriptionOnAccountDeletion($subscription, $user->id);
        $job->handle($mockManager);
    }

    public function test_handle_calls_cancel_with_actor(): void
    {
        $user = User::factory()->create();
        $subscription = $this->createSubscription($user, 'active');

        $mockManager = Mockery::mock(SubscriptionManager::class);
        $mockManager->shouldReceive('cancel')
            ->once()
            ->withArgs(function (Subscription $sub, ?User $actor) use ($subscription, $user) {
                return $sub->id === $subscription->id
                    && $actor !== null
                    && $actor->id === $user->id;
            });

        $job = new CancelSubscriptionOnAccountDeletion($subscription, $user->id);
        $job->handle($mockManager);
    }

    public function test_handle_passes_null_actor_when_user_is_hard_deleted(): void
    {
        $user = User::factory()->create();
        $subscription = $this->createSubscription($user, 'active');
        $userId = $user->id;

        // User は SoftDeletes を使わないためハードデリート — Job 実行時は User が存在しない
        $user->delete();

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context) use ($userId) {
                return str_contains($message, 'ユーザーが見つかりません')
                    && $context['user_id'] === $userId
                    && isset($context['subscription_id']);
            });

        $mockManager = Mockery::mock(SubscriptionManager::class);
        $mockManager->shouldReceive('cancel')
            ->once()
            ->withArgs(function (Subscription $sub, ?User $actor) {
                return $actor === null; // ハードデリート済みユーザーは find() で null になる
            });

        $job = new CancelSubscriptionOnAccountDeletion($subscription, $userId);
        $job->handle($mockManager);
    }

    public function test_handle_logs_warning_when_user_not_found(): void
    {
        $user = User::factory()->create();
        $subscription = $this->createSubscription($user, 'active');
        $userId = $user->id;

        $user->delete();

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context) use ($userId) {
                return str_contains($message, 'ユーザーが見つかりません')
                    && $context['user_id'] === $userId;
            });

        $mockManager = Mockery::mock(SubscriptionManager::class);
        $mockManager->shouldReceive('cancel')->once();

        $job = new CancelSubscriptionOnAccountDeletion($subscription, $userId);
        $job->handle($mockManager);
    }

    public function test_failed_logs_error(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return str_contains($message, 'Failed to cancel subscription on account deletion')
                    && isset($context['subscription_id'])
                    && isset($context['user_id'])
                    && isset($context['error']);
            });

        $user = User::factory()->create();
        $subscription = $this->createSubscription($user, 'active');

        $job = new CancelSubscriptionOnAccountDeletion($subscription, $user->id);
        $job->failed(new \RuntimeException('Fincode API unavailable'));
    }
}
