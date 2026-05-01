<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// メンテナンス中は通常起動せず専用レスポンスへ切り替える。
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// 以後のクラス解決で失敗しないようオートローダーを先に読み込む。
require __DIR__.'/../vendor/autoload.php';

// フレームワークを起動して現在のHTTPリクエストを処理させる。
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
