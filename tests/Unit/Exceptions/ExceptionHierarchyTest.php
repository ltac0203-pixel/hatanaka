<?php

namespace Tests\Unit\Exceptions;

use App\Exceptions\CardInUseException;
use App\Exceptions\CircuitBreakerOpenException;
use App\Exceptions\ExpiredCardException;
use App\Exceptions\FincodeApiException;
use App\Exceptions\FincodeRateLimitException;
use App\Exceptions\FincodeServerException;
use App\Exceptions\FincodeTimeoutException;
use App\Exceptions\PlanUnavailableException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ExceptionHierarchyTest extends TestCase
{
    // -------------------------------------------------------
    // FincodeRateLimitException
    // -------------------------------------------------------

    public function test_rate_limit_exception_extends_fincode_api_exception(): void
    {
        $e = new FincodeRateLimitException;

        $this->assertInstanceOf(FincodeApiException::class, $e);
    }

    public function test_rate_limit_exception_has_default_message(): void
    {
        $e = new FincodeRateLimitException;

        $this->assertSame('Fincode APIのレート制限に達しました。', $e->getMessage());
    }

    public function test_rate_limit_exception_has_default_status_code_429(): void
    {
        $e = new FincodeRateLimitException;

        $this->assertSame(429, $e->getStatusCode());
    }

    public function test_rate_limit_exception_retry_after_seconds_defaults_to_null(): void
    {
        $e = new FincodeRateLimitException;

        $this->assertNull($e->getRetryAfterSeconds());
    }

    public function test_rate_limit_exception_returns_retry_after_seconds(): void
    {
        $e = new FincodeRateLimitException(retryAfterSeconds: 60);

        $this->assertSame(60, $e->getRetryAfterSeconds());
    }

    // -------------------------------------------------------
    // FincodeServerException
    // -------------------------------------------------------

    public function test_server_exception_extends_fincode_api_exception(): void
    {
        $e = new FincodeServerException;

        $this->assertInstanceOf(FincodeApiException::class, $e);
    }

    public function test_server_exception_has_default_message(): void
    {
        $e = new FincodeServerException;

        $this->assertSame('Fincode APIでサーバーエラーが発生しました。', $e->getMessage());
    }

    public function test_server_exception_has_default_status_code_500(): void
    {
        $e = new FincodeServerException;

        $this->assertSame(500, $e->getStatusCode());
    }

    public function test_server_exception_accepts_custom_status_code(): void
    {
        $e = new FincodeServerException(statusCode: 502);

        $this->assertSame(502, $e->getStatusCode());
    }

    // -------------------------------------------------------
    // FincodeTimeoutException
    // -------------------------------------------------------

    public function test_timeout_exception_extends_fincode_api_exception(): void
    {
        $e = new FincodeTimeoutException;

        $this->assertInstanceOf(FincodeApiException::class, $e);
    }

    public function test_timeout_exception_has_default_message(): void
    {
        $e = new FincodeTimeoutException;

        $this->assertSame('Fincode APIへの接続がタイムアウトしました。', $e->getMessage());
    }

    public function test_timeout_exception_status_code_is_always_zero(): void
    {
        $e = new FincodeTimeoutException;

        $this->assertSame(0, $e->getStatusCode());
    }

    // -------------------------------------------------------
    // CircuitBreakerOpenException
    // -------------------------------------------------------

    public function test_circuit_breaker_exception_extends_fincode_api_exception(): void
    {
        $e = new CircuitBreakerOpenException(30);

        $this->assertInstanceOf(FincodeApiException::class, $e);
    }

    public function test_circuit_breaker_exception_has_default_message(): void
    {
        $e = new CircuitBreakerOpenException(30);

        $this->assertSame('Fincode APIへの接続が一時的に遮断されています。', $e->getMessage());
    }

    public function test_circuit_breaker_exception_returns_remaining_seconds(): void
    {
        $e = new CircuitBreakerOpenException(45);

        $this->assertSame(45, $e->getRemainingSeconds());
    }

    public function test_circuit_breaker_exception_has_status_code_503(): void
    {
        $e = new CircuitBreakerOpenException(10);

        $this->assertSame(503, $e->getStatusCode());
    }

    // -------------------------------------------------------
    // CardInUseException
    // -------------------------------------------------------

    public function test_card_in_use_exception_extends_runtime_exception(): void
    {
        $e = new CardInUseException;

        $this->assertInstanceOf(RuntimeException::class, $e);
    }

    public function test_card_in_use_exception_is_not_fincode_api_exception(): void
    {
        $e = new CardInUseException;

        $this->assertNotInstanceOf(FincodeApiException::class, $e);
    }

    public function test_card_in_use_exception_has_default_message(): void
    {
        $e = new CardInUseException;

        $this->assertSame('アクティブなサブスクリプションで使用中のカードは削除できません。', $e->getMessage());
    }

    // -------------------------------------------------------
    // ExpiredCardException
    // -------------------------------------------------------

    public function test_expired_card_exception_extends_runtime_exception(): void
    {
        $e = new ExpiredCardException;

        $this->assertInstanceOf(RuntimeException::class, $e);
    }

    public function test_expired_card_exception_is_not_fincode_api_exception(): void
    {
        $e = new ExpiredCardException;

        $this->assertNotInstanceOf(FincodeApiException::class, $e);
    }

    public function test_expired_card_exception_has_default_message(): void
    {
        $e = new ExpiredCardException;

        $this->assertSame('このカードは期限切れです。', $e->getMessage());
    }

    // -------------------------------------------------------
    // PlanUnavailableException
    // -------------------------------------------------------

    public function test_plan_unavailable_exception_extends_runtime_exception(): void
    {
        $e = new PlanUnavailableException('plan_123');

        $this->assertInstanceOf(RuntimeException::class, $e);
    }

    public function test_plan_unavailable_exception_is_not_fincode_api_exception(): void
    {
        $e = new PlanUnavailableException('plan_123');

        $this->assertNotInstanceOf(FincodeApiException::class, $e);
    }

    public function test_plan_unavailable_exception_has_default_message(): void
    {
        $e = new PlanUnavailableException('plan_123');

        $this->assertSame('このプランは現在利用できません。', $e->getMessage());
    }

    public function test_plan_unavailable_exception_returns_fincode_plan_id(): void
    {
        $e = new PlanUnavailableException('plan_abc');

        $this->assertSame('plan_abc', $e->getFincodePlanId());
    }
}
