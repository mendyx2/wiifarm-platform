<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_naira_text_becomes_kobo_exactly()
    {
        $this->assertSame(150050, Money::parseNaira('1,500.50'));
        $this->assertSame(150050, Money::parseNaira('1500.5'));
        $this->assertSame(1, Money::parseNaira('0.01'));
        $this->assertSame(1500000, Money::parseNaira('₦15,000'));
    }

    /** @return array<string, array{string}> */
    public static function badAmounts(): array
    {
        return ['negative' => ['-5'], 'three decimals' => ['1.005'], 'words' => ['ten'], 'empty' => [''], 'scientific' => ['1e5']];
    }

    #[DataProvider('badAmounts')]
    public function test_anything_else_is_refused(string $text)
    {
        $this->expectException(InvalidArgumentException::class);
        Money::parseNaira($text);
    }

    public function test_shares_round_down_to_the_kobo()
    {
        // The specification's own example: direct referral 6% of a ₦15,000 pack is ₦900.
        $this->assertSame(90_000, Money::share(1_500_000, '6.00'));
        $this->assertSame(371_250, Money::share(1_500_000, '24.75'));
        $this->assertSame(0, Money::share(1, '50'));
    }

    public function test_a_pack_splits_exactly_as_the_specification_says()
    {
        $parts = Money::allocate(1_500_000, [
            'company' => '30', 'executive' => '10', 'operations' => '5', 'members' => '55',
        ], remainderTo: 'members');

        $this->assertSame(['company' => 450_000, 'executive' => 150_000, 'operations' => 75_000, 'members' => 825_000], $parts);
    }

    public function test_the_rounding_remainder_goes_to_the_named_part_and_nothing_is_lost()
    {
        $parts = Money::allocate(100, ['a' => '33.33', 'b' => '33.33', 'c' => '33.34'], remainderTo: 'c');

        $this->assertSame(100, array_sum($parts));
        $this->assertSame(['a' => 33, 'b' => 33, 'c' => 34], $parts);
    }

    public function test_percentages_must_add_up_to_100()
    {
        $this->expectException(InvalidArgumentException::class);
        Money::allocate(100, ['a' => '50', 'b' => '49.99'], remainderTo: 'a');
    }
}
