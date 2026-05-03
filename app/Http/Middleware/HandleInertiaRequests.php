<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * 初回描画時に必ず使うレイアウトを固定し、Inertia の応答形式を揃える。
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * アセット更新を検知できるよう親実装のバージョン判定を引き継ぐ。
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $success = $request->session()->get('success');
        $error = $request->session()->get('error')
            ?? $request->session()->get('message');
        $hasFlashMessage = $success !== null || $error !== null;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? (new UserResource($request->user()))->resolve() : null,
            ],
            'flash' => [
                'key' => $hasFlashMessage ? Str::uuid()->toString() : null,
                'success' => $success,
                'error' => $error,
            ],
        ];
    }
}
