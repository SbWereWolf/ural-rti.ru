<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('category')->nullOnDelete();
            $table->string('name');
            $table->timestamps();

            $table->index(['parent_id', 'id']);
        });

        Schema::create('product', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('category')->restrictOnDelete();
            $table->string('sku')->unique();
            $table->string('name');
            $table->decimal('price', 12, 4);
            $table->timestamps();

            $table->index(['category_id', 'id']);
            $table->index(['price', 'id']);
            $table->index(['category_id', 'price', 'id']);
        });

        Schema::create('stocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->unique()->constrained('product')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamp('actual_at');
            $table->timestamps();

            $table->index(['quantity', 'product_id']);
            $table->index(['product_id', 'quantity']);
        });

        Schema::create('product_catalog_count', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('quantity');
            $table->timestamp('actual_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_catalog_count');
        Schema::dropIfExists('stocks');
        Schema::dropIfExists('product');
        Schema::dropIfExists('category');
    }
};
