<?php

namespace Modules\Product\Http\Resources;

use App\Core\Extensions\ModelExtensions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Product\Models\Product;

/** @mixin Product */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'type' => $this->type,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'complete_name' => $this->category->complete_name,
            ]),
            'uom_id' => $this->uom_id,
            'uom' => $this->whenLoaded('uom', fn () => ['id' => $this->uom->id, 'name' => $this->uom->name]),
            'purchase_uom_id' => $this->purchase_uom_id,
            'purchase_uom' => $this->whenLoaded('purchaseUom', fn () => [
                'id' => $this->purchaseUom->id,
                'name' => $this->purchaseUom->name,
            ]),
            'sale_price' => $this->sale_price,
            'cost' => $this->cost,
            'can_be_sold' => $this->can_be_sold,
            'can_be_purchased' => $this->can_be_purchased,
            'available_in_pos' => $this->available_in_pos,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            ...app(ModelExtensions::class)->values($this->resource),
        ];
    }
}
