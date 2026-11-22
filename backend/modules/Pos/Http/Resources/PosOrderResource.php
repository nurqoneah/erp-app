<?php

namespace Modules\Pos\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Inventory\Models\Picking;
use Modules\Pos\Models\PosOrder;

/** @mixin PosOrder */
class PosOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'state' => $this->state,
            'session_id' => $this->session_id,
            'session' => $this->whenLoaded('session', fn () => ['id' => $this->session->id, 'name' => $this->session->name]),
            'config' => $this->whenLoaded('config', fn () => ['id' => $this->config->id, 'name' => $this->config->name]),
            'user' => $this->whenLoaded('user', fn () => ['id' => $this->user->id, 'name' => $this->user->name]),
            'partner_id' => $this->partner_id,
            'partner' => $this->whenLoaded('partner', fn () => $this->partner ? ['id' => $this->partner->id, 'name' => $this->partner->name] : null),
            'date_order' => $this->date_order,
            'amount_untaxed' => $this->amount_untaxed,
            'amount_tax' => $this->amount_tax,
            'amount_total' => $this->amount_total,
            'amount_paid' => $this->amount_paid,
            'amount_return' => $this->amount_return,
            'to_invoice' => $this->to_invoice,
            'invoice' => $this->whenLoaded('invoice', fn () => $this->invoice ? ['id' => $this->invoice->id, 'number' => $this->invoice->number] : null),
            'is_refund' => $this->is_refund,
            'refunded_order' => $this->whenLoaded('refundedOrder', fn () => $this->refundedOrder ? ['id' => $this->refundedOrder->id, 'name' => $this->refundedOrder->name] : null),
            'notes' => $this->notes,
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line) => [
                'id' => $line->id,
                'product_id' => $line->product_id,
                'product' => ['id' => $line->product->id, 'name' => $line->product->name, 'uom' => $line->product->uom?->name],
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'discount' => $line->discount,
                'tax_rate' => $line->tax_rate,
                'subtotal' => $line->subtotal,
                'total' => $line->total,
                // Untuk retur: jumlah yang masih bisa dikembalikan.
                'refundable_quantity' => $line->relationLoaded('refundLines')
                    ? round((float) $line->quantity + (float) $line->refundLines->sum('quantity'), 4)
                    : null,
            ])),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($p) => [
                'id' => $p->id,
                'method' => ['id' => $p->method->id, 'name' => $p->method->name, 'type' => $p->method->type],
                'amount' => $p->amount,
                'is_change' => $p->is_change,
            ])),
            'pickings' => $this->when($request->routeIs('pos.orders.show'), fn () => Picking::query()
                ->where('origin_type', PosOrder::ORIGIN)->where('origin_id', $this->id)->get(['id', 'reference', 'state'])),
        ];
    }
}
