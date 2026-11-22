<?php

namespace Modules\Sales\Providers;

use App\Core\Modules\ModuleProvider;
use Illuminate\Support\Facades\Event;
use Modules\Inventory\Events\PickingDone;
use Modules\Invoicing\Events\InvoiceChanged;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Services\SalesOrderService;

/**
 * Mendengarkan event Inventori & Faktur untuk memperbarui jumlah terkirim / difakturkan.
 */
class SalesProvider extends ModuleProvider
{
    protected string $module = 'Sales';

    public function boot(): void
    {
        Event::listen(PickingDone::class, function (PickingDone $event) {
            if ($event->picking->origin_type === SalesOrder::ORIGIN) {
                app(SalesOrderService::class)->refreshDelivered($event->picking->origin_id);
            }
        });

        Event::listen(InvoiceChanged::class, function (InvoiceChanged $event) {
            if ($event->invoice->origin_type === SalesOrder::ORIGIN) {
                app(SalesOrderService::class)->refreshInvoiced($event->invoice->origin_id);
            }
        });
    }
}
