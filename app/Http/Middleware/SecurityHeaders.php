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
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->headers->set('Content-Security-Policy', $this->buildCsp($nonce));

        if ($this->shouldAddHsts($request)) {
            $response->headers->set('Strict-Transport-Security', $this->buildHsts());
        }

        return $response;
    }

    private function buildCsp(string $nonce): string
    {
        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' https://js.test.fincode.jp https://js.fincode.jp",
            "style-src 'self' 'nonce-{$nonce}' https://fonts.googleapis.com https://fonts.bunny.net",
            "font-src 'self' https://fonts.gstatic.com https://fonts.bunny.net",
            "img-src 'self' data:",
            "connect-src 'self' https://api.test.fincode.jp https://api.fincode.jp",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

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
