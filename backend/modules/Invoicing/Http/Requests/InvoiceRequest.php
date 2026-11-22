<?php

namespace Modules\Invoicing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Invoicing\Models\Invoice;

class InvoiceRequest extends FormRequest
{
    public function rules(): array
    {
        $creating = $this->route('invoice') === null;

        return [
            'type' => [$creating ? 'required' : 'prohibited', Rule::in(Invoice::TYPES)],
            'partner_id' => ['required', Rule::exists('partners', 'id')->whereNull('deleted_at')],
            'invoice_date' => ['nullable', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'vendor_reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['nullable', 'integer'],
            'lines.*.product_id' => ['nullable', Rule::exists('products', 'id')],
            'lines.*.description' => ['nullable', 'string', 'max:500', 'required_without:lines.*.product_id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.discount' => ['nullable', 'numeric', 'between:0,100'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'between:0,100'],
        ];
    }

    public function attributes(): array
    {
        return [
            'partner_id' => 'mitra',
            'invoice_date' => 'tanggal faktur',
            'due_date' => 'jatuh tempo',
            'lines' => 'baris',
            'lines.*.product_id' => 'produk',
            'lines.*.description' => 'deskripsi',
            'lines.*.quantity' => 'jumlah',
            'lines.*.unit_price' => 'harga',
            'lines.*.discount' => 'diskon',
            'lines.*.tax_rate' => 'pajak',
        ];
    }
}
