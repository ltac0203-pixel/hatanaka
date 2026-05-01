<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Http\Middleware\SecurityHeaders;
use App\Models\FincodeCard;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    public function test_env_production_has_debug_disabled(): void
    {
        $path = base_path('.env.production');
        if (! file_exists($path)) {
            $this->markTestSkipped('.env.production does not exist in this environment');
        }
        $content = file_get_contents($path);

        $this->assertMatchesRegularExpression('/^APP_DEBUG=false\r?$/m', $content);
    }

    public function test_env_production_has_session_encrypt_enabled(): void
    {
        $path = base_path('.env.production');
        if (! file_exists($path)) {
            $this->markTestSkipped('.env.production does not exist in this environment');
        }
        $content = file_get_contents($path);

        $this->assertMatchesRegularExpression('/^SESSION_ENCRYPT=true\r?$/m', $content);
    }

    public function test_env_production_has_secure_cookie_enabled(): void
    {
        $path = base_path('.env.production');
        if (! file_exists($path)) {
            $this->markTestSkipped('.env.production does not exist in this environment');
        }
        $content = file_get_contents($path);

        $this->assertMatchesRegularExpression('/^SESSION_SECURE_COOKIE=true\r?$/m', $content);
    }

    public function test_env_production_has_no_hardcoded_db_password(): void
    {
        $path = base_path('.env.production');
        if (! file_exists($path)) {
            $this->markTestSkipped('.env.production does not exist in this environment');
        }
        $content = file_get_contents($path);

        preg_match('/^DB_PASSWORD=(.*)$/m', $content, $matches);

        $this->assertEmpty(
            trim($matches[1] ?? ''),
            'DB_PASSWORD should not contain a hardcoded value in .env.production'
        );
    }

    public function test_env_production_has_no_hardcoded_mail_password(): void
    {
        $path = base_path('.env.production');
        if (! file_exists($path)) {
            $this->markTestSkipped('.env.production does not exist in this environment');
        }
        $content = file_get_contents($path);

        preg_match('/^MAIL_PASSWORD=(.*)$/m', $content, $matches);

        $this->assertEmpty(
            trim($matches[1] ?? ''),
            'MAIL_PASSWORD should not contain a hardcoded value in .env.production'
        );
    }

    public function test_env_production_has_no_hardcoded_fincode_api_key(): void
    {
        $path = base_path('.env.production');
        if (! file_exists($path)) {
            $this->markTestSkipped('.env.production does not exist in this environment');
        }
        $content = file_get_contents($path);

        preg_match('/^FINCODE_API_KEY=(.*)$/m', $content, $matches);

        $this->assertEmpty(
            trim($matches[1] ?? ''),
            'FINCODE_API_KEY should not contain a hardcoded value in .env.production'
        );
    }

    public function test_store_subscription_request_only_allows_safe_fields(): void
    {
        $request = new \App\Http\Requests\StoreSubscriptionRequest;
        $rules = $request->rules();

        $allowedFields = array_keys($rules);
        $this->assertNotContains('status', $allowedFields, 'StoreSubscriptionRequest should not accept status');
        $this->assertNotContains('user_id', $allowedFields, 'StoreSubscriptionRequest should not accept user_id');
        $this->assertNotContains('canceled_at', $allowedFields, 'StoreSubscriptionRequest should not accept canceled_at');
        $this->assertNotContains('ends_at', $allowedFields, 'StoreSubscriptionRequest should not accept ends_at');
    }

    public function test_card_resource_does_not_expose_holder_name(): void
    {
        $card = new FincodeCard;
        $card->id = 1;
        $card->brand = 'visa';
        $card->last4 = '4242';
        $card->exp_month = '12';
        $card->exp_year = '2030';
        $card->holder_name = 'TEST USER';
        $card->is_default = true;
        $card->fincode_card_id = 'card_123';
        $card->fincode_customer_id = 'cus_123';

        $resource = new \App\Http\Resources\CardResource($card);
        $array = $resource->resolve();

        $this->assertArrayNotHasKey('holder_name', $array);
    }

    public function test_cors_config_restricts_origins(): void
    {
        $corsConfig = config('cors');

        $this->assertNotNull($corsConfig);
        $this->assertNotContains('*', $corsConfig['allowed_origins']);
        $this->assertTrue($corsConfig['supports_credentials']);
    }

    public function test_subscription_status_enum_defines_allowed_values(): void
    {
        $values = SubscriptionStatus::values();

        $this->assertIsArray($values);
        $this->assertContains('active', $values);
        $this->assertContains('canceled', $values);
        $this->assertContains('incomplete', $values);
        $this->assertNotContains('hacked', $values);
        $this->assertNull(SubscriptionStatus::tryFromApi('hacked'));
    }

    public function test_security_headers_middleware_exists(): void
    {
        $this->assertTrue(class_exists(SecurityHeaders::class));

        $middleware = new SecurityHeaders;
        $request = Request::create('https://example.com/test', 'GET');
        $response = $middleware->handle($request, function () {
            return new Response('OK');
        });
        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertEquals('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertEquals('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertEquals('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        $this->assertNotNull($csp);
        $this->assertStringContainsString('https://js.fincode.jp', $csp);
        $this->assertStringContainsString("style-src 'self' 'nonce-", $csp);
        $this->assertStringNotContainsString("'unsafe-inline'", $csp);
        $this->assertStringContainsString('report-uri /api/security/csp-reports', $csp);
        $this->assertSame(
            'max-age=31536000; includeSubDomains; preload',
            $response->headers->get('Strict-Transport-Security')
        );
    }

    public function test_security_headers_middleware_skips_hsts_for_insecure_requests(): void
    {
        $middleware = new SecurityHeaders;
        $request = Request::create('http://example.com/test', 'GET');
        $response = $middleware->handle($request, function () {
            return new Response('OK');
        });

        $this->assertNull($response->headers->get('Strict-Transport-Security'));
    }

    public function test_security_headers_middleware_is_registered_globally(): void
    {
        $app = $this->app;
        $kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
        $globalMiddleware = $kernel->getGlobalMiddleware();

        $this->assertContains(
            \App\Http\Middleware\SecurityHeaders::class,
            $globalMiddleware,
            'SecurityHeaders middleware should be registered in the global middleware stack'
        );
    }

    public function test_csp_report_endpoint_accepts_legacy_reports_and_logs_violation(): void
    {
        Log::spy();

        $response = $this->call(
            'POST',
            '/api/security/csp-reports',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/csp-report',
                'HTTP_USER_AGENT' => 'SecurityTest/1.0',
                'REMOTE_ADDR' => '127.0.0.1',
            ],
            json_encode([
                'csp-report' => [
                    'document-uri' => 'https://example.com/account',
                    'blocked-uri' => 'inline',
                    'violated-directive' => 'style-src-elem',
                    'effective-directive' => 'style-src-elem',
                    'original-policy' => "default-src 'self'",
                    'line-number' => 12,
                    'column-number' => 8,
                ],
            ], JSON_THROW_ON_ERROR)
        );

        $response->assertNoContent();

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Content Security Policy violation reported.'
                    && $context['kind'] === 'csp-report'
                    && $context['document_uri'] === 'https://example.com/account'
                    && $context['blocked_uri'] === 'inline'
                    && $context['effective_directive'] === 'style-src-elem'
                    && $context['line_number'] === 12
                    && $context['column_number'] === 8
                    && $context['user_agent'] === 'SecurityTest/1.0'
                    && $context['ip_address'] === '127.0.0.1';
            });
    }

    public function test_csp_report_endpoint_accepts_reporting_api_payloads_and_logs_violation(): void
    {
        Log::spy();

        $response = $this->call(
            'POST',
            '/api/security/csp-reports',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/reports+json',
                'HTTP_USER_AGENT' => 'SecurityTest/2.0',
                'REMOTE_ADDR' => '127.0.0.1',
            ],
            json_encode([
                [
                    'age' => 10,
                    'type' => 'csp-violation',
                    'url' => 'https://example.com/settings',
                    'body' => [
                        'blockedURL' => 'inline',
                        'effectiveDirective' => 'style-src-attr',
                        'originalPolicy' => "default-src 'self'",
                        'lineNumber' => 20,
                        'columnNumber' => 4,
                    ],
                ],
            ], JSON_THROW_ON_ERROR)
        );

        $response->assertNoContent();

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Content Security Policy violation reported.'
                    && $context['kind'] === 'report-to'
                    && $context['type'] === 'csp-violation'
                    && $context['age'] === 10
                    && $context['document_uri'] === 'https://example.com/settings'
                    && $context['blocked_uri'] === 'inline'
                    && $context['effective_directive'] === 'style-src-attr'
                    && $context['line_number'] === 20
                    && $context['column_number'] === 4
                    && $context['user_agent'] === 'SecurityTest/2.0'
                    && $context['ip_address'] === '127.0.0.1';
            });
    }
}
