# Каталог товаров API

## 1. Развёртывание приложения

### 1.1. Требования к окружению

Нужны:

```text
PHP 8.2+
Composer
SQLite
PHP extensions: pdo_sqlite, sqlite3, mbstring, xml, dom, xmlwriter
```

Для отчёта покрытия тестами дополнительно нужен драйверов покрытия
`Xdebug`

### 1.2. Установка зависимостей

```bash
composer install
```

### 1.3. Создание `.env`

```bash
cp .env.example .env
php artisan key:generate
```

### 1.4. Подготовка базы данных

```bash
php artisan migrate:fresh
php artisan db:seed --class=CatalogSeeder
php artisan catalog:refresh-product-count
```

### 1.5. Запуск приложения

```bash
php artisan serve
```

Проверка endpoint:

```bash
curl 'http://127.0.0.1:8000/api/products?per_page=5'
```

---

## 2. Бизнес-описание приложения

Приложение предоставляет API каталога товаров для сценариев витрины
или товарного листинга.

Основные возможности:

```text
1. Получение постраничного каталога товаров.
2. Фильтрация товаров по категории.
3. Фильтрация товаров по диапазону цены.
4. Фильтрация товаров только в наличии.
5. Возврат связанных данных категории и остатка в одном ответе.
6. Защита от N+1 за счёт чтения из SQL VIEW с JOIN-ами.
7. Быстрый total для запроса без фильтров через product_catalog_count.
8. Понятные ошибки валидации на русском языке с подсказками по исправлению.
```

Endpoint рассчитан на большой каталог: подготовленный набор данных
содержит 999 000 товаров, 27 930 категорий и 999 000 записей остатков.

---

## 3. API

### 3.1. Получение товаров

```http
GET /api/products
```

Поддерживаемые query-параметры:

| Параметр      | Пример   | Назначение                             |
|---------------|----------|----------------------------------------|
| `page`        | `1`      | номер страницы                         |
| `per_page`    | `50`     | размер страницы, максимум `100`        |
| `category_id` | `3`      | фильтр по конкретной категории         |
| `price_min`   | `100.00` | минимальная цена                       |
| `price_max`   | `500.00` | максимальная цена                      |
| `in_stock`    | `true`   | только товары с положительным остатком |

Примеры:

```bash
curl 'http://127.0.0.1:8000/api/products?per_page=5'
curl 'http://127.0.0.1:8000/api/products?category_id=3&per_page=5'
curl 'http://127.0.0.1:8000/api/products?price_min=10000&price_max=20000&per_page=5'
curl 'http://127.0.0.1:8000/api/products?in_stock=true&per_page=5'
```

### 3.2. Формат ответа

```json
{
  "data": [
    {
      "product": {
        "id": 1,
        "name": "Product 000001",
        "sku": "SKU-000001",
        "price": "199.90"
      },
      "category": {
        "id": 3,
        "name": "category-01-01-01"
      },
      "stock": {
        "quantity": 25,
        "actual_at": "2026-05-25T10:30:00Z"
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 50,
    "total": 999000,
    "last_page": 19980
  }
}
```

---

## 4. Ошибки валидации

Ошибки возвращаются в фиксированном формате.

Пример:

```json
{
  "message": "Ошибка валидации. Исправьте параметры запроса из поля errors и повторите запрос.",
  "errors": {
    "per_page": [
      {
        "message": "Параметр per_page не должен быть больше 100.",
        "suggestion": "Передайте per_page=50 или другое целое число от 1 до 100."
      }
    ]
  }
}
```

Каждая ошибка содержит:

```text
message — что именно неверно;
suggestion — что пользователь может сделать, чтобы исправить запрос.
```

---

## 5. Команда актуализации количества товаров

Для быстрого `meta.total` без фильтров используется таблица:

```text
product_catalog_count
```

Её нужно актуализировать после массовых изменений каталога:

```bash
php artisan catalog:refresh-product-count
```

Команда выполняет:

```sql
SELECT COUNT(*) FROM product;
```

Затем создаёт или обновляет строку в `product_catalog_count`:

```text
quantity — актуальное количество товаров;
actual_at — дата и время актуализации.
```

Если в `product_catalog_count` нет строк, API считает total равным 
`0` до следующего запуска команды.

---

## 6. Структура данных

Основные таблицы:

```text
category
product
stocks
product_catalog_count
```

Для чтения каталога используются три SQL `VIEW`:

```text
product_catalog_view
category_product_catalog_view
stock_product_catalog_view
```

API не использует lazy loading связей Eloquent для категории и 
остатка. Все данные для ответа приходят из `VIEW`, поэтому количество 
SQL-запросов не растёт при увеличении `per_page`.

---

## 7. Генерация данных

### 7.1. Laravel seeder

```bash
php artisan db:seed --class=CatalogSeeder
php artisan catalog:refresh-product-count
```

## 8. Команды для тестирования

### 8.1. Все тесты

```bash
php artisan test
```

или через Composer:

```bash
composer test
```

### 8.2. Только тесты каталога

```bash
php artisan test --filter=ProductIndexTest
php artisan test --filter=RefreshProductCatalogCountCommandTest
php artisan test --filter=ProductCatalogResourceTest
```

### 8.3. Покрытие кода

С Xdebug:

```bash
XDEBUG_MODE=coverage php artisan test --coverage
```

Проверка минимального покрытия 100%:

```bash
XDEBUG_MODE=coverage php artisan test --coverage --min=100
```

---

## 9. Полезные команды разработки

Список маршрутов API:

```bash
php artisan route:list --path=api/products
```

Список Artisan-команд каталога:

```bash
php artisan list catalog
```

Очистка кэшей Laravel:

```bash
php artisan optimize:clear
```
