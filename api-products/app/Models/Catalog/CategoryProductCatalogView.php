<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;

class CategoryProductCatalogView extends Model
{
    protected $table = 'category_product_catalog_view';

    protected $primaryKey = 'product_id';

    public $incrementing = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'category_id' => 'integer',
            'stock_quantity' => 'integer',
            'stock_actual_at' => 'datetime',
        ];
    }
}
