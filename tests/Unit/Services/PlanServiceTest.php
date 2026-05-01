<?php

namespace Tests\Unit\Services;

use App\Exceptions\FincodeApiException;
use App\Services\Fincode\CircuitBreaker;
use App\Services\Fincode\FincodeClient;
use App\Services\Fincode\PlanNormalizer;
use App\Services\Fincode\PlanService;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class PlanServiceTest extends TestCase
{
    private PlanService $service;

    private MockHandler $mockHandler;

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
            'cache.default' => 'array',
        ]);

        $this->mockHandler = new MockHandler;
        $handlerStack = HandlerStack::create($this->mockHandler);
        $client = new Client(['handler' => $handlerStack]);

        $fincodeClient = new FincodeClient($client, $this->makeCircuitBreakerMock());
        $this->service = new PlanService($fincodeClient, new PlanNormalizer);
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

    public function test_list_active_plans_returns_only_active(): void
    {
        $responseData = [
            'list' => [
                [
                    'id' => 'pl_active',
                    'name' => 'Active Plan',
                    'amount' => 1000,
                    'interval' => 'month',
                    'status' => 'active',
                ],
                [
                    'id' => 'pl_inactive',
                    'name' => 'Inactive Plan',
                    'amount' => 2000,
                    'interval' => 'month',
                    'status' => 'inactive',
                ],
            ],
        ];

        $this->mockHandler->append(new Response(200, [], json_encode($responseData)));

        $plans = $this->service->listActivePlans();

        $this->assertCount(1, $plans);
        $this->assertSame('pl_active', $plans[0]['fincode_plan_id']);
        $this->assertSame('active', $plans[0]['status']);
    }

    public function test_list_active_plans_with_list_format(): void
    {
        $responseData = [
            'list' => [
                [
                    'id' => 'pl_1',
                    'name' => 'Plan 1',
                    'amount' => 500,
                    'interval' => 'monthly',
                    'status' => 'active',
                ],
                [
                    'id' => 'pl_2',
                    'name' => 'Plan 2',
                    'amount' => 1500,
                    'interval' => 'yearly',
                    'status' => 'active',
                ],
            ],
        ];

        $this->mockHandler->append(new Response(200, [], json_encode($responseData)));

        $plans = $this->service->listActivePlans();

        $this->assertCount(2, $plans);
        $this->assertSame('pl_1', $plans[0]['fincode_plan_id']);
        $this->assertSame('pl_2', $plans[1]['fincode_plan_id']);
    }

    public function test_list_active_plans_with_data_format(): void
    {
        $responseData = [
            'data' => [
                [
                    'id' => 'pl_data_1',
                    'name' => 'Data Plan',
                    'amount' => 3000,
                    'interval' => 'month',
                    'status' => 'active',
                ],
            ],
        ];

        $this->mockHandler->append(new Response(200, [], json_encode($responseData)));

        $plans = $this->service->listActivePlans();

        $this->assertCount(1, $plans);
        $this->assertSame('pl_data_1', $plans[0]['fincode_plan_id']);
    }

    public function test_list_active_plans_returns_empty_when_no_plans(): void
    {
        $responseData = ['list' => []];

        $this->mockHandler->append(new Response(200, [], json_encode($responseData)));

        $plans = $this->service->listActivePlans();

        $this->assertCount(0, $plans);
        $this->assertSame([], $plans);
    }

    public function test_find_active_plan_returns_plan(): void
    {
        $responseData = [
            'id' => 'pl_found',
            'name' => 'Found Plan',
            'amount' => 2000,
            'interval' => 'month',
            'status' => 'active',
        ];

        $this->mockHandler->append(new Response(200, [], json_encode($responseData)));

        $plan = $this->service->findActivePlan('pl_found');

        $this->assertNotNull($plan);
        $this->assertSame('pl_found', $plan['fincode_plan_id']);
        $this->assertSame('Found Plan', $plan['name']);
        $this->assertSame(2000, $plan['amount']);
        $this->assertSame('monthly', $plan['interval']);
        $this->assertSame('active', $plan['status']);
    }

    public function test_find_active_plan_returns_null_on_404(): void
    {
        $errorResponse = new Response(404, [], json_encode(['error' => 'Not found']));
        $this->mockHandler->append(
            new RequestException(
                'Not found',
                new Request('GET', '/v1/plans/pl_notfound'),
                $errorResponse
            )
        );

        $plan = $this->service->findActivePlan('pl_notfound');

        $this->assertNull($plan);
    }

    public function test_find_active_plan_throws_on_other_errors(): void
    {
        $errorResponse = new Response(500, [], json_encode(['error' => 'Internal server error']));
        $this->mockHandler->append(
            new RequestException(
                'Server error',
                new Request('GET', '/v1/plans/pl_error'),
                $errorResponse
            )
        );

        $this->expectException(FincodeApiException::class);

        $this->service->findActivePlan('pl_error');
    }

    public function test_find_active_plan_returns_null_for_inactive(): void
    {
        $responseData = [
            'id' => 'pl_disabled',
            'name' => 'Disabled Plan',
            'amount' => 1000,
            'interval' => 'month',
            'status' => 'disabled',
        ];

        $this->mockHandler->append(new Response(200, [], json_encode($responseData)));

        $plan = $this->service->findActivePlan('pl_disabled');

        $this->assertNull($plan);
    }

    public function test_normalize_interval_conversions(): void
    {
        $intervals = [
            'month' => 'monthly',
            'monthly' => 'monthly',
            'year' => 'yearly',
            'yearly' => 'yearly',
            'week' => 'weekly',
            'weekly' => 'weekly',
            'day' => 'daily',
            'daily' => 'daily',
            'unknown' => 'monthly', // 未知の周期は月次へ寄せて表示崩れを防ぐ。
        ];

        foreach ($intervals as $input => $expected) {
            Cache::flush(); // 同一キーの繰り返し呼び出しでキャッシュが汚染しないよう毎回クリア

            $responseData = [
                'id' => 'pl_interval',
                'name' => 'Interval Test',
                'amount' => 1000,
                'interval' => $input,
                'status' => 'active',
            ];

            $this->mockHandler->append(new Response(200, [], json_encode($responseData)));

            $plan = $this->service->findActivePlan('pl_interval');

            $this->assertSame($expected, $plan['interval'], "Failed asserting interval '{$input}' normalizes to '{$expected}'");
        }
    }

    public function test_interval_label_single(): void
    {
        $cases = [
            'month' => '月',
            'year' => '年',
            'week' => '週',
            'day' => '日',
        ];

        foreach ($cases as $interval => $expectedLabel) {
            Cache::flush(); // 同一キーの繰り返し呼び出しでキャッシュが汚染しないよう毎回クリア

            $responseData = [
                'id' => 'pl_label',
                'name' => 'Label Test',
                'amount' => 1000,
                'interval' => $interval,
                'interval_count' => 1,
                'status' => 'active',
            ];

            $this->mockHandler->append(new Response(200, [], json_encode($responseData)));

            $plan = $this->service->findActivePlan('pl_label');

            $this->assertSame($expectedLabel, $plan['interval_label'], "Failed asserting interval_label for '{$interval}'");
        }
    }

    public function test_interval_label_with_count(): void
    {
        $responseData = [
            'id' => 'pl_count',
            'name' => 'Count Plan',
            'amount' => 3000,
            'interval' => 'month',
            'interval_count' => 3,
            'status' => 'active',
        ];

        $this->mockHandler->append(new Response(200, [], json_encode($responseData)));

        $plan = $this->service->findActivePlan('pl_count');

        $this->assertSame('3月', $plan['interval_label']);
        $this->assertSame('¥3,000/3月', $plan['price_display']);
    }

    public function test_normalize_status_conversions(): void
    {
        $statuses = [
            'active' => 'active',
            'ACTIVE' => 'active',
            'enabled' => 'active',
            'public' => 'active',
            'inactive' => 'inactive',
            'disabled' => 'inactive',
            'archived' => 'archived',
            'deleted' => 'archived',
            'unknown_status' => 'inactive', // 未知の状態を有効扱いしないよう安全側へ倒す。
        ];

        foreach ($statuses as $input => $expected) {
            Cache::flush(); // 同一キーの繰り返し呼び出しでキャッシュが汚染しないよう毎回クリア

            $responseData = [
                'id' => 'pl_status',
                'name' => 'Status Test',
                'amount' => 1000,
                'interval' => 'month',
                'status' => $input,
            ];

            $this->mockHandler->append(new Response(200, [], json_encode($responseData)));

            // 非アクティブな状態は単体取得で null になるため、一覧正規化の結果で判定する。
            $this->mockHandler->reset();
            $this->mockHandler->append(new Response(200, [], json_encode(['list' => [$responseData]])));

            $plans = $this->service->listActivePlans();

            if ($expected === 'active') {
                $this->assertCount(1, $plans, "Status '{$input}' should normalize to active");
                $this->assertSame('active', $plans[0]['status']);
            } else {
                $this->assertCount(0, $plans, "Status '{$input}' should normalize to '{$expected}' and be filtered out");
            }
        }
    }

    public function test_normalize_plan_maps_alternative_field_names(): void
    {
        $responseData = [
            'plan_id' => 'pl_alt',
            'name' => 'Alt Plan',
            'price' => 5000,
            'cycle' => 'year',
            'cycle_count' => 2,
            'status' => 'enabled',
            'features' => ['Feature A', 'Feature B'],
        ];

        $this->mockHandler->append(new Response(200, [], json_encode(['list' => [$responseData]])));

        $plans = $this->service->listActivePlans();

        $this->assertCount(1, $plans);
        $plan = $plans[0];
        $this->assertSame('pl_alt', $plan['fincode_plan_id']);
        $this->assertSame(5000, $plan['amount']);
        $this->assertSame('yearly', $plan['interval']);
        $this->assertSame(2, $plan['interval_count']);
        $this->assertSame('active', $plan['status']);
        $this->assertSame(['Feature A', 'Feature B'], $plan['features']);
        $this->assertSame('2年', $plan['interval_label']);
        $this->assertSame('¥5,000/2年', $plan['price_display']);
    }

    public function test_list_active_plans_filters_out_deleted_plans(): void
    {
        $responseData = [
            'list' => [
                [
                    'id' => 'pl_active',
                    'name' => 'Active Plan',
                    'amount' => 980,
                    'interval' => 'month',
                    'status' => 'active',
                    'delete_flag' => '0',
                ],
                [
                    'id' => 'pl_deleted',
                    'name' => 'Deleted Plan',
                    'amount' => 500,
                    'interval' => 'month',
                    'status' => 'active',
                    'delete_flag' => '1',
                ],
            ],
        ];

        $this->mockHandler->append(new Response(200, [], json_encode($responseData)));

        $plans = $this->service->listActivePlans();

        $this->assertCount(1, $plans);
        $this->assertSame('pl_active', $plans[0]['fincode_plan_id']);
    }

    public function test_delete_flag_overrides_active_status(): void
    {
        $responseData = [
            'list' => [
                [
                    'id' => 'pl_deleted_active',
                    'name' => 'Deleted But Active',
                    'amount' => 1000,
                    'interval' => 'month',
                    'status' => 'active',
                    'delete_flag' => '1',
                ],
            ],
        ];

        $this->mockHandler->append(new Response(200, [], json_encode($responseData)));

        $plans = $this->service->listActivePlans();

        $this->assertCount(0, $plans);
    }

    public function test_plans_without_delete_flag_are_included(): void
    {
        $responseData = [
            'list' => [
                [
                    'id' => 'pl_no_flag',
                    'name' => 'No Delete Flag Plan',
                    'amount' => 600,
                    'interval' => 'month',
                    'status' => 'active',
                ],
            ],
        ];

        $this->mockHandler->append(new Response(200, [], json_encode($responseData)));

        $plans = $this->service->listActivePlans();

        $this->assertCount(1, $plans);
        $this->assertSame('pl_no_flag', $plans[0]['fincode_plan_id']);
        $this->assertSame('active', $plans[0]['status']);
    }

    public function test_find_active_plan_returns_null_for_deleted_plan(): void
    {
        $responseData = [
            'id' => 'pl_deleted',
            'name' => 'Deleted Plan',
            'amount' => 500,
            'interval' => 'month',
            'status' => 'active',
            'delete_flag' => '1',
        ];

        $this->mockHandler->append(new Response(200, [], json_encode($responseData)));

        $plan = $this->service->findActivePlan('pl_deleted');

        $this->assertNull($plan);
    }

    public function test_list_active_plans_uses_cache_on_second_call(): void
    {
        $responseData = [
            'list' => [
                [
                    'id' => 'pl_cached',
                    'name' => 'Cached Plan',
                    'amount' => 1000,
                    'interval' => 'month',
                    'status' => 'active',
                ],
            ],
        ];

        // レスポンスを1件だけ登録 — 2回目はキャッシュから返るはずなので HTTP は呼ばれない
        $this->mockHandler->append(new Response(200, [], json_encode($responseData)));

        $plans1 = $this->service->listActivePlans();
        $plans2 = $this->service->listActivePlans();

        $this->assertCount(1, $plans1);
        $this->assertCount(1, $plans2);
        $this->assertSame($plans1[0]['fincode_plan_id'], $plans2[0]['fincode_plan_id']);
        $this->assertSame(0, $this->mockHandler->count()); // 残りリクエストが0 = 追加 HTTP コール無し
    }

    public function test_find_active_plan_uses_cache_on_second_call(): void
    {
        $responseData = [
            'id' => 'pl_cached_single',
            'name' => 'Cached Single Plan',
            'amount' => 2000,
            'interval' => 'month',
            'status' => 'active',
        ];

        // レスポンスを1件だけ登録 — 2回目はキャッシュから返るはずなので HTTP は呼ばれない
        $this->mockHandler->append(new Response(200, [], json_encode($responseData)));

        $plan1 = $this->service->findActivePlan('pl_cached_single');
        $plan2 = $this->service->findActivePlan('pl_cached_single');

        $this->assertNotNull($plan1);
        $this->assertNotNull($plan2);
        $this->assertSame($plan1['fincode_plan_id'], $plan2['fincode_plan_id']);
        $this->assertSame(0, $this->mockHandler->count()); // 追加 HTTP コール無し
    }

    public function test_find_active_plan_caches_null_result_to_avoid_repeated_api_calls(): void
    {
        $responseData = [
            'id' => 'pl_inactive_cached',
            'name' => 'Inactive Plan',
            'amount' => 500,
            'interval' => 'month',
            'status' => 'active',
            'delete_flag' => '1', // アクティブでないため null を返す
        ];

        // 1件だけ登録 — null 結果も false としてキャッシュされるため2回目は HTTP を呼ばない
        $this->mockHandler->append(new Response(200, [], json_encode($responseData)));

        $plan1 = $this->service->findActivePlan('pl_inactive_cached');
        $plan2 = $this->service->findActivePlan('pl_inactive_cached');

        $this->assertNull($plan1);
        $this->assertNull($plan2);
        $this->assertSame(0, $this->mockHandler->count()); // 2回目はキャッシュを使用
    }
}
