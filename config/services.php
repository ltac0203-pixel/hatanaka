<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 外部連携の資格情報を一箇所へ集約する
    |--------------------------------------------------------------------------
    |
    | Mailgun や Postmark など外部サービスの認証情報をまとめる設定です。
    | パッケージが慣習的な場所から資格情報を参照できるようにします。
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
