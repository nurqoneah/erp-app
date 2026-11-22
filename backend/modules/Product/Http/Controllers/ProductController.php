<?php

namespace Modules\Product\Http\Controllers;

use App\Core\Http\ListQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\Product\Http\Requests\ProductRequest;
use Modules\Product\Http\Resources\ProductResource;
use Modules\Product\Models\Product;
use Modules\Product\Models\ProductCategory;

class ProductController extends Controller
{
    private const RELATIONS = ['category', 'uom', 'purchaseUom'];

    /**
     * Filter: ?search=, ?type=, ?category_id= (termasuk sub-kategori), ?sold=1, ?purchased=1, ?pos=1, ?active=
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Product::query()->with(self::RELATIONS);
        ListQuery::filterActive($query, $request);

        $query->when($request->query('type'), fn ($q, $type) => $q->where('type', $type));
        $query->when($request->boolean('sold'), fn ($q) => $q->where('can_be_sold', true));
        $query->when($request->boolean('purchased'), fn ($q) => $q->where('can_be_purchased', true));
        $query->when($request->boolean('pos'), fn ($q) => $q->where('available_in_pos', true));
        $query->when($request->query('category_id'), function ($q, $categoryId) {
            $category = ProductCategory::query()->find($categoryId);
            $q->whereIn('category_id', ProductCategory::query()
                ->where('id', $categoryId)
                ->when($category, fn ($c) => $c->orWhere('complete_name', 'like', $category->complete_name.' / %'))
                ->select('id'));
        });

        return ProductResource::collection(ListQuery::paginate(
            $query,
            $request,
            ['products.name', 'sku', 'barcode'],
            ['name', 'sku', 'sale_price', 'cost', 'created_at'],
            'name',
        ));
    }

    public function show(Product $product): ProductResource
    {
        return new ProductResource($product->load(self::RELATIONS));
    }

    public function store(ProductRequest $request): JsonResponse
    {
        $product = Product::query()->create($request->validated());

        return (new ProductResource($product->load(self::RELATIONS)))->response()->setStatusCode(201);
    }

    public function update(ProductRequest $request, Product $product): ProductResource
    {
        $product->update($request->validated());

        return new ProductResource($product->load(self::RELATIONS));
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json(['message' => 'Produk dihapus.']);
    }
}
