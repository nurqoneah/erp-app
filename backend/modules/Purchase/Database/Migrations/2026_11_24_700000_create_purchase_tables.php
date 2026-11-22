<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('partner_id')->constrained('partners')->restrictOnDelete();
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            // draft (RFQ) | confirmed (pesanan pembelian) | cancelled
            $table->string('state', 10)->default('draft');
            $table->timestamp('confirmed_at')->nullable();
            $table->decimal('amount_untaxed', 18, 2)->default(0);
            $table->decimal('amount_tax', 18, 2)->default(0);
            $table->decimal('amount_total', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->auditColumns();
            $table->timestamps();

            $table->index(['state', 'order_date']);
        });

        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->unsignedInteger('sequence')->default(0);
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('description');
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('discount', 5, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->decimal('qty_received', 18, 4)->default(0);
            $table->decimal('qty_billed', 18, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
        Schema::dropIfExists('purchase_orders');
    }
};
