<?php

namespace App\Services\Catalog;

use App\Http\Requests\ProductIndexRequest;
use App\Models\Catalog\CategoryProductCatalogView;
use App\Models\Catalog\ProductCatalogCount;
use App\Models\Catalog\ProductCatalogView;
use App\Models\Catalog\StockProductCatalogView;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ProductCatalogQuery
{
    /**
     * @var list<string>
     */
    private const RESPONSE_COLUMNS = [
        'product_id',
        'product_name',
        'product_sku',
        'product_price',
        'category_id',
        'category_name',
        'stock_quantity',
        'stock_actual_at',
    ];

    public function paginate(ProductIndexRequest $request): LengthAwarePaginator
    {
        $query = $this->baseQuery($request);

        $this->applyFilters($query, $request);

        $query->orderBy('category_id')->orderBy('product_id');

        return $query->paginate(
            perPage: $request->perPage(),
            columns: self::RESPONSE_COLUMNS,
            pageName: 'page',
            page: $request->page(),
            total: $this->customTotal($request),
        );
    }

    /**
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    private function baseQuery(ProductIndexRequest $request): Builder
    {
        if ($request->categoryId() !== null) {
            return CategoryProductCatalogView::query();
        }

        if ($request->inStock()) {
            return StockProductCatalogView::query();
        }

        return ProductCatalogView::query();
    }

    /**
     * @param Builder<\Illuminate\Database\Eloquent\Model> $query
     */
    private function applyFilters(Builder $query, ProductIndexRequest $request): void
    {
        if ($request->categoryId() !== null) {
            $query->where('category_id', $request->categoryId());
        }

        if ($request->priceMin() !== null) {
            $query->where('product_price', '>=', $request->priceMin());
        }

        if ($request->priceMax() !== null) {
            $query->where('product_price', '<=', $request->priceMax());
        }

        if ($request->inStock()) {
            $query->where('stock_quantity', '>', 0);
        }
    }

    private function customTotal(ProductIndexRequest $request): ?int
    {
        if ($request->hasEffectiveFilters()) {
            return null;
        }

        return ProductCatalogCount::query()->value('quantity') ?? 0;
    }
}
