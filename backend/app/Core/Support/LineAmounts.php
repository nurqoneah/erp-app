<?php

namespace App\Core\Support;

/**
 * Perhitungan nominal baris dokumen (penawaran, pesanan, faktur).
 *
 * Sama seperti Odoo: nilai dihitung dengan float lalu dibulatkan ke 2 desimal
 * per baris; total dokumen = jumlah nilai baris yang sudah dibulatkan.
 */
final class LineAmounts
{
    public static function money(float|int|string|null $value): float
    {
        return round((float) $value, 2);
    }

    public static function quantity(float|int|string|null $value): float
    {
        return round((float) $value, 4);
    }

    /**
     * @return array{subtotal: float, tax_amount: float, total: float}
     */
    public static function compute(
        float|int|string $quantity,
        float|int|string $unitPrice,
        float|int|string|null $discount = 0,
        float|int|string|null $taxRate = 0,
    ): array {
        $subtotal = self::money((float) $quantity * (float) $unitPrice * (1 - (float) $discount / 100));
        $tax = self::money($subtotal * (float) $taxRate / 100);

        return ['subtotal' => $subtotal, 'tax_amount' => $tax, 'total' => self::money($subtotal + $tax)];
    }

    /**
     * Total dokumen dari baris-barisnya.
     *
     * @param  iterable<array{subtotal: mixed, tax_amount: mixed}|object>  $lines
     * @return array{amount_untaxed: float, amount_tax: float, amount_total: float}
     */
    public static function totals(iterable $lines): array
    {
        $untaxed = 0.0;
        $tax = 0.0;
        foreach ($lines as $line) {
            $untaxed += (float) data_get($line, 'subtotal');
            $tax += (float) data_get($line, 'tax_amount');
        }

        return [
            'amount_untaxed' => self::money($untaxed),
            'amount_tax' => self::money($tax),
            'amount_total' => self::money($untaxed + $tax),
        ];
    }
}
