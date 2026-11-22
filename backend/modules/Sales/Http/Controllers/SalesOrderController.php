<?php

namespace Modules\Sales\Http\Controllers;

use App\Core\Exceptions\BusinessException;
use App\Core\Http\ListQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Sales\Http\Requests\SalesOrderRequest;
use Modules\Sales\Http\Resources\SalesOrderResource;
use Modules\Sales\Models\SalesOrder;
use Modules\Sales\Services\SalesOrderService;

class SalesOrderController extends Controller
{
    private const RELATIONS = ['partner', 'lines.product.uom'];

    public function __construct(private readonly SalesOrderService $orders) {}

    /**
     * Filter: ?state=draft|confirmed|cancelled (bisa dipisah koma), ?partner_id=, ?search=
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = SalesOrder::query()
            ->select('sales_orders.*')
            ->join('partners', 'partners.id', '=', 'sales_orders.partner_id')
            ->with(['partner', 'lines.product.uom']);
        $query->when($request->query('state'), fn ($q, $state) => $q->whereIn('sales_orders.state', explode(',', $state)));
        $query->when($request->query('partner_id'), fn ($q, $id) => $q->where('sales_orders.partner_id', $id));

        return SalesOrderResource::collection(ListQuery::paginate(
            $query, $request, ['sales_orders.number', 'partners.name'],
            ['sales_orders.number', 'sales_orders.order_date', 'sales_orders.amount_total', 'partners.name'],
            'sales_orders.id', 'desc',
        ));
    }

    public function show(SalesOrder $order): SalesOrderResource
    {
        return (new SalesOrderResource($order->load(self::RELATIONS)))->withRelated();
    }

    public function store(SalesOrderRequest $request): JsonResponse
    {
        $order = $this->orders->save(null, $request->validated());

        return $this->show($order)->response()->setStatusCode(201);
    }

    public function update(SalesOrderRequest $request, SalesOrder $order): SalesOrderResource
    {
        return $this->show($this->orders->save($order, $request->validated()));
    }

    public function destroy(SalesOrder $order): JsonResponse
    {
        if ($order->state !== SalesOrder::STATE_DRAFT) {
            throw new BusinessException('Hanya penawaran (draf) yang bisa dihapus; batalkan pesanan.');
        }
        $order->delete();

        return response()->json(['message' => 'Penawaran dihapus.']);
    }

    public function confirm(SalesOrder $order): SalesOrderResource
    {
        return $this->show($this->orders->confirm($order));
    }

    public function cancel(SalesOrder $order): SalesOrderResource
    {
        return $this->show($this->orders->cancel($order));
    }

    public function setToDraft(SalesOrder $order): SalesOrderResource
    {
        return $this->show($this->orders->setToDraft($order));
    }

    public function createInvoice(SalesOrder $order): JsonResponse
    {
        $invoice = $this->orders->createInvoice($order);

        return response()->json(['message' => 'Faktur draf dibuat.', 'invoice_id' => $invoice->id], 201);
    }
}
