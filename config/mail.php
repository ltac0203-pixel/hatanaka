<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 個別指定なしで使う配送手段を決める
    |--------------------------------------------------------------------------
    |
    | 個別指定がない場合に使う既定メーラーを決めます。
    | 追加メーラーは下の `mailers` 配列で定義できます。
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | 配送手段ごとに接続条件を切り替えられるようにする
    |--------------------------------------------------------------------------
    |
    | アプリケーションで使う各メーラーと設定値をここで定義します。
    | 用途に応じて自由に追加・変更できます。
    |
    | Laravel は複数のメール配送ドライバーをサポートしています。
    | 利用する配送方法を各メーラーごとに指定してください。
    |
    | 利用可能: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |          "postmark", "resend", "log", "array",
    |          "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | 送信元表記を全メールで揃える
    |--------------------------------------------------------------------------
    |
    | すべてのメールで共通の送信元を使いたい場合の設定です。
    | ここで指定した名前とアドレスが全メールの既定値になります。
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],

];
