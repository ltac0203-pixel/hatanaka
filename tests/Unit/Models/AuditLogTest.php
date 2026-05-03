<?php

namespace Tests\Unit\Models;

use App\Models\AuditLog;
use App\Models\FincodeCard;
use App\Models\FincodeCustomer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 2, 14, 10, 0, 0));

        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_user_relationship(): void
    {
        // user_id は $fillable から外しているため、直接代入してから save() する。
        $auditLog = new AuditLog([
            'event' => 'card.created',
            'auditable_type' => User::class,
            'auditable_id' => $this->user->id,
            'old_values' => null,
            'new_values' => ['name' => 'Test'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);
        $auditLog->user_id = $this->user->id;
        $auditLog->save();

        $user = $auditLog->user()->firstOrFail();

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame($this->user->id, $user->id);
    }

    public function test_auditable_polymorphic_relationship(): void
    {
        $fincodeCustomer = FincodeCustomer::create([
            'user_id' => $this->user->id,
            'fincode_customer_id' => 'cus_audit_test',
            'name' => $this->user->name,
            'email' => $this->user->email,
        ]);

        $card = FincodeCard::create([
            'user_id' => $this->user->id,
            'fincode_customer_id' => 'cus_audit_test',
            'fincode_card_id' => 'card_audit_test',
            'brand' => 'Visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'holder_name' => 'TEST USER',
            'is_default' => true,
        ]);

        $auditLog = new AuditLog([
            'event' => 'card.created',
            'auditable_type' => FincodeCard::class,
            'auditable_id' => $card->id,
            'old_values' => null,
            'new_values' => ['brand' => 'Visa', 'last4' => '4242'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);
        $auditLog->user_id = $this->user->id;
        $auditLog->save();

        $auditable = $auditLog->auditable()->firstOrFail();

        $this->assertInstanceOf(FincodeCard::class, $auditable);
        $this->assertSame($card->id, $auditable->id);
    }

    public function test_values_are_cast_to_arrays(): void
    {
        $oldValues = ['status' => 'active'];
        $newValues = ['status' => 'canceled'];
        $metadata = ['reason' => 'user_request', 'ip' => '192.168.1.1'];

        $auditLog = new AuditLog([
            'event' => 'subscription.canceled',
            'auditable_type' => User::class,
            'auditable_id' => $this->user->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => $metadata,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);
        $auditLog->user_id = $this->user->id;
        $auditLog->save();

        $auditLog->refresh();

        $this->assertIsArray($auditLog->old_values);
        $this->assertSame('active', $auditLog->old_values['status']);

        $this->assertIsArray($auditLog->new_values);
        $this->assertSame('canceled', $auditLog->new_values['status']);

        $this->assertIsArray($auditLog->metadata);
        $this->assertSame('user_request', $auditLog->metadata['reason']);
    }
}
