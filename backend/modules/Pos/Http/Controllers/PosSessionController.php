<?php

namespace Modules\Pos\Http\Controllers;

use App\Core\Http\ListQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Modules\Pos\Http\Resources\PosSessionResource;
use Modules\Pos\Models\PosOrder;
use Modules\Pos\Models\PosSession;
use Modules\Pos\Services\PosService;
use Modules\Product\Models\Product;

class PosSessionController extends Controller
{
    public function __construct(private readonly PosService $pos) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = PosSession::query()
            ->with(['config', 'user'])
            ->withCount(['orders' => fn ($q) => $q->whereIn('state', [PosOrder::STATE_PAID, PosOrder::STATE_DONE])])
            ->withSum(['orders' => fn ($q) => $q->whereIn('state', [PosOrder::STATE_PAID, PosOrder::STATE_DONE])], 'amount_total');
        $query->when($request->query('config_id'), fn ($q, $id) => $q->where('config_id', $id));
        $query->when($request->query('state'), fn ($q, $state) => $q->where('state', $state));

        return PosSessionResource::collection(ListQuery::paginate($query, $request, ['name'], ['opened_at', 'closed_at'], 'id', 'desc'));
    }

    public function show(PosSession $session): JsonResponse
    {
        $session->load(['config.paymentMethods', 'user'])->loadCount('orders');

        return response()->json([
            'data' => new PosSessionResource($session),
            'summary' => $this->pos->closingData($session),
            'suggested_opening_cash' => $session->state === PosSession::STATE_OPENING_CONTROL
                ? $this->pos->suggestedOpeningCash($session->config) : null,
        ]);
    }

    public function openingControl(Request $request, PosSession $session): JsonResponse
    {
        $data = $request->validate([
            'cash' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], ['cash' => 'kas awal']);

        $this->pos->setOpeningControl($session, (float) $data['cash'], $data['notes'] ?? null);

        return $this->show($session->refresh());
    }

    /**
     * Data awal layar kasir: konfigurasi, metode pembayaran, dan produk yang tersedia di POS.
     */
    public function load(PosSession $session): JsonResponse
    {
        $session->load(['config.paymentMethods', 'user']);

        $products = Product::query()
            ->with(['uom', 'category'])
            ->where('is_active', true)
            ->where('available_in_pos', true)
            ->where('can_be_sold', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'session' => new PosSessionResource($session),
            'config' => [
                'id' => $session->config->id,
                'name' => $session->config->name,
                'receipt_header' => $session->config->receipt_header,
                'receipt_footer' => $session->config->receipt_footer,
                'cash_control' => $session->config->cashControl(),
            ],
            'payment_methods' => $session->config->paymentMethods->where('is_active', true)->values()
                ->map(fn ($m) => ['id' => $m->id, 'name' => $m->name, 'type' => $m->type]),
            'products' => $products->map(fn (Product $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'barcode' => $p->barcode,
                'price' => $p->sale_price,
                'type' => $p->type,
                'uom' => $p->uom?->name,
                'category_id' => $p->category_id,
                'category' => $p->category?->complete_name,
            ]),
        ]);
    }

    public function cashMove(Request $request, PosSession $session): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['in', 'out'])],
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:255'],
        ], [], ['amount' => 'jumlah', 'reason' => 'alasan']);

        $move = $this->pos->cashMove($session, $data['type'], (float) $data['amount'], $data['reason']);

        return response()->json(['data' => $move], 201);
    }

    public function close(Request $request, PosSession $session): JsonResponse
    {
        $data = $request->validate([
            'counted_cash' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [], ['counted_cash' => 'kas terhitung']);

        $this->pos->closeSession($session, isset($data['counted_cash']) ? (float) $data['counted_cash'] : null, $data['notes'] ?? null, $request->user());

        return $this->show($session->refresh());
    }
}
