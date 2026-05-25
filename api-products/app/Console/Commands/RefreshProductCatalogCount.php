<?php

namespace App\Console\Commands;

use App\Models\Catalog\ProductCatalogCount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RefreshProductCatalogCount extends Command
{
    protected $signature = 'catalog:refresh-product-count';

    protected $description =
        'Актуализирует кэшированное количество товаров каталога.';

    public function handle(): int
    {
        $quantity = DB::table('product')->count();
        $now = now();

        $count = ProductCatalogCount::query()->first();

        if ($count === null) {
            ProductCatalogCount::query()->create([
                'quantity' => $quantity,
                'actual_at' => $now,
            ]);
        } else {
            $count->forceFill([
                'quantity' => $quantity,
                'actual_at' => $now,
            ])->save();
        }

        $this->components->info(
            "Количество товаров каталога актуализировано: {$quantity}"
        );

        return self::SUCCESS;
    }
}
