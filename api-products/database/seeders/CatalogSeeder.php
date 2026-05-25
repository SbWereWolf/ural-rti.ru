<?php

namespace Database\Seeders;

use Faker\Factory as FakerFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CatalogSeeder extends Seeder
{
    private const ROOT_CATEGORIES = 30;
    private const SECOND_LEVEL_CATEGORIES = 30;
    private const THIRD_LEVEL_CATEGORIES = 30;
    private const PRODUCTS_PER_LEAF_CATEGORY = 37;
    private const INSERT_CHUNK_SIZE = 500;
    private const EMPTY_STOCK_PERCENT = 10;

    public function run(): void
    {
        DB::disableQueryLog();

        $now = now()->toDateTimeString();

        DB::transaction(function () use ($now): void {
            $this->clearCatalogTables();

            $leafCategoryIds = $this->seedCategories($now);
            $productCount = $this->seedProductsAndStocks($leafCategoryIds, $now);

            DB::table('product_catalog_count')->insert([
                'quantity' => $productCount,
                'actual_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    /**
     * @return list<int>
     */
    private function seedCategories(string $now): array
    {
        $categoryRows = [];
        $leafCategoryIds = [];
        $categoryId = 1;

        for ($root = 1; $root <= self::ROOT_CATEGORIES; $root++) {
            $rootId = $categoryId++;
            $categoryRows[] = $this->categoryRow(
                id: $rootId,
                parentId: null,
                name: sprintf('category-%02d', $root),
                now: $now,
            );

            for ($second = 1; $second <= self::SECOND_LEVEL_CATEGORIES; $second++) {
                $secondId = $categoryId++;
                $categoryRows[] = $this->categoryRow(
                    id: $secondId,
                    parentId: $rootId,
                    name: sprintf('category-%02d-%02d', $root, $second),
                    now: $now,
                );

                for ($third = 1; $third <= self::THIRD_LEVEL_CATEGORIES; $third++) {
                    $thirdId = $categoryId++;
                    $leafCategoryIds[] = $thirdId;
                    $categoryRows[] = $this->categoryRow(
                        id: $thirdId,
                        parentId: $secondId,
                        name: sprintf('category-%02d-%02d-%02d', $root, $second, $third),
                        now: $now,
                    );

                    if (count($categoryRows) >= self::INSERT_CHUNK_SIZE) {
                        DB::table('category')->insert($categoryRows);
                        $categoryRows = [];
                    }
                }
            }
        }

        if ($categoryRows !== []) {
            DB::table('category')->insert($categoryRows);
        }

        return $leafCategoryIds;
    }

    /**
     * @param list<int> $leafCategoryIds
     */
    private function seedProductsAndStocks(array $leafCategoryIds, string $now): int
    {
        $faker = FakerFactory::create();
        $productRows = [];
        $stockRows = [];
        $productId = 1;

        foreach ($leafCategoryIds as $categoryId) {
            for ($i = 1; $i <= self::PRODUCTS_PER_LEAF_CATEGORY; $i++) {
                $price = $faker->randomFloat(2, 1, 99_999.99);

                $productRows[] = [
                    'id' => $productId,
                    'category_id' => $categoryId,
                    'sku' => sprintf('SKU-%06d', $productId),
                    'name' => $faker->words(3, true),
                    'price' => number_format($price, 4, '.', ''),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $stockRows[] = [
                    'product_id' => $productId,
                    'quantity' => $this->stockQuantity($productId, $faker),
                    'actual_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                $productId++;

                if (count($productRows) >= self::INSERT_CHUNK_SIZE) {
                    $this->insertProductAndStockRows($productRows, $stockRows);
                    $productRows = [];
                    $stockRows = [];
                }
            }
        }

        if ($productRows !== []) {
            $this->insertProductAndStockRows($productRows, $stockRows);
        }

        return $productId - 1;
    }

    /**
     * @param list<array<string, mixed>> $productRows
     * @param list<array<string, mixed>> $stockRows
     */
    private function insertProductAndStockRows(array $productRows, array $stockRows): void
    {
        DB::table('product')->insert($productRows);
        DB::table('stocks')->insert($stockRows);
    }

    private function stockQuantity(int $productId, \Faker\Generator $faker): int
    {
        if (self::EMPTY_STOCK_PERCENT <= 0) {
            return $faker->numberBetween(1, 1_000);
        }

        if (self::EMPTY_STOCK_PERCENT >= 100) {
            return 0;
        }

        $everyNth = intdiv(100, self::EMPTY_STOCK_PERCENT);

        if ($everyNth > 0 && $productId % $everyNth === 0) {
            return 0;
        }

        return $faker->numberBetween(1, 1_000);
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryRow(int $id, ?int $parentId, string $name, string $now): array
    {
        return [
            'id' => $id,
            'parent_id' => $parentId,
            'name' => $name,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }

    private function clearCatalogTables(): void
    {
        DB::table('product_catalog_count')->delete();
        DB::table('stocks')->delete();
        DB::table('product')->delete();
        DB::table('category')->delete();

        DB::statement("DELETE FROM sqlite_sequence WHERE name IN ('category', 'product', 'stocks', 'product_catalog_count')");
    }
}
