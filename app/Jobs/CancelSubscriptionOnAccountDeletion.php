<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CancelSubscriptionOnAccountDeletion implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public int $timeout = 60;

    public bool $deleteWhenMissingModels = true;

    public function __construct(
        private Subscription $subscription,
        private int $userId,
    ) {}

    public function handle(SubscriptionManager $subscriptionManager): void
    {
        if ($this->subscription->isCanceled()) {
            return;
        }

        $actor = User::find($this->userId);

        if ($actor === null) {
            Log::warning('ユーザーが見つかりません。アカウント削除済みの可能性があります。', [
                'user_id' => $this->userId,
                'subscription_id' => $this->subscription->id,
            ]);
        }

        $subscriptionManager->cancel($this->subscription, $actor);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Failed to cancel subscription on account deletion', [
            'subscription_id' => $this->subscription->id,
            'user_id' => $this->userId,
            'error' => $e->getMessage(),
        ]);
    }
}
