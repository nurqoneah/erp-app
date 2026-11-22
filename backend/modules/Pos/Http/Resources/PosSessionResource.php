<?php

namespace Modules\Pos\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Pos\Models\PosSession;

/** @mixin PosSession */
class PosSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name ?? 'Sesi baru',
            'state' => $this->state,
            'config' => $this->whenLoaded('config', fn () => ['id' => $this->config->id, 'name' => $this->config->name]),
            'user' => $this->whenLoaded('user', fn () => ['id' => $this->user->id, 'name' => $this->user->name]),
            'opened_at' => $this->opened_at,
            'closed_at' => $this->closed_at,
            'cash_register_balance_start' => $this->cash_register_balance_start,
            'cash_register_balance_end' => $this->cash_register_balance_end,
            'cash_register_balance_end_real' => $this->cash_register_balance_end_real,
            'cash_register_difference' => $this->cash_register_difference,
            'opening_notes' => $this->opening_notes,
            'closing_notes' => $this->closing_notes,
            'orders_count' => $this->whenCounted('orders'),
            'orders_amount' => $this->when(isset($this->orders_sum_amount_total), fn () => number_format((float) $this->orders_sum_amount_total, 2, '.', '')),
        ];
    }
}
