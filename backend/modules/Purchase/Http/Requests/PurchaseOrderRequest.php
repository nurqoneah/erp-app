<?php

namespace Modules\Purchase\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseOrderRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'partner_id' => ['required', Rule::exists('partners', 'id')->whereNull('deleted_at')],
            'order_date' => ['nullable', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'lines.*.description' => ['nullable', 'string', 'max:500'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.discount' => ['nullable', 'numeric', 'between:0,100'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'partner_id' => 'pemasok',
            'order_date' => 'tanggal',
            'expected_date' => 'perkiraan tiba',
            'lines' => 'baris produk',
            'lines.*.product_id' => 'produk',
            'lines.*.quantity' => 'jumlah',
            'lines.*.unit_price' => 'harga',
            'lines.*.discount' => 'diskon',
            'lines.*.tax_rate' => 'pajak',
        ];
    }
}
