<?php

namespace Modules\Invoicing\Services;

use App\Core\Exceptions\BusinessException;
use App\Core\Support\LineAmounts;
use Illuminate\Support\Facades\DB;
use Modules\Base\Services\SequenceService;
use Modules\Invoicing\Events\InvoiceChanged;
use Modules\Invoicing\Models\Invoice;
use Modules\Invoicing\Models\InvoicePayment;
use Modules\Product\Models\Product;

/**
 * Siklus faktur: draf → diposting (dapat nomor) → dibayar; atau dibatalkan.
 */
class InvoiceService
{
    public function __construct(private readonly SequenceService $sequences) {}

    /**
     * @param  list<array{product_id?: ?int, description?: ?string, quantity: mixed, unit_price: mixed, discount?: mixed,
     *                    tax_rate?: mixed, origin_line_type?: ?string, origin_line_id?: ?int}>  $lines
     * @param  array<string, mixed>  $attributes  invoice_date, due_date, vendor_reference, origin, origin_type, origin_id, notes
     */
    public function create(string $type, int $partnerId, array $lines, array $attributes = []): Invoice
    {
        if (! in_array($type, Invoice::TYPES, true)) {
            throw new BusinessException("Tipe faktur [{$type}] tidak dikenal.");
        }

        $invoice = DB::transaction(function () use ($type, $partnerId, $lines, $attributes) {
            $invoice = Invoice::query()->create([
                ...collect($attributes)->only(['invoice_date', 'due_date', 'vendor_reference', 'origin', 'origin_type', 'origin_id', 'notes'])->all(),
                'type' => $type,
                'partner_id' => $partnerId,
                'state' => Invoice::STATE_DRAFT,
                'payment_state' => Invoice::PAYMENT_NOT_PAID,
            ]);
            $this->saveLines($invoice, $lines);

            return $invoice;
        });

        event(new InvoiceChanged($invoice));

        return $invoice;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>|null  $lines  null = baris tidak diubah
     */
    public function updateDraft(Invoice $invoice, array $attributes, ?array $lines = null): Invoice
    {
        $this->ensureState($invoice, Invoice::STATE_DRAFT, 'Hanya faktur draf yang bisa diubah.');

        DB::transaction(function () use ($invoice, $attributes, $lines) {
            $invoice->update(collect($attributes)->only(['partner_id', 'invoice_date', 'due_date', 'vendor_reference', 'notes'])->all());
            if ($lines !== null) {
                $this->saveLines($invoice, $lines, keepOrigin: true);
            }
        });

        event(new InvoiceChanged($invoice));

        return $invoice;
    }

    public function post(Invoice $invoice): Invoice
    {
        DB::transaction(function () use ($invoice) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $this->ensureState($invoice, Invoice::STATE_DRAFT, 'Hanya faktur draf yang bisa diposting.');
            if ($invoice->lines()->doesntExist()) {
                throw new BusinessException('Faktur belum punya baris.');
            }
            if ((float) $invoice->amount_total < 0) {
                throw new BusinessException('Total faktur tidak boleh negatif.');
            }

            $date = $invoice->invoice_date ?? now()->startOfDay();
            $invoice->update([
                'number' => $invoice->number ?? $this->sequences->next('invoicing.'.$invoice->type, $date),
                'invoice_date' => $date,
                'due_date' => $invoice->due_date ?? $date,
                'state' => Invoice::STATE_POSTED,
            ]);
            $this->refreshPaymentState($invoice);
        });

        return $this->changed($invoice);
    }

    public function cancel(Invoice $invoice): Invoice
    {
        if ($invoice->state === Invoice::STATE_CANCELLED) {
            throw new BusinessException('Faktur sudah dibatalkan.');
        }
        if ($invoice->payments()->exists()) {
            throw new BusinessException('Faktur yang sudah ada pembayarannya tidak bisa dibatalkan.');
        }

        $invoice->update(['state' => Invoice::STATE_CANCELLED]);

        return $this->changed($invoice);
    }

    public function resetToDraft(Invoice $invoice): Invoice
    {
        if ($invoice->state === Invoice::STATE_DRAFT) {
            throw new BusinessException('Faktur sudah berstatus draf.');
        }
        if ($invoice->payments()->exists()) {
            throw new BusinessException('Faktur yang sudah ada pembayarannya tidak bisa dikembalikan ke draf.');
        }

        $invoice->update(['state' => Invoice::STATE_DRAFT, 'payment_state' => Invoice::PAYMENT_NOT_PAID]);

        return $this->changed($invoice);
    }

    /** Hapus faktur draf yang belum pernah diposting (belum punya nomor). */
    public function delete(Invoice $invoice): void
    {
        $this->ensureState($invoice, Invoice::STATE_DRAFT, 'Hanya faktur draf yang bisa dihapus.');
        if ($invoice->number !== null) {
            throw new BusinessException('Faktur yang pernah diposting tidak bisa dihapus; batalkan saja.');
        }

        $invoice->delete();
        event(new InvoiceChanged($invoice));
    }

    public function registerPayment(Invoice $invoice, float $amount, string $date, string $method, ?string $reference = null): InvoicePayment
    {
        return DB::transaction(function () use ($invoice, $amount, $date, $method, $reference) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            $this->ensureState($invoice, Invoice::STATE_POSTED, 'Pembayaran hanya untuk faktur yang sudah diposting.');

            $amount = LineAmounts::money($amount);
            if ($amount <= 0) {
                throw new BusinessException('Jumlah pembayaran harus lebih dari 0.');
            }
            if ($amount > $invoice->amountDue()) {
                throw new BusinessException('Jumlah pembayaran melebihi sisa tagihan ('.number_format($invoice->amountDue(), 2, ',', '.').').');
            }

            $payment = $invoice->payments()->create(compact('date', 'amount', 'method', 'reference'));
            $invoice->amount_paid = LineAmounts::money((float) $invoice->amount_paid + $amount);
            $this->refreshPaymentState($invoice);

            return $payment;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function saveLines(Invoice $invoice, array $lines, bool $keepOrigin = false): void
    {
        // Saat diedit dari UI, pertahankan tautan ke baris dokumen asal (mis. baris pesanan).
        $origins = $keepOrigin ? $invoice->lines()->get()->keyBy('id') : collect();
        $products = Product::withTrashed()->whereIn('id', array_filter(array_column($lines, 'product_id')))->get()->keyBy('id');

        $invoice->lines()->delete();
        foreach (array_values($lines) as $index => $line) {
            $product = isset($line['product_id']) ? $products[$line['product_id']] ?? null : null;
            $description = trim((string) ($line['description'] ?? '')) ?: $product?->name;
            if (! $description) {
                throw new BusinessException('Setiap baris faktur harus punya produk atau deskripsi.');
            }
            $origin = isset($line['id']) ? $origins->get($line['id']) : null;

            $invoice->lines()->create([
                'sequence' => $index,
                'product_id' => $product?->id,
                'description' => $description,
                'quantity' => LineAmounts::quantity($line['quantity']),
                'unit_price' => LineAmounts::money($line['unit_price']),
                'discount' => (float) ($line['discount'] ?? 0),
                'tax_rate' => (float) ($line['tax_rate'] ?? 0),
                'origin_line_type' => $line['origin_line_type'] ?? $origin?->origin_line_type,
                'origin_line_id' => $line['origin_line_id'] ?? $origin?->origin_line_id,
                ...LineAmounts::compute($line['quantity'], $line['unit_price'], $line['discount'] ?? 0, $line['tax_rate'] ?? 0),
            ]);
        }

        $invoice->update(LineAmounts::totals($invoice->lines()->get()));
    }

    private function refreshPaymentState(Invoice $invoice): void
    {
        $paid = (float) $invoice->amount_paid;
        $invoice->payment_state = match (true) {
            $paid <= 0 => Invoice::PAYMENT_NOT_PAID,
            $paid + 0.001 >= (float) $invoice->amount_total => Invoice::PAYMENT_PAID,
            default => Invoice::PAYMENT_PARTIAL,
        };
        $invoice->save();
    }

    private function ensureState(Invoice $invoice, string $state, string $message): void
    {
        if ($invoice->state !== $state) {
            throw new BusinessException($message);
        }
    }

    private function changed(Invoice $invoice): Invoice
    {
        $invoice->refresh();
        event(new InvoiceChanged($invoice));

        return $invoice;
    }
}
