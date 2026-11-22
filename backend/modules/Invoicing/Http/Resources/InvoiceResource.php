<?php

namespace Modules\Invoicing\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Invoicing\Models\Invoice;

/** @mixin Invoice */
class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'number' => $this->number,
            'display_name' => $this->label(),
            'partner_id' => $this->partner_id,
            'partner' => $this->whenLoaded('partner', fn () => ['id' => $this->partner->id, 'name' => $this->partner->name]),
            'invoice_date' => $this->invoice_date?->format('Y-m-d'),
            'due_date' => $this->due_date?->format('Y-m-d'),
            'vendor_reference' => $this->vendor_reference,
            'state' => $this->state,
            'payment_state' => $this->payment_state,
            'origin' => $this->origin,
            'origin_type' => $this->origin_type,
            'origin_id' => $this->origin_id,
            'amount_untaxed' => $this->amount_untaxed,
            'amount_tax' => $this->amount_tax,
            'amount_total' => $this->amount_total,
            'amount_paid' => $this->amount_paid,
            'amount_due' => number_format($this->amountDue(), 2, '.', ''),
            'notes' => $this->notes,
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($line) => [
                'id' => $line->id,
                'product_id' => $line->product_id,
                'product' => $line->product ? ['id' => $line->product->id, 'name' => $line->product->name, 'uom' => $line->product->uom?->name] : null,
                'description' => $line->description,
                'quantity' => $line->quantity,
                'unit_price' => $line->unit_price,
                'discount' => $line->discount,
                'tax_rate' => $line->tax_rate,
                'subtotal' => $line->subtotal,
                'tax_amount' => $line->tax_amount,
                'total' => $line->total,
            ])),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($p) => [
                'id' => $p->id,
                'date' => $p->date->format('Y-m-d'),
                'amount' => $p->amount,
                'method' => $p->method,
                'reference' => $p->reference,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
