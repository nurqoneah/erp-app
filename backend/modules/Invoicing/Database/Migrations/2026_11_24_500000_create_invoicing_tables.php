<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            // out_invoice (faktur pelanggan) | in_invoice (tagihan pemasok)
            $table->string('type', 12);
            // Nomor diberikan saat diposting.
            $table->string('number')->nullable()->unique();
            $table->foreignId('partner_id')->constrained('partners')->restrictOnDelete();
            $table->date('invoice_date')->nullable();
            $table->date('due_date')->nullable();
            // Nomor faktur dari pemasok (khusus tagihan).
            $table->string('vendor_reference')->nullable();
            // draft | posted | cancelled
            $table->string('state', 10)->default('draft');
            // not_paid | partial | paid
            $table->string('payment_state', 10)->default('not_paid');
            $table->string('origin')->nullable();
            $table->string('origin_type', 40)->nullable();
            $table->unsignedBigInteger('origin_id')->nullable();
            $table->decimal('amount_untaxed', 18, 2)->default(0);
            $table->decimal('amount_tax', 18, 2)->default(0);
            $table->decimal('amount_total', 18, 2)->default(0);
            $table->decimal('amount_paid', 18, 2)->default(0);
            $table->text('notes')->nullable();
            $table->auditColumns();
            $table->timestamps();

            $table->index(['type', 'state']);
            $table->index(['origin_type', 'origin_id']);
        });

        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->unsignedInteger('sequence')->default(0);
            $table->foreignId('product_id')->nullable()->constrained('products')->restrictOnDelete();
            $table->string('description');
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('discount', 5, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->string('origin_line_type', 40)->nullable();
            $table->unsignedBigInteger('origin_line_id')->nullable();
            $table->timestamps();

            $table->index(['origin_line_type', 'origin_line_id']);
        });

        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->date('date');
            $table->decimal('amount', 18, 2);
            // cash | bank | other
            $table->string('method', 10);
            $table->string('reference')->nullable();
            $table->auditColumns();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};
