<?php

namespace Tests\Feature\Database;

use App\Models\FincodeCard;
use App\Models\FincodeCustomer;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubscriptionDatabaseConstraintsTest extends TestCase
{
    use RefreshDatabase;

    private function createUser(): User
    {
        return User::factory()->create();
    }

    private function uniqueSuffix(): string
    {
        return Str::lower(Str::random(8));
    }

    private function createCustomer(User $user, string $customerId): FincodeCustomer
    {
        return FincodeCustomer::create([
            'user_id' => $user->id,
            'fincode_customer_id' => $customerId,
            'name' => $user->name,
            'email' => $user->email,
        ]);
    }

    private function createCard(User $user, string $customerId, string $cardId): FincodeCard
    {
        return FincodeCard::create([
            'user_id' => $user->id,
            'fincode_customer_id' => $customerId,
            'fincode_card_id' => $cardId,
            'brand' => 'Visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'holder_name' => 'TEST USER',
            'is_default' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function insertSubscription(User $user, FincodeCustomer $customer, FincodeCard $card, array $overrides = []): int
    {
        $now = now();

        return DB::table('subscriptions')->insertGetId(array_merge([
            'user_id' => $user->id,
            'fincode_plan_id' => 'pl_db_test',
            'plan_name' => 'DB Constraint Plan',
            'plan_amount' => 1000,
            'plan_interval' => 'monthly',
            'plan_interval_count' => 1,
            'plan_snapshot' => json_encode([
                'name' => 'DB Constraint Plan',
                'amount' => 1000,
                'interval' => 'monthly',
            ]),
            'fincode_subscription_id' => 'sub_db_test_001',
            'fincode_customer_id' => $customer->fincode_customer_id,
            'fincode_card_id' => $card->fincode_card_id,
            'status' => 'active',
            'start_date' => $now->toDateString(),
            'next_charge_date' => $now->copy()->addMonth()->toDateString(),
            'metadata' => json_encode(['source' => 'database-test']),
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides));
    }

    public function test_db_unique_index_blocks_second_active_subscription_for_same_user(): void
    {
        $user = $this->createUser();
        $customer = $this->createCustomer($user, 'cus_'.$this->uniqueSuffix());
        $card = $this->createCard($user, $customer->fincode_customer_id, 'card_'.$this->uniqueSuffix());

        $this->insertSubscription($user, $customer, $card, [
            'fincode_subscription_id' => 'sub_'.$this->uniqueSuffix(),
        ]);

        try {
            $this->insertSubscription($user, $customer, $card, [
                'fincode_subscription_id' => 'sub_'.$this->uniqueSuffix(),
            ]);
            $this->fail('A second active subscription for the same user should violate the database unique constraint.');
        } catch (QueryException $exception) {
            $this->assertSame('23000', $exception->errorInfo[0] ?? null);
            $this->assertStringContainsString('subscriptions_active_user_id_unique', $exception->getMessage());
        }
    }

    public function test_db_unique_index_allows_replacement_after_soft_delete(): void
    {
        $user = $this->createUser();
        $customer = $this->createCustomer($user, 'cus_'.$this->uniqueSuffix());
        $card = $this->createCard($user, $customer->fincode_customer_id, 'card_'.$this->uniqueSuffix());

        $existingSubscriptionId = $this->insertSubscription($user, $customer, $card, [
            'fincode_subscription_id' => 'sub_'.$this->uniqueSuffix(),
        ]);

        Subscription::query()->findOrFail($existingSubscriptionId)->delete();

        $replacementSubscriptionId = $this->insertSubscription($user, $customer, $card, [
            'fincode_subscription_id' => 'sub_'.$this->uniqueSuffix(),
        ]);

        $this->assertSame(2, DB::table('subscriptions')->where('user_id', $user->id)->count());
        $this->assertSame(1, DB::table('subscriptions')
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->count());
        $this->assertDatabaseHas('subscriptions', [
            'id' => $replacementSubscriptionId,
            'user_id' => $user->id,
            'status' => 'active',
        ]);
    }

    public function test_force_deleting_customer_cascades_to_subscription_and_card_records(): void
    {
        $targetUser = $this->createUser();
        $targetCustomer = $this->createCustomer($targetUser, 'cus_'.$this->uniqueSuffix());
        $targetCard = $this->createCard($targetUser, $targetCustomer->fincode_customer_id, 'card_'.$this->uniqueSuffix());
        $targetSubscriptionId = $this->insertSubscription($targetUser, $targetCustomer, $targetCard, [
            'fincode_subscription_id' => 'sub_'.$this->uniqueSuffix(),
        ]);

        $survivingUser = $this->createUser();
        $survivingCustomer = $this->createCustomer($survivingUser, 'cus_'.$this->uniqueSuffix());
        $survivingCard = $this->createCard($survivingUser, $survivingCustomer->fincode_customer_id, 'card_'.$this->uniqueSuffix());
        $survivingSubscriptionId = $this->insertSubscription($survivingUser, $survivingCustomer, $survivingCard, [
            'fincode_subscription_id' => 'sub_'.$this->uniqueSuffix(),
        ]);

        $targetCustomer->forceDelete();

        $this->assertDatabaseMissing('fincode_customers', [
            'id' => $targetCustomer->id,
        ]);
        $this->assertDatabaseMissing('fincode_cards', [
            'id' => $targetCard->id,
        ]);
        $this->assertDatabaseMissing('subscriptions', [
            'id' => $targetSubscriptionId,
        ]);

        $this->assertDatabaseHas('fincode_customers', [
            'id' => $survivingCustomer->id,
        ]);
        $this->assertDatabaseHas('fincode_cards', [
            'id' => $survivingCard->id,
        ]);
        $this->assertDatabaseHas('subscriptions', [
            'id' => $survivingSubscriptionId,
        ]);
    }
}
