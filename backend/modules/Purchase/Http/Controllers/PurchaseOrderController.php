<?php

namespace Modules\Purchase\Http\Controllers;

use App\Core\Exceptions\BusinessException;
use App\Core\Http\ListQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Purchase\Http\Requests\PurchaseOrderRequest;
use Modules\Purchase\Http\Resources\PurchaseOrderResource;
use Modules\Purchase\Models\PurchaseOrder;
use Modules\Purchase\Services\PurchaseOrderService;

class PurchaseOrderController extends Controller
{
    private const RELATIONS = ['partner', 'lines.product.uom'];

    public function __construct(private readonly PurchaseOrderService $orders) {}

    /**
     * Filter: ?state=draft|confirmed|cancelled (bisa dipisah koma), ?partner_id=, ?search=
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = PurchaseOrder::query()
            ->select('purchase_orders.*')
            ->join('partners', 'partners.id', '=', 'purchase_orders.partner_id')
            ->with(['partner', 'lines.product.uom']);
        $query->when($request->query('state'), fn ($q, $state) => $q->whereIn('purchase_orders.state', explode(',', $state)));
        $query->when($request->query('partner_id'), fn ($q, $id) => $q->where('purchase_orders.partner_id', $id));

        return PurchaseOrderResource::collection(ListQuery::paginate(
            $query, $request, ['purchase_orders.number', 'partners.name'],
            ['purchase_orders.number', 'purchase_orders.order_date', 'purchase_orders.amount_total', 'partners.name'],
            'purchase_orders.id', 'desc',
        ));
    }

    public function show(PurchaseOrder $order): PurchaseOrderResource
    {
        return (new PurchaseOrderResource($order->load(self::RELATIONS)))->withRelated();
    }

    public function store(PurchaseOrderRequest $request): JsonResponse
    {
        $order = $this->orders->save(null, $request->validated());

        return $this->show($order)->response()->setStatusCode(201);
    }

    public function update(PurchaseOrderRequest $request, PurchaseOrder $order): PurchaseOrderResource
    {
        return $this->show($this->orders->save($order, $request->validated()));
    }

    public function destroy(PurchaseOrder $order): JsonResponse
    {
        if ($order->state !== PurchaseOrder::STATE_DRAFT) {
            throw new BusinessException('Hanya RFQ (draf) yang bisa dihapus; batalkan pesanan.');
        }
        $order->delete();

        return response()->json(['message' => 'RFQ dihapus.']);
    }

    public function confirm(PurchaseOrder $order): PurchaseOrderResource
    {
        return $this->show($this->orders->confirm($order));
    }

    public function cancel(PurchaseOrder $order): PurchaseOrderResource
    {
        return $this->show($this->orders->cancel($order));
    }

    public function setToDraft(PurchaseOrder $order): PurchaseOrderResource
    {
        return $this->show($this->orders->setToDraft($order));
    }

    public function createBill(PurchaseOrder $order): JsonResponse
    {
        $invoice = $this->orders->createBill($order);

        return response()->json(['message' => 'Tagihan draf dibuat.', 'bill_id' => $invoice->id], 201);
    }
}
