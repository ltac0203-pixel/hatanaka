<?php

namespace Tests\Unit\Services;

use App\Services\Fincode\FincodeConfigValidator;
use Tests\TestCase;

class FincodeConfigValidatorTest extends TestCase
{
    private FincodeConfigValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new FincodeConfigValidator;
    }

    public function test_validate_returns_valid_config_for_test_environment(): void
    {
        config([
            'fincode.public_key' => 'p_test_abc123',
            'fincode.base_url' => 'https://api.test.fincode.jp',
            'fincode.sdk_sri_hash' => 'sha384-testhash',
        ]);

        $result = $this->validator->validate();

        $this->assertTrue($result['is_valid']);
        $this->assertSame('p_test_abc123', $result['public_key']);
        $this->assertSame('https://js.test.fincode.jp/v1/fincode.js', $result['sdk_url']);
        $this->assertSame('sha384-testhash', $result['sri_hash']);
        $this->assertNull($result['error']);
    }

    public function test_validate_returns_valid_config_for_production_environment(): void
    {
        config([
            'fincode.public_key' => 'p_live_xyz789',
            'fincode.base_url' => 'https://api.fincode.jp',
            'fincode.sdk_sri_hash' => '',
        ]);

        $result = $this->validator->validate();

        $this->assertTrue($result['is_valid']);
        $this->assertSame('p_live_xyz789', $result['public_key']);
        $this->assertSame('https://js.fincode.jp/v1/fincode.js', $result['sdk_url']);
        $this->assertNull($result['sri_hash']);
        $this->assertNull($result['error']);
    }

    public function test_validate_returns_error_when_public_key_is_empty(): void
    {
        config([
            'fincode.public_key' => '',
            'fincode.base_url' => 'https://api.test.fincode.jp',
        ]);

        $result = $this->validator->validate();

        $this->assertFalse($result['is_valid']);
        $this->assertSame('', $result['public_key']);
        $this->assertNull($result['sdk_url']);
        $this->assertStringContains('FINCODE_PUBLIC_KEY が未設定', $result['error']);
    }

    public function test_validate_returns_error_when_public_key_format_is_invalid(): void
    {
        config([
            'fincode.public_key' => 'invalid_key_format',
            'fincode.base_url' => 'https://api.test.fincode.jp',
        ]);

        $result = $this->validator->validate();

        $this->assertFalse($result['is_valid']);
        $this->assertStringContains('形式が不正', $result['error']);
    }

    public function test_validate_returns_error_when_base_url_is_empty(): void
    {
        config([
            'fincode.public_key' => 'p_test_abc123',
            'fincode.base_url' => '',
        ]);

        $result = $this->validator->validate();

        $this->assertFalse($result['is_valid']);
        $this->assertStringContains('FINCODE_BASE_URL が未設定', $result['error']);
    }

    public function test_validate_returns_error_when_base_url_is_unrecognized(): void
    {
        config([
            'fincode.public_key' => 'p_test_abc123',
            'fincode.base_url' => 'https://unknown.example.com',
        ]);

        $result = $this->validator->validate();

        $this->assertFalse($result['is_valid']);
        $this->assertStringContains('https://api.fincode.jp または', $result['error']);
    }

    public function test_validate_returns_error_when_environment_is_mismatched(): void
    {
        config([
            'fincode.public_key' => 'p_test_abc123',
            'fincode.base_url' => 'https://api.fincode.jp',
        ]);

        $result = $this->validator->validate();

        $this->assertFalse($result['is_valid']);
        $this->assertStringContains('環境が一致していません', $result['error']);
    }

    public function test_is_valid_public_key_accepts_test_key(): void
    {
        $this->assertTrue($this->validator->isValidPublicKey('p_test_abc123'));
    }

    public function test_is_valid_public_key_accepts_live_key(): void
    {
        $this->assertTrue($this->validator->isValidPublicKey('p_live_xyz789'));
    }

    public function test_is_valid_public_key_rejects_invalid_format(): void
    {
        $this->assertFalse($this->validator->isValidPublicKey('invalid'));
        $this->assertFalse($this->validator->isValidPublicKey(''));
        $this->assertFalse($this->validator->isValidPublicKey('p_staging_abc'));
        $this->assertFalse($this->validator->isValidPublicKey('p_test_'));
    }

    public function test_is_mismatched_environment_detects_test_key_with_prod_url(): void
    {
        $this->assertTrue($this->validator->isMismatchedEnvironment('p_test_abc', 'https://api.fincode.jp'));
    }

    public function test_is_mismatched_environment_detects_live_key_with_test_url(): void
    {
        $this->assertTrue($this->validator->isMismatchedEnvironment('p_live_abc', 'https://api.test.fincode.jp'));
    }

    public function test_is_mismatched_environment_returns_false_for_matching_test(): void
    {
        $this->assertFalse($this->validator->isMismatchedEnvironment('p_test_abc', 'https://api.test.fincode.jp'));
    }

    public function test_is_mismatched_environment_returns_false_for_matching_live(): void
    {
        $this->assertFalse($this->validator->isMismatchedEnvironment('p_live_abc', 'https://api.fincode.jp'));
    }

    private function assertStringContains(string $needle, ?string $haystack): void
    {
        $this->assertNotNull($haystack);
        $this->assertStringContainsString($needle, $haystack);
    }
}
