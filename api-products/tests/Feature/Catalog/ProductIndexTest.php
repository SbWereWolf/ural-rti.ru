<?php

namespace Tests\Feature\Catalog;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProductIndexTest extends TestCase
{
    private const LEAF_CATEGORY_ID = 3;
    private const NON_LEAF_CATEGORY_ID = 1;
    private const PRICE_BOUNDARY = '50000';
    private const PRICE_MIN = '10000';
    private const PRICE_MAX = '20000';

    /**
     * @param array<string, string|int> $params
     */
    #[DataProvider('filterMatrixProvider')]
    public function test_filter_matrix(
        array $params,
        ?int $expectedCategoryId,
        bool $expectedInStockOnly,
        ?string $expectedPriceMin,
        ?string $expectedPriceMax,
    ): void {
        $params['per_page'] = 5;

        $response = $this->getJson('/api/products?'.http_build_query($params));
        $expectedTotal = $this->expectedTotal(
            categoryId: $expectedCategoryId,
            inStockOnly: $expectedInStockOnly,
            priceMin: $expectedPriceMin,
            priceMax: $expectedPriceMax,
        );

        $response
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', $expectedTotal)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'product' => ['id', 'name', 'sku', 'price'],
                        'category' => ['id', 'name'],
                        'stock' => ['quantity', 'actual_at'],
                    ],
                ],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);

        $items = $response->json('data');
        $this->assertCount(min(5, $expectedTotal), $items);
        $this->assertItemsMatchFilters($items, $expectedCategoryId, $expectedInStockOnly, $expectedPriceMin, $expectedPriceMax);
        $this->assertItemsAreOrderedByCategoryAndProduct($items);
    }

    /**
     * @return iterable<string, array{0: array<string, string|int>, 1: int|null, 2: bool, 3: string|null, 4: string|null}>
     */
    public static function filterMatrixProvider(): iterable
    {
        $categories = [
            'no-category' => [null, []],
            'leaf-category' => [self::LEAF_CATEGORY_ID, ['category_id' => self::LEAF_CATEGORY_ID]],
            'non-leaf-category' => [self::NON_LEAF_CATEGORY_ID, ['category_id' => self::NON_LEAF_CATEGORY_ID]],
        ];

        $stocks = [
            'any-stock' => [false, []],
            'in-stock-only' => [true, ['in_stock' => 'true']],
        ];

        $prices = [
            'no-price' => [null, null, []],
            'price-min' => [self::PRICE_BOUNDARY, null, ['price_min' => self::PRICE_BOUNDARY]],
            'price-max' => [null, self::PRICE_BOUNDARY, ['price_max' => self::PRICE_BOUNDARY]],
            'price-range' => [self::PRICE_MIN, self::PRICE_MAX, ['price_min' => self::PRICE_MIN, 'price_max' => self::PRICE_MAX]],
        ];

        foreach ($categories as $categoryLabel => [$categoryId, $categoryParams]) {
            foreach ($stocks as $stockLabel => [$inStockOnly, $stockParams]) {
                foreach ($prices as $priceLabel => [$priceMin, $priceMax, $priceParams]) {
                    yield $categoryLabel.' / '.$stockLabel.' / '.$priceLabel => [
                        array_merge($categoryParams, $stockParams, $priceParams),
                        $categoryId,
                        $inStockOnly,
                        $priceMin,
                        $priceMax,
                    ];
                }
            }
        }
    }

    public function test_it_uses_product_catalog_view_without_effective_filters(): void
    {
        $queries = $this->captureQueries(fn () => $this->getJson('/api/products?per_page=1&in_stock=false')->assertOk());

        $this->assertStringContainsString('from "product_catalog_count"', $this->joinedQueries($queries));
        $this->assertStringContainsString('from "product_catalog_view"', $this->joinedQueries($queries));
        $this->assertStringContainsString('select "product_id", "product_name", "product_sku", "product_price", "category_id", "category_name", "stock_quantity", "stock_actual_at"', $this->joinedQueries($queries));
        $this->assertStringNotContainsString('select *', $this->joinedQueries($queries));
        $this->assertStringNotContainsString('count(*) as aggregate', $this->joinedQueries($queries));
    }

    public function test_it_uses_category_product_catalog_view_when_category_filter_is_present(): void
    {
        $queries = $this->captureQueries(fn () => $this->getJson('/api/products?category_id=3&per_page=1')->assertOk());

        $this->assertStringContainsString('from "category_product_catalog_view"', $this->joinedQueries($queries));
        $this->assertStringContainsString('where "category_id" = ?', $this->joinedQueries($queries));
        $this->assertStringContainsString('select "product_id", "product_name", "product_sku", "product_price", "category_id", "category_name", "stock_quantity", "stock_actual_at"', $this->joinedQueries($queries));
        $this->assertStringNotContainsString('select *', $this->joinedQueries($queries));
        $this->assertStringContainsString('count(*) as aggregate', $this->joinedQueries($queries));
    }

    public function test_it_uses_stock_product_catalog_view_when_only_stock_filter_is_present(): void
    {
        $queries = $this->captureQueries(fn () => $this->getJson('/api/products?in_stock=true&per_page=1')->assertOk());

        $this->assertStringContainsString('from "stock_product_catalog_view"', $this->joinedQueries($queries));
        $this->assertStringContainsString('where "stock_quantity" > ?', $this->joinedQueries($queries));
        $this->assertStringContainsString('select "product_id", "product_name", "product_sku", "product_price", "category_id", "category_name", "stock_quantity", "stock_actual_at"', $this->joinedQueries($queries));
        $this->assertStringNotContainsString('select *', $this->joinedQueries($queries));
        $this->assertStringContainsString('count(*) as aggregate', $this->joinedQueries($queries));
    }

    public function test_it_treats_in_stock_false_as_no_effective_filter(): void
    {
        $response = $this->getJson('/api/products?in_stock=false&per_page=1');

        $response
            ->assertOk()
            ->assertJsonPath('meta.total', 999000)
            ->assertJsonCount(1, 'data');
    }

    public function test_it_returns_zero_total_when_custom_count_table_is_empty(): void
    {
        $originalRows = DB::table('product_catalog_count')->get()->map(fn ($row) => (array) $row)->all();

        try {
            DB::table('product_catalog_count')->delete();

            $this->getJson('/api/products?per_page=1')
                ->assertOk()
                ->assertJsonPath('meta.total', 0);
        } finally {
            DB::table('product_catalog_count')->delete();

            if ($originalRows !== []) {
                DB::table('product_catalog_count')->insert($originalRows);
            }
        }
    }

    #[DataProvider('validationErrorProvider')]
    public function test_it_returns_actionable_validation_errors(
        string $queryString,
        string $field,
        string $expectedMessage,
        string $expectedSuggestion,
    ): void {
        $this->getJson('/api/products?'.$queryString)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Ошибка валидации. Исправьте параметры запроса из поля errors и повторите запрос.')
            ->assertJsonPath('errors.'.$field.'.0.message', $expectedMessage)
            ->assertJsonPath('errors.'.$field.'.0.suggestion', $expectedSuggestion);
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public static function validationErrorProvider(): iterable
    {
        yield 'page должен быть положительным целым числом' => [
            'page=0',
            'page',
            'Параметр page должен быть больше или равен 1.',
            'Передайте page как целое число от 1, например page=1.',
        ];

        yield 'per_page не может превышать максимум' => [
            'per_page=101',
            'per_page',
            'Параметр per_page не должен быть больше 100.',
            'Передайте per_page=50 или другое целое число от 1 до 100.',
        ];

        yield 'категория должна существовать' => [
            'category_id=999999999',
            'category_id',
            'Категория с указанным category_id не найдена.',
            'Передайте id существующей категории третьего уровня или уберите category_id, чтобы получить весь каталог.',
        ];

        yield 'price_min должен быть числом' => [
            'price_min=cheap',
            'price_min',
            'Параметр price_min должен быть числом.',
            'Передайте неотрицательное число, например price_min=100.00.',
        ];

        yield 'price_max должен быть больше или равен price_min' => [
            'price_min=10&price_max=1',
            'price_max',
            'Параметр price_max должен быть больше или равен price_min.',
            'Передайте неотрицательное число, которое больше или равно price_min, например price_max=500.00.',
        ];

        yield 'in_stock должен быть булевым значением' => [
            'in_stock=maybe',
            'in_stock',
            'Параметр in_stock должен быть булевым значением.',
            'Передайте in_stock=true, чтобы получить только товары в наличии, или in_stock=false, чтобы не фильтровать по остатку.',
        ];
    }

    private function expectedTotal(?int $categoryId, bool $inStockOnly, ?string $priceMin, ?string $priceMax): int
    {
        if ($categoryId === null && ! $inStockOnly && $priceMin === null && $priceMax === null) {
            return DB::table('product_catalog_count')->value('quantity') ?? 0;
        }

        $query = DB::table('product')
            ->join('stocks', 'stocks.product_id', '=', 'product.id');

        if ($categoryId !== null) {
            $query->where('product.category_id', $categoryId);
        }

        if ($inStockOnly) {
            $query->where('stocks.quantity', '>', 0);
        }

        if ($priceMin !== null) {
            $query->where('product.price', '>=', $priceMin);
        }

        if ($priceMax !== null) {
            $query->where('product.price', '<=', $priceMax);
        }

        return $query->count();
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function assertItemsMatchFilters(array $items, ?int $categoryId, bool $inStockOnly, ?string $priceMin, ?string $priceMax): void
    {
        foreach ($items as $item) {
            $price = (float) $item['product']['price'];

            $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $item['product']['price']);

            if ($categoryId !== null) {
                $this->assertSame($categoryId, $item['category']['id']);
            }

            if ($inStockOnly) {
                $this->assertGreaterThan(0, $item['stock']['quantity']);
            }

            if ($priceMin !== null) {
                $this->assertGreaterThanOrEqual((float) $priceMin, $price);
            }

            if ($priceMax !== null) {
                $this->assertLessThanOrEqual((float) $priceMax, $price);
            }
        }
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function assertItemsAreOrderedByCategoryAndProduct(array $items): void
    {
        $previousCategoryId = null;
        $previousProductId = null;

        foreach ($items as $item) {
            $categoryId = $item['category']['id'];
            $productId = $item['product']['id'];

            if ($previousCategoryId !== null && $previousProductId !== null) {
                $this->assertTrue(
                    $categoryId > $previousCategoryId
                    || ($categoryId === $previousCategoryId && $productId >= $previousProductId)
                );
            }

            $previousCategoryId = $categoryId;
            $previousProductId = $productId;
        }
    }

    /**
     * @return list<string>
     */
    private function captureQueries(callable $callback): array
    {
        $queries = [];

        DB::listen(static function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        $callback();

        return $queries;
    }

    /**
     * @param list<string> $queries
     */
    private function joinedQueries(array $queries): string
    {
        return strtolower(implode("\n", $queries));
    }
}
