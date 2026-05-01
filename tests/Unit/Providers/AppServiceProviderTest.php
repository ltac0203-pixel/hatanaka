<?php

namespace Tests\Unit\Providers;

use App\Services\Fincode\FincodeApiConfigValidator;
use RuntimeException;
use Tests\TestCase;

class AppServiceProviderTest extends TestCase
{
    public function test_fincode_api_config_validator_throws_when_api_key_is_missing(): void
    {
        config(['fincode.api_key' => '']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('FINCODE_API_KEY が未設定');

        (new FincodeApiConfigValidator)->validateOrFail();
    }
}
