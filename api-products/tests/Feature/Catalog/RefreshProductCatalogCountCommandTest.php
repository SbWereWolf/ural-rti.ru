<?php

namespace Tests\Feature\Catalog;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RefreshProductCatalogCountCommandTest extends TestCase
{
    public function test_it_refreshes_product_catalog_count(): void
    {
        $originalRows = DB::table('product_catalog_count')->get()->map(fn ($row) => (array) $row)->all();

        try {
            DB::table('product_catalog_count')->delete();
            DB::table('product_catalog_count')->insert([
                'quantity' => 1,
                'actual_at' => now()->subDay()->toDateTimeString(),
                'created_at' => now()->subDay()->toDateTimeString(),
                'updated_at' => now()->subDay()->toDateTimeString(),
            ]);

            $this->artisan('catalog:refresh-product-count')
                ->assertSuccessful();

            $this->assertSame(999000, DB::table('product_catalog_count')->value('quantity'));
            $this->assertNotNull(DB::table('product_catalog_count')->value('actual_at'));
        } finally {
            DB::table('product_catalog_count')->delete();

            if ($originalRows !== []) {
                DB::table('product_catalog_count')->insert($originalRows);
            }
        }
    }

    public function test_it_creates_count_row_when_table_is_empty(): void
    {
        $originalRows = DB::table('product_catalog_count')->get()->map(fn ($row) => (array) $row)->all();

        try {
            DB::table('product_catalog_count')->delete();

            $this->artisan('catalog:refresh-product-count')
                ->assertSuccessful();

            $this->assertSame(999000, DB::table('product_catalog_count')->value('quantity'));
        } finally {
            DB::table('product_catalog_count')->delete();

            if ($originalRows !== []) {
                DB::table('product_catalog_count')->insert($originalRows);
            }
        }
    }
}
