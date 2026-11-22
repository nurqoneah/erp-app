<?php

namespace Modules\Inventory\Http\Controllers;

use App\Core\Http\ListQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Inventory\Models\StockLocation;
use Modules\Inventory\Models\StockMove;
use Modules\Inventory\Models\StockQuant;
use Modules\Inventory\Services\InventoryService;

class StockController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * Stok per produk per lokasi internal. Filter: ?search= (produk), ?location_id=, ?warehouse_id=, ?product_id=
     */
    public function index(Request $request): JsonResponse
    {
        $query = StockQuant::query()
            ->select('stock_quants.*')
            ->join('products', 'products.id', '=', 'stock_quants.product_id')
            ->join('stock_locations', 'stock_locations.id', '=', 'stock_quants.location_id')
            ->with(['product.uom', 'location'])
            ->where('stock_locations.usage', StockLocation::USAGE_INTERNAL)
            ->where('stock_quants.quantity', '!=', 0);

        $query->when($request->query('location_id'), fn ($q, $id) => $q->where('stock_quants.location_id', $id));
        $query->when($request->query('warehouse_id'), fn ($q, $id) => $q->where('stock_locations.warehouse_id', $id));
        $query->when($request->query('product_id'), fn ($q, $id) => $q->where('stock_quants.product_id', $id));

        $page = ListQuery::paginate(
            $query, $request, ['products.name', 'products.sku', 'products.barcode'],
            ['products.name', 'stock_locations.complete_name', 'stock_quants.quantity'], 'products.name',
        );

        return response()->json($page->through(fn (StockQuant $quant) => [
            'id' => $quant->id,
            'product' => [
                'id' => $quant->product->id,
                'name' => $quant->product->name,
                'sku' => $quant->product->sku,
                'uom' => $quant->product->uom?->name,
            ],
            'location' => ['id' => $quant->location->id, 'complete_name' => $quant->location->complete_name],
            'quantity' => $quant->quantity,
        ]));
    }

    /**
     * Stok tersedia per produk: ?product_ids[]=1&product_ids[]=2 → {"1": 10, "2": 0}
     */
    public function summary(Request $request): JsonResponse
    {
        $ids = $request->validate([
            'product_ids' => ['required', 'array', 'max:200'],
            'product_ids.*' => ['integer'],
            'warehouse_id' => ['nullable', 'integer'],
        ]);
        $onHand = $this->inventory->onHand($ids['product_ids'], $ids['warehouse_id'] ?? null);

        return response()->json(['data' => collect($ids['product_ids'])
            ->mapWithKeys(fn ($id) => [$id => $onHand[$id] ?? 0.0])]);
    }

    /**
     * Riwayat penyesuaian stok (perpindahan tanpa transfer).
     */
    public function adjustments(Request $request): JsonResponse
    {
        $query = StockMove::query()
            ->whereNull('picking_id')
            ->with(['product.uom', 'sourceLocation', 'destLocation']);

        $page = ListQuery::paginate($query, $request, ['reference'], ['done_at'], 'id', 'desc');

        return response()->json($page->through(function (StockMove $move) {
            $increase = $move->destLocation->isInternal();

            return [
                'id' => $move->id,
                'reference' => $move->reference,
                'product' => ['id' => $move->product->id, 'name' => $move->product->name, 'uom' => $move->product->uom?->name],
                'location' => $increase ? $move->destLocation->complete_name : $move->sourceLocation->complete_name,
                'change' => ($increase ? '' : '-').$move->quantity,
                'done_at' => $move->done_at,
            ];
        }));
    }

    public function adjust(Request $request): JsonResponse
    {
        $data = $request->validate([
            'location_id' => ['required', Rule::exists('stock_locations', 'id')->where('usage', StockLocation::USAGE_INTERNAL)],
            'reason' => ['nullable', 'string', 'max:100'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'distinct', Rule::exists('products', 'id')],
            'lines.*.quantity' => ['required', 'numeric', 'min:0'],
        ], [], [
            'location_id' => 'lokasi',
            'lines' => 'baris produk',
            'lines.*.product_id' => 'produk',
            'lines.*.quantity' => 'jumlah terhitung',
        ]);

        $moves = $this->inventory->adjust(
            StockLocation::query()->findOrFail($data['location_id']),
            collect($data['lines'])->mapWithKeys(fn ($l) => [$l['product_id'] => $l['quantity']])->all(),
            $data['reason'] ?? null,
        );

        return response()->json(['message' => "Stok disesuaikan ({$moves->count()} produk berubah).", 'changed' => $moves->count()]);
    }
}
