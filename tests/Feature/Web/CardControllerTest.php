<?php

namespace Tests\Feature\Web;

use App\Exceptions\CardInUseException;
use App\Exceptions\FincodeApiException;
use App\Models\FincodeCard;
use App\Models\FincodeCustomer;
use App\Models\User;
use App\Services\CardManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CardControllerTest extends TestCase
{
    use RefreshDatabase;

    private function setValidFincodeConfig(): void
    {
        config()->set('fincode.public_key', 'p_test_123');
        config()->set('fincode.base_url', 'https://api.test.fincode.jp');
        config()->set('fincode.sdk_sri_hash', '');
    }

    private function createUserWithCard(): array
    {
        $user = User::factory()->create();

        FincodeCustomer::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_test_123',
            'name' => $user->name,
            'email' => $user->email,
        ]);

        $card = FincodeCard::create([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_test_123',
            'fincode_card_id' => 'card_test_123',
            'brand' => 'Visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'holder_name' => 'TEST USER',
            'is_default' => true,
        ]);

        return [$user, $card];
    }

    public function test_create_renders_card_form(): void
    {
        $this->setValidFincodeConfig();

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/cards/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Card/Create')
            ->where('fincode_public_key', 'p_test_123')
            ->where('fincode_sdk_url', 'https://js.test.fincode.jp/v1/fincode.js')
            ->where('fincode_sdk_sri_hash', null)
            ->where('fincode_config_error', null)
        );
    }

    public function test_create_passes_sri_hash_when_configured(): void
    {
        $this->setValidFincodeConfig();
        config()->set('fincode.sdk_sri_hash', 'sha384-oqVuAfXRKap7fdgcCY5uykM6+R9GqQ8K/uxy9rx7HNQlGYl1kPzQho1wx4JwY8wC');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/cards/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Card/Create')
            ->where('fincode_sdk_sri_hash', 'sha384-oqVuAfXRKap7fdgcCY5uykM6+R9GqQ8K/uxy9rx7HNQlGYl1kPzQho1wx4JwY8wC')
        );
    }

    public function test_create_shows_error_when_public_key_is_missing(): void
    {
        config()->set('fincode.public_key', null);
        config()->set('fincode.base_url', 'https://api.test.fincode.jp');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/cards/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Card/Create')
            ->where('fincode_public_key', '')
            ->where('fincode_sdk_url', null)
            ->where('fincode_config_error', '決済設定エラー: FINCODE_PUBLIC_KEY が未設定です。運用管理者にお問い合わせください。')
        );
    }

    public function test_create_shows_error_when_public_key_format_is_invalid(): void
    {
        config()->set('fincode.public_key', 'invalid_key');
        config()->set('fincode.base_url', 'https://api.test.fincode.jp');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/cards/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Card/Create')
            ->where('fincode_public_key', '')
            ->where('fincode_sdk_url', null)
            ->where('fincode_config_error', '決済設定エラー: FINCODE_PUBLIC_KEY の形式が不正です。運用管理者にお問い合わせください。')
        );
    }

    public function test_create_shows_error_when_base_url_is_missing(): void
    {
        config()->set('fincode.public_key', 'p_test_123');
        config()->set('fincode.base_url', null);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/cards/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Card/Create')
            ->where('fincode_public_key', '')
            ->where('fincode_sdk_url', null)
            ->where('fincode_config_error', '決済設定エラー: FINCODE_BASE_URL が未設定です。運用管理者にお問い合わせください。')
        );
    }

    public function test_create_shows_error_when_base_url_is_invalid(): void
    {
        config()->set('fincode.public_key', 'p_test_123');
        config()->set('fincode.base_url', 'https://example.com');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/cards/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Card/Create')
            ->where('fincode_public_key', '')
            ->where('fincode_sdk_url', null)
            ->where('fincode_config_error', '決済設定エラー: FINCODE_BASE_URL は https://api.fincode.jp または https://api.test.fincode.jp を指定してください。')
        );
    }

    public function test_create_shows_error_when_public_key_and_base_url_environment_mismatch(): void
    {
        config()->set('fincode.public_key', 'p_test_123');
        config()->set('fincode.base_url', 'https://api.fincode.jp');

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/cards/create');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Card/Create')
            ->where('fincode_public_key', '')
            ->where('fincode_sdk_url', 'https://js.fincode.jp/v1/fincode.js')
            ->where('fincode_config_error', '決済設定エラー: FINCODE_PUBLIC_KEY と FINCODE_BASE_URL の環境が一致していません。運用管理者にお問い合わせください。')
        );
    }

    public function test_create_requires_authentication(): void
    {
        $response = $this->get('/cards/create');

        $response->assertRedirect('/login');
    }

    public function test_store_creates_card_successfully(): void
    {
        [$user, $existingCard] = $this->createUserWithCard();

        $mockCard = FincodeCard::make([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_test_123',
            'fincode_card_id' => 'card_new_123',
            'brand' => 'Mastercard',
            'last4' => '5678',
            'exp_month' => 6,
            'exp_year' => 2028,
            'holder_name' => 'TEST USER',
            'is_default' => false,
        ]);

        $cardManager = Mockery::mock(CardManager::class);
        $cardManager->shouldReceive('create')
            ->once()
            ->andReturn($mockCard);
        $this->app->instance(CardManager::class, $cardManager);

        $response = $this->actingAs($user)->post('/cards', [
            'token' => 'tok_test_abc123_12345',
            'is_default' => false,
        ]);

        $response->assertRedirect(route('subscription.index'));
        $response->assertSessionHas('success', 'カードを登録しました。');
    }

    public function test_store_validates_token_required(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/cards', []);

        $response->assertSessionHasErrors('token');
    }

    public function test_store_handles_fincode_error(): void
    {
        $user = User::factory()->create();

        $cardManager = Mockery::mock(CardManager::class);
        $cardManager->shouldReceive('create')
            ->once()
            ->andThrow(new FincodeApiException('Fincode API error', 500));
        $this->app->instance(CardManager::class, $cardManager);

        $response = $this->actingAs($user)->post('/cards', [
            'token' => 'tok_test_abc123_12345',
        ]);

        $response->assertRedirect(route('cards.create'));
        $response->assertSessionHasErrors('card');
    }

    public function test_store_requires_authentication(): void
    {
        $response = $this->post('/cards', [
            'token' => 'tok_test_abc123_12345',
        ]);

        $response->assertRedirect('/login');
    }

    public function test_destroy_deletes_own_card(): void
    {
        [$user, $card] = $this->createUserWithCard();

        $cardManager = Mockery::mock(CardManager::class);
        $cardManager->shouldReceive('delete')
            ->once()
            ->with(Mockery::on(fn ($c) => $c->id === $card->id))
            ->andReturnNull();
        $this->app->instance(CardManager::class, $cardManager);

        $response = $this->actingAs($user)->delete("/cards/{$card->id}");

        $response->assertRedirect(route('subscription.index'));
        $response->assertSessionHas('success', 'カードを削除しました。');
    }

    public function test_destroy_handles_card_in_use_error(): void
    {
        [$user, $card] = $this->createUserWithCard();

        $cardManager = Mockery::mock(CardManager::class);
        $cardManager->shouldReceive('delete')
            ->once()
            ->with(Mockery::on(fn ($c) => $c->id === $card->id))
            ->andThrow(new CardInUseException);
        $this->app->instance(CardManager::class, $cardManager);

        $response = $this->actingAs($user)->delete("/cards/{$card->id}");

        $response->assertRedirect(route('subscription.index'));
        $response->assertSessionHasErrors([
            'card' => 'アクティブなサブスクリプションで使用中のカードは削除できません。',
        ]);
    }

    public function test_destroy_handles_fincode_api_error(): void
    {
        [$user, $card] = $this->createUserWithCard();

        $cardManager = Mockery::mock(CardManager::class);
        $cardManager->shouldReceive('delete')
            ->once()
            ->with(Mockery::on(fn ($c) => $c->id === $card->id))
            ->andThrow(new FincodeApiException('Fincode API error', 503));
        $this->app->instance(CardManager::class, $cardManager);

        $response = $this->actingAs($user)->delete("/cards/{$card->id}");

        $response->assertRedirect(route('subscription.index'));
        $response->assertSessionHasErrors([
            'card' => 'カードの削除に失敗しました。時間をおいて再試行してください。',
        ]);
    }

    public function test_destroy_rejects_other_users_card(): void
    {
        [$owner, $card] = $this->createUserWithCard();
        $otherUser = User::factory()->create();

        $response = $this->actingAs($otherUser)->delete("/cards/{$card->id}");

        $response->assertStatus(403);
    }

    public function test_destroy_requires_authentication(): void
    {
        [$user, $card] = $this->createUserWithCard();

        $response = $this->delete("/cards/{$card->id}");

        $response->assertRedirect('/login');
    }
}
