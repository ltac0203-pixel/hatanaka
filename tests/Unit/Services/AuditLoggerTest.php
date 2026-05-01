<?php

namespace Tests\Unit\Services;

use App\Models\AuditLog;
use App\Models\FincodeCard;
use App\Models\FincodeCustomer;
use App\Models\Subscription;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\RequestContextResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLoggerTest extends TestCase
{
    use RefreshDatabase;

    private AuditLogger $auditLogger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->auditLogger = new AuditLogger(new RequestContextResolver($this->app));
    }

    public function test_log_creates_audit_log(): void
    {
        $user = User::factory()->create();

        FincodeCustomer::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_test',
            'name' => $user->name,
            'email' => $user->email,
        ]);

        $card = FincodeCard::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_test',
            'fincode_card_id' => 'card_test',
            'brand' => 'Visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'holder_name' => 'TEST',
            'is_default' => true,
        ]);

        $log = $this->auditLogger->log('card.created', $card, $user);

        $this->assertInstanceOf(AuditLog::class, $log);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'card.created',
            'auditable_type' => FincodeCard::class,
            'auditable_id' => $card->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_log_with_all_fields(): void
    {
        $user = User::factory()->create();

        $customer = FincodeCustomer::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_audit_test',
            'name' => $user->name,
            'email' => $user->email,
        ]);

        FincodeCard::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_audit_test',
            'fincode_card_id' => 'card_test',
            'brand' => 'Visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'holder_name' => 'TEST',
            'is_default' => true,
        ]);

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'fincode_plan_id' => 'pl_test',
            'plan_name' => 'Test Plan',
            'plan_amount' => 1000,
            'plan_interval' => 'monthly',
            'plan_interval_count' => 1,
            'plan_snapshot' => ['name' => 'Test Plan'],
            'fincode_subscription_id' => 'sub_test',
            'fincode_customer_id' => 'cus_audit_test',
            'fincode_card_id' => 'card_test',
            'status' => 'active',
            'start_date' => now(),
        ]);

        $oldValues = ['status' => 'active'];
        $newValues = ['status' => 'canceled'];
        $metadata = ['reason' => 'user_requested'];

        $log = $this->auditLogger->log(
            'subscription.canceled',
            $subscription,
            $user,
            $oldValues,
            $newValues,
            $metadata
        );

        $this->assertSame('subscription.canceled', $log->event);
        $this->assertSame(Subscription::class, $log->auditable_type);
        $this->assertSame($subscription->id, $log->auditable_id);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame($oldValues, $log->old_values);
        $this->assertSame($newValues, $log->new_values);
        $this->assertSame($metadata, $log->metadata);
    }

    public function test_log_without_user(): void
    {
        $user = User::factory()->create();

        FincodeCustomer::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_test',
            'name' => $user->name,
            'email' => $user->email,
        ]);

        $card = FincodeCard::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_test',
            'fincode_card_id' => 'card_null_user',
            'brand' => 'Mastercard',
            'last4' => '5555',
            'exp_month' => 6,
            'exp_year' => 2028,
            'holder_name' => 'TEST',
            'is_default' => false,
        ]);

        $log = $this->auditLogger->log('card.created', $card, null);

        $this->assertNull($log->user_id);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'card.created',
            'user_id' => null,
            'auditable_id' => $card->id,
        ]);
    }

    public function test_log_ignores_synthetic_console_request_context(): void
    {
        $user = User::factory()->create();

        FincodeCustomer::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_console_test',
            'name' => $user->name,
            'email' => $user->email,
        ]);

        $card = FincodeCard::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_console_test',
            'fincode_card_id' => 'card_console_test',
            'brand' => 'Visa',
            'last4' => '1111',
            'exp_month' => 5,
            'exp_year' => 2031,
            'holder_name' => 'TEST',
            'is_default' => true,
        ]);

        $log = $this->auditLogger->log('card.created', $card, $user);

        $this->assertNull($log->ip_address);
        $this->assertNull($log->user_agent);
    }

    public function test_log_captures_ip_and_user_agent(): void
    {
        $user = User::factory()->create();

        FincodeCustomer::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_test',
            'name' => $user->name,
            'email' => $user->email,
        ]);

        $card = FincodeCard::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_test',
            'fincode_card_id' => 'card_ip_test',
            'brand' => 'Visa',
            'last4' => '1234',
            'exp_month' => 3,
            'exp_year' => 2029,
            'holder_name' => 'TEST',
            'is_default' => true,
        ]);

        // 実リクエスト由来の値を拾えているか確かめるため、現在のリクエストへ直接注入する。
        $request = $this->app['request'];
        $request->server->set('REMOTE_ADDR', '192.168.1.100');
        $request->headers->set('User-Agent', 'PHPUnit Test Agent');

        $log = $this->auditLogger->log('card.created', $card, $user);

        $this->assertSame('192.168.1.100', $log->ip_address);
        $this->assertSame('PHPUnit Test Agent', $log->user_agent);
    }
}
