<?php

namespace Modules\Inventory\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Inventory\Models\Picking;

/** @mixin Picking */
class PickingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'type' => $this->type,
            'state' => $this->state,
            'partner_id' => $this->partner_id,
            'partner' => $this->whenLoaded('partner', fn () => $this->partner ? ['id' => $this->partner->id, 'name' => $this->partner->name] : null),
            'warehouse_id' => $this->warehouse_id,
            'source_location_id' => $this->source_location_id,
            'source_location' => $this->whenLoaded('sourceLocation', fn () => [
                'id' => $this->sourceLocation->id, 'complete_name' => $this->sourceLocation->complete_name,
            ]),
            'dest_location_id' => $this->dest_location_id,
            'dest_location' => $this->whenLoaded('destLocation', fn () => [
                'id' => $this->destLocation->id, 'complete_name' => $this->destLocation->complete_name,
            ]),
            'scheduled_date' => $this->scheduled_date?->format('Y-m-d'),
            'done_at' => $this->done_at,
            'origin' => $this->origin,
            'origin_type' => $this->origin_type,
            'origin_id' => $this->origin_id,
            'notes' => $this->notes,
            // Transfer dari dokumen lain (mis. pesanan penjualan) barisnya dikunci.
            'is_editable' => $this->isDraft() && $this->origin_type === null,
            'moves' => $this->whenLoaded('moves', fn () => $this->moves->map(fn ($move) => [
                'id' => $move->id,
                'product_id' => $move->product_id,
                'product' => [
                    'id' => $move->product->id,
                    'name' => $move->product->name,
                    'sku' => $move->product->sku,
                    'type' => $move->product->type,
                    'uom' => $move->product->uom?->name,
                ],
                'quantity' => $move->quantity,
                'state' => $move->state,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
