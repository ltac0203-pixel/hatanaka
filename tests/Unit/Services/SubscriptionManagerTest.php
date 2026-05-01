<?php

namespace Tests\Unit\Services;

use App\Events\SubscriptionCanceled;
use App\Events\SubscriptionCreated;
use App\Events\SubscriptionStatusChanged;
use App\Exceptions\ActiveSubscriptionExistsException;
use App\Exceptions\ExpiredCardException;
use App\Exceptions\FincodeApiException;
use App\Models\FincodeCard;
use App\Models\FincodeCustomer;
use App\Models\Subscription;
use App\Models\User;
use App\Services\CustomerSyncService;
use App\Services\Fincode\SubscriptionService as FincodeSubscriptionService;
use App\Services\RequestContextResolver;
use App\Services\SubscriptionManager;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class SubscriptionManagerTest extends TestCase
{
    use DatabaseMigrations;

    private SubscriptionManager $manager;

    private $mockSubscriptionService;

    private $mockCustomerSyncService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockSubscriptionService = Mockery::mock(FincodeSubscriptionService::class);
        $this->mockCustomerSyncService = Mockery::mock(CustomerSyncService::class);

        $this->manager = new SubscriptionManager(
            $this->mockSubscriptionService,
            $this->mockCustomerSyncService,
            new RequestContextResolver($this->app)
        );
    }

    private function createFullSetup(): array
    {
        $user = User::factory()->create();

        $customer = FincodeCustomer::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_sub_test',
            'name' => $user->name,
            'email' => $user->email,
        ]);

        $card = FincodeCard::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_sub_test',
            'fincode_card_id' => 'card_sub_test',
            'brand' => 'Visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'holder_name' => 'TEST USER',
            'is_default' => true,
        ]);

        $planData = [
            'fincode_plan_id' => 'pl_test',
            'name' => 'Test Plan',
            'amount' => 1000,
            'interval' => 'monthly',
            'interval_count' => 1,
            'status' => 'active',
        ];

        return [$user, $customer, $card, $planData];
    }

    private function fincodeSubscriptionResponse(array $overrides = []): array
    {
        return array_merge([
            'id' => 'sub_fincode_123',
            'status' => 'ACTIVE',
            'start_date' => '2026-03-01',
            'stop_date' => '2027-03-01',
            'next_charge_date' => '2026-04-01',
        ], $overrides);
    }

    private function expectedCreateIdempotencyKey(User $user, array $planData, FincodeCard $card, string $startDate): string
    {
        return 'subscription:create:'.hash('sha256', implode('|', [
            (string) $user->id,
            $planData['fincode_plan_id'],
            $card->fincode_card_id,
            $startDate,
        ]));
    }

    private function setRequestContext(string $ipAddress, string $userAgent): void
    {
        $request = $this->app['request'];
        $request->server->set('REMOTE_ADDR', $ipAddress);
        $request->headers->set('User-Agent', $userAgent);
    }

    public function test_create_subscription_calls_fincode_api(): void
    {
        [$user, $customer, $card, $planData] = $this->createFullSetup();
        $expectedIdempotencyKey = $this->expectedCreateIdempotencyKey($user, $planData, $card, '2026-03-01');

        Event::fake();

        $this->mockCustomerSyncService->shouldReceive('ensureCustomerExists')
            ->once()
            ->with($user)
            ->andReturn($customer);

        $this->mockSubscriptionService->shouldReceive('create')
            ->once()
            ->withArgs(function (array $payload, ?string $idempotencyKey) use ($expectedIdempotencyKey): bool {
                return $payload === [
                    'plan_id' => 'pl_test',
                    'customer_id' => 'cus_sub_test',
                    'card_id' => 'card_sub_test',
                    'start_date' => '2026-03-01',
                ] && $idempotencyKey === $expectedIdempotencyKey;
            })
            ->andReturn($this->fincodeSubscriptionResponse());

        $this->manager->create($user, $planData, $card, '2026-03-01');
    }

    public function test_create_subscription_saves_to_database(): void
    {
        [$user, $customer, $card, $planData] = $this->createFullSetup();

        Event::fake();

        $this->mockCustomerSyncService->shouldReceive('ensureCustomerExists')
            ->once()
            ->andReturn($customer);

        $this->mockSubscriptionService->shouldReceive('create')
            ->once()
            ->andReturn($this->fincodeSubscriptionResponse());

        $subscription = $this->manager->create($user, $planData, $card, '2026-03-01');

        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $user->id,
            'fincode_plan_id' => 'pl_test',
            'plan_name' => 'Test Plan',
            'plan_amount' => 1000,
            'plan_interval' => 'monthly',
            'plan_interval_count' => 1,
            'fincode_subscription_id' => 'sub_fincode_123',
            'fincode_customer_id' => 'cus_sub_test',
            'fincode_card_id' => 'card_sub_test',
            'status' => 'active',
        ]);
    }

    public function test_create_subscription_parses_dates(): void
    {
        [$user, $customer, $card, $planData] = $this->createFullSetup();

        Event::fake();

        $this->mockCustomerSyncService->shouldReceive('ensureCustomerExists')
            ->once()
            ->andReturn($customer);

        $this->mockSubscriptionService->shouldReceive('create')
            ->once()
            ->andReturn($this->fincodeSubscriptionResponse([
                'start_date' => '2026-05-15T10:30:00+09:00',
                'stop_date' => '2027-05-15T10:30:00+09:00',
                'next_charge_date' => '2026-06-15T00:00:00+09:00',
            ]));

        $subscription = $this->manager->create($user, $planData, $card, '2026-05-15');

        $this->assertSame('2026-05-15', $subscription->start_date->toDateString());
        $this->assertSame('2027-05-15', $subscription->stop_date->toDateString());
        $this->assertSame('2026-06-15', $subscription->next_charge_date->toDateString());
    }

    public function test_create_subscription_converts_utc_dates_to_app_timezone(): void
    {
        [$user, $customer, $card, $planData] = $this->createFullSetup();

        Event::fake();

        $this->mockCustomerSyncService->shouldReceive('ensureCustomerExists')
            ->once()
            ->andReturn($customer);

        $this->mockSubscriptionService->shouldReceive('create')
            ->once()
            ->andReturn($this->fincodeSubscriptionResponse([
                'start_date' => '2026-05-15T23:00:00Z',
                'stop_date' => '2027-05-15T20:00:00Z',
                'next_charge_date' => '2026-06-14T15:00:00Z',
            ]));

        $subscription = $this->manager->create($user, $planData, $card, '2026-05-15');

        $this->assertSame('2026-05-16', $subscription->start_date->toDateString());
        $this->assertSame('2027-05-16', $subscription->stop_date->toDateString());
        $this->assertSame('2026-06-15', $subscription->next_charge_date->toDateString());
    }

    public function test_create_subscription_handles_null_optional_dates(): void
    {
        [$user, $customer, $card, $planData] = $this->createFullSetup();

        Event::fake();

        $this->mockCustomerSyncService->shouldReceive('ensureCustomerExists')
            ->once()
            ->andReturn($customer);

        $this->mockSubscriptionService->shouldReceive('create')
            ->once()
            ->andReturn([
                'id' => 'sub_no_dates',
                'status' => 'ACTIVE',
                'start_date' => '2026-03-01',
            ]);

        $subscription = $this->manager->create($user, $planData, $card, '2026-03-01');

        $this->assertNull($subscription->stop_date);
        $this->assertNull($subscription->next_charge_date);
    }

    public function test_create_subscription_stores_plan_snapshot(): void
    {
        [$user, $customer, $card, $planData] = $this->createFullSetup();

        Event::fake();

        $this->mockCustomerSyncService->shouldReceive('ensureCustomerExists')
            ->once()
            ->andReturn($customer);

        $this->mockSubscriptionService->shouldReceive('create')
            ->once()
            ->andReturn($this->fincodeSubscriptionResponse());

        $subscription = $this->manager->create($user, $planData, $card, '2026-03-01');

        $this->assertIsArray($subscription->plan_snapshot);
        $this->assertSame('pl_test', $subscription->plan_snapshot['fincode_plan_id']);
        $this->assertSame('Test Plan', $subscription->plan_snapshot['name']);
        $this->assertSame(1000, $subscription->plan_snapshot['amount']);
    }

    public function test_create_subscription_dispatches_created_event(): void
    {
        [$user, $customer, $card, $planData] = $this->createFullSetup();

        $this->setRequestContext('192.168.10.20', 'SubscriptionManagerTest/1.0');

        Event::fake();

        $this->mockCustomerSyncService->shouldReceive('ensureCustomerExists')
            ->once()
            ->andReturn($customer);

        $this->mockSubscriptionService->shouldReceive('create')
            ->once()
            ->andReturn($this->fincodeSubscriptionResponse());

        $subscription = $this->manager->create($user, $planData, $card, '2026-03-01');

        Event::assertDispatched(SubscriptionCreated::class, function (SubscriptionCreated $event) use ($subscription, $user): bool {
            return $event->auditEvent() === 'subscription.created'
                && $event->actor()?->is($user)
                && $event->auditable()->is($subscription)
                && $event->oldValues() === []
                && $event->newValues()['id'] === $subscription->id
                && $event->newValues()['status'] === 'active'
                && $event->ipAddress === '192.168.10.20'
                && $event->userAgent === 'SubscriptionManagerTest/1.0';
        });
    }

    public function test_create_subscription_throws_when_active_subscription_exists(): void
    {
        [$user, $customer, $card, $planData] = $this->createFullSetup();

        Event::fake();

        Subscription::create([
            'user_id' => $user->id,
            'fincode_plan_id' => $planData['fincode_plan_id'],
            'plan_name' => $planData['name'],
            'plan_amount' => $planData['amount'],
            'plan_interval' => $planData['interval'],
            'plan_interval_count' => $planData['interval_count'],
            'plan_snapshot' => $planData,
            'fincode_subscription_id' => 'sub_existing_active',
            'fincode_customer_id' => $customer->fincode_customer_id,
            'fincode_card_id' => $card->fincode_card_id,
            'status' => 'active',
            'start_date' => '2026-02-01',
        ]);

        $this->mockCustomerSyncService->shouldNotReceive('ensureCustomerExists');
        $this->mockSubscriptionService->shouldNotReceive('create');

        $this->expectException(ActiveSubscriptionExistsException::class);

        try {
            $this->manager->create($user, $planData, $card, '2026-03-01');
        } finally {
            Event::assertNotDispatched(SubscriptionCreated::class);
        }
    }

    public function test_create_subscription_rolls_back_on_fincode_error(): void
    {
        [$user, $customer, $card, $planData] = $this->createFullSetup();

        Event::fake();

        $this->mockCustomerSyncService->shouldReceive('ensureCustomerExists')
            ->once()
            ->andReturn($customer);

        $this->mockSubscriptionService->shouldReceive('create')
            ->once()
            ->andThrow(new FincodeApiException('Subscription creation failed', 400));

        $this->expectException(FincodeApiException::class);

        try {
            $this->manager->create($user, $planData, $card, '2026-03-01');
        } finally {
            $this->assertDatabaseMissing('subscriptions', [
                'user_id' => $user->id,
                'fincode_plan_id' => 'pl_test',
            ]);

            Event::assertNotDispatched(SubscriptionCreated::class);
        }
    }

    public function test_cancel_subscription_calls_fincode_api(): void
    {
        [$user, $customer, $card, $planData] = $this->createFullSetup();

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'fincode_plan_id' => 'pl_test',
            'plan_name' => 'Test Plan',
            'plan_amount' => 1000,
            'plan_interval' => 'monthly',
            'plan_interval_count' => 1,
            'plan_snapshot' => $planData,
            'fincode_subscription_id' => 'sub_cancel_api',
            'fincode_customer_id' => 'cus_sub_test',
            'fincode_card_id' => 'card_sub_test',
            'status' => 'active',
            'start_date' => now(),
        ]);

        Event::fake();

        $this->mockSubscriptionService->shouldReceive('cancel')
            ->once()
            ->with('sub_cancel_api');

        $this->manager->cancel($subscription);
    }

    public function test_cancel_subscription_updates_status(): void
    {
        [$user, $customer, $card, $planData] = $this->createFullSetup();

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'fincode_plan_id' => 'pl_test',
            'plan_name' => 'Test Plan',
            'plan_amount' => 1000,
            'plan_interval' => 'monthly',
            'plan_interval_count' => 1,
            'plan_snapshot' => $planData,
            'fincode_subscription_id' => 'sub_cancel_status',
            'fincode_customer_id' => 'cus_sub_test',
            'fincode_card_id' => 'card_sub_test',
            'status' => 'active',
            'start_date' => now(),
        ]);

        Event::fake();

        $this->mockSubscriptionService->shouldReceive('cancel')->once();

        $this->manager->cancel($subscription);

        $subscription->refresh();
        $this->assertSame('canceled', $subscription->status);
        $this->assertNotNull($subscription->canceled_at);
        $this->assertNotNull($subscription->stop_date);
    }

    public function test_cancel_subscription_dispatches_canceled_and_status_changed_events(): void
    {
        [$user, $customer, $card, $planData] = $this->createFullSetup();

        $this->setRequestContext('192.168.10.21', 'SubscriptionCancelTest/1.0');

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'fincode_plan_id' => 'pl_test',
            'plan_name' => 'Test Plan',
            'plan_amount' => 1000,
            'plan_interval' => 'monthly',
            'plan_interval_count' => 1,
            'plan_snapshot' => $planData,
            'fincode_subscription_id' => 'sub_cancel_audit',
            'fincode_customer_id' => 'cus_sub_test',
            'fincode_card_id' => 'card_sub_test',
            'status' => 'active',
            'start_date' => now(),
        ]);

        Event::fake();

        $this->mockSubscriptionService->shouldReceive('cancel')->once();

        $this->manager->cancel($subscription);

        Event::assertDispatched(SubscriptionCanceled::class, function (SubscriptionCanceled $event) use ($subscription, $user): bool {
            return $event->auditEvent() === 'subscription.canceled'
                && $event->actor()?->is($user)
                && $event->auditable()->is($subscription)
                && $event->oldValues()['status'] === 'active'
                && $event->newValues()['status'] === 'canceled'
                && $event->ipAddress === '192.168.10.21'
                && $event->userAgent === 'SubscriptionCancelTest/1.0';
        });

        Event::assertDispatched(SubscriptionStatusChanged::class, function (SubscriptionStatusChanged $event) use ($subscription, $user): bool {
            return $event->auditEvent() === 'subscription.status_changed'
                && $event->actor()?->is($user)
                && $event->auditable()->is($subscription)
                && $event->fromStatus() === 'active'
                && $event->toStatus() === 'canceled'
                && $event->metadata()['trigger'] === 'subscription.cancel'
                && $event->ipAddress === '192.168.10.21'
                && $event->userAgent === 'SubscriptionCancelTest/1.0';
        });
    }

    public function test_cancel_subscription_dispatches_null_request_context_for_console_execution(): void
    {
        [$user, $customer, $card, $planData] = $this->createFullSetup();

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'fincode_plan_id' => 'pl_test',
            'plan_name' => 'Test Plan',
            'plan_amount' => 1000,
            'plan_interval' => 'monthly',
            'plan_interval_count' => 1,
            'plan_snapshot' => $planData,
            'fincode_subscription_id' => 'sub_cancel_console',
            'fincode_customer_id' => 'cus_sub_test',
            'fincode_card_id' => 'card_sub_test',
            'status' => 'active',
            'start_date' => now(),
        ]);

        Event::fake();

        $this->mockSubscriptionService->shouldReceive('cancel')->once();

        $this->manager->cancel($subscription);

        Event::assertDispatched(SubscriptionCanceled::class, fn (SubscriptionCanceled $event): bool => $event->ipAddress === null && $event->userAgent === null);
        Event::assertDispatched(SubscriptionStatusChanged::class, fn (SubscriptionStatusChanged $event): bool => $event->ipAddress === null && $event->userAgent === null);
    }

    public function test_create_subscription_throws_expired_card_exception(): void
    {
        [$user, $customer, $card, $planData] = $this->createFullSetup();

        // カードを期限切れに変更
        $card->update(['exp_month' => 1, 'exp_year' => 2020]);

        Event::fake();

        $this->mockCustomerSyncService->shouldNotReceive('ensureCustomerExists');
        $this->mockSubscriptionService->shouldNotReceive('create');

        $this->expectException(ExpiredCardException::class);

        $this->manager->create($user, $planData, $card, '2026-03-01');
    }

    public function test_create_subscription_logs_warning_for_unknown_status(): void
    {
        [$user, $customer, $card, $planData] = $this->createFullSetup();

        Event::fake();
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Unknown subscription status from Fincode API, falling back to incomplete'
                    && $context['raw_status'] === 'UNKNOWN_STATUS';
            });
        Log::makePartial();

        $this->mockCustomerSyncService->shouldReceive('ensureCustomerExists')
            ->once()
            ->andReturn($customer);

        $this->mockSubscriptionService->shouldReceive('create')
            ->once()
            ->andReturn($this->fincodeSubscriptionResponse(['status' => 'UNKNOWN_STATUS']));

        $subscription = $this->manager->create($user, $planData, $card, '2026-03-01');

        $this->assertSame('incomplete', $subscription->status);
    }
}
