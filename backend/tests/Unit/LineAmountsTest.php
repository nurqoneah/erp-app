<?php

namespace Tests\Unit;

use App\Core\Support\LineAmounts;
use PHPUnit\Framework\TestCase;

class LineAmountsTest extends TestCase
{
    public function test_computes_discount_and_tax_with_rounding(): void
    {
        $this->assertSame(
            ['subtotal' => 89100.0, 'tax_amount' => 9801.0, 'total' => 98901.0],
            LineAmounts::compute(3, 33000, 10, 11),
        );
        $this->assertSame(['subtotal' => 0.33, 'tax_amount' => 0.04, 'total' => 0.37], LineAmounts::compute(1, '0.333', 0, 12));
    }

    public function test_totals_sum_rounded_lines(): void
    {
        $lines = [LineAmounts::compute(1, '0.105', 0, 0), LineAmounts::compute(1, '0.105', 0, 0)];

        $this->assertSame(['amount_untaxed' => 0.22, 'amount_tax' => 0.0, 'amount_total' => 0.22], LineAmounts::totals($lines));
    }
}
