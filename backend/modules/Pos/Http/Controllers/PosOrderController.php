<?php

namespace Modules\Pos\Http\Controllers;

use App\Core\Http\ListQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Modules\Pos\Http\Resources\PosOrderResource;
use Modules\Pos\Models\PosOrder;
use Modules\Pos\Models\PosSession;
use Modules\Pos\Services\PosService;

class PosOrderController extends Controller
{
    private const RELATIONS = ['session', 'config', 'user', 'partner', 'invoice', 'refundedOrder', 'lines.product.uom', 'lines.refundLines', 'payments.method'];

    public function __construct(private readonly PosService $pos) {}

    /**
     * Filter: ?session_id=, ?config_id=, ?state=, ?search= (nomor / pelanggan)
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = PosOrder::query()
            ->select('pos_orders.*')
            ->leftJoin('partners', 'partners.id', '=', 'pos_orders.partner_id')
            ->with(['session', 'config', 'user', 'partner']);
        foreach (['session_id', 'config_id', 'state'] as $filter) {
            $query->when($request->query($filter), fn ($q, $value) => $q->where("pos_orders.{$filter}", $value));
        }

        return PosOrderResource::collection(ListQuery::paginate(
            $query, $request, ['pos_orders.name', 'partners.name'], ['pos_orders.date_order', 'pos_orders.amount_total'], 'pos_orders.id', 'desc',
        ));
    }

    public function show(PosOrder $order): PosOrderResource
    {
        return new PosOrderResource($order->load(self::RELATIONS));
    }

    /** Simpan pesanan yang sudah dibayar dari layar kasir (penjualan atau retur). */
    public function store(Request $request, PosSession $session): JsonResponse
    {
        $data = $request->validate([
            'partner_id' => ['nullable', Rule::exists('partners', 'id')->whereNull('deleted_at')],
            'to_invoice' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', Rule::exists('products', 'id')],
            'lines.*.quantity' => ['required', 'numeric', 'not_in:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.discount' => ['nullable', 'numeric', 'between:0,100'],
            'lines.*.tax_rate' => ['nullable', 'numeric', 'between:0,100'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.refunded_line_id' => ['nullable', 'distinct', Rule::exists('pos_order_lines', 'id')],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.payment_method_id' => ['required', 'integer'],
            'payments.*.amount' => ['required', 'numeric'],
        ], [], [
            'partner_id' => 'pelanggan',
            'lines' => 'produk',
            'payments' => 'pembayaran',
        ]);

        $order = $this->pos->createOrder($session, $data, $request->user());

        return (new PosOrderResource($order->load(self::RELATIONS)))->response()->setStatusCode(201);
    }
}
