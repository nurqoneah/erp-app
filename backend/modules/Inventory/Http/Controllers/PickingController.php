<?php

namespace Modules\Inventory\Http\Controllers;

use App\Core\Http\ListQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Exceptions\InventoryException;
use Modules\Inventory\Http\Requests\PickingRequest;
use Modules\Inventory\Http\Resources\PickingResource;
use Modules\Inventory\Models\Picking;
use Modules\Inventory\Services\InventoryService;

class PickingController extends Controller
{
    private const RELATIONS = ['partner', 'sourceLocation', 'destLocation', 'moves.product.uom'];

    public function __construct(private readonly InventoryService $inventory) {}

    /**
     * Filter: ?type=receipt|delivery|internal, ?state=draft|done|cancelled, ?search=, ?origin_type=&origin_id=
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Picking::query()->with(['partner', 'sourceLocation', 'destLocation']);
        $query->when($request->query('type'), fn ($q, $type) => $q->where('type', $type));
        $query->when($request->query('state'), fn ($q, $state) => $q->where('state', $state));
        $query->when($request->query('origin_type'), fn ($q, $type) => $q->where('origin_type', $type)->where('origin_id', $request->query('origin_id')));

        return PickingResource::collection(ListQuery::paginate(
            $query, $request, ['reference', 'origin'], ['reference', 'scheduled_date', 'created_at'], 'id', 'desc',
        ));
    }

    public function show(Picking $picking): PickingResource
    {
        return new PickingResource($picking->load(self::RELATIONS));
    }

    public function store(PickingRequest $request): JsonResponse
    {
        $data = $request->validated();
        $picking = $this->inventory->createPicking($data['type'], $data['lines'], $data);

        return (new PickingResource($picking->load(self::RELATIONS)))->response()->setStatusCode(201);
    }

    public function update(PickingRequest $request, Picking $picking): PickingResource
    {
        if ($picking->origin_type !== null) {
            throw new InventoryException("Transfer {$picking->reference} berasal dari {$picking->origin}; barisnya tidak bisa diubah.");
        }

        $data = $request->validated();
        DB::transaction(function () use ($picking, $data) {
            $picking->update(collect($data)->only(['partner_id', 'scheduled_date', 'source_location_id', 'dest_location_id', 'notes'])
                ->filter(fn ($v, $k) => $v !== null || in_array($k, ['partner_id', 'notes'], true))
                ->all());
            $this->inventory->replaceMoves($picking->refresh(), $data['lines']);
        });

        return new PickingResource($picking->refresh()->load(self::RELATIONS));
    }

    public function validateTransfer(Picking $picking): PickingResource
    {
        return new PickingResource($this->inventory->validate($picking)->load(self::RELATIONS));
    }

    public function cancel(Picking $picking): PickingResource
    {
        return new PickingResource($this->inventory->cancel($picking)->load(self::RELATIONS));
    }
}
