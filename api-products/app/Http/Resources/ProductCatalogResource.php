<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductCatalogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'product' => [
                'id' => $this->resource->product_id,
                'name' => $this->resource->product_name,
                'sku' => $this->resource->product_sku,
                'price' => number_format((float) $this->resource->product_price, 2, '.', ''),
            ],
            'category' => [
                'id' => $this->resource->category_id,
                'name' => $this->resource->category_name,
            ],
            'stock' => [
                'quantity' => $this->resource->stock_quantity,
                'actual_at' => $this->resource->stock_actual_at?->toJSON(),
            ],
        ];
    }
}
