<?php

namespace Tests\Feature;

use App\Events\CardDeleted;
use App\Events\CardRegistered;
use App\Events\CustomerCreated;
use App\Events\SubscriptionCanceled;
use App\Events\SubscriptionCreated;
use App\Events\SubscriptionStatusChanged;
use App\Listeners\AuditEventListener;
use Tests\TestCase;

class EventDiscoveryTest extends TestCase
{
    public function test_audit_event_listener_is_registered_for_auditable_events(): void
    {
        $listeners = app('events')->getRawListeners();

        $expectedListeners = [
            CardRegistered::class => AuditEventListener::class.'@handleCardRegistered',
            CardDeleted::class => AuditEventListener::class.'@handleCardDeleted',
            SubscriptionCreated::class => AuditEventListener::class.'@handleSubscriptionCreated',
            SubscriptionCanceled::class => AuditEventListener::class.'@handleSubscriptionCanceled',
            SubscriptionStatusChanged::class => AuditEventListener::class.'@handleSubscriptionStatusChanged',
            CustomerCreated::class => AuditEventListener::class.'@handleCustomerCreated',
        ];

        foreach ($expectedListeners as $event => $listener) {
            $this->assertArrayHasKey($event, $listeners);
            $this->assertContains($listener, $listeners[$event]);
        }
    }
}
