<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->restrictOnDelete();
            $table->string('complete_name')->index();
            $table->auditColumns();
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku', 64)->nullable()->index()->comment('Kode internal');
            $table->string('barcode', 64)->nullable()->index();
            $table->string('type', 12)->default('storable'); // storable | consumable | service
            $table->foreignId('category_id')->constrained('product_categories')->restrictOnDelete();
            $table->foreignId('uom_id')->constrained('uoms')->restrictOnDelete();
            $table->foreignId('purchase_uom_id')->constrained('uoms')->restrictOnDelete();
            $table->decimal('sale_price', 18, 2)->default(0);
            $table->decimal('cost', 18, 2)->default(0);
            $table->boolean('can_be_sold')->default(true);
            $table->boolean('can_be_purchased')->default(true);
            $table->boolean('available_in_pos')->default(true);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->auditColumns();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
        Schema::dropIfExists('product_categories');
    }
};
