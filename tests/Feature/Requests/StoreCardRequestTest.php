<?php

namespace Tests\Feature\Requests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreCardRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_valid_token_and_is_default_passes(): void
    {
        $response = $this->actingAs($this->user)->post(route('cards.store'), [
            'token' => 'tok_test_abcdefghijklmnopqr',
            'is_default' => true,
        ]);

        // CardManagerが外部APIを呼ぶためバリデーション通過後にエラーになるが、422ではない
        $response->assertSessionDoesntHaveErrors(['token', 'is_default']);
    }

    public function test_token_is_required(): void
    {
        $response = $this->actingAs($this->user)->post(route('cards.store'), [
            'is_default' => false,
        ]);

        $response->assertSessionHasErrors('token');
    }

    public function test_token_must_be_string(): void
    {
        $response = $this->actingAs($this->user)->post(route('cards.store'), [
            'token' => 12345,
        ]);

        $response->assertSessionHasErrors('token');
    }

    public function test_is_default_must_be_boolean(): void
    {
        $response = $this->actingAs($this->user)->post(route('cards.store'), [
            'token' => 'tok_test_abcdefghijklmnopqr',
            'is_default' => 'not-a-boolean',
        ]);

        $response->assertSessionHasErrors('is_default');
    }

    public function test_is_default_is_optional(): void
    {
        $response = $this->actingAs($this->user)->post(route('cards.store'), [
            'token' => 'tok_test_abcdefghijklmnopqr',
        ]);

        $response->assertSessionDoesntHaveErrors(['token', 'is_default']);
    }

    public function test_token_with_invalid_characters_is_rejected(): void
    {
        $response = $this->actingAs($this->user)->post(route('cards.store'), [
            'token' => 'invalid token with spaces!!!!',
        ]);

        $response->assertSessionHasErrors('token');
    }

    public function test_token_too_short_is_rejected(): void
    {
        $response = $this->actingAs($this->user)->post(route('cards.store'), [
            'token' => 'short',
        ]);

        $response->assertSessionHasErrors('token');
    }

    public function test_replayed_token_is_rejected(): void
    {
        $token = 'tok_replay_'.str_repeat('a', 20);

        $this->actingAs($this->user)->post(route('cards.store'), [
            'token' => $token,
        ]);

        // 同じトークンの 2 回目送信は二重送信検出キャッシュにより拒否される。
        $response = $this->actingAs($this->user)->post(route('cards.store'), [
            'token' => $token,
        ]);

        $response->assertSessionHasErrors('token');
    }
}
