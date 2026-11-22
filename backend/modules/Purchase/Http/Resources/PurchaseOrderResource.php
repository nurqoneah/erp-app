<?php

namespace Modules\Purchase\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Services\PurchaseOrderService;

/** @mixin PurchaseOrder */
class PurchaseOrderResource extends JsonResource
{
    /** Sertakan penerimaan & tagihan terkait (halaman detail). */
    public bool $withRelated = false;

    public function withRelated(): static
    {
        $this->withRelated = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $service = app(PurchaseOrderService::class);

        return [
            'id' => $this->id,
            'number' => $this->number,
            'state' => $this->state,
            'partner_id' => $this->partner_id,
            'partner' => $this->whenLoaded('partner', fn () => ['id' => $this->partner->id, 'name' => $this->partner->name]),
            'order_date' => $this->order_date?->format('Y-m-d'),
            'expected_date' => $this->expected_date?->format('Y-m-d'),
            'confirmed_at' => $this->confirmed_at,
            'amount_untaxed' => $this->amount_untaxed,
            'amount_tax' => $this->amount_tax,
            'amount_total' => $this->amount_total,
            'notes' => $this->notes,
            'receipt_status' => $this->whenLoaded('lines', fn () => $this->receiptStatus()),
            'bill_status' => $this->whenLoaded('lines', fn () => $this->billStatus()),
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line) => [
                'id' => $line->id,
                'product_id' => $line->product_id,
                'product' => ['id' => $line->product->id, 'name' => $line->product->name, 'type' => $line->product->type, 'uom' => $line->product->uom?->name],
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'discount' => $line->discount,
                'tax_rate' => $line->tax_rate,
                'subtotal' => $line->subtotal,
                'tax_amount' => $line->tax_amount,
                'total' => $line->total,
                'qty_received' => $line->qty_received,
                'qty_billed' => $line->qty_billed,
            ])),
            'pickings' => $this->when($this->withRelated, fn () => $service->pickings($this->resource)->get(['id', 'reference', 'state'])),
            'bills' => $this->when($this->withRelated, fn () => $service->billsQuery($this->resource)->get()
                ->map(fn ($inv) => [
                    'id' => $inv->id,
                    'display_name' => $inv->label(),
                    'state' => $inv->state,
                    'payment_state' => $inv->payment_state,
                    'amount_total' => $inv->amount_total,
                ])),
            'created_at' => $this->created_at,
        ];
    }
}
