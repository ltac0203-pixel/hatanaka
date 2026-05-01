<?php

namespace Tests\Unit\Services;

use App\Events\CustomerCreated;
use App\Models\FincodeCustomer;
use App\Models\User;
use App\Services\CustomerSyncService;
use App\Services\Fincode\CustomerService;
use App\Services\RequestContextResolver;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class CustomerSyncServiceTest extends TestCase
{
    use DatabaseMigrations;

    private CustomerSyncService $syncService;

    private $mockCustomerService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockCustomerService = Mockery::mock(CustomerService::class);
        $this->syncService = new CustomerSyncService(
            $this->mockCustomerService,
            new RequestContextResolver($this->app)
        );
    }

    private function setRequestContext(string $ipAddress, string $userAgent): void
    {
        $request = $this->app['request'];
        $request->server->set('REMOTE_ADDR', $ipAddress);
        $request->headers->set('User-Agent', $userAgent);
    }

    public function test_ensure_customer_exists_returns_existing(): void
    {
        $user = User::factory()->create();

        $customer = FincodeCustomer::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_existing',
            'name' => $user->name,
            'email' => $user->email,
        ]);

        Event::fake();

        $this->mockCustomerService->shouldNotReceive('create');

        $result = $this->syncService->ensureCustomerExists($user);

        $this->assertSame($customer->id, $result->id);
        $this->assertSame('cus_existing', $result->fincode_customer_id);
        Event::assertNotDispatched(CustomerCreated::class);
    }

    public function test_ensure_customer_exists_creates_new(): void
    {
        $user = User::factory()->create();

        Event::fake();

        $this->mockCustomerService->shouldReceive('create')
            ->once()
            ->with(['name' => $user->name, 'email' => $user->email])
            ->andReturn([
                'id' => 'cus_new',
                'name' => $user->name,
                'email' => $user->email,
            ]);

        $result = $this->syncService->ensureCustomerExists($user);

        $this->assertSame('cus_new', $result->fincode_customer_id);
        $this->assertSame($user->id, $result->user_id);
        $this->assertDatabaseHas('fincode_customers', [
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_new',
        ]);

        Event::assertDispatched(CustomerCreated::class);
    }

    public function test_ensure_customer_exists_calls_fincode_api(): void
    {
        $user = User::factory()->create();

        Event::fake();

        $this->mockCustomerService->shouldReceive('create')
            ->once()
            ->with(['name' => $user->name, 'email' => $user->email])
            ->andReturn([
                'id' => 'cus_api_call',
                'name' => $user->name,
                'email' => $user->email,
            ]);

        $customer = $this->syncService->ensureCustomerExists($user);

        $this->assertSame('cus_api_call', $customer->fincode_customer_id);
    }

    public function test_ensure_customer_exists_saves_to_database(): void
    {
        $user = User::factory()->create();

        Event::fake();

        $this->mockCustomerService->shouldReceive('create')
            ->once()
            ->andReturn([
                'id' => 'cus_saved',
                'name' => 'Saved Customer',
                'email' => 'saved@example.com',
                'phone_cc' => '81',
                'phone_no' => '09012345678',
                'addr_country' => 'JP',
                'addr_state' => 'Tokyo',
                'addr_city' => 'Shibuya',
                'addr_line_1' => '1-2-3',
                'addr_line_2' => 'Building 4F',
                'addr_post_code' => '150-0001',
                'metadata' => ['key' => 'value'],
            ]);

        $customer = $this->syncService->ensureCustomerExists($user);

        $this->assertDatabaseHas('fincode_customers', [
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_saved',
            'name' => 'Saved Customer',
            'email' => 'saved@example.com',
            'phone_cc' => '81',
            'phone_no' => '09012345678',
            'addr_country' => 'JP',
            'addr_state' => 'Tokyo',
            'addr_city' => 'Shibuya',
            'addr_line_1' => '1-2-3',
            'addr_line_2' => 'Building 4F',
            'addr_post_code' => '150-0001',
        ]);

        $this->assertNotNull($customer->synced_at);
    }

    public function test_ensure_customer_exists_dispatches_created_event(): void
    {
        $user = User::factory()->create();

        $this->setRequestContext('192.168.10.30', 'CustomerSyncServiceTest/1.0');

        Event::fake();

        $this->mockCustomerService->shouldReceive('create')
            ->once()
            ->andReturn([
                'id' => 'cus_created_event',
                'name' => $user->name,
                'email' => $user->email,
            ]);

        $customer = $this->syncService->ensureCustomerExists($user);

        Event::assertDispatched(CustomerCreated::class, function (CustomerCreated $event) use ($customer, $user): bool {
            return $event->auditEvent() === 'customer.created'
                && $event->actor()?->is($user)
                && $event->auditable()->is($customer)
                && $event->oldValues() === []
                && $event->newValues()['id'] === $customer->id
                && $event->newValues()['fincode_customer_id'] === 'cus_created_event'
                && $event->ipAddress === '192.168.10.30'
                && $event->userAgent === 'CustomerSyncServiceTest/1.0';
        });
    }

    public function test_ensure_customer_exists_returns_existing_when_already_exists(): void
    {
        $user = User::factory()->create();

        $customer = FincodeCustomer::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_already',
            'name' => $user->name,
            'email' => $user->email,
        ]);

        Event::fake();

        $this->mockCustomerService->shouldNotReceive('create');

        $result = $this->syncService->ensureCustomerExists($user);

        $this->assertSame($customer->id, $result->id);
        $this->assertSame('cus_already', $result->fincode_customer_id);
        Event::assertNotDispatched(CustomerCreated::class);
    }

    public function test_ensure_customer_exists_does_not_call_api_when_exists_after_lock(): void
    {
        $user = User::factory()->create();

        Event::fake();
        Log::spy();

        // createCustomer 経由で顧客を作成
        $this->mockCustomerService->shouldReceive('create')
            ->once()
            ->with(['name' => $user->name, 'email' => $user->email])
            ->andReturn([
                'id' => 'cus_first',
                'name' => $user->name,
                'email' => $user->email,
            ]);

        $firstResult = $this->syncService->ensureCustomerExists($user);
        $this->assertSame('cus_first', $firstResult->fincode_customer_id);

        // 2回目: 既存顧客が fast path で返される（API 未呼出）
        $this->mockCustomerService->shouldNotReceive('create');

        $secondResult = $this->syncService->ensureCustomerExists($user);
        $this->assertSame($firstResult->id, $secondResult->id);
    }

    public function test_sync_customer_updates_data(): void
    {
        $user = User::factory()->create();

        $customer = FincodeCustomer::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_sync',
            'name' => 'Old Name',
            'email' => 'old@example.com',
        ]);

        Event::fake();

        $this->mockCustomerService->shouldReceive('getCustomer')
            ->once()
            ->with('cus_sync')
            ->andReturn([
                'id' => 'cus_sync',
                'name' => 'Updated Name',
                'email' => 'updated@example.com',
                'phone_cc' => '81',
                'phone_no' => '08011112222',
            ]);

        $this->syncService->syncCustomer($customer);

        $customer->refresh();

        $this->assertSame('Updated Name', $customer->name);
        $this->assertSame('updated@example.com', $customer->email);
        $this->assertSame('81', $customer->phone_cc);
        $this->assertSame('08011112222', $customer->phone_no);
        $this->assertNotNull($customer->synced_at);
        Event::assertNotDispatched(CustomerCreated::class);
    }
}
