<?php

namespace Tests\Unit\Services;

use App\Services\Fincode\FincodeApiConfigValidator;
use RuntimeException;
use Tests\TestCase;

class FincodeApiConfigValidatorTest extends TestCase
{
    private FincodeApiConfigValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new FincodeApiConfigValidator;
    }

    public function test_validate_or_fail_accepts_configured_api_key(): void
    {
        config(['fincode.api_key' => 'test_api_key']);

        $this->validator->validateOrFail();

        $this->addToAssertionCount(1);
    }

    public function test_validate_or_fail_throws_when_api_key_is_empty(): void
    {
        config(['fincode.api_key' => '']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('FINCODE_API_KEY が未設定');

        $this->validator->validateOrFail();
    }

    public function test_require_api_key_trims_surrounding_whitespace(): void
    {
        $this->assertSame('test_api_key', FincodeApiConfigValidator::requireApiKey('  test_api_key  '));
    }

    public function test_require_api_key_throws_when_value_is_null(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('FINCODE_API_KEY が未設定');

        FincodeApiConfigValidator::requireApiKey(null);
    }
}
