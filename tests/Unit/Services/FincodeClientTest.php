<?php

namespace Tests\Unit\Services;

use App\Exceptions\FincodeApiException;
use App\Exceptions\FincodeRateLimitException;
use App\Exceptions\FincodeServerException;
use App\Exceptions\FincodeTimeoutException;
use App\Services\Fincode\CircuitBreaker;
use App\Services\Fincode\FincodeClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class FincodeClientTest extends TestCase
{
    private FincodeClient $service;

    private MockHandler $mockHandler;

    private array $history = [];

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
            'fincode.retry.enabled' => false,
        ]);

        $this->mockHandler = new MockHandler;
        $handlerStack = HandlerStack::create($this->mockHandler);
        $handlerStack->push(Middleware::history($this->history));
        $client = new Client(['handler' => $handlerStack]);

        $this->service = new FincodeClient($client, $this->makeCircuitBreakerMock());
    }

    private function makeCircuitBreakerMock(): CircuitBreaker
    {
        $mockCircuitBreaker = Mockery::mock(CircuitBreaker::class);
        $mockCircuitBreaker->shouldReceive('isOpen')->andReturn(false);
        $mockCircuitBreaker->shouldReceive('isHalfOpen')->andReturn(false);
        $mockCircuitBreaker->shouldReceive('recordSuccess')->byDefault();
        $mockCircuitBreaker->shouldReceive('recordFailure')->byDefault();

        return $mockCircuitBreaker;
    }

    public function test_get_sends_correct_request(): void
    {
        $this->mockHandler->append(new Response(200, [], json_encode(['success' => true])));

        $result = $this->service->get('/v1/plans', ['status' => 'active']);

        $this->assertCount(1, $this->history);
        $request = $this->history[0]['request'];
        $this->assertSame('GET', $request->getMethod());
        $this->assertStringContainsString('/v1/plans', (string) $request->getUri());
        $this->assertStringContainsString('status=active', $request->getUri()->getQuery());
        $this->assertSame(['success' => true], $result);
    }

    public function test_post_sends_correct_request_with_idempotency_key(): void
    {
        $this->mockHandler->append(new Response(200, [], json_encode(['id' => 'sub_123'])));

        $result = $this->service->post('/v1/subscriptions', ['plan_id' => 'pl_1'], 'my-idempotent-key');

        $this->assertCount(1, $this->history);
        $request = $this->history[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringContainsString('/v1/subscriptions', (string) $request->getUri());
        $this->assertSame('my-idempotent-key', $request->getHeaderLine('Idempotency-Key'));

        $body = json_decode($request->getBody()->getContents(), true);
        $this->assertSame('pl_1', $body['plan_id']);
        $this->assertSame(['id' => 'sub_123'], $result);
    }

    public function test_post_generates_idempotency_key_when_null(): void
    {
        $this->mockHandler->append(new Response(200, [], json_encode([])));

        $this->service->post('/v1/subscriptions', []);

        $request = $this->history[0]['request'];
        $idempotencyKey = $request->getHeaderLine('Idempotency-Key');
        $this->assertNotEmpty($idempotencyKey);
        // 冪等キーが再送防止に使える形式で自動生成されることを保証する。
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $idempotencyKey
        );
    }

    public function test_put_sends_correct_request(): void
    {
        $this->mockHandler->append(new Response(200, [], json_encode(['updated' => true])));

        $result = $this->service->put('/v1/customers/cus_1', ['name' => 'New Name'], 'put-key');

        $request = $this->history[0]['request'];
        $this->assertSame('PUT', $request->getMethod());
        $this->assertStringContainsString('/v1/customers/cus_1', (string) $request->getUri());
        $this->assertSame('put-key', $request->getHeaderLine('Idempotency-Key'));
        $this->assertSame(['updated' => true], $result);
    }

    public function test_delete_sends_correct_request(): void
    {
        $this->mockHandler->append(new Response(200, [], json_encode(['deleted' => true])));

        $result = $this->service->delete('/v1/customers/cus_1/cards/card_1');

        $request = $this->history[0]['request'];
        $this->assertSame('DELETE', $request->getMethod());
        $this->assertStringContainsString('/v1/customers/cus_1/cards/card_1', (string) $request->getUri());
        $this->assertSame(['deleted' => true], $result);
    }

    public function test_request_includes_bearer_auth(): void
    {
        $this->mockHandler->append(new Response(200, [], json_encode([])));

        $this->service->get('/v1/plans');

        $request = $this->history[0]['request'];
        $this->assertSame('Bearer test_api_key', $request->getHeaderLine('Authorization'));
    }

    public function test_request_includes_api_version(): void
    {
        $this->mockHandler->append(new Response(200, [], json_encode([])));

        $this->service->get('/v1/plans');

        $request = $this->history[0]['request'];
        $this->assertSame('2024-01-01', $request->getHeaderLine('Api-Version'));
    }

    public function test_constructor_throws_when_api_key_is_missing(): void
    {
        config(['fincode.api_key' => '   ']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('FINCODE_API_KEY が未設定');

        new FincodeClient(new Client(['handler' => HandlerStack::create(new MockHandler)]), $this->makeCircuitBreakerMock());
    }

    public function test_request_throws_fincode_api_exception_on_error(): void
    {
        $errorResponse = new Response(422, [], json_encode(['error' => 'Validation failed']));
        $this->mockHandler->append(
            new RequestException(
                'Client error',
                new Request('POST', '/v1/subscriptions'),
                $errorResponse
            )
        );

        try {
            $this->service->post('/v1/subscriptions', []);
            $this->fail('Expected FincodeApiException was not thrown');
        } catch (FincodeApiException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertSame(['error' => 'Validation failed'], $e->getErrorBody());
        }
    }

    public function test_mask_sensitive_data(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('maskSensitiveData');
        $method->setAccessible(true);

        $data = [
            'card_no' => '4111111111111111',
            'cvc' => '123',
            'password' => 'secret',
            'api_key' => 'sk_test_key',
            'token' => 'tok_abc',
            'holder_name' => 'JOHN DOE',
            'authorization' => 'Bearer xyz',
            'name' => 'visible_value',
        ];

        $masked = $method->invoke($this->service, $data);

        $this->assertSame('***MASKED***', $masked['card_no']);
        $this->assertSame('***MASKED***', $masked['cvc']);
        $this->assertSame('***MASKED***', $masked['password']);
        $this->assertSame('***MASKED***', $masked['api_key']);
        $this->assertSame('***MASKED***', $masked['token']);
        $this->assertSame('***MASKED***', $masked['holder_name']);
        $this->assertSame('***MASKED***', $masked['authorization']);
        $this->assertSame('visible_value', $masked['name']);
    }

    public function test_mask_sensitive_data_recursive(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('maskSensitiveData');
        $method->setAccessible(true);

        $data = [
            'customer' => [
                'name' => 'John',
                'card' => [
                    'card_no' => '4111111111111111',
                    'cvc' => '123',
                ],
            ],
            'token' => 'tok_secret',
        ];

        $masked = $method->invoke($this->service, $data);

        $this->assertSame('John', $masked['customer']['name']);
        $this->assertSame('***MASKED***', $masked['customer']['card']['card_no']);
        $this->assertSame('***MASKED***', $masked['customer']['card']['cvc']);
        $this->assertSame('***MASKED***', $masked['token']);
    }

    public function test_mask_sensitive_data_with_non_array(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('maskSensitiveData');
        $method->setAccessible(true);

        $result = $method->invoke($this->service, 'just a string');
        $this->assertSame('just a string', $result);
    }

    public function test_get_last_idempotency_key_returns_auto_generated_key(): void
    {
        $this->mockHandler->append(new Response(200, [], json_encode([])));

        $this->service->post('/v1/subscriptions', []);

        $key = $this->service->getLastIdempotencyKey();
        $this->assertNotNull($key);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $key
        );
    }

    public function test_get_last_idempotency_key_returns_provided_key(): void
    {
        $this->mockHandler->append(new Response(200, [], json_encode([])));

        $this->service->post('/v1/subscriptions', [], 'my-custom-key');

        $this->assertSame('my-custom-key', $this->service->getLastIdempotencyKey());
    }

    public function test_get_last_idempotency_key_is_null_initially(): void
    {
        $this->assertNull($this->service->getLastIdempotencyKey());
    }

    public function test_mask_sensitive_data_with_integer_keys(): void
    {
        $reflection = new \ReflectionClass($this->service);
        $method = $reflection->getMethod('maskSensitiveData');
        $method->setAccessible(true);

        $data = ['value1', 'value2', ['card_no' => '4111111111111111']];

        $masked = $method->invoke($this->service, $data);

        $this->assertSame('value1', $masked[0]);
        $this->assertSame('value2', $masked[1]);
        $this->assertSame('***MASKED***', $masked[2]['card_no']);
    }

    public function test_request_throws_rate_limit_exception_on_429(): void
    {
        $errorResponse = new Response(429, ['Retry-After' => '30'], json_encode(['error' => 'rate_limited']));
        $this->mockHandler->append(
            new RequestException('Too Many Requests', new Request('GET', '/v1/plans'), $errorResponse)
        );

        $this->expectException(FincodeRateLimitException::class);
        $this->service->get('/v1/plans');
    }

    public function test_request_throws_server_exception_on_500(): void
    {
        $errorResponse = new Response(500, [], json_encode(['error' => 'internal']));
        $this->mockHandler->append(
            new RequestException('Server Error', new Request('GET', '/v1/plans'), $errorResponse)
        );

        $this->expectException(FincodeServerException::class);
        $this->service->get('/v1/plans');
    }

    public function test_request_throws_timeout_exception_on_connect_failure(): void
    {
        $this->mockHandler->append(
            new ConnectException('Connection timed out', new Request('GET', '/v1/plans'))
        );

        $this->expectException(FincodeTimeoutException::class);
        $this->service->get('/v1/plans');
    }
}
