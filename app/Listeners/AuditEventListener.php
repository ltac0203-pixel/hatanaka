<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\AbstractAuditableEvent;
use App\Events\CardDeleted;
use App\Events\CardRegistered;
use App\Events\Contracts\AuditableEvent;
use App\Events\CustomerCreated;
use App\Events\SubscriptionCanceled;
use App\Events\SubscriptionCreated;
use App\Events\SubscriptionStatusChanged;
use App\Services\AuditLogger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\Dispatcher;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class AuditEventListener implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    public int $backoff = 30;

    public string $queue = 'audit';

    public function __construct(
        private AuditLogger $auditLogger
    ) {}

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            CardRegistered::class => 'handleCardRegistered',
            CardDeleted::class => 'handleCardDeleted',
            SubscriptionCreated::class => 'handleSubscriptionCreated',
            SubscriptionCanceled::class => 'handleSubscriptionCanceled',
            SubscriptionStatusChanged::class => 'handleSubscriptionStatusChanged',
            CustomerCreated::class => 'handleCustomerCreated',
        ];
    }

    public function handleCardRegistered(CardRegistered $event): void
    {
        $this->record($event);
    }

    public function handleCardDeleted(CardDeleted $event): void
    {
        $this->record($event);
    }

    public function handleSubscriptionCreated(SubscriptionCreated $event): void
    {
        $this->record($event);
    }

    public function handleSubscriptionCanceled(SubscriptionCanceled $event): void
    {
        $this->record($event);
    }

    public function handleSubscriptionStatusChanged(SubscriptionStatusChanged $event): void
    {
        $this->record($event);
    }

    public function handleCustomerCreated(CustomerCreated $event): void
    {
        $this->record($event);
    }

    private function record(AuditableEvent $event): void
    {
        $this->auditLogger->log(
            $event->auditEvent(),
            $event->auditable(),
            $event->actor(),
            $event->oldValues(),
            $event->newValues(),
            $event->metadata(),
            $event instanceof AbstractAuditableEvent ? $event->ipAddress : null,
            $event instanceof AbstractAuditableEvent ? $event->userAgent : null,
        );
    }

    public function failed(AuditableEvent $event, \Throwable $e): void
    {
        Log::error('Failed to record audit event', [
            'event' => $event->auditEvent(),
            'auditable_type' => get_class($event->auditable()),
            'auditable_id' => $event->auditable()->id,
            'error' => $e->getMessage(),
        ]);
    }
}
