<?php

namespace Modules\Product\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Product\Models\Product;
use Modules\Product\Models\Uom;
use Modules\Product\Models\UomCategory;

/**
 * Kategori satuan beserta satuan-satuannya. Tiap kategori harus punya tepat satu satuan acuan.
 */
class UomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = UomCategory::query()
            ->with(['uoms' => fn ($q) => $q
                ->when(! $request->boolean('all'), fn ($q) => $q->where('is_active', true))
                ->orderByRaw("case type when 'reference' then 0 when 'smaller' then 1 else 2 end")
                ->orderBy('ratio')])
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $categories->map(fn (UomCategory $c) => [
            'id' => $c->id,
            'name' => $c->name,
            'uoms' => $c->uoms->map(fn (Uom $u) => $this->presentUom($u)),
        ])]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('uom_categories', 'name')],
            'reference_name' => ['required', 'string', 'max:50'],
        ], [], ['name' => 'nama kategori', 'reference_name' => 'satuan acuan']);

        $category = DB::transaction(function () use ($data) {
            $category = UomCategory::query()->create(['name' => $data['name']]);
            $category->uoms()->create(['name' => $data['reference_name'], 'type' => Uom::TYPE_REFERENCE, 'ratio' => 1]);

            return $category;
        });

        return response()->json(['data' => ['id' => $category->id, 'name' => $category->name]], 201);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $uom = Uom::query()->create($data);

        return response()->json(['data' => $this->presentUom($uom)], 201);
    }

    public function update(Request $request, Uom $uom): JsonResponse
    {
        $data = $this->validated($request, $uom);

        if ((int) $data['category_id'] !== $uom->category_id && $this->isUsed($uom)) {
            throw ValidationException::withMessages(['category_id' => 'Kategori satuan yang sudah dipakai produk tidak bisa diubah.']);
        }
        if ($uom->type === Uom::TYPE_REFERENCE && $data['type'] !== Uom::TYPE_REFERENCE) {
            throw ValidationException::withMessages(['type' => 'Satuan acuan tidak bisa diubah tipenya.']);
        }

        $uom->update($data);

        return response()->json(['data' => $this->presentUom($uom)]);
    }

    private function validated(Request $request, ?Uom $uom = null): array
    {
        $data = $request->validate([
            'category_id' => ['required', Rule::exists('uom_categories', 'id')],
            'name' => [
                'required', 'string', 'max:50',
                Rule::unique('uoms', 'name')->where('category_id', $request->input('category_id'))->ignore($uom?->id),
            ],
            'type' => ['required', Rule::in(Uom::TYPES)],
            'ratio' => ['required', 'numeric', 'gt:0'],
            'rounding' => ['nullable', 'numeric', 'gt:0'],
            'is_active' => ['boolean'],
        ], [], ['name' => 'nama satuan', 'ratio' => 'rasio', 'type' => 'tipe']);

        if ($data['type'] === Uom::TYPE_REFERENCE) {
            $data['ratio'] = 1;
            $exists = Uom::query()
                ->where('category_id', $data['category_id'])
                ->where('type', Uom::TYPE_REFERENCE)
                ->when($uom, fn ($q) => $q->whereKeyNot($uom->id))
                ->exists();
            if ($exists) {
                throw ValidationException::withMessages(['type' => 'Kategori ini sudah punya satuan acuan.']);
            }
        }
        $data['rounding'] ??= 0.01;

        return $data;
    }

    private function isUsed(Uom $uom): bool
    {
        return Product::withTrashed()
            ->where(fn ($q) => $q->where('uom_id', $uom->id)->orWhere('purchase_uom_id', $uom->id))
            ->exists();
    }

    private function presentUom(Uom $uom): array
    {
        return [
            'id' => $uom->id,
            'category_id' => $uom->category_id,
            'name' => $uom->name,
            'type' => $uom->type,
            'ratio' => $uom->ratio,
            'rounding' => $uom->rounding,
            'is_active' => $uom->is_active,
        ];
    }
}
