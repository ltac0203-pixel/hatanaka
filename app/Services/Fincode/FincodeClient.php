<?php

declare(strict_types=1);

namespace App\Services\Fincode;

use App\Exceptions\CircuitBreakerOpenException;
use App\Exceptions\FincodeApiException;
use App\Exceptions\FincodeRateLimitException;
use App\Exceptions\FincodeServerException;
use App\Exceptions\FincodeTimeoutException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;

class FincodeClient
{
    protected ClientInterface $client;

    protected string $apiKey;

    /**
     * API 仕様差分で壊れないよう利用バージョンを明示する。
     */
    protected string $apiVersion;

    protected CircuitBreaker $circuitBreaker;

    private ?string $lastIdempotencyKey = null;

    private ?string $tenantShopId;

    private bool $logRequests;

    private bool $logResponses;

    private bool $retryEnabled;

    private int $retryMaxAttempts;

    private int $retryBaseDelayMs;

    private int $retryMaxDelayMs;

    private const SENSITIVE_KEYS = ['card_no', 'cvc', 'password', 'api_key', 'secret', 'token', 'holder_name', 'authorization'];

    public function __construct(ClientInterface $client, CircuitBreaker $circuitBreaker)
    {
        $this->apiKey = FincodeApiConfigValidator::requireApiKey(config('fincode.api_key'));
        $this->apiVersion = (string) config('fincode.api_version', '');
        $this->client = $client;
        $this->circuitBreaker = $circuitBreaker;
        $this->tenantShopId = config('fincode.tenant_shop_id') ?: null;
        $this->logRequests = (bool) config('fincode.log_requests', false);
        $this->logResponses = (bool) config('fincode.log_responses', false);
        $this->retryEnabled = (bool) config('fincode.retry.enabled', true);
        $this->retryMaxAttempts = (int) config('fincode.retry.max_attempts', 3);
        $this->retryBaseDelayMs = (int) config('fincode.retry.base_delay_ms', 200);
        $this->retryMaxDelayMs = (int) config('fincode.retry.max_delay_ms', 5000);
    }

    /**
     * 読み取り系 API を例外変換付きで呼び出せるようにする。
     *
     * @throws FincodeApiException
     */
    public function get(string $uri, array $query = []): array
    {
        return $this->request('GET', $uri, [
            'query' => $query,
        ]);
    }

    /**
     * 作成系 API を冪等キー付きで安全に呼び出せるようにする。
     *
     * @throws FincodeApiException
     */
    public function post(string $uri, array $data = [], ?string $idempotentKey = null): array
    {
        return $this->requestWithIdempotency('POST', $uri, $data, $idempotentKey);
    }

    /**
     * 更新系 API を冪等キー付きで安全に呼び出せるようにする。
     *
     * @throws FincodeApiException
     */
    public function put(string $uri, array $data = [], ?string $idempotentKey = null): array
    {
        return $this->requestWithIdempotency('PUT', $uri, $data, $idempotentKey);
    }

    public function getLastIdempotencyKey(): ?string
    {
        return $this->lastIdempotencyKey;
    }

    private function requestWithIdempotency(string $method, string $uri, array $data = [], ?string $idempotentKey = null): array
    {
        $idempotentKey ??= (string) Str::uuid();
        $this->lastIdempotencyKey = $idempotentKey;

        return $this->request($method, $uri, [
            'json' => $data,
            'headers' => ['Idempotency-Key' => $idempotentKey],
        ]);
    }

    /**
     * 削除系 API も同じ例外処理へ寄せて扱えるようにする。
     *
     * @throws FincodeApiException
     */
    public function delete(string $uri): array
    {
        return $this->request('DELETE', $uri);
    }

    protected function request(string $method, string $uri, array $options = []): array
    {
        if ($this->circuitBreaker->isOpen()) {
            throw new CircuitBreakerOpenException($this->circuitBreaker->getRemainingSeconds());
        }

        // 呼び出し側がヘッダー未指定でも共通認証ヘッダーを安全に追加できるようにする。
        $options['headers'] = $options['headers'] ?? [];

        // すべての API 呼び出しで認証方式と利用バージョンを統一する。
        $options['headers']['Authorization'] = 'Bearer '.$this->apiKey;
        $options['headers']['Api-Version'] = $this->apiVersion;

        if (! empty($this->tenantShopId)) {
            $options['headers']['Tenant-Shop-Id'] = $this->tenantShopId;
        }

        // 障害調査をしやすくしつつ機微情報はマスクして残す。
        if ($this->logRequests) {
            Log::info('Fincode API Request', [
                'method' => $method,
                'uri' => $uri,
                'options' => $this->maskSensitiveData($options),
            ]);
        }

        $maxAttempts = $this->retryEnabled ? $this->retryMaxAttempts : 1;
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $response = $this->client->request($method, $uri, $options);
                $body = json_decode($response->getBody()->getContents(), true);

                // 外部応答の差分を後追いできるよう成功レスポンスも必要時だけ残す。
                if ($this->logResponses) {
                    Log::info('Fincode API Response', [
                        'status_code' => $response->getStatusCode(),
                        'body' => $this->maskSensitiveData($body),
                    ]);
                }

                $this->circuitBreaker->recordSuccess();

                return $body ?? [];
            } catch (GuzzleException $e) {
                $lastException = $e;
                $statusCode = 0;
                $errorBody = [];

                if ($e instanceof ConnectException) {
                    $this->circuitBreaker->recordFailure();
                    if ($this->shouldRetry($attempt, $maxAttempts, 0)) {
                        $this->applyDelay($attempt, null);

                        continue;
                    }
                    $this->logError($method, $uri, 0, $e, []);

                    throw new FincodeTimeoutException($e->getMessage());
                }

                if ($e instanceof RequestException && $e->hasResponse()) {
                    $statusCode = $e->getResponse()->getStatusCode();
                    $errorBody = json_decode($e->getResponse()->getBody()->getContents(), true) ?? [];

                    if ($statusCode >= 500) {
                        $this->circuitBreaker->recordFailure();
                    }

                    if ($this->shouldRetry($attempt, $maxAttempts, $statusCode)) {
                        $retryAfter = $statusCode === 429
                            ? $this->parseRetryAfterHeader($e->getResponse())
                            : null;
                        $this->applyDelay($attempt, $retryAfter);

                        continue;
                    }
                }

                $this->logError($method, $uri, $statusCode, $e, $errorBody);
                $this->classifyAndThrow($e, $statusCode, $errorBody);
            }
        }

        if ($lastException !== null) {
            $statusCode = 0;
            $errorBody = [];

            if ($lastException instanceof RequestException && $lastException->hasResponse()) {
                $statusCode = $lastException->getResponse()->getStatusCode();
                $errorBody = json_decode($lastException->getResponse()->getBody()->getContents(), true) ?? [];
            }

            $this->logError($method, $uri, $statusCode, $lastException, $errorBody);
            $this->classifyAndThrow($lastException, $statusCode, $errorBody);
        }

        // @codeCoverageIgnoreStart
        throw new FincodeApiException('リクエスト処理中に予期しないエラーが発生しました。');
        // @codeCoverageIgnoreEnd
    }

    private function shouldRetry(int $attempt, int $maxAttempts, int $statusCode): bool
    {
        if ($attempt >= $maxAttempts) {
            return false;
        }

        return $statusCode === 0 || $statusCode === 429 || $statusCode >= 500;
    }

    private function calculateDelay(int $attempt, ?int $retryAfterSeconds): int
    {
        if ($retryAfterSeconds !== null) {
            return $retryAfterSeconds * 1000;
        }

        $baseDelay = $this->retryBaseDelayMs;
        $maxDelay = $this->retryMaxDelayMs;

        $delay = $baseDelay * (2 ** ($attempt - 1));

        $jitter = $delay * 0.1;
        $delay = $delay + mt_rand((int) (-$jitter), (int) $jitter);

        return min((int) $delay, $maxDelay);
    }

    private function applyDelay(int $attempt, ?int $retryAfterSeconds): void
    {
        $delayMs = $this->calculateDelay($attempt, $retryAfterSeconds);
        usleep($delayMs * 1000);
    }

    private function parseRetryAfterHeader(ResponseInterface $response): ?int
    {
        $retryAfter = $response->getHeaderLine('Retry-After');

        if ($retryAfter === '') {
            return null;
        }

        if (is_numeric($retryAfter)) {
            return (int) $retryAfter;
        }

        $timestamp = strtotime($retryAfter);

        if ($timestamp === false) {
            return null;
        }

        return max(0, $timestamp - time());
    }

    /**
     * @throws FincodeApiException
     */
    private function classifyAndThrow(GuzzleException $e, int $statusCode, array $errorBody): never
    {
        if ($e instanceof ConnectException) {
            throw new FincodeTimeoutException($e->getMessage());
        }

        if ($statusCode === 429) {
            $retryAfter = null;
            if ($e instanceof RequestException && $e->hasResponse()) {
                $retryAfter = $this->parseRetryAfterHeader($e->getResponse());
            }

            throw new FincodeRateLimitException($e->getMessage(), $statusCode, $errorBody, $retryAfter);
        }

        if ($statusCode >= 500) {
            throw new FincodeServerException($e->getMessage(), $statusCode, $errorBody);
        }

        throw new FincodeApiException($e->getMessage(), $statusCode, $errorBody);
    }

    private function logError(string $method, string $uri, int $statusCode, \Throwable $e, array $errorBody): void
    {
        Log::error('Fincode API Error', [
            'method' => $method,
            'uri' => $uri,
            'status_code' => $statusCode,
            'error' => $e->getMessage(),
            'error_body' => $this->maskSensitiveData($errorBody),
        ]);
    }

    protected function maskSensitiveData($data)
    {
        if (! is_array($data)) {
            return $data;
        }

        foreach ($data as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
                $data[$key] = '***MASKED***';
            } elseif (is_array($value)) {
                $data[$key] = $this->maskSensitiveData($value);
            }
        }

        return $data;
    }
}
