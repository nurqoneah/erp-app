<?php

namespace Modules\Pos\Services;

use App\Core\Exceptions\BusinessException;
use App\Core\Support\LineAmounts;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Base\Services\SequenceService;
use Modules\Inventory\Models\Picking;
use Modules\Inventory\Models\StockLocation;
use Modules\Inventory\Services\InventoryService;
use Modules\Invoicing\Models\Invoice;
use Modules\Invoicing\Services\InvoiceService;
use Modules\Pos\Models\CashMove;
use Modules\Pos\Models\PaymentMethod;
use Modules\Pos\Models\PosConfig;
use Modules\Pos\Models\PosOrder;
use Modules\Pos\Models\PosOrderLine;
use Modules\Pos\Models\PosSession;
use Modules\Product\Models\Product;

/**
 * Alur POS mengikuti point_of_sale Odoo 19:
 * - satu sesi aktif per POS; sesi dibuka dengan menghitung kas awal (opening control);
 * - pesanan disimpan saat dibayar; kembalian dicatat sebagai pembayaran tunai negatif;
 * - stok dikurangi saat itu juga (real time) dan boleh minus;
 * - pesanan bisa langsung difakturkan (butuh pelanggan);
 * - retur = pesanan dengan jumlah negatif yang merujuk baris pesanan asal;
 * - sesi ditutup dengan menghitung kas; selisih dicatat, batas selisih hanya bisa dilewati manajer.
 */
class PosService
{
    public const MANAGER_PERMISSION = 'pos.config.manage';

    public function __construct(
        private readonly SequenceService $sequences,
        private readonly InventoryService $inventory,
        private readonly InvoiceService $invoices,
    ) {}

    // ------------------------------------------------------------------
    // Sesi
    // ------------------------------------------------------------------

    /**
     * Buka (atau lanjutkan) sesi POS. Sesi baru berstatus Kontrol Pembukaan.
     */
    public function openSession(PosConfig $config, User $user): PosSession
    {
        if (! $config->is_active) {
            throw new BusinessException("POS {$config->name} tidak aktif.");
        }
        if ($config->paymentMethods()->doesntExist()) {
            throw new BusinessException("POS {$config->name} belum punya metode pembayaran.");
        }

        return DB::transaction(function () use ($config, $user) {
            PosConfig::query()->whereKey($config->id)->lockForUpdate()->first();

            return $config->currentSession()->first() ?? PosSession::query()->create([
                'config_id' => $config->id,
                'user_id' => $user->id,
                'state' => PosSession::STATE_OPENING_CONTROL,
            ]);
        });
    }

    /** Kas awal yang disarankan: hasil hitung kas saat sesi sebelumnya ditutup. */
    public function suggestedOpeningCash(PosConfig $config): float
    {
        return (float) ($config->lastClosedSession()->value('cash_register_balance_end_real') ?? 0);
    }

    public function setOpeningControl(PosSession $session, float $cash, ?string $notes): PosSession
    {
        if ($session->state !== PosSession::STATE_OPENING_CONTROL) {
            throw new BusinessException('Sesi ini sudah dibuka.');
        }
        if ($cash < 0) {
            throw new BusinessException('Kas awal tidak boleh negatif.');
        }

        $session->update([
            'name' => $this->sequences->next('pos.session'),
            'state' => PosSession::STATE_OPENED,
            'opened_at' => now(),
            'cash_register_balance_start' => LineAmounts::money($cash),
            'opening_notes' => $notes,
        ]);

        return $session;
    }

    /** Kas masuk (type "in") atau kas keluar (type "out"). */
    public function cashMove(PosSession $session, string $type, float $amount, string $reason): CashMove
    {
        $this->ensureOpened($session);
        if (! $session->config->cashControl()) {
            throw new BusinessException('POS ini tidak punya metode pembayaran tunai.');
        }
        if ($amount <= 0) {
            throw new BusinessException('Jumlah harus lebih dari 0.');
        }

        return $session->cashMoves()->create([
            'amount' => LineAmounts::money($type === 'out' ? -$amount : $amount),
            'reason' => $reason,
        ]);
    }

    /**
     * Ringkasan untuk dialog tutup kasir (seperti get_closing_control_data di Odoo).
     *
     * @return array<string, mixed>
     */
    public function closingData(PosSession $session): array
    {
        $orders = $session->orders()->whereIn('state', [PosOrder::STATE_PAID, PosOrder::STATE_DONE])->get();
        $payments = DB::table('pos_payments')
            ->join('pos_orders', 'pos_orders.id', '=', 'pos_payments.order_id')
            ->where('pos_orders.session_id', $session->id)
            ->whereIn('pos_orders.state', [PosOrder::STATE_PAID, PosOrder::STATE_DONE])
            ->groupBy('pos_payments.payment_method_id')
            ->selectRaw('pos_payments.payment_method_id, sum(pos_payments.amount) as amount, count(distinct pos_payments.order_id) as orders')
            ->get()
            ->keyBy('payment_method_id');

        $methods = $session->config->paymentMethods;
        $cashMethod = $methods->first(fn (PaymentMethod $m) => $m->isCash());
        $cashPayments = $cashMethod ? (float) ($payments[$cashMethod->id]->amount ?? 0) : 0.0;
        $cashMoves = $session->cashMoves()->get();

        return [
            'orders' => ['count' => $orders->count(), 'amount' => LineAmounts::money($orders->sum('amount_total'))],
            'cash' => $cashMethod ? [
                'payment_method_id' => $cashMethod->id,
                'name' => $cashMethod->name,
                'opening' => (float) $session->cash_register_balance_start,
                'payments' => LineAmounts::money($cashPayments),
                'moves' => $cashMoves->map(fn (CashMove $m) => [
                    'id' => $m->id, 'amount' => (float) $m->amount, 'reason' => $m->reason, 'created_at' => $m->created_at,
                ])->all(),
                'expected' => $this->expectedCash($session),
            ] : null,
            'non_cash' => $methods->reject(fn (PaymentMethod $m) => $m->isCash())->map(fn (PaymentMethod $m) => [
                'payment_method_id' => $m->id,
                'name' => $m->name,
                'amount' => LineAmounts::money($payments[$m->id]->amount ?? 0),
                'count' => (int) ($payments[$m->id]->orders ?? 0),
            ])->values()->all(),
            'max_difference' => $session->config->set_maximum_difference ? (float) $session->config->amount_authorized_diff : null,
        ];
    }

    /** Kas yang seharusnya ada di laci: kas awal + pembayaran tunai (bersih kembalian) + kas masuk/keluar. */
    public function expectedCash(PosSession $session): float
    {
        $cashMethodIds = $session->config->paymentMethods->filter(fn (PaymentMethod $m) => $m->isCash())->pluck('id');
        $cashPayments = DB::table('pos_payments')
            ->join('pos_orders', 'pos_orders.id', '=', 'pos_payments.order_id')
            ->where('pos_orders.session_id', $session->id)
            ->whereIn('pos_orders.state', [PosOrder::STATE_PAID, PosOrder::STATE_DONE])
            ->whereIn('pos_payments.payment_method_id', $cashMethodIds)
            ->sum('pos_payments.amount');

        return LineAmounts::money((float) $session->cash_register_balance_start + (float) $cashPayments + (float) $session->cashMoves()->sum('amount'));
    }

    /**
     * Tutup sesi: catat kas hasil hitung & selisihnya, lalu posting semua pesanan.
     */
    public function closeSession(PosSession $session, ?float $countedCash, ?string $notes, User $user): PosSession
    {
        return DB::transaction(function () use ($session, $countedCash, $notes, $user) {
            $session = PosSession::query()->lockForUpdate()->findOrFail($session->id);
            if (! in_array($session->state, [PosSession::STATE_OPENED, PosSession::STATE_CLOSING_CONTROL], true)) {
                throw new BusinessException('Sesi ini tidak sedang berjalan.');
            }

            $drafts = $session->orders()->where('state', PosOrder::STATE_DRAFT)->pluck('name');
            if ($drafts->isNotEmpty()) {
                throw new BusinessException('Masih ada pesanan draf di sesi ini: '.$drafts->implode(', ').'. Bayar atau batalkan dulu.');
            }

            $expected = $this->expectedCash($session);
            $hasCash = $session->config->cashControl();
            $counted = $hasCash ? LineAmounts::money($countedCash ?? 0) : 0.0;
            $difference = $hasCash ? LineAmounts::money($counted - $expected) : 0.0;

            $config = $session->config;
            if ($config->set_maximum_difference && abs($difference) > (float) $config->amount_authorized_diff + 0.001
                && ! $user->can(self::MANAGER_PERMISSION)) {
                throw new BusinessException(sprintf(
                    'Selisih kas %s melebihi batas %s. Hitung ulang atau minta manajer POS menutup sesi.',
                    number_format($difference, 2, ',', '.'),
                    number_format((float) $config->amount_authorized_diff, 2, ',', '.'),
                ));
            }

            $session->update([
                'state' => PosSession::STATE_CLOSED,
                'closed_at' => now(),
                'cash_register_balance_end' => $expected,
                'cash_register_balance_end_real' => $counted,
                'cash_register_difference' => $difference,
                'closing_notes' => $notes,
            ]);
            $session->orders()->where('state', PosOrder::STATE_PAID)->update(['state' => PosOrder::STATE_DONE]);

            return $session;
        });
    }

    // ------------------------------------------------------------------
    // Pesanan
    // ------------------------------------------------------------------

    /**
     * Simpan pesanan yang sudah dibayar dari layar kasir (termasuk retur).
     *
     * @param  array{partner_id?: ?int, to_invoice?: bool, notes?: ?string,
     *               lines: list<array{product_id: int, quantity: mixed, unit_price: mixed, discount?: mixed, tax_rate?: mixed, description?: ?string, refunded_line_id?: ?int}>,
     *               payments: list<array{payment_method_id: int, amount: mixed}>}  $data
     */
    public function createOrder(PosSession $session, array $data, User $user): PosOrder
    {
        $this->ensureOpened($session);
        $config = $session->config()->with('paymentMethods')->firstOrFail();

        $order = DB::transaction(function () use ($session, $config, $data, $user) {
            $lines = $this->prepareLines($data['lines']);
            $totals = LineAmounts::totals($lines);
            $isRefund = $lines->contains(fn (array $l) => $l['refunded_line_id'] !== null);
            $refundedOrderId = $isRefund ? PosOrderLine::query()->whereKey($lines->first()['refunded_line_id'])->value('order_id') : null;

            if (! empty($data['to_invoice'])) {
                if (empty($data['partner_id'])) {
                    throw new BusinessException('Pilih pelanggan untuk membuat faktur.');
                }
                if ($totals['amount_total'] < 0) {
                    throw new BusinessException('Retur tidak bisa difakturkan (nota kredit belum tersedia).');
                }
            }

            [$payments, $paid, $change] = $this->preparePayments($config, $data['payments'], $totals['amount_total']);

            $order = PosOrder::query()->create([
                'name' => $this->sequences->next('pos.order'),
                'session_id' => $session->id,
                'config_id' => $config->id,
                'user_id' => $user->id,
                'partner_id' => $data['partner_id'] ?? null,
                'date_order' => now(),
                'state' => PosOrder::STATE_PAID,
                ...$totals,
                'amount_paid' => $paid,
                'amount_return' => $change,
                'to_invoice' => ! empty($data['to_invoice']),
                'is_refund' => $isRefund,
                'refunded_order_id' => $refundedOrderId,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($lines as $line) {
                $order->lines()->create($line);
            }
            foreach ($payments as $payment) {
                $order->payments()->create([...$payment, 'payment_date' => now()]);
            }

            $this->createPickings($order->load('lines.product'), $config);

            if ($order->to_invoice) {
                $this->invoiceOrder($order, $config);
            }

            return $order;
        });

        return $order->refresh();
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return Collection<int, array<string, mixed>>
     */
    private function prepareLines(array $lines): Collection
    {
        if ($lines === []) {
            throw new BusinessException('Pesanan masih kosong.');
        }

        $products = Product::query()->whereIn('id', array_column($lines, 'product_id'))->get()->keyBy('id');
        $refundedIds = array_filter(array_column($lines, 'refunded_line_id'));
        $refunded = PosOrderLine::query()->with('refundLines')->whereIn('id', $refundedIds)->get()->keyBy('id');
        $refundOrders = $refunded->pluck('order_id')->unique();
        if ($refundOrders->count() > 1) {
            throw new BusinessException('Satu retur hanya untuk satu pesanan asal.');
        }
        if ($refundedIds && count($refundedIds) !== count($lines)) {
            throw new BusinessException('Retur tidak bisa dicampur dengan penjualan baru dalam satu pesanan.');
        }

        return collect($lines)->map(function (array $line) use ($products, $refunded) {
            $product = $products[$line['product_id']] ?? throw new BusinessException('Produk tidak ditemukan.');
            $quantity = LineAmounts::quantity($line['quantity']);
            $refundedLineId = $line['refunded_line_id'] ?? null;

            if ($refundedLineId) {
                $original = $refunded[$refundedLineId] ?? throw new BusinessException('Baris pesanan asal tidak ditemukan.');
                $alreadyRefunded = -(float) $original->refundLines->sum('quantity');
                $refundable = round((float) $original->quantity - $alreadyRefunded, 4);
                if ($quantity >= 0 || -$quantity > $refundable + 1e-9) {
                    throw new BusinessException("Jumlah retur {$product->name} maksimal ".rtrim(rtrim(number_format($refundable, 4, ',', '.'), '0'), ',').'.');
                }
                $line['unit_price'] = $original->unit_price;
                $line['discount'] = $original->discount;
                $line['tax_rate'] = $original->tax_rate;
            } else {
                if (! $product->available_in_pos || ! $product->can_be_sold || ! $product->is_active) {
                    throw new BusinessException("{$product->name} tidak tersedia di POS.");
                }
                if ($quantity <= 0) {
                    throw new BusinessException("Jumlah {$product->name} harus lebih dari 0.");
                }
            }

            return [
                'product_id' => $product->id,
                'description' => trim((string) ($line['description'] ?? '')) ?: $product->name,
                'quantity' => $quantity,
                'unit_price' => LineAmounts::money($line['unit_price']),
                'discount' => (float) ($line['discount'] ?? 0),
                'tax_rate' => (float) ($line['tax_rate'] ?? 0),
                'refunded_line_id' => $refundedLineId,
                ...LineAmounts::compute($quantity, $line['unit_price'], $line['discount'] ?? 0, $line['tax_rate'] ?? 0),
            ];
        });
    }

    /**
     * Validasi pembayaran & hitung kembalian. Kembalian hanya bisa dari pembayaran tunai.
     *
     * @param  list<array{payment_method_id: int, amount: mixed}>  $payments
     * @return array{0: list<array{payment_method_id: int, amount: float, is_change: bool}>, 1: float, 2: float}
     */
    private function preparePayments(PosConfig $config, array $payments, float $total): array
    {
        $methods = $config->paymentMethods->keyBy('id');
        $rows = [];
        $paid = 0.0;
        $cashMethod = null;

        foreach ($payments as $payment) {
            $method = $methods[$payment['payment_method_id']] ?? throw new BusinessException('Metode pembayaran tidak tersedia di POS ini.');
            $amount = LineAmounts::money($payment['amount']);
            if ($amount == 0) {
                continue;
            }
            $rows[] = ['payment_method_id' => $method->id, 'amount' => $amount, 'is_change' => false];
            $paid += $amount;
            if ($method->isCash()) {
                $cashMethod ??= $method;
            }
        }
        $paid = LineAmounts::money($paid);

        if ($total >= 0) {
            if ($paid + 0.001 < $total) {
                throw new BusinessException('Pembayaran kurang '.number_format($total - $paid, 2, ',', '.').'.');
            }
            $change = LineAmounts::money($paid - $total);
            if ($change > 0) {
                if (! $cashMethod) {
                    throw new BusinessException('Kembalian hanya bisa diberikan untuk pembayaran tunai.');
                }
                $nonCash = collect($rows)->filter(fn ($r) => ! $methods[$r['payment_method_id']]->isCash())->sum('amount');
                if ($nonCash > $total + 0.001) {
                    throw new BusinessException('Pembayaran non-tunai tidak boleh melebihi total.');
                }
                $rows[] = ['payment_method_id' => $cashMethod->id, 'amount' => -$change, 'is_change' => true];
            }
        } else {
            // Retur: uang dikembalikan tepat sebesar total (negatif), tanpa kembalian.
            if (abs($paid - $total) > 0.001) {
                throw new BusinessException('Jumlah pengembalian dana harus sama dengan total retur.');
            }
            $change = 0.0;
        }

        return [$rows, $paid, $change];
    }

    /**
     * Pengurangan stok real time. Penjualan: gudang → pelanggan; retur: pelanggan → gudang.
     */
    private function createPickings(PosOrder $order, PosConfig $config): void
    {
        $goods = $order->lines->filter(fn (PosOrderLine $l) => $l->product->type !== Product::TYPE_SERVICE && (float) $l->quantity != 0);

        foreach ([Picking::TYPE_DELIVERY => $goods->filter(fn ($l) => (float) $l->quantity > 0), Picking::TYPE_RECEIPT => $goods->filter(fn ($l) => (float) $l->quantity < 0)] as $type => $lines) {
            if ($lines->isEmpty()) {
                continue;
            }

            $attributes = [
                'warehouse_id' => $config->warehouse_id,
                'partner_id' => $order->partner_id,
                'origin' => $order->name,
                'origin_type' => PosOrder::ORIGIN,
                'origin_id' => $order->id,
            ];
            if ($type === Picking::TYPE_RECEIPT) {
                $attributes['source_location_id'] = $this->inventory->virtualLocation(StockLocation::USAGE_CUSTOMER)->id;
            }

            $picking = $this->inventory->createPicking($type, $lines->map(fn (PosOrderLine $l) => [
                'product_id' => $l->product_id,
                'quantity' => abs((float) $l->quantity),
                'origin_line_type' => 'pos.order.line',
                'origin_line_id' => $l->id,
            ])->values()->all(), $attributes);

            $this->inventory->validate($picking, allowNegative: true);
        }
    }

    /** Buat faktur, posting, dan tandai lunas sesuai pembayaran pesanan. */
    private function invoiceOrder(PosOrder $order, PosConfig $config): void
    {
        $invoice = $this->invoices->create(Invoice::TYPE_CUSTOMER, $order->partner_id, $order->lines->map(fn (PosOrderLine $l) => [
            'product_id' => $l->product_id,
            'description' => $l->description,
            'quantity' => $l->quantity,
            'unit_price' => $l->unit_price,
            'discount' => $l->discount,
            'tax_rate' => $l->tax_rate,
            'origin_line_type' => 'pos.order.line',
            'origin_line_id' => $l->id,
        ])->values()->all(), [
            'invoice_date' => now()->toDateString(),
            'origin' => $order->name,
            'origin_type' => PosOrder::ORIGIN,
            'origin_id' => $order->id,
        ]);
        $this->invoices->post($invoice);

        $methods = $config->paymentMethods->keyBy('id');
        $order->payments()->get()->groupBy('payment_method_id')->each(function ($payments, $methodId) use ($invoice, $methods) {
            $amount = (float) $payments->sum('amount');
            if ($amount > 0) {
                $this->invoices->registerPayment($invoice, $amount, now()->toDateString(), $methods[$methodId]->isCash() ? 'cash' : 'bank', $methods[$methodId]->name);
            }
        });

        $order->update(['invoice_id' => $invoice->id, 'state' => PosOrder::STATE_DONE]);
    }

    private function ensureOpened(PosSession $session): void
    {
        if (! $session->isOpened()) {
            throw new BusinessException('Sesi kasir belum dibuka atau sudah ditutup.');
        }
    }
}
