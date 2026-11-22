<?php

namespace Modules\Purchase\Providers;

use App\Core\Modules\ModuleProvider;
use Illuminate\Support\Facades\Event;
use Modules\Inventory\Events\PickingDone;
use Modules\Invoicing\Events\InvoiceChanged;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Services\PurchaseOrderService;

/**
 * Mendengarkan event Inventori & Faktur untuk memperbarui jumlah diterima / ditagihkan.
 */
class PurchaseProvider extends ModuleProvider
{
    protected string $module = 'Purchase';

    public function boot(): void
    {
        Event::listen(PickingDone::class, function (PickingDone $event) {
            if ($event->picking->origin_type === PurchaseOrder::ORIGIN) {
                app(PurchaseOrderService::class)->refreshReceived($event->picking->origin_id);
            }
        });

        Event::listen(InvoiceChanged::class, function (InvoiceChanged $event) {
            if ($event->invoice->origin_type === PurchaseOrder::ORIGIN) {
                app(PurchaseOrderService::class)->refreshBilled($event->invoice->origin_id);
            }
        });
    }
}
