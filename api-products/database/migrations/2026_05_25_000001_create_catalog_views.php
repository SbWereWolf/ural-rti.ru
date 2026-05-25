<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE VIEW product_catalog_view AS
            SELECT
                p.id AS product_id,
                p.name AS product_name,
                p.sku AS product_sku,
                p.price AS product_price,
                c.id AS category_id,
                c.name AS category_name,
                s.quantity AS stock_quantity,
                s.actual_at AS stock_actual_at
            FROM product p
            JOIN category c ON c.id = p.category_id
            JOIN stocks s ON s.product_id = p.id
        SQL);

        DB::statement(<<<'SQL'
            CREATE VIEW category_product_catalog_view AS
            SELECT
                p.id AS product_id,
                p.name AS product_name,
                p.sku AS product_sku,
                p.price AS product_price,
                c.id AS category_id,
                c.name AS category_name,
                s.quantity AS stock_quantity,
                s.actual_at AS stock_actual_at
            FROM category c
            JOIN product p ON p.category_id = c.id
            JOIN stocks s ON s.product_id = p.id
        SQL);

        DB::statement(<<<'SQL'
            CREATE VIEW stock_product_catalog_view AS
            SELECT
                p.id AS product_id,
                p.name AS product_name,
                p.sku AS product_sku,
                p.price AS product_price,
                c.id AS category_id,
                c.name AS category_name,
                s.quantity AS stock_quantity,
                s.actual_at AS stock_actual_at
            FROM stocks s
            JOIN product p ON p.id = s.product_id
            JOIN category c ON c.id = p.category_id
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS stock_product_catalog_view');
        DB::statement('DROP VIEW IF EXISTS category_product_catalog_view');
        DB::statement('DROP VIEW IF EXISTS product_catalog_view');
    }
};
