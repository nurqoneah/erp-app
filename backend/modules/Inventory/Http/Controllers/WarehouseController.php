<?php

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Inventory\Exceptions\InventoryException;
use Modules\Inventory\Models\StockLocation;
use Modules\Inventory\Models\Warehouse;
use Modules\Inventory\Services\InventoryService;

class WarehouseController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function index(): JsonResponse
    {
        $warehouses = Warehouse::query()
            ->with(['locations' => fn ($q) => $q->where('usage', StockLocation::USAGE_INTERNAL)->orderBy('complete_name')])
            ->orderBy('id')
            ->get();

        return response()->json(['data' => $warehouses->map(fn (Warehouse $w) => [
            'id' => $w->id,
            'name' => $w->name,
            'code' => $w->code,
            'is_active' => $w->is_active,
            'stock_location_id' => $w->stock_location_id,
            'locations' => $w->locations->map(fn (StockLocation $l) => [
                'id' => $l->id, 'name' => $l->name, 'complete_name' => $l->complete_name, 'is_active' => $l->is_active,
            ]),
        ])]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:10', 'alpha_num', Rule::unique('warehouses', 'code')],
        ], [], ['name' => 'nama gudang', 'code' => 'kode']);

        $warehouse = $this->inventory->createWarehouse($data['name'], $data['code']);

        return response()->json(['data' => ['id' => $warehouse->id]], 201);
    }

    public function update(Request $request, Warehouse $warehouse): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ], [], ['name' => 'nama gudang']);

        if (array_key_exists('is_active', $data) && ! $data['is_active']
            && Warehouse::query()->where('is_active', true)->whereKeyNot($warehouse->id)->doesntExist()) {
            throw new InventoryException('Minimal harus ada satu gudang aktif.');
        }

        $warehouse->update($data);

        return response()->json(['data' => ['id' => $warehouse->id]]);
    }

    /**
     * Lokasi untuk pilihan di form. ?usage=internal (default semua kecuali view).
     */
    public function locations(Request $request): JsonResponse
    {
        $locations = StockLocation::query()
            ->where('is_active', true)
            ->when($request->query('usage'),
                fn ($q, $usage) => $q->where('usage', $usage),
                fn ($q) => $q->where('usage', '!=', StockLocation::USAGE_VIEW))
            ->orderBy('complete_name')
            ->get(['id', 'complete_name', 'usage', 'warehouse_id']);

        return response()->json(['data' => $locations]);
    }

    /** Tambah sub-lokasi internal di sebuah gudang, mis. "WH/Stok/Rak A". */
    public function storeLocation(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'not_regex:/\\//'],
            'parent_id' => ['required', Rule::exists('stock_locations', 'id')->whereNotNull('warehouse_id')],
        ], ['name.not_regex' => 'Nama lokasi tidak boleh mengandung "/".'], ['name' => 'nama lokasi', 'parent_id' => 'induk']);

        $parent = StockLocation::query()->findOrFail($data['parent_id']);
        $location = StockLocation::query()->create([
            'name' => $data['name'],
            'parent_id' => $parent->id,
            'usage' => StockLocation::USAGE_INTERNAL,
            'warehouse_id' => $parent->warehouse_id,
        ]);

        return response()->json(['data' => ['id' => $location->id, 'complete_name' => $location->complete_name]], 201);
    }
}
