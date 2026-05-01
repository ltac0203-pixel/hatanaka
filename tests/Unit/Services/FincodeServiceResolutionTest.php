<?php

namespace Tests\Unit\Services;

use App\Services\Fincode\CardService;
use App\Services\Fincode\CustomerService;
use App\Services\Fincode\FincodeClient;
use App\Services\Fincode\PlanService;
use App\Services\Fincode\SubscriptionService;
use RuntimeException;
use Tests\TestCase;

class FincodeServiceResolutionTest extends TestCase
{
    public function test_fincode_services_resolve_from_container(): void
    {
        config([
            'fincode.api_key' => 'test_api_key',
            'fincode.base_url' => 'https://api.test.fincode.jp',
            'fincode.api_version' => '2024-01-01',
            'fincode.tenant_shop_id' => null,
            'fincode.log_requests' => false,
            'fincode.log_responses' => false,
        ]);

        foreach ([
            FincodeClient::class,
            PlanService::class,
            CardService::class,
            CustomerService::class,
            SubscriptionService::class,
        ] as $serviceClass) {
            $this->assertInstanceOf($serviceClass, $this->app->make($serviceClass));
        }
    }

    public function test_fincode_client_resolution_fails_when_api_key_is_missing(): void
    {
        config([
            'fincode.api_key' => '',
            'fincode.base_url' => 'https://api.test.fincode.jp',
            'fincode.api_version' => '2024-01-01',
            'fincode.tenant_shop_id' => null,
            'fincode.log_requests' => false,
            'fincode.log_responses' => false,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('FINCODE_API_KEY が未設定');

        $this->app->make(FincodeClient::class);
    }
}
