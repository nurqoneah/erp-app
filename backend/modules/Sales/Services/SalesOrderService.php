<?php

namespace Modules\Sales\Services;

use App\Core\Exceptions\BusinessException;
use App\Core\Support\LineAmounts;
use Illuminate\Support\Facades\DB;
use Modules\Base\Services\SequenceService;
use Modules\Inventory\Models\Picking;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Services\InventoryService;
use Modules\Invoicing\Models\Invoice;
use Modules\Invoicing\Models\InvoiceLine;
use Modules\Invoicing\Services\InvoiceService;
use Modules\Product\Models\Product;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Models\SalesOrderLine;

/**
 * Alur penjualan: penawaran → konfirmasi (buat pengiriman) → faktur.
 */
class SalesOrderService
{
    public function __construct(
        private readonly SequenceService $sequences,
        private readonly InventoryService $inventory,
        private readonly InvoiceService $invoices,
    ) {}

    /**
     * Buat atau ubah penawaran (hanya status draf).
     *
     * @param  array{partner_id: int, order_date?: ?string, validity_date?: ?string, notes?: ?string,
     *               lines: list<array{product_id: int, description?: ?string, quantity: mixed, unit_price: mixed, discount?: mixed, tax_rate?: mixed}>}  $data
     */
    public function save(?SalesOrder $order, array $data): SalesOrder
    {
        if ($order && $order->state !== SalesOrder::STATE_DRAFT) {
            throw new BusinessException('Hanya penawaran (draf) yang bisa diubah.');
        }

        return DB::transaction(function () use ($order, $data) {
            $attributes = [
                'partner_id' => $data['partner_id'],
                'order_date' => $data['order_date'] ?? $order?->order_date ?? now()->toDateString(),
                'validity_date' => $data['validity_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ];

            if ($order) {
                $order->update($attributes);
            } else {
                $order = SalesOrder::query()->create([
                    ...$attributes,
                    'number' => $this->sequences->next('sales.order'),
                    'state' => SalesOrder::STATE_DRAFT,
                ]);
            }

            $products = Product::query()->whereIn('id', array_column($data['lines'], 'product_id'))->get()->keyBy('id');
            $order->lines()->delete();
            foreach (array_values($data['lines']) as $index => $line) {
                $product = $products[$line['product_id']] ?? throw new BusinessException('Produk tidak ditemukan.');
                if (! $product->can_be_sold) {
                    throw new BusinessException("{$product->name} tidak ditandai bisa dijual.");
                }
                $order->lines()->create([
                    'sequence' => $index,
                    'product_id' => $product->id,
                    'description' => trim((string) ($line['description'] ?? '')) ?: $product->name,
                    'quantity' => LineAmounts::quantity($line['quantity']),
                    'unit_price' => LineAmounts::money($line['unit_price']),
                    'discount' => (float) ($line['discount'] ?? 0),
                    'tax_rate' => (float) ($line['tax_rate'] ?? 0),
                    ...LineAmounts::compute($line['quantity'], $line['unit_price'], $line['discount'] ?? 0, $line['tax_rate'] ?? 0),
                ]);
            }
            $order->update(LineAmounts::totals($order->lines()->get()));

            return $order->refresh();
        });
    }

    /**
     * Konfirmasi penawaran menjadi pesanan dan buat pengiriman untuk barang.
     */
    public function confirm(SalesOrder $order): SalesOrder
    {
        DB::transaction(function () use ($order) {
            $order = SalesOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->state !== SalesOrder::STATE_DRAFT) {
                throw new BusinessException('Hanya penawaran (draf) yang bisa dikonfirmasi.');
            }
            $lines = $order->lines()->with('product')->get();
            if ($lines->isEmpty()) {
                throw new BusinessException('Penawaran belum punya baris produk.');
            }

            $order->update(['state' => SalesOrder::STATE_CONFIRMED, 'confirmed_at' => now()]);

            $goods = $lines->filter(fn (SalesOrderLine $l) => $l->product->type !== Product::TYPE_SERVICE);
            if ($goods->isNotEmpty()) {
                $this->inventory->createPicking(Picking::TYPE_DELIVERY, $goods->map(fn (SalesOrderLine $l) => [
                    'product_id' => $l->product_id,
                    'quantity' => $l->quantity,
                    'origin_line_type' => SalesOrder::LINE_ORIGIN,
                    'origin_line_id' => $l->id,
                ])->values()->all(), [
                    'partner_id' => $order->partner_id,
                    'origin' => $order->number,
                    'origin_type' => SalesOrder::ORIGIN,
                    'origin_id' => $order->id,
                ]);
            }
        });

        return $order->refresh();
    }

    public function cancel(SalesOrder $order): SalesOrder
    {
        DB::transaction(function () use ($order) {
            if ($order->state === SalesOrder::STATE_CANCELLED) {
                throw new BusinessException('Pesanan sudah dibatalkan.');
            }

            $pickings = $this->pickings($order)->get();
            if ($pickings->contains('state', Picking::STATE_DONE)) {
                throw new BusinessException('Pesanan yang barangnya sudah dikirim tidak bisa dibatalkan.');
            }
            if ($this->invoicesQuery($order)->where('state', '!=', Invoice::STATE_CANCELLED)->exists()) {
                throw new BusinessException('Batalkan dulu faktur pesanan ini.');
            }

            $pickings->where('state', Picking::STATE_DRAFT)->each(fn (Picking $p) => $this->inventory->cancel($p));
            $order->update(['state' => SalesOrder::STATE_CANCELLED]);
        });

        return $order->refresh();
    }

    /** Kembalikan pesanan yang dibatalkan menjadi penawaran. */
    public function setToDraft(SalesOrder $order): SalesOrder
    {
        if ($order->state !== SalesOrder::STATE_CANCELLED) {
            throw new BusinessException('Hanya pesanan yang dibatalkan yang bisa dijadikan penawaran lagi.');
        }
        $order->update(['state' => SalesOrder::STATE_DRAFT, 'confirmed_at' => null]);

        return $order->refresh();
    }

    /**
     * Buat faktur draf untuk jumlah yang belum difakturkan.
     */
    public function createInvoice(SalesOrder $order): Invoice
    {
        if ($order->state !== SalesOrder::STATE_CONFIRMED) {
            throw new BusinessException('Faktur hanya bisa dibuat dari pesanan yang sudah dikonfirmasi.');
        }

        $lines = $order->lines()->get()->filter(fn (SalesOrderLine $l) => $l->qtyToInvoice() > 0);
        if ($lines->isEmpty()) {
            throw new BusinessException('Semua baris pesanan sudah difakturkan.');
        }

        return $this->invoices->create(Invoice::TYPE_CUSTOMER, $order->partner_id, $lines->map(fn (SalesOrderLine $l) => [
            'product_id' => $l->product_id,
            'description' => $l->description,
            'quantity' => $l->qtyToInvoice(),
            'unit_price' => $l->unit_price,
            'discount' => $l->discount,
            'tax_rate' => $l->tax_rate,
            'origin_line_type' => SalesOrder::LINE_ORIGIN,
            'origin_line_id' => $l->id,
        ])->values()->all(), [
            'origin' => $order->number,
            'origin_type' => SalesOrder::ORIGIN,
            'origin_id' => $order->id,
        ]);
    }

    /** Hitung ulang jumlah terkirim dari perpindahan stok yang selesai. */
    public function refreshDelivered(int $orderId): void
    {
        $delivered = StockMove::query()
            ->where('origin_line_type', SalesOrder::LINE_ORIGIN)
            ->where('state', Picking::STATE_DONE)
            ->whereIn('origin_line_id', SalesOrderLine::query()->where('order_id', $orderId)->select('id'))
            ->groupBy('origin_line_id')
            ->selectRaw('origin_line_id, sum(quantity) as qty')
            ->pluck('qty', 'origin_line_id');

        SalesOrderLine::query()->where('order_id', $orderId)->get()
            ->each(fn (SalesOrderLine $l) => $l->update(['qty_delivered' => $delivered[$l->id] ?? 0]));
    }

    /** Hitung ulang jumlah yang sudah difakturkan (faktur yang tidak dibatalkan). */
    public function refreshInvoiced(int $orderId): void
    {
        $invoiced = InvoiceLine::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->where('invoices.state', '!=', Invoice::STATE_CANCELLED)
            ->where('invoice_lines.origin_line_type', SalesOrder::LINE_ORIGIN)
            ->whereIn('invoice_lines.origin_line_id', SalesOrderLine::query()->where('order_id', $orderId)->select('id'))
            ->groupBy('invoice_lines.origin_line_id')
            ->selectRaw('invoice_lines.origin_line_id, sum(invoice_lines.quantity) as qty')
            ->pluck('qty', 'origin_line_id');

        SalesOrderLine::query()->where('order_id', $orderId)->get()
            ->each(fn (SalesOrderLine $l) => $l->update(['qty_invoiced' => $invoiced[$l->id] ?? 0]));
    }

    public function pickings(SalesOrder $order)
    {
        return Picking::query()->where('origin_type', SalesOrder::ORIGIN)->where('origin_id', $order->id)->orderBy('id');
    }

    public function invoicesQuery(SalesOrder $order)
    {
        return Invoice::query()->where('origin_type', SalesOrder::ORIGIN)->where('origin_id', $order->id)->orderBy('id');
    }
}
