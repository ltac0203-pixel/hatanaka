<?php

namespace Tests\Unit\Listeners;

use App\Events\CardDeleted;
use App\Events\CustomerCreated;
use App\Events\SubscriptionStatusChanged;
use App\Listeners\AuditEventListener;
use App\Models\FincodeCard;
use App\Models\FincodeCustomer;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AuditEventListenerTest extends TestCase
{
    use RefreshDatabase;

    private AuditEventListener $listener;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->listener = new AuditEventListener(app(AuditLogger::class));
    }

    public function test_it_logs_deleted_card_events(): void
    {
        $user = User::factory()->create();

        FincodeCustomer::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_listener',
            'name' => $user->name,
            'email' => $user->email,
        ]);

        $card = FincodeCard::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_listener',
            'fincode_card_id' => 'card_listener',
            'brand' => 'Visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'holder_name' => 'TEST USER',
            'is_default' => true,
        ]);

        $this->listener->handleCardDeleted(new CardDeleted(
            $card,
            $user,
            $card->toArray(),
            []
        ));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => 'card.deleted',
            'auditable_type' => FincodeCard::class,
            'auditable_id' => $card->id,
        ]);

        $auditLog = $user->auditLogs()->latest('id')->first();
        $this->assertSame([], $auditLog->new_values);
        $this->assertNull($auditLog->ip_address);
        $this->assertNull($auditLog->user_agent);
    }

    public function test_it_logs_subscription_status_changes_with_metadata(): void
    {
        $user = User::factory()->create();

        FincodeCustomer::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_listener',
            'name' => $user->name,
            'email' => $user->email,
        ]);

        FincodeCard::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_listener',
            'fincode_card_id' => 'card_listener',
            'brand' => 'Visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'holder_name' => 'TEST USER',
            'is_default' => true,
        ]);

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'fincode_plan_id' => 'pl_listener',
            'plan_name' => 'Listener Plan',
            'plan_amount' => 1000,
            'plan_interval' => 'monthly',
            'plan_interval_count' => 1,
            'plan_snapshot' => ['fincode_plan_id' => 'pl_listener'],
            'fincode_subscription_id' => 'sub_listener',
            'fincode_customer_id' => 'cus_listener',
            'fincode_card_id' => 'card_listener',
            'status' => 'canceled',
            'start_date' => now()->subMonth(),
            'stop_date' => now()->toDateString(),
        ]);

        $this->listener->handleSubscriptionStatusChanged(new SubscriptionStatusChanged(
            $subscription,
            $user,
            ['status' => 'active'],
            ['status' => 'canceled'],
            'active',
            'canceled',
            ['trigger' => 'subscription.cancel']
        ));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => 'subscription.status_changed',
            'auditable_type' => Subscription::class,
            'auditable_id' => $subscription->id,
        ]);

        $auditLog = $user->auditLogs()->latest('id')->first();
        $this->assertSame('active', $auditLog->metadata['from_status']);
        $this->assertSame('canceled', $auditLog->metadata['to_status']);
        $this->assertSame('subscription.cancel', $auditLog->metadata['trigger']);
    }

    public function test_it_logs_created_customers(): void
    {
        $user = User::factory()->create();

        $customer = FincodeCustomer::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_created',
            'name' => $user->name,
            'email' => $user->email,
        ]);

        $this->listener->handleCustomerCreated(new CustomerCreated(
            $customer,
            $user,
            [],
            $customer->toArray()
        ));

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => 'customer.created',
            'auditable_type' => FincodeCustomer::class,
            'auditable_id' => $customer->id,
        ]);
    }

    public function test_event_dispatch_queues_listener_on_audit_queue(): void
    {
        $user = User::factory()->create();

        FincodeCustomer::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_queue_test',
            'name' => $user->name,
            'email' => $user->email,
        ]);

        $card = FincodeCard::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_queue_test',
            'fincode_card_id' => 'card_queue_test',
            'brand' => 'Visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'holder_name' => 'TEST USER',
            'is_default' => true,
        ]);

        event(new CardDeleted($card, $user, $card->toArray(), []));

        Queue::assertPushedOn('audit', \Illuminate\Events\CallQueuedListener::class, function ($job) {
            return $job->class === AuditEventListener::class;
        });
    }

    public function test_ip_address_and_user_agent_are_passed_to_audit_log(): void
    {
        $user = User::factory()->create();

        FincodeCustomer::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_ip_test',
            'name' => $user->name,
            'email' => $user->email,
        ]);

        $card = FincodeCard::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_ip_test',
            'fincode_card_id' => 'card_ip_test',
            'brand' => 'Visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'holder_name' => 'TEST USER',
            'is_default' => true,
        ]);

        $this->listener->handleCardDeleted(new CardDeleted(
            $card,
            $user,
            $card->toArray(),
            [],
            [],
            '192.168.1.1',
            'TestBrowser/1.0'
        ));

        $auditLog = $user->auditLogs()->latest('id')->first();
        $this->assertSame('192.168.1.1', $auditLog->ip_address);
        $this->assertSame('TestBrowser/1.0', $auditLog->user_agent);
    }
}
