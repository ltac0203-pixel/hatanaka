<?php

declare(strict_types=1);

namespace App\Services\Fincode;

use App\Exceptions\FincodeApiException;
use Illuminate\Support\Facades\Log;

class FincodeOperationLogger
{
    /**
     * Fincode API 失敗時の定型ログ (exception_class / status_code) を付与して例外を再 throw する。
     *
     * 各 Manager で重複していた catch + Log::error + throw の三点セットを一箇所に集約し、
     * ログコンテキストの shape (exception_class / status_code) が揺れないようにする。
     *
     * 呼び出し側が `exception_class` / `status_code` キーを渡しても helper 側の値で上書きされる
     * （array_merge の右側優先）。ログ shape の一貫性を担保するため意図的にこの順序にしている。
     *
     * @param  array<string, mixed>  $context  追加コンテキスト (user_id, subscription_id など)
     */
    public static function rethrowWithLog(string $message, array $context, FincodeApiException $e): never
    {
        Log::error($message, array_merge($context, [
            'exception_class' => $e::class,
            'status_code' => $e->getStatusCode(),
        ]));

        throw $e;
    }
}
