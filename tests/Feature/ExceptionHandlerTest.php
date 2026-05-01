<?php

namespace Tests\Feature;

use App\Exceptions\ActiveSubscriptionExistsException;
use App\Exceptions\FincodeApiException;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ExceptionHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_subscription_exists_exception_returns_422_json_for_api(): void
    {
        $user = User::factory()->create();

        Route::middleware('api')->get('/api/test/active-sub', function () {
            throw new ActiveSubscriptionExistsException;
        });

        $response = $this->actingAs($user)->getJson('/api/test/active-sub');

        $response->assertStatus(422)
            ->assertJsonPath('message', '既にアクティブなサブスクリプションがあります。')
            ->assertJsonPath('errors.fincode_plan_id.0', '既にアクティブなサブスクリプションがあります。');
    }

    public function test_active_subscription_exists_exception_redirects_back_with_errors_for_web(): void
    {
        $user = User::factory()->create();

        Route::middleware('web')->get('/test/active-sub', function () {
            throw new ActiveSubscriptionExistsException;
        });

        $response = $this->actingAs($user)
            ->from('/plans/pl_test')
            ->get('/test/active-sub');

        $response->assertRedirect('/plans/pl_test');
        $response->assertSessionHasErrors('fincode_plan_id');
    }

    public function test_fincode_api_exception_5xx_returns_503_json_for_api(): void
    {
        $user = User::factory()->create();

        Route::middleware('api')->get('/api/test/fincode-5xx', function () {
            throw new FincodeApiException('Server error', 500);
        });

        $response = $this->actingAs($user)->getJson('/api/test/fincode-5xx');

        $response->assertStatus(503)
            ->assertJsonPath('message', '決済サービスとの通信でエラーが発生しました。');
    }

    public function test_fincode_api_exception_4xx_returns_client_error_json_for_api(): void
    {
        $user = User::factory()->create();

        Route::middleware('api')->get('/api/test/fincode-4xx', function () {
            throw new FincodeApiException('Bad request', 400, ['error_code' => 'E01']);
        });

        $response = $this->actingAs($user)->getJson('/api/test/fincode-4xx');

        $response->assertStatus(400)
            ->assertJsonPath('message', '決済サービスとの通信でエラーが発生しました。')
            ->assertJsonMissing(['error_code' => 'E01']);
    }

    public function test_fincode_api_exception_web_handler_returns_correct_status(): void
    {
        Route::middleware('web')->get('/test/fincode-5xx', function () {
            throw new FincodeApiException('Server error', 500);
        });

        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get('/test/fincode-5xx', ['Accept' => 'application/json']);

        $response->assertStatus(503);
    }
}
