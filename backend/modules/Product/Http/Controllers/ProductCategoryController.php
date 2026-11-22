<?php

namespace Modules\Product\Http\Controllers;

use App\Core\Http\ListQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Product\Models\ProductCategory;

class ProductCategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ProductCategory::query()->withCount('products');
        $page = ListQuery::paginate($query, $request, ['complete_name'], ['complete_name'], 'complete_name');

        return response()->json($page->through(fn (ProductCategory $c) => $this->present($c)));
    }

    public function store(Request $request): JsonResponse
    {
        $category = ProductCategory::query()->create($this->validated($request));

        return response()->json(['data' => $this->present($category->loadCount('products'))], 201);
    }

    public function update(Request $request, ProductCategory $category): JsonResponse
    {
        $data = $this->validated($request);

        if ($category->isSelfOrDescendant(ProductCategory::query()->find($data['parent_id'] ?? null))) {
            throw ValidationException::withMessages(['parent_id' => 'Induk tidak boleh kategori ini sendiri atau turunannya.']);
        }

        DB::transaction(fn () => $category->update($data));

        return response()->json(['data' => $this->present($category->loadCount('products'))]);
    }

    public function destroy(ProductCategory $category): JsonResponse
    {
        if ($category->products()->withTrashed()->exists() || $category->children()->exists()) {
            abort(422, 'Kategori masih dipakai produk atau punya sub-kategori.');
        }

        $category->delete();

        return response()->json(['message' => 'Kategori dihapus.']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', 'not_regex:/\\//'],
            'parent_id' => ['nullable', Rule::exists('product_categories', 'id')],
        ], ['name.not_regex' => 'Nama kategori tidak boleh mengandung "/".'], ['name' => 'nama', 'parent_id' => 'induk']);
    }

    private function present(ProductCategory $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'parent_id' => $category->parent_id,
            'complete_name' => $category->complete_name,
            'products_count' => $category->products_count,
        ];
    }
}
