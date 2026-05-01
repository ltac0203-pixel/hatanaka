<?php

namespace Tests\Unit\Services;

use App\Exceptions\CircuitBreakerOpenException;
use App\Exceptions\FincodeApiException;
use App\Exceptions\FincodeRateLimitException;
use App\Exceptions\FincodeServerException;
use App\Exceptions\FincodeTimeoutException;
use App\Services\Fincode\CircuitBreaker;
use App\Services\Fincode\FincodeClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Mockery;
use Tests\TestCase;

class FincodeClientRetryTest extends TestCase
{
    private FincodeClient $service;

    private MockHandler $mockHandler;

    private array $history = [];

    private $mockCircuitBreaker;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'fincode.api_key' => 'test_api_key',
            'fincode.base_url' => 'https://api.test.fincode.jp',
            'fincode.api_version' => '2024-01-01',
            'fincode.tenant_shop_id' => null,
            'fincode.log_requests' => false,
            'fincode.log_responses' => false,
            'fincode.retry.enabled' => true,
            'fincode.retry.max_attempts' => 3,
            'fincode.retry.base_delay_ms' => 0,
            'fincode.retry.max_delay_ms' => 0,
            'fincode.circuit_breaker.enabled' => false,
        ]);

        $this->mockHandler = new MockHandler;
        $this->mockCircuitBreaker = $this->makeCircuitBreakerMock();
        $this->rebuildService();
    }

    private function rebuildService(?CircuitBreaker $circuitBreaker = null): void
    {
        $handlerStack = HandlerStack::create($this->mockHandler);
        $handlerStack->push(Middleware::history($this->history));
        $client = new Client(['handler' => $handlerStack]);

        $this->service = new FincodeClient($client, $circuitBreaker ?? $this->mockCircuitBreaker);
    }

    private function makeCircuitBreakerMock(): CircuitBreaker
    {
        $mockCircuitBreaker = Mockery::mock(CircuitBreaker::class);
        $mockCircuitBreaker->shouldReceive('isOpen')->andReturn(false)->byDefault();
        $mockCircuitBreaker->shouldReceive('isHalfOpen')->andReturn(false)->byDefault();
        $mockCircuitBreaker->shouldReceive('recordSuccess')->byDefault();
        $mockCircuitBreaker->shouldReceive('recordFailure')->byDefault();

        return $mockCircuitBreaker;
    }

    public function test_500_error_is_retried_max_attempts_times(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->mockHandler->append(new Response(500, [], json_encode(['error' => 'Server Error'])));
        }

        $this->expectException(FincodeServerException::class);

        try {
            $this->service->get('/v1/plans');
        } finally {
            $this->assertCount(3, $this->history);
        }
    }

    public function test_429_error_is_retried(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->mockHandler->append(new Response(429, [], json_encode(['error' => 'Rate limit'])));
        }

        $this->expectException(FincodeRateLimitException::class);

        try {
            $this->service->get('/v1/plans');
        } finally {
            $this->assertCount(3, $this->history);
        }
    }

    public function test_422_error_is_not_retried(): void
    {
        $this->mockHandler->append(new Response(422, [], json_encode(['error' => 'Validation failed'])));

        $this->expectException(FincodeApiException::class);

        try {
            $this->service->get('/v1/plans');
        } finally {
            $this->assertCount(1, $this->history);
        }
    }

    public function test_connect_exception_is_retried(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->mockHandler->append(
                new ConnectException('Connection timed out', new Request('GET', '/v1/plans'))
            );
        }

        $this->expectException(FincodeTimeoutException::class);

        try {
            $this->service->get('/v1/plans');
        } finally {
            $this->assertCount(3, $this->history);
        }
    }

    public function test_retry_succeeds_on_third_attempt(): void
    {
        $this->mockHandler->append(new Response(500, [], json_encode(['error' => 'Server Error'])));
        $this->mockHandler->append(new Response(500, [], json_encode(['error' => 'Server Error'])));
        $this->mockHandler->append(new Response(200, [], json_encode(['success' => true])));

        $result = $this->service->get('/v1/plans');

        $this->assertCount(3, $this->history);
        $this->assertSame(['success' => true], $result);
    }

    public function test_retry_disabled_does_not_retry(): void
    {
        config(['fincode.retry.enabled' => false]);

        $this->history = [];
        $this->mockHandler = new MockHandler;
        $this->rebuildService();

        $this->mockHandler->append(new Response(500, [], json_encode(['error' => 'Server Error'])));

        $this->expectException(FincodeServerException::class);

        try {
            $this->service->get('/v1/plans');
        } finally {
            $this->assertCount(1, $this->history);
        }
    }

    public function test_circuit_breaker_open_throws_exception(): void
    {
        $mockCb = Mockery::mock(CircuitBreaker::class);
        $mockCb->shouldReceive('isOpen')->andReturn(true);
        $mockCb->shouldReceive('getRemainingSeconds')->andReturn(15);
        $this->rebuildService($mockCb);

        try {
            $this->service->get('/v1/plans');
            $this->fail('Expected CircuitBreakerOpenException was not thrown');
        } catch (CircuitBreakerOpenException $e) {
            $this->assertSame(15, $e->getRemainingSeconds());
            $this->assertCount(0, $this->history);
        }
    }

    public function test_5xx_error_records_failure_on_circuit_breaker(): void
    {
        $mockCb = Mockery::mock(CircuitBreaker::class);
        $mockCb->shouldReceive('isOpen')->andReturn(false);
        $mockCb->shouldReceive('isHalfOpen')->andReturn(false);
        $mockCb->shouldReceive('recordFailure')->times(3);
        $this->rebuildService($mockCb);

        for ($i = 0; $i < 3; $i++) {
            $this->mockHandler->append(new Response(500, [], json_encode(['error' => 'Server Error'])));
        }

        try {
            $this->service->get('/v1/plans');
        } catch (FincodeServerException $e) {
            // expected
        }

        $mockCb->shouldHaveReceived('recordFailure')->times(3);
    }

    public function test_success_records_success_on_circuit_breaker(): void
    {
        $mockCb = Mockery::mock(CircuitBreaker::class);
        $mockCb->shouldReceive('isOpen')->andReturn(false);
        $mockCb->shouldReceive('isHalfOpen')->andReturn(false);
        $mockCb->shouldReceive('recordSuccess')->once();
        $this->rebuildService($mockCb);

        $this->mockHandler->append(new Response(200, [], json_encode(['success' => true])));

        $this->service->get('/v1/plans');

        $mockCb->shouldHaveReceived('recordSuccess')->once();
    }

    public function test_4xx_error_does_not_record_failure_on_circuit_breaker(): void
    {
        $mockCb = Mockery::mock(CircuitBreaker::class);
        $mockCb->shouldReceive('isOpen')->andReturn(false);
        $mockCb->shouldReceive('isHalfOpen')->andReturn(false);
        $this->rebuildService($mockCb);

        $this->mockHandler->append(new Response(422, [], json_encode(['error' => 'Validation failed'])));

        try {
            $this->service->get('/v1/plans');
        } catch (FincodeApiException $e) {
            // expected
        }

        $mockCb->shouldNotHaveReceived('recordFailure');
    }
}
