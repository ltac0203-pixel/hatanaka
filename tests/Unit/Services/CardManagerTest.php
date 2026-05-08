<?php

namespace Tests\Unit\Services;

use App\Events\CardDeleted;
use App\Events\CardRegistered;
use App\Exceptions\CardInUseException;
use App\Exceptions\FincodeApiException;
use App\Models\FincodeCard;
use App\Models\FincodeCustomer;
use App\Models\Subscription;
use App\Models\User;
use App\Services\CardManager;
use App\Services\CustomerSyncService;
use App\Services\Fincode\CardService;
use App\Services\RequestContextResolver;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class CardManagerTest extends TestCase
{
    use DatabaseMigrations;

    private CardManager $cardManager;

    private $mockCardService;

    private $mockCustomerSyncService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockCardService = Mockery::mock(CardService::class);
        $this->mockCustomerSyncService = Mockery::mock(CustomerSyncService::class);

        $this->cardManager = new CardManager(
            $this->mockCardService,
            $this->mockCustomerSyncService,
            new RequestContextResolver($this->app)
        );
    }

    private function createUserWithCustomer(): array
    {
        $user = User::factory()->create();

        $customer = FincodeCustomer::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_test_123',
            'name' => $user->name,
            'email' => $user->email,
        ]);

        return [$user, $customer];
    }

    private function fincodeCardResponse(array $overrides = []): array
    {
        return array_merge([
            'id' => 'card_fincode_123',
            'brand' => 'Visa',
            'card_no' => '************4242',
            // Fincode の card レスポンスは expire を YYMM (4桁) で返す。
            'expire' => '3012',
            'holder_name' => 'TEST USER',
        ], $overrides);
    }

    private function setRequestContext(string $ipAddress, string $userAgent): void
    {
        $request = $this->app['request'];
        $request->server->set('REMOTE_ADDR', $ipAddress);
        $request->headers->set('User-Agent', $userAgent);
    }

    public function test_create_card_first_card_becomes_default(): void
    {
        [$user, $customer] = $this->createUserWithCustomer();

        Event::fake();

        $this->mockCustomerSyncService->shouldReceive('ensureCustomerExists')
            ->once()
            ->with($user)
            ->andReturn($customer);

        $this->mockCardService->shouldReceive('create')
            ->once()
            ->with('cus_test_123', 'tok_test', true, Mockery::type('string'))
            ->andReturn($this->fincodeCardResponse());

        $card = $this->cardManager->create($user, 'tok_test', false);

        $this->assertTrue($card->is_default);
        $this->assertSame('card_fincode_123', $card->fincode_card_id);
        Event::assertDispatched(CardRegistered::class);
    }

    public function test_create_card_uses_loaded_cards_relation_without_count_query(): void
    {
        [$user, $customer] = $this->createUserWithCustomer();

        $user->setRelation('fincodeCards', new EloquentCollection);

        Event::fake();

        $this->mockCustomerSyncService->shouldReceive('ensureCustomerExists')
            ->once()
            ->with($user)
            ->andReturn($customer);

        $this->mockCardService->shouldReceive('create')
            ->once()
            ->with('cus_test_123', 'tok_loaded_empty', true, Mockery::type('string'))
            ->andReturn($this->fincodeCardResponse(['id' => 'card_loaded_empty']));

        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $card = $this->cardManager->create($user, 'tok_loaded_empty', false);
        } finally {
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
        }

        $countQueries = array_filter(
            $queries,
            static fn (array $query): bool => preg_match('/\bcount\s*\(/i', $query['query']) === 1,
        );

        $this->assertTrue($card->is_default);
        $this->assertCount(0, $countQueries);
    }

    public function test_create_card_keeps_non_default_when_loaded_cards_relation_is_not_empty(): void
    {
        [$user, $customer] = $this->createUserWithCustomer();

        $existingCard = FincodeCard::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_test_123',
            'fincode_card_id' => 'card_loaded_existing',
            'brand' => 'Visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'holder_name' => 'TEST',
            'is_default' => true,
        ]);

        $user->setRelation('fincodeCards', new EloquentCollection([$existingCard]));

        Event::fake();

        $this->mockCustomerSyncService->shouldReceive('ensureCustomerExists')
            ->once()
            ->with($user)
            ->andReturn($customer);

        $this->mockCardService->shouldReceive('create')
            ->once()
            ->with('cus_test_123', 'tok_loaded_existing', false, Mockery::type('string'))
            ->andReturn($this->fincodeCardResponse(['id' => 'card_loaded_new']));

        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $newCard = $this->cardManager->create($user, 'tok_loaded_existing', false);
        } finally {
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
        }

        $countQueries = array_filter(
            $queries,
            static fn (array $query): bool => preg_match('/\bcount\s*\(/i', $query['query']) === 1,
        );

        $this->assertFalse($newCard->is_default);
        $this->assertCount(0, $countQueries);
    }

    public function test_create_card_with_is_default_clears_other_defaults(): void
    {
        [$user, $customer] = $this->createUserWithCustomer();

        $existingCard = FincodeCard::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_test_123',
            'fincode_card_id' => 'card_existing',
            'brand' => 'Mastercard',
            'last4' => '5555',
            'exp_month' => 6,
            'exp_year' => 2028,
            'holder_name' => 'TEST',
            'is_default' => true,
        ]);

        Event::fake();

        $this->mockCustomerSyncService->shouldReceive('ensureCustomerExists')
            ->once()
            ->andReturn($customer);

        $this->mockCardService->shouldReceive('create')
            ->once()
            ->with('cus_test_123', 'tok_new', true, Mockery::type('string'))
            ->andReturn($this->fincodeCardResponse(['id' => 'card_new']));

        $newCard = $this->cardManager->create($user, 'tok_new', true);

        $existingCard->refresh();
        $this->assertFalse($existingCard->is_default);
        $this->assertTrue($newCard->is_default);
        Event::assertDispatched(CardRegistered::class);
    }

    public function test_create_card_calls_fincode_api(): void
    {
        [$user, $customer] = $this->createUserWithCustomer();

        Event::fake();

        $this->mockCustomerSyncService->shouldReceive('ensureCustomerExists')
            ->once()
            ->andReturn($customer);

        $this->mockCardService->shouldReceive('create')
            ->once()
            ->with('cus_test_123', 'tok_api_test', true, Mockery::type('string'))
            ->andReturn($this->fincodeCardResponse());

        $this->cardManager->create($user, 'tok_api_test');
    }

    public function test_create_card_saves_to_database(): void
    {
        [$user, $customer] = $this->createUserWithCustomer();

        Event::fake();

        $this->mockCustomerSyncService->shouldReceive('ensureCustomerExists')
            ->once()
            ->andReturn($customer);

        $this->mockCardService->shouldReceive('create')
            ->once()
            ->andReturn($this->fincodeCardResponse([
                'id' => 'card_saved',
                'brand' => 'JCB',
                'card_no' => '************1234',
                'expire' => '2903',
                'holder_name' => 'SAVE TEST',
            ]));

        $card = $this->cardManager->create($user, 'tok_save');

        $this->assertDatabaseHas('fincode_cards', [
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_test_123',
            'fincode_card_id' => 'card_saved',
            'brand' => 'JCB',
            'last4' => '1234',
            'exp_month' => 3,
            'exp_year' => 2029,
            'holder_name' => 'SAVE TEST',
        ]);

        Event::assertDispatched(CardRegistered::class, fn (CardRegistered $event) => $event->auditable()->is($card));
    }

    public function test_create_card_dispatches_registered_event(): void
    {
        [$user, $customer] = $this->createUserWithCustomer();

        $this->setRequestContext('192.168.10.10', 'CardManagerTest/1.0');

        Event::fake();

        $this->mockCustomerSyncService->shouldReceive('ensureCustomerExists')
            ->once()
            ->andReturn($customer);

        $this->mockCardService->shouldReceive('create')
            ->once()
            ->andReturn($this->fincodeCardResponse());

        $card = $this->cardManager->create($user, 'tok_audit');

        Event::assertDispatched(CardRegistered::class, function (CardRegistered $event) use ($card, $user): bool {
            return $event->auditEvent() === 'card.created'
                && $event->actor()?->is($user)
                && $event->oldValues() === []
                && $event->newValues()['id'] === $card->id
                && $event->newValues()['fincode_card_id'] === 'card_fincode_123'
                && $event->ipAddress === '192.168.10.10'
                && $event->userAgent === 'CardManagerTest/1.0';
        });
    }

    public function test_create_card_rolls_back_on_fincode_error(): void
    {
        [$user, $customer] = $this->createUserWithCustomer();

        Event::fake();

        $this->mockCustomerSyncService->shouldReceive('ensureCustomerExists')
            ->once()
            ->andReturn($customer);

        $this->mockCardService->shouldReceive('create')
            ->once()
            ->andThrow(new FincodeApiException('Card creation failed', 400, ['error' => 'invalid_token']));

        $this->expectException(FincodeApiException::class);

        try {
            $this->cardManager->create($user, 'tok_invalid');
        } finally {
            $this->assertDatabaseMissing('fincode_cards', [
                'user_id' => $user->id,
                'fincode_customer_id' => 'cus_test_123',
            ]);

            Event::assertNotDispatched(CardRegistered::class);
        }
    }

    public function test_delete_card_calls_fincode_api(): void
    {
        [$user] = $this->createUserWithCustomer();

        $card = FincodeCard::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_test_123',
            'fincode_card_id' => 'card_del_api',
            'brand' => 'Visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'holder_name' => 'TEST',
            'is_default' => true,
        ]);

        Event::fake();

        $this->mockCardService->shouldReceive('deleteCard')
            ->once()
            ->with('cus_test_123', 'card_del_api', Mockery::type('string'));

        $this->cardManager->delete($card);

        Event::assertDispatched(CardDeleted::class);
    }

    public function test_delete_card_soft_deletes(): void
    {
        [$user] = $this->createUserWithCustomer();

        $card = FincodeCard::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_test_123',
            'fincode_card_id' => 'card_soft_del',
            'brand' => 'Visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'holder_name' => 'TEST',
            'is_default' => true,
        ]);

        Event::fake();

        $this->mockCardService->shouldReceive('deleteCard')->once();

        $this->cardManager->delete($card);

        $this->assertSoftDeleted('fincode_cards', [
            'id' => $card->id,
            'fincode_card_id' => 'card_soft_del',
        ]);
    }

    public function test_delete_default_card_promotes_another(): void
    {
        [$user] = $this->createUserWithCustomer();

        $defaultCard = FincodeCard::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_test_123',
            'fincode_card_id' => 'card_default',
            'brand' => 'Visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'holder_name' => 'TEST',
            'is_default' => true,
        ]);

        $otherCard = FincodeCard::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_test_123',
            'fincode_card_id' => 'card_other',
            'brand' => 'Mastercard',
            'last4' => '5555',
            'exp_month' => 6,
            'exp_year' => 2028,
            'holder_name' => 'TEST',
            'is_default' => false,
        ]);

        Event::fake();

        $this->mockCardService->shouldReceive('deleteCard')->once();

        $this->cardManager->delete($defaultCard);

        $otherCard->refresh();
        $this->assertTrue($otherCard->is_default);
    }

    public function test_delete_default_card_promotes_oldest_remaining_card(): void
    {
        [$user] = $this->createUserWithCustomer();

        $defaultCard = FincodeCard::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_test_123',
            'fincode_card_id' => 'card_default_oldest_rule',
            'brand' => 'Visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'holder_name' => 'TEST',
            'is_default' => true,
        ]);

        $oldestRemainingCard = FincodeCard::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_test_123',
            'fincode_card_id' => 'card_oldest_remaining',
            'brand' => 'Mastercard',
            'last4' => '5555',
            'exp_month' => 6,
            'exp_year' => 2028,
            'holder_name' => 'TEST',
            'is_default' => false,
        ]);

        $newestRemainingCard = FincodeCard::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_test_123',
            'fincode_card_id' => 'card_newest_remaining',
            'brand' => 'JCB',
            'last4' => '1111',
            'exp_month' => 8,
            'exp_year' => 2029,
            'holder_name' => 'TEST',
            'is_default' => false,
        ]);

        Event::fake();

        $this->mockCardService->shouldReceive('deleteCard')->once();

        $this->cardManager->delete($defaultCard);

        $oldestRemainingCard->refresh();
        $newestRemainingCard->refresh();

        $this->assertTrue($oldestRemainingCard->is_default);
        $this->assertFalse($newestRemainingCard->is_default);
    }

    public function test_delete_card_dispatches_deleted_event(): void
    {
        [$user] = $this->createUserWithCustomer();

        $this->setRequestContext('192.168.10.11', 'CardManagerDeleteTest/1.0');

        $card = FincodeCard::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_test_123',
            'fincode_card_id' => 'card_audit_del',
            'brand' => 'Visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'holder_name' => 'TEST',
            'is_default' => true,
        ]);

        Event::fake();

        $this->mockCardService->shouldReceive('deleteCard')->once();

        $this->cardManager->delete($card);

        Event::assertDispatched(CardDeleted::class, function (CardDeleted $event) use ($card, $user): bool {
            return $event->auditEvent() === 'card.deleted'
                && $event->actor()?->is($user)
                && $event->auditable()->is($card)
                && $event->oldValues()['id'] === $card->id
                && $event->newValues() === []
                && $event->ipAddress === '192.168.10.11'
                && $event->userAgent === 'CardManagerDeleteTest/1.0';
        });
    }

    public function test_delete_card_uses_loaded_user_relation_without_additional_owner_query(): void
    {
        [$user] = $this->createUserWithCustomer();

        $card = FincodeCard::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_test_123',
            'fincode_card_id' => 'card_loaded_user',
            'brand' => 'Visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'holder_name' => 'TEST',
            'is_default' => true,
        ])->load('user');

        Event::fake();

        $this->mockCardService->shouldReceive('deleteCard')->once();

        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $this->cardManager->delete($card);
        } finally {
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
        }

        $userQueries = array_filter(
            $queries,
            static fn (array $query): bool => preg_match('/from\s+[\"`]?users[\"`]?/i', $query['query']) === 1,
        );

        $this->assertCount(0, $userQueries);
        Event::assertDispatched(CardDeleted::class, fn (CardDeleted $event): bool => $event->actor()?->is($user) === true);
    }

    public function test_delete_card_throws_card_in_use_exception_when_active_subscription_exists(): void
    {
        [$user] = $this->createUserWithCustomer();

        $card = FincodeCard::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_test_123',
            'fincode_card_id' => 'card_in_use',
            'brand' => 'Visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'holder_name' => 'TEST',
            'is_default' => true,
        ]);

        Subscription::create([
            'user_id' => $user->id,
            'fincode_plan_id' => 'pl_test',
            'plan_name' => 'Test Plan',
            'plan_amount' => 1000,
            'plan_interval' => 'monthly',
            'plan_interval_count' => 1,
            'plan_snapshot' => [],
            'fincode_subscription_id' => 'sub_active_test',
            'fincode_customer_id' => 'cus_test_123',
            'fincode_card_id' => 'card_in_use',
            'status' => 'active',
            'start_date' => now(),
        ]);

        $this->mockCardService->shouldNotReceive('deleteCard');

        $this->expectException(CardInUseException::class);

        $this->cardManager->delete($card);
    }
}
