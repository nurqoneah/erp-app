<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('uom_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->auditColumns();
            $table->timestamps();
        });

        Schema::create('uoms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('uom_categories')->restrictOnDelete();
            $table->string('name');
            // reference: satuan acuan kategori (ratio 1); bigger: 1 satuan = ratio x acuan; smaller: 1 acuan = ratio x satuan
            $table->string('type', 10)->default('reference');
            $table->decimal('ratio', 18, 6)->default(1);
            $table->decimal('rounding', 18, 6)->default(0.01);
            $table->boolean('is_active')->default(true);
            $table->auditColumns();
            $table->timestamps();

            $table->unique(['category_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uoms');
        Schema::dropIfExists('uom_categories');
    }
};
