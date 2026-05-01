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
            'token' => 'tok_test_abc123',
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
            'token' => 'tok_test_abc123',
            'is_default' => 'not-a-boolean',
        ]);

        $response->assertSessionHasErrors('is_default');
    }

    public function test_is_default_is_optional(): void
    {
        $response = $this->actingAs($this->user)->post(route('cards.store'), [
            'token' => 'tok_test_abc123',
        ]);

        $response->assertSessionDoesntHaveErrors(['token', 'is_default']);
    }
}
