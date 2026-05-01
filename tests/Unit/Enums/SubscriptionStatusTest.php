<?php

namespace Tests\Unit\Enums;

use App\Enums\SubscriptionStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SubscriptionStatusTest extends TestCase
{
    #[Test]
    public function enum_values_match_database_enum_definition(): void
    {
        $expected = ['active', 'canceled', 'expired', 'unpaid', 'incomplete'];

        $this->assertSame($expected, SubscriptionStatus::values());
    }

    #[Test]
    public function each_case_has_correct_string_value(): void
    {
        $this->assertSame('active', SubscriptionStatus::Active->value);
        $this->assertSame('canceled', SubscriptionStatus::Canceled->value);
        $this->assertSame('expired', SubscriptionStatus::Expired->value);
        $this->assertSame('unpaid', SubscriptionStatus::Unpaid->value);
        $this->assertSame('incomplete', SubscriptionStatus::Incomplete->value);
    }

    #[Test]
    public function try_from_api_converts_uppercase_status(): void
    {
        $this->assertSame(SubscriptionStatus::Active, SubscriptionStatus::tryFromApi('ACTIVE'));
        $this->assertSame(SubscriptionStatus::Canceled, SubscriptionStatus::tryFromApi('CANCELED'));
        $this->assertSame(SubscriptionStatus::Expired, SubscriptionStatus::tryFromApi('EXPIRED'));
        $this->assertSame(SubscriptionStatus::Unpaid, SubscriptionStatus::tryFromApi('UNPAID'));
        $this->assertSame(SubscriptionStatus::Incomplete, SubscriptionStatus::tryFromApi('INCOMPLETE'));
    }

    #[Test]
    public function try_from_api_converts_lowercase_status(): void
    {
        $this->assertSame(SubscriptionStatus::Active, SubscriptionStatus::tryFromApi('active'));
    }

    #[Test]
    public function try_from_api_converts_mixed_case_status(): void
    {
        $this->assertSame(SubscriptionStatus::Active, SubscriptionStatus::tryFromApi('Active'));
        $this->assertSame(SubscriptionStatus::Canceled, SubscriptionStatus::tryFromApi('Canceled'));
    }

    #[Test]
    public function try_from_api_returns_null_for_unknown_status(): void
    {
        $this->assertNull(SubscriptionStatus::tryFromApi('UNKNOWN'));
        $this->assertNull(SubscriptionStatus::tryFromApi(''));
        $this->assertNull(SubscriptionStatus::tryFromApi('pending'));
    }
}
