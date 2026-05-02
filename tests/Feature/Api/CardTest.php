<?php

namespace Tests\Feature\Api;

use App\Models\FincodeCard;
use App\Models\FincodeCustomer;
use App\Models\User;
use App\Services\CardManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CardTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithCard(): array
    {
        $user = User::factory()->create();

        $customer = FincodeCustomer::create([
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

    public function test_authenticated_user_can_list_cards(): void
    {
        [$user, $card] = $this->createUserWithCard();

        $response = $this->actingAs($user)->getJson('/api/subscription/cards');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.brand', 'Visa')
            ->assertJsonPath('data.0.last4', '4242')
            ->assertJsonPath('data.0.display_name', 'Visa ****4242');
    }

    public function test_user_cannot_see_other_users_cards(): void
    {
        [$user1, $card] = $this->createUserWithCard();
        $user2 = User::factory()->create();

        $response = $this->actingAs($user2)->getJson('/api/subscription/cards');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_store_card_requires_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/subscription/cards', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }

    public function test_user_can_store_card(): void
    {
        $user = User::factory()->create();

        $mockCard = new FincodeCard([
            'user_id' => $user->id,
            'fincode_customer_id' => 'cus_test',
            'fincode_card_id' => 'card_test',
            'brand' => 'Visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'holder_name' => 'TEST',
            'is_default' => true,
        ]);
        $mockCard->id = 1;

        $cardManager = Mockery::mock(CardManager::class);
        $cardManager->shouldReceive('create')
            ->once()
            ->with($user, 'tok_test_123_12345678', false)
            ->andReturn($mockCard);

        $this->app->instance(CardManager::class, $cardManager);

        $response = $this->actingAs($user)->postJson('/api/subscription/cards', [
            'token' => 'tok_test_123_12345678',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.brand', 'Visa');
    }

    public function test_user_can_delete_own_card(): void
    {
        [$user, $card] = $this->createUserWithCard();

        $cardManager = Mockery::mock(CardManager::class);
        $cardManager->shouldReceive('delete')->once();
        $this->app->instance(CardManager::class, $cardManager);

        $response = $this->actingAs($user)->deleteJson("/api/subscription/cards/{$card->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'カードを削除しました。');
    }

    public function test_user_cannot_delete_other_users_card(): void
    {
        [$user1, $card] = $this->createUserWithCard();
        $user2 = User::factory()->create();

        $response = $this->actingAs($user2)->deleteJson("/api/subscription/cards/{$card->id}");

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_cards(): void
    {
        $response = $this->getJson('/api/subscription/cards');

        $response->assertStatus(401);
    }
}
