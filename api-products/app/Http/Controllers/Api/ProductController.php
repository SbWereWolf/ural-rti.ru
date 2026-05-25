<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductIndexRequest;
use App\Http\Resources\ProductCatalogResource;
use App\Services\Catalog\ProductCatalogQuery;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function __invoke(ProductIndexRequest $request, ProductCatalogQuery $catalogQuery): JsonResponse
    {
        $paginator = $catalogQuery->paginate($request);

        return response()->json([
            'data' => ProductCatalogResource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
