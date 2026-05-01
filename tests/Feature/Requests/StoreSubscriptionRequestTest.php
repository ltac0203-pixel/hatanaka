<?php

namespace Tests\Feature\Requests;

use App\Http\Requests\StoreSubscriptionRequest;
use App\Models\FincodeCard;
use App\Models\FincodeCustomer;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreSubscriptionRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private FincodeCustomer $customer;

    private FincodeCard $card;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->customer = FincodeCustomer::create([
            'user_id' => $this->user->id,
            'fincode_customer_id' => 'c_test_'.fake()->unique()->bothify('??########'),
            'name' => $this->user->name,
            'email' => $this->user->email,
        ]);

        $this->card = FincodeCard::create([
            'user_id' => $this->user->id,
            'fincode_customer_id' => $this->customer->fincode_customer_id,
            'fincode_card_id' => 'cs_test_'.fake()->unique()->bothify('??########'),
            'brand' => 'Visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => now()->addYear()->year,
            'is_default' => true,
        ]);
    }

    public function test_valid_input_passes_validation(): void
    {
        $response = $this->actingAs($this->user)->post(route('subscription.store'), [
            'fincode_plan_id' => 'pl_test_plan001',
            'card_id' => $this->card->id,
            'start_date' => now()->addDay()->format('Y-m-d'),
        ]);

        $response->assertSessionDoesntHaveErrors(['fincode_plan_id', 'card_id', 'start_date']);
    }

    public function test_fincode_plan_id_is_required(): void
    {
        $response = $this->actingAs($this->user)->post(route('subscription.store'), [
            'card_id' => $this->card->id,
            'start_date' => now()->addDay()->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('fincode_plan_id');
    }

    public function test_card_id_is_required(): void
    {
        $response = $this->actingAs($this->user)->post(route('subscription.store'), [
            'fincode_plan_id' => 'pl_test_plan001',
            'start_date' => now()->addDay()->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('card_id');
    }

    public function test_card_id_must_exist_in_database(): void
    {
        $response = $this->actingAs($this->user)->post(route('subscription.store'), [
            'fincode_plan_id' => 'pl_test_plan001',
            'card_id' => 99999,
            'start_date' => now()->addDay()->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('card_id');
    }

    public function test_start_date_is_required(): void
    {
        $response = $this->actingAs($this->user)->post(route('subscription.store'), [
            'fincode_plan_id' => 'pl_test_plan001',
            'card_id' => $this->card->id,
        ]);

        $response->assertSessionHasErrors('start_date');
    }

    public function test_start_date_must_be_today_or_later(): void
    {
        $response = $this->actingAs($this->user)->post(route('subscription.store'), [
            'fincode_plan_id' => 'pl_test_plan001',
            'card_id' => $this->card->id,
            'start_date' => now()->subDay()->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('start_date');
    }

    public function test_active_subscription_is_rejected(): void
    {
        Subscription::create([
            'user_id' => $this->user->id,
            'fincode_plan_id' => 'pl_existing',
            'plan_name' => 'Existing Plan',
            'plan_amount' => 1000,
            'plan_interval' => 'monthly',
            'plan_interval_count' => 1,
            'fincode_subscription_id' => 'sb_test_existing',
            'fincode_customer_id' => $this->customer->fincode_customer_id,
            'fincode_card_id' => $this->card->fincode_card_id,
            'status' => 'active',
            'start_date' => now()->subMonth()->format('Y-m-d'),
        ]);

        $response = $this->actingAs($this->user)->post(route('subscription.store'), [
            'fincode_plan_id' => 'pl_test_plan001',
            'card_id' => $this->card->id,
            'start_date' => now()->addDay()->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('fincode_plan_id');
    }

    public function test_other_users_card_is_rejected(): void
    {
        $otherUser = User::factory()->create();
        $otherCustomer = FincodeCustomer::create([
            'user_id' => $otherUser->id,
            'fincode_customer_id' => 'c_test_'.fake()->unique()->bothify('??########'),
            'name' => $otherUser->name,
            'email' => $otherUser->email,
        ]);
        $otherCard = FincodeCard::create([
            'user_id' => $otherUser->id,
            'fincode_customer_id' => $otherCustomer->fincode_customer_id,
            'fincode_card_id' => 'cs_test_'.fake()->unique()->bothify('??########'),
            'brand' => 'Mastercard',
            'last4' => '5555',
            'exp_month' => 12,
            'exp_year' => now()->addYear()->year,
            'is_default' => true,
        ]);

        $response = $this->actingAs($this->user)->post(route('subscription.store'), [
            'fincode_plan_id' => 'pl_test_plan001',
            'card_id' => $otherCard->id,
            'start_date' => now()->addDay()->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('card_id');
    }

    public function test_expired_card_is_rejected(): void
    {
        $expiredCard = FincodeCard::create([
            'user_id' => $this->user->id,
            'fincode_customer_id' => $this->customer->fincode_customer_id,
            'fincode_card_id' => 'cs_test_'.fake()->unique()->bothify('??########'),
            'brand' => 'Visa',
            'last4' => '1111',
            'exp_month' => 1,
            'exp_year' => now()->subYear()->year,
            'is_default' => false,
        ]);

        $response = $this->actingAs($this->user)->post(route('subscription.store'), [
            'fincode_plan_id' => 'pl_test_plan001',
            'card_id' => $expiredCard->id,
            'start_date' => now()->addDay()->format('Y-m-d'),
        ]);

        $response->assertSessionHasErrors('card_id');
    }

    public function test_get_validated_card_returns_correct_instance(): void
    {
        $request = StoreSubscriptionRequest::create(
            route('subscription.store'),
            'POST',
            [
                'fincode_plan_id' => 'pl_test_plan001',
                'card_id' => $this->card->id,
                'start_date' => now()->addDay()->format('Y-m-d'),
            ]
        );

        $request->setUserResolver(fn () => $this->user);
        $request->setContainer(app());

        app()->call([$request, 'validateResolved']);

        $validatedCard = $request->getValidatedCard();
        $this->assertNotNull($validatedCard);
        $this->assertEquals($this->card->id, $validatedCard->id);
    }
}
