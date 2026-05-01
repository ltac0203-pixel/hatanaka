<?php

namespace Tests\Unit\Config;

use Tests\TestCase;

class FincodeConfigTest extends TestCase
{
    private array $originalEnv = [];

    protected function tearDown(): void
    {
        $this->restoreEnv();
        parent::tearDown();
    }

    public function test_public_key_prefers_fincode_public_key_over_legacy_vite_key(): void
    {
        $this->setEnv('FINCODE_PUBLIC_KEY', 'p_test_primary');
        $this->setEnv('VITE_FINCODE_PUBLIC_KEY', 'p_test_legacy');

        $config = require base_path('config/fincode.php');

        $this->assertSame('p_test_primary', $config['public_key']);
    }

    public function test_public_key_falls_back_to_legacy_vite_key_when_fincode_public_key_is_missing(): void
    {
        $this->setEnv('FINCODE_PUBLIC_KEY', null);
        $this->setEnv('VITE_FINCODE_PUBLIC_KEY', 'p_test_legacy');

        $config = require base_path('config/fincode.php');

        $this->assertSame('p_test_legacy', $config['public_key']);
    }

    public function test_tenant_shop_id_falls_back_to_legacy_shop_id_key_when_new_key_is_missing(): void
    {
        $this->setEnv('FINCODE_TENANT_SHOP_ID', null);
        $this->setEnv('FINCODE_SHOP_ID', 's_test_legacy');

        $config = require base_path('config/fincode.php');

        $this->assertSame('s_test_legacy', $config['tenant_shop_id']);
    }

    private function setEnv(string $key, ?string $value): void
    {
        if (! array_key_exists($key, $this->originalEnv)) {
            $this->originalEnv[$key] = getenv($key);
        }

        if ($value === null) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            return;
        }

        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    private function restoreEnv(): void
    {
        foreach ($this->originalEnv as $key => $value) {
            if ($value === false) {
                putenv($key);
                unset($_ENV[$key], $_SERVER[$key]);

                continue;
            }

            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        $this->originalEnv = [];
    }
}
