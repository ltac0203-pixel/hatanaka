<?php

declare(strict_types=1);

namespace App\Services\Fincode;

use Illuminate\Support\Facades\Cache;

class CircuitBreaker
{
    private const CACHE_PREFIX = 'fincode_circuit_breaker:';

    private const MIN_CACHE_TTL = 300;

    private int $failureThreshold;

    private int $recoveryTimeout;

    private bool $enabled;

    public function __construct()
    {
        $this->enabled = config('fincode.circuit_breaker.enabled', true);
        $this->failureThreshold = config('fincode.circuit_breaker.failure_threshold', 5);
        $this->recoveryTimeout = config('fincode.circuit_breaker.recovery_timeout', 30);
    }

    public function isOpen(): bool
    {
        if (! $this->enabled) {
            return false;
        }

        $state = $this->getState();

        if ($state !== 'open') {
            return false;
        }

        if ($this->recoveryTimeoutElapsed()) {
            $this->transitionTo('half-open');

            return false;
        }

        return true;
    }

    public function isHalfOpen(): bool
    {
        return $this->enabled && $this->getState() === 'half-open';
    }

    public function getRemainingSeconds(): int
    {
        $openedAt = (int) Cache::get(self::CACHE_PREFIX.'opened_at', 0);

        if ($openedAt === 0) {
            return 0;
        }

        $remaining = $this->recoveryTimeout - (time() - $openedAt);

        return max(0, $remaining);
    }

    public function recordSuccess(): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->resetState();
    }

    public function recordFailure(): void
    {
        if (! $this->enabled) {
            return;
        }

        $state = $this->getState();

        if ($state === 'half-open') {
            $this->transitionTo('open');

            return;
        }

        $failures = $this->incrementFailureCount();

        if ($failures >= $this->failureThreshold) {
            $this->transitionTo('open');
        }
    }

    public function getState(): string
    {
        return Cache::get(self::CACHE_PREFIX.'state', 'closed');
    }

    public function getFailureCount(): int
    {
        return (int) Cache::get(self::CACHE_PREFIX.'failure_count', 0);
    }

    public function reset(): void
    {
        $this->resetState();
    }

    private function transitionTo(string $state): void
    {
        $ttl = max($this->recoveryTimeout * 3, self::MIN_CACHE_TTL);

        Cache::put(self::CACHE_PREFIX.'state', $state, $ttl);

        if ($state === 'open') {
            Cache::put(self::CACHE_PREFIX.'opened_at', time(), $ttl);
        }

        if ($state === 'closed' || $state === 'half-open') {
            Cache::forget(self::CACHE_PREFIX.'failure_count');
        }
    }

    private function recoveryTimeoutElapsed(): bool
    {
        $openedAt = (int) Cache::get(self::CACHE_PREFIX.'opened_at', 0);

        return (time() - $openedAt) >= $this->recoveryTimeout;
    }

    private function incrementFailureCount(): int
    {
        $key = self::CACHE_PREFIX.'failure_count';
        $ttl = max($this->recoveryTimeout * 3, self::MIN_CACHE_TTL);

        Cache::add($key, 0, $ttl);

        return (int) Cache::increment($key);
    }

    private function resetState(): void
    {
        Cache::forget(self::CACHE_PREFIX.'state');
        Cache::forget(self::CACHE_PREFIX.'failure_count');
        Cache::forget(self::CACHE_PREFIX.'opened_at');
    }
}
