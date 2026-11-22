<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Inventory\Models\Picking;

class PickingRequest extends FormRequest
{
    public function rules(): array
    {
        $creating = $this->route('picking') === null;

        return [
            'type' => [$creating ? 'required' : 'prohibited', Rule::in(Picking::TYPES)],
            'partner_id' => ['nullable', Rule::exists('partners', 'id')],
            'scheduled_date' => ['nullable', 'date'],
            'source_location_id' => ['nullable', Rule::exists('stock_locations', 'id')],
            'dest_location_id' => ['nullable', Rule::exists('stock_locations', 'id')],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', Rule::exists('products', 'id')],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'partner_id' => 'mitra',
            'scheduled_date' => 'tanggal rencana',
            'source_location_id' => 'lokasi asal',
            'dest_location_id' => 'lokasi tujuan',
            'lines' => 'baris produk',
            'lines.*.product_id' => 'produk',
            'lines.*.quantity' => 'jumlah',
        ];
    }
}
