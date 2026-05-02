<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));
        Vite::useCspNonce($nonce);

        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // 重要 API への不要な機能アクセスを明示的に拒否し、サブリソース経由の権限昇格を抑止する。
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=(), accelerometer=(), gyroscope=(), magnetometer=(), midi=(), serial=(), interest-cohort=()'
        );
        $response->headers->set('Content-Security-Policy', $this->buildCsp($nonce));

        if ($this->shouldAddHsts($request)) {
            $response->headers->set('Strict-Transport-Security', $this->buildHsts());
        }

        return $response;
    }

    private function buildCsp(string $nonce): string
    {
        // 本番環境かどうかで Fincode のホストを切り替え、不要なエンドポイントを CSP から外す。
        $isFincodeProduction = filter_var(env('FINCODE_PRODUCTION', false), FILTER_VALIDATE_BOOLEAN);
        $fincodeScriptHost = $isFincodeProduction ? 'https://js.fincode.jp' : 'https://js.test.fincode.jp';
        $fincodeApiHost = $isFincodeProduction ? 'https://api.fincode.jp' : 'https://api.test.fincode.jp';

        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' {$fincodeScriptHost}",
            "style-src 'self' 'nonce-{$nonce}' https://fonts.googleapis.com https://fonts.bunny.net",
            "font-src 'self' https://fonts.gstatic.com https://fonts.bunny.net",
            "img-src 'self' data:",
            "connect-src 'self' {$fincodeApiHost}",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            // プラグイン/オブジェクト経由の任意コード実行を完全に塞ぐ。
            "object-src 'none'",
        ];

        // HTTPS でしかアクセスを許さないことを明示し、混在コンテンツの自動アップグレードを促す (HSTS と二重化)。
        if (! app()->environment('local', 'testing')) {
            $directives[] = 'upgrade-insecure-requests';
        }

        if (config('security.csp.report_enabled')) {
            $reportUri = trim((string) config('security.csp.report_uri', ''));

            if ($reportUri !== '') {
                $directives[] = "report-uri {$reportUri}";
            }
        }

        return implode('; ', $directives);
    }

    private function shouldAddHsts(Request $request): bool
    {
        return (bool) config('security.hsts.enabled', true) && $request->isSecure();
    }

    private function buildHsts(): string
    {
        $directives = ['max-age='.(int) config('security.hsts.max_age', 31536000)];

        if (config('security.hsts.include_subdomains', true)) {
            $directives[] = 'includeSubDomains';
        }

        if (config('security.hsts.preload', true)) {
            $directives[] = 'preload';
        }

        return implode('; ', $directives);
    }
}
