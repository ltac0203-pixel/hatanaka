<?php

namespace Tests\Unit\Models;

use App\Models\FincodeCard;
use App\Models\FincodeCustomer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FincodeCardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private FincodeCustomer $fincodeCustomer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->fincodeCustomer = FincodeCustomer::create([
            'user_id' => $this->user->id,
            'fincode_customer_id' => 'cus_test_001',
            'name' => $this->user->name,
            'email' => $this->user->email,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createCard(array $overrides = []): FincodeCard
    {
        return FincodeCard::create(array_merge([
            'user_id' => $this->user->id,
            'fincode_customer_id' => 'cus_test_001',
            'fincode_card_id' => 'card_test_001',
            'brand' => 'Visa',
            'last4' => '4242',
            'exp_month' => 12,
            'exp_year' => 2030,
            'holder_name' => 'TEST USER',
            'is_default' => true,
        ], $overrides));
    }

    public function test_display_name_attribute(): void
    {
        $card = $this->createCard(['brand' => 'Visa', 'last4' => '4242']);

        $this->assertSame('Visa ****4242', $card->display_name);
    }

    public function test_display_name_with_different_brands(): void
    {
        $brands = [
            ['brand' => 'Mastercard', 'last4' => '5555', 'fincode_card_id' => 'card_mc_001'],
            ['brand' => 'JCB', 'last4' => '3530', 'fincode_card_id' => 'card_jcb_001'],
            ['brand' => 'Amex', 'last4' => '0005', 'fincode_card_id' => 'card_amex_001'],
        ];

        foreach ($brands as $data) {
            $card = $this->createCard($data);

            $this->assertSame("{$data['brand']} ****{$data['last4']}", $card->display_name);

            $card->forceDelete();
        }
    }

    public function test_expiry_display_attribute(): void
    {
        $card = $this->createCard(['exp_month' => 1, 'exp_year' => 2030]);
        $this->assertSame('01/2030', $card->expiry_display);

        $card->forceDelete();

        $card = $this->createCard(['exp_month' => 12, 'exp_year' => 2025]);
        $this->assertSame('12/2025', $card->expiry_display);
    }

    public function test_expiry_display_with_single_digit_month(): void
    {
        $card = $this->createCard(['exp_month' => 3, 'exp_year' => 2026]);

        $this->assertSame('03/2026', $card->expiry_display);
    }

    public function test_card_is_not_expired_when_future(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 6, 15));

        $card = $this->createCard(['exp_month' => 12, 'exp_year' => 2030]);

        $this->assertFalse($card->isExpired());
    }

    public function test_card_is_expired_when_past(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 3, 1));

        $card = $this->createCard(['exp_month' => 1, 'exp_year' => 2026]);

        $this->assertTrue($card->isExpired());
    }

    public function test_card_is_not_expired_at_end_of_current_month(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 2, 28, 23, 59, 59));

        $card = $this->createCard(['exp_month' => 2, 'exp_year' => 2026]);

        $this->assertFalse($card->isExpired());
    }

    public function test_is_expired_attribute_matches_method(): void
    {
        Carbon::setTestNow(Carbon::create(2025, 6, 15));

        $card = $this->createCard(['exp_month' => 12, 'exp_year' => 2030]);
        $this->assertSame($card->isExpired(), $card->is_expired);

        $card->forceDelete();

        $card = $this->createCard(['exp_month' => 1, 'exp_year' => 2020]);
        $this->assertSame($card->isExpired(), $card->is_expired);
    }

    public function test_user_relationship(): void
    {
        $card = $this->createCard();

        $user = $card->user()->firstOrFail();

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame($this->user->id, $user->id);
    }

    public function test_soft_delete(): void
    {
        $card = $this->createCard();

        $card->delete();

        $this->assertSoftDeleted('fincode_cards', ['id' => $card->id]);
        $this->assertNull(FincodeCard::find($card->id));
        $this->assertNotNull(FincodeCard::withTrashed()->find($card->id));
    }
}
