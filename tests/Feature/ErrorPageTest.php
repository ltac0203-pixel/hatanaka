<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ErrorPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->get('/test/error/too-many-requests', fn () => abort(429));
        Route::middleware('web')->get('/test/error/gateway-timeout', fn () => abort(504));
        Route::middleware('api')->get('/api/test/error/too-many-requests', fn () => abort(429));
        Route::middleware('api')->get('/api/test/error/gateway-timeout', fn () => abort(504));
    }

    #[DataProvider('webErrorStatusProvider')]
    public function test_web_requests_render_custom_inertia_error_page_when_debug_is_disabled(string $path, int $status): void
    {
        config(['app.debug' => false]);

        $response = $this->get($path);

        $response->assertStatus($status);
        $response->assertInertia(fn ($page) => $page
            ->component('Error')
            ->where('status', $status)
        );
    }

    #[DataProvider('apiErrorStatusProvider')]
    public function test_api_requests_keep_json_error_response_when_debug_is_disabled(string $path, int $status): void
    {
        config(['app.debug' => false]);

        $response = $this->getJson($path);

        $response->assertStatus($status);
        $response->assertJsonStructure(['message']);
        $response->assertHeaderMissing('X-Inertia');
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function webErrorStatusProvider(): array
    {
        return [
            'not found' => ['/this-page-does-not-exist', 404],
            'too many requests' => ['/test/error/too-many-requests', 429],
            'gateway timeout' => ['/test/error/gateway-timeout', 504],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function apiErrorStatusProvider(): array
    {
        return [
            'not found' => ['/api/this-endpoint-does-not-exist', 404],
            'too many requests' => ['/api/test/error/too-many-requests', 429],
            'gateway timeout' => ['/api/test/error/gateway-timeout', 504],
        ];
    }
}
