<?php

namespace Modules\Product\Http\Requests;

use App\Core\Extensions\ModelExtensions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Modules\Product\Models\Product;
use Modules\Product\Models\Uom;

class ProductRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // Satuan beli default = satuan jual.
        if (! $this->filled('purchase_uom_id') && $this->filled('uom_id')) {
            $this->merge(['purchase_uom_id' => $this->input('uom_id')]);
        }
    }

    public function rules(): array
    {
        /** @var Product|null $product */
        $product = $this->route('product');
        $unique = fn (string $column) => Rule::unique('products', $column)
            ->ignore($product?->id)
            ->whereNull('deleted_at');

        return [
            ...app(ModelExtensions::class)->rules(Product::class),
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:64', $unique('sku')],
            'barcode' => ['nullable', 'string', 'max:64', $unique('barcode')],
            'type' => ['required', Rule::in(Product::TYPES)],
            'category_id' => ['required', Rule::exists('product_categories', 'id')],
            'uom_id' => ['required', Rule::exists('uoms', 'id')],
            'purchase_uom_id' => ['required', Rule::exists('uoms', 'id')],
            'sale_price' => ['required', 'numeric', 'min:0', 'max:9999999999999999'],
            'cost' => ['required', 'numeric', 'min:0', 'max:9999999999999999'],
            'can_be_sold' => ['boolean'],
            'can_be_purchased' => ['boolean'],
            'available_in_pos' => ['boolean'],
            'description' => ['nullable', 'string', 'max:5000'],
            'is_active' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['uom_id', 'purchase_uom_id'])) {
                    return;
                }
                $categories = Uom::query()
                    ->whereIn('id', [$this->input('uom_id'), $this->input('purchase_uom_id')])
                    ->pluck('category_id')
                    ->unique();
                if ($categories->count() > 1) {
                    $validator->errors()->add('purchase_uom_id', 'Satuan beli harus satu kategori dengan satuan jual.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            ...app(ModelExtensions::class)->attributes(Product::class),
            'name' => 'nama produk',
            'sku' => 'kode internal',
            'category_id' => 'kategori',
            'uom_id' => 'satuan',
            'purchase_uom_id' => 'satuan beli',
            'sale_price' => 'harga jual',
            'cost' => 'harga pokok',
        ];
    }
}
