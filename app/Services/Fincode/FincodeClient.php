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
     * Fincode のサブスクリプション削除のように pay_type を query で要求する API があるため、
     * クエリ引数を受け取れる形に揃える。
     *
     * @throws FincodeApiException
     */
    public function delete(string $uri, array $query = []): array
    {
        return $this->request('DELETE', $uri, [
            'query' => $query,
        ]);
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
        $maxDelay = $this->retryMaxDelayMs;

        if ($retryAfterSeconds !== null) {
            // サーバー側 Retry-After を信用しすぎるとワーカーが長時間スリープして DoS になるため、
            // 設定された retry.max_delay_ms で上限クリップする。
            return min($retryAfterSeconds * 1000, $maxDelay);
        }

        $baseDelay = $this->retryBaseDelayMs;

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

        // 上流からの Retry-After は秒数 / HTTP-date 表現のいずれもあり得るが、
        // 上限を retryMaxDelayMs (秒換算) に強制クリップしてワーカー長期占有を防ぐ。
        $maxSeconds = max(1, (int) ceil($this->retryMaxDelayMs / 1000));

        if (is_numeric($retryAfter)) {
            return min(max(0, (int) $retryAfter), $maxSeconds);
        }

        $timestamp = strtotime($retryAfter);

        if ($timestamp === false) {
            return null;
        }

        return min(max(0, $timestamp - time()), $maxSeconds);
    }

    /**
     * @throws FincodeApiException
     */
    private function classifyAndThrow(GuzzleException $e, int $statusCode, array $errorBody): never
    {
        // Guzzle 例外メッセージは HTTP レスポンスボディを文字列で含むため、
        // ログ・例外ハンドラ経由のセンシティブ情報漏洩を防ぐためクラス名と HTTP メタのみへ正規化する。
        $sanitized = $this->sanitizeExceptionMessage($e, $statusCode);

        if ($e instanceof ConnectException) {
            throw new FincodeTimeoutException($sanitized);
        }

        if ($statusCode === 429) {
            $retryAfter = null;
            if ($e instanceof RequestException && $e->hasResponse()) {
                $retryAfter = $this->parseRetryAfterHeader($e->getResponse());
            }

            throw new FincodeRateLimitException($sanitized, $statusCode, $errorBody, $retryAfter);
        }

        if ($statusCode >= 500) {
            throw new FincodeServerException($sanitized, $statusCode, $errorBody);
        }

        throw new FincodeApiException($sanitized, $statusCode, $errorBody);
    }

    private function sanitizeExceptionMessage(\Throwable $e, int $statusCode): string
    {
        if ($e instanceof RequestException) {
            $request = $e->getRequest();

            return sprintf(
                '%s %s %s failed with status %d',
                $e::class,
                $request->getMethod(),
                (string) $request->getUri()->withUserInfo(''),
                $statusCode
            );
        }

        return $e::class;
    }

    private function logError(string $method, string $uri, int $statusCode, \Throwable $e, array $errorBody): void
    {
        // Guzzle の RequestException::getMessage() は HTTP レスポンスボディを文字列化して埋め込むため、
        // 配列ベースの maskSensitiveData をバイパスして PII/カード情報が平文で残る。
        // クラス名のみ残し、本文は別途マスク済み error_body に集約する。
        Log::error('Fincode API Error', [
            'method' => $method,
            'uri' => $uri,
            'status_code' => $statusCode,
            'exception_class' => $e::class,
            'error_body' => $this->maskSensitiveData($errorBody),
        ]);
    }

    /**
     * 配列を再帰的に走査して SENSITIVE_KEYS に該当するキーを `***MASKED***` に置換する。
     * 配列以外の値（文字列・数値・null など）はそのまま返す（プリミティブはマスク対象にしない）。
     *
     * @param  mixed  $data  ログ出力対象の値。通常は API レスポンスをデコードした連想配列だが、
     *                       スカラーや null も許容する。
     */
    protected function maskSensitiveData($data): mixed
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
