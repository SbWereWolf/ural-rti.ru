<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Model;

class ProductCatalogCount extends Model
{
    protected $table = 'product_catalog_count';

    protected $fillable = [
        'quantity',
        'actual_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'actual_at' => 'datetime',
        ];
    }
}
