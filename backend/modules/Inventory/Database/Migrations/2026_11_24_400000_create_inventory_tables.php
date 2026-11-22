<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 10)->unique();
            $table->boolean('is_active')->default(true);
            $table->auditColumns();
            $table->timestamps();
        });

        Schema::create('stock_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('complete_name');
            $table->foreignId('parent_id')->nullable()->constrained('stock_locations')->restrictOnDelete();
            // view | internal | supplier | customer | inventory
            $table->string('usage', 12);
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->auditColumns();
            $table->timestamps();

            $table->index(['usage', 'is_active']);
        });

        Schema::table('warehouses', function (Blueprint $table) {
            $table->foreignId('view_location_id')->nullable()->constrained('stock_locations')->restrictOnDelete();
            $table->foreignId('stock_location_id')->nullable()->constrained('stock_locations')->restrictOnDelete();
        });

        Schema::create('stock_quants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('location_id')->constrained('stock_locations')->restrictOnDelete();
            $table->decimal('quantity', 18, 4)->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'location_id']);
        });

        Schema::create('stock_pickings', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            // receipt | delivery | internal
            $table->string('type', 10);
            // draft | done | cancelled
            $table->string('state', 10)->default('draft');
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained('partners')->restrictOnDelete();
            $table->foreignId('source_location_id')->constrained('stock_locations')->restrictOnDelete();
            $table->foreignId('dest_location_id')->constrained('stock_locations')->restrictOnDelete();
            $table->date('scheduled_date');
            $table->timestamp('done_at')->nullable();
            // Dokumen asal, mis. "SO/2026/0001" (origin) dari sales.order #12 (origin_type/origin_id).
            $table->string('origin')->nullable();
            $table->string('origin_type', 40)->nullable();
            $table->unsignedBigInteger('origin_id')->nullable();
            $table->text('notes')->nullable();
            $table->auditColumns();
            $table->timestamps();

            $table->index(['origin_type', 'origin_id']);
            $table->index(['type', 'state']);
        });

        Schema::create('stock_moves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('picking_id')->nullable()->constrained('stock_pickings')->cascadeOnDelete();
            $table->string('reference');
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->foreignId('source_location_id')->constrained('stock_locations')->restrictOnDelete();
            $table->foreignId('dest_location_id')->constrained('stock_locations')->restrictOnDelete();
            $table->string('state', 10)->default('draft');
            $table->timestamp('done_at')->nullable();
            // Baris dokumen asal, mis. baris pesanan penjualan.
            $table->string('origin_line_type', 40)->nullable();
            $table->unsignedBigInteger('origin_line_id')->nullable();
            $table->auditColumns();
            $table->timestamps();

            $table->index(['origin_line_type', 'origin_line_id']);
            $table->index(['product_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_moves');
        Schema::dropIfExists('stock_pickings');
        Schema::dropIfExists('stock_quants');
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('view_location_id');
            $table->dropConstrainedForeignId('stock_location_id');
        });
        Schema::dropIfExists('stock_locations');
        Schema::dropIfExists('warehouses');
    }
};
