<?php

declare(strict_types=1);

namespace App\Services\Fincode;

class FincodeConfigValidator
{
    /**
     * Fincode フロントエンド設定を検証し、カードフォーム描画に必要な情報を返す。
     *
     * @return array{is_valid: bool, public_key: string, sdk_url: ?string, sri_hash: ?string, error: ?string}
     */
    public function validate(): array
    {
        $publicKey = trim((string) config('fincode.public_key', ''));
        $baseUrl = (string) config('fincode.base_url', '');
        $sriHash = trim((string) config('fincode.sdk_sri_hash', ''));

        $error = $this->detectError($publicKey, $baseUrl);

        if ($error !== null) {
            // 環境不一致エラーの場合はSDK URLは解決可能なため返す（それ以外はnull）
            $sdkUrl = $this->isMismatchedEnvironment($publicKey, $baseUrl)
                ? $this->resolveSdkUrl($baseUrl)
                : null;

            return [
                'is_valid' => false,
                'public_key' => '',
                'sdk_url' => $sdkUrl,
                'sri_hash' => null,
                'error' => $error,
            ];
        }

        return [
            'is_valid' => true,
            'public_key' => $publicKey,
            'sdk_url' => $this->resolveSdkUrl($baseUrl),
            'sri_hash' => $sriHash !== '' ? $sriHash : null,
            'error' => null,
        ];
    }

    private function detectError(string $publicKey, string $baseUrl): ?string
    {
        if ($publicKey === '') {
            return '決済設定エラー: FINCODE_PUBLIC_KEY が未設定です。運用管理者にお問い合わせください。';
        }

        if (! $this->isValidPublicKey($publicKey)) {
            return '決済設定エラー: FINCODE_PUBLIC_KEY の形式が不正です。運用管理者にお問い合わせください。';
        }

        if ($baseUrl === '') {
            return '決済設定エラー: FINCODE_BASE_URL が未設定です。運用管理者にお問い合わせください。';
        }

        if ($this->resolveSdkUrl($baseUrl) === null) {
            return '決済設定エラー: FINCODE_BASE_URL は https://api.fincode.jp または https://api.test.fincode.jp を指定してください。';
        }

        if ($this->isMismatchedEnvironment($publicKey, $baseUrl)) {
            return '決済設定エラー: FINCODE_PUBLIC_KEY と FINCODE_BASE_URL の環境が一致していません。運用管理者にお問い合わせください。';
        }

        return null;
    }

    private function resolveSdkUrl(string $baseUrl): ?string
    {
        return match ($baseUrl) {
            'https://api.fincode.jp' => 'https://js.fincode.jp/v1/fincode.js',
            'https://api.test.fincode.jp' => 'https://js.test.fincode.jp/v1/fincode.js',
            default => null,
        };
    }

    public function isValidPublicKey(string $publicKey): bool
    {
        return (bool) preg_match('/^p_(test|live)_[A-Za-z0-9]+$/', $publicKey);
    }

    public function isMismatchedEnvironment(string $publicKey, string $baseUrl): bool
    {
        if (str_starts_with($publicKey, 'p_test_')) {
            return $baseUrl !== 'https://api.test.fincode.jp';
        }

        if (str_starts_with($publicKey, 'p_live_')) {
            return $baseUrl !== 'https://api.fincode.jp';
        }

        return false;
    }
}
