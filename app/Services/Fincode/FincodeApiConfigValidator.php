<?php

declare(strict_types=1);

namespace App\Services\Fincode;

use RuntimeException;

class FincodeApiConfigValidator
{
    public function validateOrFail(): void
    {
        self::requireApiKey(config('fincode.api_key'));
    }

    public static function requireApiKey(mixed $apiKey): string
    {
        $normalizedApiKey = trim((string) $apiKey);

        if ($normalizedApiKey === '') {
            throw new RuntimeException('決済設定エラー: FINCODE_API_KEY が未設定です。運用管理者にお問い合わせください。');
        }

        return $normalizedApiKey;
    }
}
