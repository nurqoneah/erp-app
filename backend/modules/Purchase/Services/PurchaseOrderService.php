<?php

namespace Modules\Purchase\Services;

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
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Models\PurchaseOrderLine;

/**
 * Alur pembelian: RFQ → konfirmasi (buat penerimaan barang) → tagihan pemasok.
 */
class PurchaseOrderService
{
    public function __construct(
        private readonly SequenceService $sequences,
        private readonly InventoryService $inventory,
        private readonly InvoiceService $invoices,
    ) {}

    /**
     * Buat atau ubah RFQ (hanya status draf).
     *
     * @param  array{partner_id: int, order_date?: ?string, expected_date?: ?string, notes?: ?string,
     *               lines: list<array{product_id: int, description?: ?string, quantity: mixed, unit_price: mixed, discount?: mixed, tax_rate?: mixed}>}  $data
     */
    public function save(?PurchaseOrder $order, array $data): PurchaseOrder
    {
        if ($order && $order->state !== PurchaseOrder::STATE_DRAFT) {
            throw new BusinessException('Hanya RFQ (draf) yang bisa diubah.');
        }

        return DB::transaction(function () use ($order, $data) {
            $attributes = [
                'partner_id' => $data['partner_id'],
                'order_date' => $data['order_date'] ?? $order?->order_date ?? now()->toDateString(),
                'expected_date' => $data['expected_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ];

            if ($order) {
                $order->update($attributes);
            } else {
                $order = PurchaseOrder::query()->create([
                    ...$attributes,
                    'number' => $this->sequences->next('purchase.order'),
                    'state' => PurchaseOrder::STATE_DRAFT,
                ]);
            }

            $products = Product::query()->whereIn('id', array_column($data['lines'], 'product_id'))->get()->keyBy('id');
            $order->lines()->delete();
            foreach (array_values($data['lines']) as $index => $line) {
                $product = $products[$line['product_id']] ?? throw new BusinessException('Produk tidak ditemukan.');
                if (! $product->can_be_purchased) {
                    throw new BusinessException("{$product->name} tidak ditandai bisa dibeli.");
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
     * Konfirmasi RFQ menjadi pesanan pembelian dan buat penerimaan untuk barang.
     */
    public function confirm(PurchaseOrder $order): PurchaseOrder
    {
        DB::transaction(function () use ($order) {
            $order = PurchaseOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->state !== PurchaseOrder::STATE_DRAFT) {
                throw new BusinessException('Hanya RFQ (draf) yang bisa dikonfirmasi.');
            }
            $lines = $order->lines()->with('product')->get();
            if ($lines->isEmpty()) {
                throw new BusinessException('RFQ belum punya baris produk.');
            }

            $order->update(['state' => PurchaseOrder::STATE_CONFIRMED, 'confirmed_at' => now()]);

            $goods = $lines->filter(fn (PurchaseOrderLine $l) => $l->product->type !== Product::TYPE_SERVICE);
            if ($goods->isNotEmpty()) {
                $this->inventory->createPicking(Picking::TYPE_RECEIPT, $goods->map(fn (PurchaseOrderLine $l) => [
                    'product_id' => $l->product_id,
                    'quantity' => $l->quantity,
                    'origin_line_type' => PurchaseOrder::LINE_ORIGIN,
                    'origin_line_id' => $l->id,
                ])->values()->all(), [
                    'partner_id' => $order->partner_id,
                    'origin' => $order->number,
                    'origin_type' => PurchaseOrder::ORIGIN,
                    'origin_id' => $order->id,
                ]);
            }
        });

        return $order->refresh();
    }

    public function cancel(PurchaseOrder $order): PurchaseOrder
    {
        DB::transaction(function () use ($order) {
            if ($order->state === PurchaseOrder::STATE_CANCELLED) {
                throw new BusinessException('Pesanan sudah dibatalkan.');
            }

            $pickings = $this->pickings($order)->get();
            if ($pickings->contains('state', Picking::STATE_DONE)) {
                throw new BusinessException('Pesanan yang barangnya sudah diterima tidak bisa dibatalkan.');
            }
            if ($this->billsQuery($order)->where('state', '!=', Invoice::STATE_CANCELLED)->exists()) {
                throw new BusinessException('Batalkan dulu tagihan pesanan ini.');
            }

            $pickings->where('state', Picking::STATE_DRAFT)->each(fn (Picking $p) => $this->inventory->cancel($p));
            $order->update(['state' => PurchaseOrder::STATE_CANCELLED]);
        });

        return $order->refresh();
    }

    /** Kembalikan pesanan yang dibatalkan menjadi RFQ. */
    public function setToDraft(PurchaseOrder $order): PurchaseOrder
    {
        if ($order->state !== PurchaseOrder::STATE_CANCELLED) {
            throw new BusinessException('Hanya pesanan yang dibatalkan yang bisa dijadikan RFQ lagi.');
        }
        $order->update(['state' => PurchaseOrder::STATE_DRAFT, 'confirmed_at' => null]);

        return $order->refresh();
    }

    /**
     * Buat tagihan pemasok draf untuk jumlah yang belum ditagihkan.
     */
    public function createBill(PurchaseOrder $order): Invoice
    {
        if ($order->state !== PurchaseOrder::STATE_CONFIRMED) {
            throw new BusinessException('Tagihan hanya bisa dibuat dari pesanan yang sudah dikonfirmasi.');
        }

        $lines = $order->lines()->get()->filter(fn (PurchaseOrderLine $l) => $l->qtyToBill() > 0);
        if ($lines->isEmpty()) {
            throw new BusinessException('Semua baris pesanan sudah ditagihkan.');
        }

        return $this->invoices->create(Invoice::TYPE_VENDOR, $order->partner_id, $lines->map(fn (PurchaseOrderLine $l) => [
            'product_id' => $l->product_id,
            'description' => $l->description,
            'quantity' => $l->qtyToBill(),
            'unit_price' => $l->unit_price,
            'discount' => $l->discount,
            'tax_rate' => $l->tax_rate,
            'origin_line_type' => PurchaseOrder::LINE_ORIGIN,
            'origin_line_id' => $l->id,
        ])->values()->all(), [
            'origin' => $order->number,
            'origin_type' => PurchaseOrder::ORIGIN,
            'origin_id' => $order->id,
        ]);
    }

    /** Hitung ulang jumlah diterima dari perpindahan stok yang selesai. */
    public function refreshReceived(int $orderId): void
    {
        $received = StockMove::query()
            ->where('origin_line_type', PurchaseOrder::LINE_ORIGIN)
            ->where('state', Picking::STATE_DONE)
            ->whereIn('origin_line_id', PurchaseOrderLine::query()->where('order_id', $orderId)->select('id'))
            ->groupBy('origin_line_id')
            ->selectRaw('origin_line_id, sum(quantity) as qty')
            ->pluck('qty', 'origin_line_id');

        PurchaseOrderLine::query()->where('order_id', $orderId)->get()
            ->each(fn (PurchaseOrderLine $l) => $l->update(['qty_received' => $received[$l->id] ?? 0]));
    }

    /** Hitung ulang jumlah yang sudah ditagihkan (tagihan yang tidak dibatalkan). */
    public function refreshBilled(int $orderId): void
    {
        $billed = InvoiceLine::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->where('invoices.state', '!=', Invoice::STATE_CANCELLED)
            ->where('invoice_lines.origin_line_type', PurchaseOrder::LINE_ORIGIN)
            ->whereIn('invoice_lines.origin_line_id', PurchaseOrderLine::query()->where('order_id', $orderId)->select('id'))
            ->groupBy('invoice_lines.origin_line_id')
            ->selectRaw('invoice_lines.origin_line_id, sum(invoice_lines.quantity) as qty')
            ->pluck('qty', 'origin_line_id');

        PurchaseOrderLine::query()->where('order_id', $orderId)->get()
            ->each(fn (PurchaseOrderLine $l) => $l->update(['qty_billed' => $billed[$l->id] ?? 0]));
    }

    public function pickings(PurchaseOrder $order)
    {
        return Picking::query()->where('origin_type', PurchaseOrder::ORIGIN)->where('origin_id', $order->id)->orderBy('id');
    }

    public function billsQuery(PurchaseOrder $order)
    {
        return Invoice::query()->where('origin_type', PurchaseOrder::ORIGIN)->where('origin_id', $order->id)->orderBy('id');
    }
}
