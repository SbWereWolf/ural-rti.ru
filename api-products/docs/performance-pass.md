# Performance pass — `GET /api/products`

## Dataset

```text
SQLite version: 3.46.1
Database: database/database.sqlite
category rows: 27 930
product rows: 999 000
stocks rows: 999 000
stocks.quantity = 0 rows: 99 900
stocks.quantity > 0 rows: 899 100
product_catalog_count.quantity: 999 000
page: 1
per_page: 50
ORDER BY: category_id ASC, product_id ASC
Selected columns: product_id, product_name, product_sku, product_price, category_id, category_name, stock_quantity, stock_actual_at
```

## Summary

All page-data queries now select only the response columns; no `SELECT *` is used in the endpoint or performance SQL.

The previously found issue remains: `in_stock=true` without `category_id` uses `stocks_quantity_product_id_index`, then SQLite needs a temporary B-tree for `ORDER BY category_id ASC, product_id ASC`. This is expected because the stock-first access path does not match the response ordering.

---

## 01. 01_custom_total_without_filters

Custom total for the no-filter paginate() call.

SQL:

```sql
SELECT quantity FROM product_catalog_count LIMIT 1;
```

Bindings:

```text
()
```

Interpolated SQL:

```sql
SELECT quantity FROM product_catalog_count LIMIT 1;
```

EXPLAIN QUERY PLAN:

```text
(3, 0, 0, 'SCAN product_catalog_count')
```

Timing: `0.08 ms`, rows fetched: `1`.
---

## 02. 02_data_without_filters

Page data without effective filters; ProductCatalogView is used.

SQL:

```sql
SELECT product_id, product_name, product_sku, product_price, category_id, category_name, stock_quantity, stock_actual_at FROM product_catalog_view ORDER BY category_id ASC, product_id ASC LIMIT ? OFFSET ?;
```

Bindings:

```text
(50, 0)
```

Interpolated SQL:

```sql
SELECT product_id, product_name, product_sku, product_price, category_id, category_name, stock_quantity, stock_actual_at FROM product_catalog_view ORDER BY category_id ASC, product_id ASC LIMIT 50 OFFSET 0;
```

EXPLAIN QUERY PLAN:

```text
(13, 0, 0, 'SCAN c')
(15, 0, 0, 'SEARCH p USING INDEX product_category_id_id_index (category_id=?)')
(20, 0, 0, 'SEARCH s USING INDEX stocks_product_id_unique (product_id=?)')
```

Timing: `0.74 ms`, rows fetched: `50`.
---

## 03. 03_validate_category_id

Validation query produced by exists:category,id.

SQL:

```sql
SELECT COUNT(*) AS aggregate FROM category WHERE id = ?;
```

Bindings:

```text
(931,)
```

Interpolated SQL:

```sql
SELECT COUNT(*) AS aggregate FROM category WHERE id = 931;
```

EXPLAIN QUERY PLAN:

```text
(3, 0, 0, 'SEARCH category USING INTEGER PRIMARY KEY (rowid=?)')
```

Timing: `0.07 ms`, rows fetched: `1`.
---

## 04. 04_count_category_filter

Honest total for category_id filter.

SQL:

```sql
SELECT COUNT(*) AS aggregate FROM category_product_catalog_view WHERE category_id = ?;
```

Bindings:

```text
(931,)
```

Interpolated SQL:

```sql
SELECT COUNT(*) AS aggregate FROM category_product_catalog_view WHERE category_id = 931;
```

EXPLAIN QUERY PLAN:

```text
(5, 0, 0, 'SEARCH c USING INTEGER PRIMARY KEY (rowid=?)')
(8, 0, 0, 'SEARCH p USING COVERING INDEX product_category_id_id_index (category_id=?)')
(13, 0, 0, 'SEARCH s USING COVERING INDEX stocks_product_id_unique (product_id=?)')
```

Timing: `0.20 ms`, rows fetched: `1`.
---

## 05. 05_data_category_filter

Page data for category_id filter; CategoryProductCatalogView is used.

SQL:

```sql
SELECT product_id, product_name, product_sku, product_price, category_id, category_name, stock_quantity, stock_actual_at FROM category_product_catalog_view WHERE category_id = ? ORDER BY category_id ASC, product_id ASC LIMIT ? OFFSET ?;
```

Bindings:

```text
(931, 50, 0)
```

Interpolated SQL:

```sql
SELECT product_id, product_name, product_sku, product_price, category_id, category_name, stock_quantity, stock_actual_at FROM category_product_catalog_view WHERE category_id = 931 ORDER BY category_id ASC, product_id ASC LIMIT 50 OFFSET 0;
```

EXPLAIN QUERY PLAN:

```text
(13, 0, 0, 'SEARCH c USING INTEGER PRIMARY KEY (rowid=?)')
(16, 0, 0, 'SEARCH p USING INDEX product_category_id_id_index (category_id=?)')
(22, 0, 0, 'SEARCH s USING INDEX stocks_product_id_unique (product_id=?)')
```

Timing: `0.61 ms`, rows fetched: `37`.
---

## 06. 06_count_in_stock_filter

Honest total for in_stock=true without category_id; StockProductCatalogView is used.

SQL:

```sql
SELECT COUNT(*) AS aggregate FROM stock_product_catalog_view WHERE stock_quantity > ?;
```

Bindings:

```text
(0,)
```

Interpolated SQL:

```sql
SELECT COUNT(*) AS aggregate FROM stock_product_catalog_view WHERE stock_quantity > 0;
```

EXPLAIN QUERY PLAN:

```text
(5, 0, 0, 'SEARCH s USING COVERING INDEX stocks_quantity_product_id_index (quantity>?)')
(10, 0, 0, 'SEARCH p USING INTEGER PRIMARY KEY (rowid=?)')
(13, 0, 0, 'SEARCH c USING INTEGER PRIMARY KEY (rowid=?)')
```

Timing: `13685.62 ms`, rows fetched: `1`.
---

## 07. 07_data_in_stock_filter

Page data for in_stock=true without category_id; StockProductCatalogView is used. Slow on the generated dataset.

SQL:

```sql
SELECT product_id, product_name, product_sku, product_price, category_id, category_name, stock_quantity, stock_actual_at FROM stock_product_catalog_view WHERE stock_quantity > ? ORDER BY category_id ASC, product_id ASC LIMIT ? OFFSET ?;
```

Bindings:

```text
(0, 50, 0)
```

Interpolated SQL:

```sql
SELECT product_id, product_name, product_sku, product_price, category_id, category_name, stock_quantity, stock_actual_at FROM stock_product_catalog_view WHERE stock_quantity > 0 ORDER BY category_id ASC, product_id ASC LIMIT 50 OFFSET 0;
```

EXPLAIN QUERY PLAN:

```text
(12, 0, 0, 'SEARCH s USING INDEX stocks_quantity_product_id_index (quantity>?)')
(18, 0, 0, 'SEARCH p USING INTEGER PRIMARY KEY (rowid=?)')
(21, 0, 0, 'SEARCH c USING INTEGER PRIMARY KEY (rowid=?)')
(40, 0, 0, 'USE TEMP B-TREE FOR ORDER BY')
```
---

## 08. 08_count_price_filter

Honest total for a price range.

SQL:

```sql
SELECT COUNT(*) AS aggregate FROM product_catalog_view WHERE product_price >= ? AND product_price <= ?;
```

Bindings:

```text
('100.00', '200.00')
```

Interpolated SQL:

```sql
SELECT COUNT(*) AS aggregate FROM product_catalog_view WHERE product_price >= '100.00' AND product_price <= '200.00';
```

EXPLAIN QUERY PLAN:

```text
(6, 0, 0, 'SEARCH p USING INDEX product_price_id_index (price>? AND price<?)')
(16, 0, 0, 'SEARCH c USING INTEGER PRIMARY KEY (rowid=?)')
(19, 0, 0, 'SEARCH s USING COVERING INDEX stocks_product_id_unique (product_id=?)')
```

Timing: `35.90 ms`, rows fetched: `1`.
---

## 09. 09_data_price_filter

Page data for a price range.

SQL:

```sql
SELECT product_id, product_name, product_sku, product_price, category_id, category_name, stock_quantity, stock_actual_at FROM product_catalog_view WHERE product_price >= ? AND product_price <= ? ORDER BY category_id ASC, product_id ASC LIMIT ? OFFSET ?;
```

Bindings:

```text
('100.00', '200.00', 50, 0)
```

Interpolated SQL:

```sql
SELECT product_id, product_name, product_sku, product_price, category_id, category_name, stock_quantity, stock_actual_at FROM product_catalog_view WHERE product_price >= '100.00' AND product_price <= '200.00' ORDER BY category_id ASC, product_id ASC LIMIT 50 OFFSET 0;
```

EXPLAIN QUERY PLAN:

```text
(13, 0, 0, 'SEARCH p USING INDEX product_price_id_index (price>? AND price<?)')
(23, 0, 0, 'SEARCH c USING INTEGER PRIMARY KEY (rowid=?)')
(26, 0, 0, 'SEARCH s USING INDEX stocks_product_id_unique (product_id=?)')
(47, 0, 0, 'USE TEMP B-TREE FOR ORDER BY')
```

Timing: `36.77 ms`, rows fetched: `50`.
---

## 10. 10_count_category_price_stock_filter

Honest total for combined category_id + price range + in_stock=true.

SQL:

```sql
SELECT COUNT(*) AS aggregate FROM category_product_catalog_view WHERE category_id = ? AND product_price >= ? AND product_price <= ? AND stock_quantity > ?;
```

Bindings:

```text
(931, '100.00', '200.00', 0)
```

Interpolated SQL:

```sql
SELECT COUNT(*) AS aggregate FROM category_product_catalog_view WHERE category_id = 931 AND product_price >= '100.00' AND product_price <= '200.00' AND stock_quantity > 0;
```

EXPLAIN QUERY PLAN:

```text
(5, 0, 0, 'SEARCH c USING INTEGER PRIMARY KEY (rowid=?)')
(8, 0, 0, 'SEARCH p USING COVERING INDEX product_category_id_price_id_index (category_id=? AND price>? AND price<?)')
(19, 0, 0, 'SEARCH s USING COVERING INDEX stocks_product_id_quantity_index (product_id=? AND quantity>?)')
```

Timing: `0.53 ms`, rows fetched: `1`.
---

## 11. 11_data_category_price_stock_filter

Page data for combined category_id + price range + in_stock=true.

SQL:

```sql
SELECT product_id, product_name, product_sku, product_price, category_id, category_name, stock_quantity, stock_actual_at FROM category_product_catalog_view WHERE category_id = ? AND product_price >= ? AND product_price <= ? AND stock_quantity > ? ORDER BY category_id ASC, product_id ASC LIMIT ? OFFSET ?;
```

Bindings:

```text
(931, '100.00', '200.00', 0, 50, 0)
```

Interpolated SQL:

```sql
SELECT product_id, product_name, product_sku, product_price, category_id, category_name, stock_quantity, stock_actual_at FROM category_product_catalog_view WHERE category_id = 931 AND product_price >= '100.00' AND product_price <= '200.00' AND stock_quantity > 0 ORDER BY category_id ASC, product_id ASC LIMIT 50 OFFSET 0;
```

EXPLAIN QUERY PLAN:

```text
(13, 0, 0, 'SEARCH c USING INTEGER PRIMARY KEY (rowid=?)')
(16, 0, 0, 'SEARCH p USING INDEX product_category_id_price_id_index (category_id=? AND price>? AND price<?)')
(28, 0, 0, 'SEARCH s USING INDEX stocks_product_id_quantity_index (product_id=? AND quantity>?)')
(61, 0, 0, 'USE TEMP B-TREE FOR LAST TERM OF ORDER BY')
```

Timing: `0.27 ms`, rows fetched: `0`.
---

## 12. 12_alternative_in_stock_ordered_probe

Alternative in_stock=true query that preserves ORDER BY without temp sort by walking category -> product -> stocks.

SQL:

```sql
SELECT p.id AS product_id, p.name AS product_name, p.sku AS product_sku, p.price AS product_price, c.id AS category_id, c.name AS category_name, s.quantity AS stock_quantity, s.actual_at AS stock_actual_at FROM category c CROSS JOIN product p INDEXED BY product_category_id_id_index ON p.category_id = c.id CROSS JOIN stocks s INDEXED BY stocks_product_id_quantity_index ON s.product_id = p.id WHERE s.quantity > ? ORDER BY c.id ASC, p.id ASC LIMIT ? OFFSET ?;
```

Bindings:

```text
(0, 50, 0)
```

Interpolated SQL:

```sql
SELECT p.id AS product_id, p.name AS product_name, p.sku AS product_sku, p.price AS product_price, c.id AS category_id, c.name AS category_name, s.quantity AS stock_quantity, s.actual_at AS stock_actual_at FROM category c CROSS JOIN product p INDEXED BY product_category_id_id_index ON p.category_id = c.id CROSS JOIN stocks s INDEXED BY stocks_product_id_quantity_index ON s.product_id = p.id WHERE s.quantity > 0 ORDER BY c.id ASC, p.id ASC LIMIT 50 OFFSET 0;
```

EXPLAIN QUERY PLAN:

```text
(13, 0, 0, 'SCAN c')
(15, 0, 0, 'SEARCH p USING INDEX product_category_id_id_index (category_id=?)')
(20, 0, 0, 'SEARCH s USING INDEX stocks_product_id_quantity_index (product_id=? AND quantity>?)')
```

Timing: `0.51 ms`, rows fetched: `50`.
