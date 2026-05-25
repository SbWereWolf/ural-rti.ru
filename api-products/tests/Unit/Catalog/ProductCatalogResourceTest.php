<?php

namespace Tests\Unit\Catalog;

use App\Http\Resources\ProductCatalogResource;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;
use stdClass;

class ProductCatalogResourceTest extends TestCase
{
    public function test_it_maps_catalog_row_to_api_contract(): void
    {
        $row = new stdClass();
        $row->product_id = 123;
        $row->product_name = 'Test Product';
        $row->product_sku = 'SKU-000123';
        $row->product_price = '199.9000';
        $row->category_id = 456;
        $row->category_name = 'category-01-01-01';
        $row->stock_quantity = 25;
        $row->stock_actual_at = CarbonImmutable::parse('2026-05-25 10:30:00', 'UTC');

        $resource = new ProductCatalogResource($row);

        $this->assertSame([
            'product' => [
                'id' => 123,
                'name' => 'Test Product',
                'sku' => 'SKU-000123',
                'price' => '199.90',
            ],
            'category' => [
                'id' => 456,
                'name' => 'category-01-01-01',
            ],
            'stock' => [
                'quantity' => 25,
                'actual_at' => '2026-05-25T10:30:00.000000Z',
            ],
        ], $resource->toArray(Request::create('/api/products')));
    }
}
