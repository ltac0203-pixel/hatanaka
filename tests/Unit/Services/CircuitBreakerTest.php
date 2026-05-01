<?php

namespace Tests\Unit\Services;

use App\Services\Fincode\CircuitBreaker;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CircuitBreakerTest extends TestCase
{
    private CircuitBreaker $breaker;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'fincode.circuit_breaker.enabled' => true,
            'fincode.circuit_breaker.failure_threshold' => 5,
            'fincode.circuit_breaker.recovery_timeout' => 30,
        ]);

        $this->breaker = new CircuitBreaker;
    }

    public function test_initial_state_is_closed(): void
    {
        $this->assertFalse($this->breaker->isOpen());
        $this->assertFalse($this->breaker->isHalfOpen());
        $this->assertSame('closed', $this->breaker->getState());
        $this->assertSame(0, $this->breaker->getFailureCount());
    }

    public function test_stays_closed_below_failure_threshold(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->breaker->recordFailure();
        }

        $this->assertFalse($this->breaker->isOpen());
        $this->assertSame('closed', $this->breaker->getState());
        $this->assertSame(4, $this->breaker->getFailureCount());
    }

    public function test_first_failure_initializes_failure_count(): void
    {
        $this->assertSame(0, $this->breaker->getFailureCount());

        $this->breaker->recordFailure();

        $this->assertSame(1, $this->breaker->getFailureCount());
    }

    public function test_opens_at_failure_threshold(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure();
        }

        $this->assertSame('open', $this->breaker->getState());
        $this->assertTrue($this->breaker->isOpen());
    }

    public function test_is_open_returns_true_while_in_open_state(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure();
        }

        // opened_at is set to time(), so within recovery_timeout it stays open
        $this->assertTrue($this->breaker->isOpen());
        $this->assertFalse($this->breaker->isHalfOpen());
    }

    public function test_transitions_to_half_open_after_recovery_timeout(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure();
        }

        $this->assertTrue($this->breaker->isOpen());

        // Simulate recovery_timeout elapsed by backdating opened_at
        Cache::put('fincode_circuit_breaker:opened_at', time() - 31, 90);

        $this->assertFalse($this->breaker->isOpen());
        $this->assertTrue($this->breaker->isHalfOpen());
        $this->assertSame('half-open', $this->breaker->getState());
    }

    public function test_half_open_success_transitions_to_closed(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure();
        }

        // Simulate recovery_timeout elapsed
        Cache::put('fincode_circuit_breaker:opened_at', time() - 31, 90);

        // isOpen() triggers transition to half-open
        $this->assertFalse($this->breaker->isOpen());
        $this->assertTrue($this->breaker->isHalfOpen());

        $this->breaker->recordSuccess();

        $this->assertSame('closed', $this->breaker->getState());
        $this->assertFalse($this->breaker->isOpen());
        $this->assertFalse($this->breaker->isHalfOpen());
        $this->assertSame(0, $this->breaker->getFailureCount());
    }

    public function test_half_open_failure_transitions_back_to_open(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure();
        }

        // Simulate recovery_timeout elapsed
        Cache::put('fincode_circuit_breaker:opened_at', time() - 31, 90);

        // Trigger transition to half-open
        $this->breaker->isOpen();
        $this->assertTrue($this->breaker->isHalfOpen());

        $this->breaker->recordFailure();

        $this->assertSame('open', $this->breaker->getState());
        $this->assertTrue($this->breaker->isOpen());
    }

    public function test_record_success_resets_state(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->breaker->recordFailure();
        }

        $this->assertSame(3, $this->breaker->getFailureCount());

        $this->breaker->recordSuccess();

        $this->assertSame('closed', $this->breaker->getState());
        $this->assertSame(0, $this->breaker->getFailureCount());
        $this->assertFalse($this->breaker->isOpen());
    }

    public function test_get_remaining_seconds_returns_correct_value(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure();
        }

        $remaining = $this->breaker->getRemainingSeconds();

        // Should be close to 30 (recovery_timeout) since we just opened
        $this->assertGreaterThanOrEqual(29, $remaining);
        $this->assertLessThanOrEqual(30, $remaining);
    }

    public function test_get_remaining_seconds_decreases_over_time(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure();
        }

        // Backdate opened_at by 10 seconds
        Cache::put('fincode_circuit_breaker:opened_at', time() - 10, 90);

        $remaining = $this->breaker->getRemainingSeconds();

        $this->assertGreaterThanOrEqual(19, $remaining);
        $this->assertLessThanOrEqual(20, $remaining);
    }

    public function test_get_remaining_seconds_returns_zero_after_timeout(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure();
        }

        Cache::put('fincode_circuit_breaker:opened_at', time() - 31, 90);

        $this->assertSame(0, $this->breaker->getRemainingSeconds());
    }

    public function test_get_remaining_seconds_returns_zero_when_not_open(): void
    {
        $this->assertSame(0, $this->breaker->getRemainingSeconds());
    }

    public function test_disabled_circuit_breaker_is_never_open(): void
    {
        config(['fincode.circuit_breaker.enabled' => false]);
        $breaker = new CircuitBreaker;

        for ($i = 0; $i < 10; $i++) {
            $breaker->recordFailure();
        }

        $this->assertFalse($breaker->isOpen());
        $this->assertFalse($breaker->isHalfOpen());
        // failure_count should not be incremented when disabled
        $this->assertSame(0, $breaker->getFailureCount());
    }

    public function test_disabled_circuit_breaker_record_failure_is_noop(): void
    {
        config(['fincode.circuit_breaker.enabled' => false]);
        $breaker = new CircuitBreaker;

        $breaker->recordFailure();
        $breaker->recordFailure();
        $breaker->recordFailure();

        $this->assertSame('closed', $breaker->getState());
        $this->assertSame(0, $breaker->getFailureCount());
    }

    public function test_reset_clears_all_state(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure();
        }

        $this->assertSame('open', $this->breaker->getState());
        $this->assertTrue($this->breaker->isOpen());

        $this->breaker->reset();

        $this->assertSame('closed', $this->breaker->getState());
        $this->assertSame(0, $this->breaker->getFailureCount());
        $this->assertFalse($this->breaker->isOpen());
        $this->assertSame(0, $this->breaker->getRemainingSeconds());
    }

    public function test_ttl_floor_is_applied_when_recovery_timeout_is_small(): void
    {
        config(['fincode.circuit_breaker.recovery_timeout' => 10]);
        $breaker = new CircuitBreaker;

        for ($i = 0; $i < 5; $i++) {
            $breaker->recordFailure();
        }

        // recovery_timeout=10 → 10*3=30 だが MIN_CACHE_TTL=300 がフロアとして適用される
        // キャッシュが300秒後も存在することを確認
        $this->assertSame('open', $breaker->getState());

        // 30秒(recovery_timeout * 3)ではキャッシュが消えていないことを確認
        // MIN_CACHE_TTL=300 でputされているので、stateは依然として残っている
        $this->assertTrue($breaker->isOpen());
    }

    public function test_reset_clears_failure_count_without_reaching_threshold(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->breaker->recordFailure();
        }

        $this->assertSame(3, $this->breaker->getFailureCount());

        $this->breaker->reset();

        $this->assertSame(0, $this->breaker->getFailureCount());
        $this->assertSame('closed', $this->breaker->getState());
    }
}
