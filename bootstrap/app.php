<?php

use App\Exceptions\ActiveSubscriptionExistsException;
use App\Exceptions\CardInUseException;
use App\Exceptions\CircuitBreakerOpenException;
use App\Exceptions\ExpiredCardException;
use App\Exceptions\FincodeApiException;
use App\Exceptions\FincodeRateLimitException;
use App\Exceptions\FincodeServerException;
use App\Exceptions\FincodeTimeoutException;
use App\Exceptions\PlanUnavailableException;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ALB/Cloudflare/Nginx 等の TLS 終端越しでも isSecure() を正しく判定し、
        // HSTS をはじめとするセキュリティ機能が稼働するよう X-Forwarded-* を信頼する。
        // 既定は「プロキシを信頼しない」(= 空配列) とし、X-Forwarded-* スプーフィングで
        // $request->ip() を任意値にされて IP ベースの throttle / 監査が無効化されることを防ぐ。
        // LB/CDN 配下で本アプリを運用する場合は TRUSTED_PROXIES (.env) に CIDR を明示する
        // (例: '10.0.0.0/8'、複数なら ',' 区切り、すべて信頼するなら '*')。
        // '*' はプロキシ経路が完全に制御されている環境でのみ使うこと。
        $trustedProxies = env('TRUSTED_PROXIES');
        $middleware->trustProxies(
            at: match (true) {
                $trustedProxies === null || $trustedProxies === '' => [],
                $trustedProxies === '*' => '*',
                default => array_values(array_filter(array_map('trim', explode(',', (string) $trustedProxies)))),
            },
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB,
        );

        $middleware->append(SecurityHeaders::class);

        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'ability' => CheckForAnyAbility::class,
            'abilities' => CheckAbilities::class,
        ]);

        $middleware->statefulApi();
    })
    ->withEvents()
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (FincodeRateLimitException $e, Request $request) {
            $headers = $e->getRetryAfterSeconds() !== null
                ? ['Retry-After' => $e->getRetryAfterSeconds()]
                : [];

            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => '決済サービスのレート制限に達しました。しばらく待ってから再度お試しください。',
                ], 429, $headers);
            }

            return Inertia::render('Error', ['status' => 429])
                ->toResponse($request)
                ->setStatusCode(429);
        });

        $exceptions->render(function (CircuitBreakerOpenException $e, Request $request) {
            $headers = ['Retry-After' => $e->getRemainingSeconds()];

            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => '決済サービスへの接続が一時的に遮断されています。しばらく待ってから再度お試しください。',
                ], 503, $headers);
            }

            return Inertia::render('Error', ['status' => 503])
                ->toResponse($request)
                ->setStatusCode(503);
        });

        $exceptions->render(function (FincodeTimeoutException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => '決済サービスへの接続がタイムアウトしました。',
                ], 504);
            }

            return Inertia::render('Error', ['status' => 504])
                ->toResponse($request)
                ->setStatusCode(504);
        });

        $exceptions->render(function (FincodeServerException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => '決済サービスでサーバーエラーが発生しました。',
                ], 503);
            }

            return Inertia::render('Error', ['status' => 503])
                ->toResponse($request)
                ->setStatusCode(503);
        });

        $exceptions->render(function (CardInUseException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 409);
            }

            return back()->withErrors(['card' => $e->getMessage()]);
        });

        $exceptions->render(function (ExpiredCardException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => ['card_id' => [$e->getMessage()]],
                ], 422);
            }

            return back()->withErrors(['card_id' => $e->getMessage()]);
        });

        $exceptions->render(function (PlanUnavailableException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => ['fincode_plan_id' => [$e->getMessage()]],
                ], 422);
            }

            return back()->withErrors(['fincode_plan_id' => $e->getMessage()]);
        });

        $exceptions->render(function (ActiveSubscriptionExistsException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors' => ['fincode_plan_id' => [$e->getMessage()]],
                ], 422);
            }

            return back()->withErrors(['fincode_plan_id' => $e->getMessage()]);
        });

        $exceptions->render(function (FincodeApiException $e, Request $request) {
            $statusCode = $e->getStatusCode() ?: 500;
            $httpStatus = match (true) {
                $statusCode === 401 || $statusCode === 403 => 502,
                $statusCode >= 400 && $statusCode < 500 => $statusCode,
                default => 503,
            };

            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => '決済サービスとの通信でエラーが発生しました。',
                ], $httpStatus);
            }

            return Inertia::render('Error', ['status' => $httpStatus])
                ->toResponse($request)
                ->setStatusCode($httpStatus);
        });

        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return $response;
            }

            $status = $response->getStatusCode();

            if ($status === 419) {
                return back()->with('error', 'ページの有効期限が切れました。再度お試しください。');
            }

            if (config('app.debug')) {
                return $response;
            }

            if (in_array($status, [403, 404, 429, 500, 503, 504], true)) {
                return Inertia::render('Error', [
                    'status' => $status,
                ])->toResponse($request)->setStatusCode($status);
            }

            return $response;
        });
    })->create();
