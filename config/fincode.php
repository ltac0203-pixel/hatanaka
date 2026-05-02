<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Fincode 連携で使う接続条件を揃える
    |--------------------------------------------------------------------------
    |
    | Fincode API 連携に使う設定です。
    | 環境差分が出る値は `.env` 側で設定します。
    |
    */

    // API キーは必須。未設定は AppServiceProvider と FincodeClient で fail fast する。
    'api_key' => env('FINCODE_API_KEY'),
    'public_key' => env('FINCODE_PUBLIC_KEY', env('VITE_FINCODE_PUBLIC_KEY')),
    'base_url' => env('FINCODE_BASE_URL', filter_var(env('FINCODE_PRODUCTION', false), FILTER_VALIDATE_BOOLEAN) ? 'https://api.fincode.jp' : 'https://api.test.fincode.jp'),
    'api_version' => env('FINCODE_API_VERSION', '20211001'),
    'tenant_shop_id' => env('FINCODE_TENANT_SHOP_ID', env('FINCODE_SHOP_ID')),
    'timeout' => env('FINCODE_TIMEOUT', 30),
    'connect_timeout' => env('FINCODE_CONNECT_TIMEOUT', 10),

    // TLS 検証用 CA バンドル。未設定 (true) の場合 PHP/cURL のシステムデフォルトを使う。
    // Windows のように OS に CA バンドルが未配備の環境では、明示的にパスを指定する必要がある。
    'ca_bundle' => env('FINCODE_CA_BUNDLE', storage_path('certs/cacert.pem')),

    'log_requests' => env('FINCODE_LOG_REQUESTS', false),
    'log_responses' => env('FINCODE_LOG_RESPONSES', false),

    'circuit_breaker' => [
        'enabled' => env('FINCODE_CIRCUIT_BREAKER_ENABLED', true),
        'failure_threshold' => env('FINCODE_CIRCUIT_BREAKER_THRESHOLD', 5),
        'recovery_timeout' => env('FINCODE_CIRCUIT_BREAKER_RECOVERY_TIMEOUT', 30),
    ],
    'retry' => [
        'enabled' => env('FINCODE_RETRY_ENABLED', true),
        'max_attempts' => env('FINCODE_RETRY_MAX_ATTEMPTS', 3),
        'base_delay_ms' => env('FINCODE_RETRY_BASE_DELAY_MS', 200),
        'max_delay_ms' => env('FINCODE_RETRY_MAX_DELAY_MS', 5000),
    ],

    /*
    |--------------------------------------------------------------------------
    | 読み込むSDK改ざんを検知できるようにする
    |--------------------------------------------------------------------------
    |
    | Fincode JavaScript SDK の SRI ハッシュを指定します。
    | 読み込んだスクリプトが改ざんされていないことを検証できます。生成例:
    |   openssl dgst -sha384 -binary fincode.js | openssl base64 -A
    | 生成した値の先頭には "sha384-" を付けてください。
    |
    */
    'sdk_sri_hash' => env('FINCODE_SDK_SRI_HASH', ''),
];
