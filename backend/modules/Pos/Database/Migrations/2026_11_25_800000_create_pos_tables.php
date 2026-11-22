<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Struktur mengikuti point_of_sale Odoo: pos.config, pos.session, pos.order(.line),
 * pos.payment, pos.payment.method, dan kas masuk/keluar sesi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // cash (tunai, ikut kontrol kas) | bank (kartu, QRIS, transfer)
            $table->string('type', 10);
            $table->unsignedInteger('sequence')->default(10);
            $table->boolean('is_active')->default(true);
            $table->auditColumns();
            $table->timestamps();
        });

        Schema::create('pos_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->text('receipt_header')->nullable();
            $table->text('receipt_footer')->nullable();
            // Batas selisih kas saat tutup sesi; lebih dari ini hanya manajer yang boleh menutup.
            $table->boolean('set_maximum_difference')->default(false);
            $table->decimal('amount_authorized_diff', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->auditColumns();
            $table->timestamps();
        });

        Schema::create('pos_config_payment_method', function (Blueprint $table) {
            $table->foreignId('pos_config_id')->constrained('pos_configs')->cascadeOnDelete();
            $table->foreignId('pos_payment_method_id')->constrained('pos_payment_methods')->restrictOnDelete();
            $table->primary(['pos_config_id', 'pos_payment_method_id']);
        });

        Schema::create('pos_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable()->unique();
            $table->foreignId('config_id')->constrained('pos_configs')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            // opening_control | opened | closing_control | closed
            $table->string('state', 20)->default('opening_control');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->decimal('cash_register_balance_start', 18, 2)->default(0);
            $table->decimal('cash_register_balance_end', 18, 2)->default(0);
            $table->decimal('cash_register_balance_end_real', 18, 2)->default(0);
            $table->decimal('cash_register_difference', 18, 2)->default(0);
            $table->text('opening_notes')->nullable();
            $table->text('closing_notes')->nullable();
            $table->auditColumns();
            $table->timestamps();

            $table->index(['config_id', 'state']);
        });

        Schema::create('pos_cash_moves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('pos_sessions')->cascadeOnDelete();
            // Positif = kas masuk, negatif = kas keluar.
            $table->decimal('amount', 18, 2);
            $table->string('reason');
            $table->auditColumns();
            $table->timestamps();
        });

        Schema::create('pos_orders', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->foreignId('session_id')->constrained('pos_sessions')->restrictOnDelete();
            $table->foreignId('config_id')->constrained('pos_configs')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained('partners')->restrictOnDelete();
            $table->timestamp('date_order');
            // draft | paid | done (diposting saat sesi ditutup / difakturkan) | cancel
            $table->string('state', 10)->default('draft');
            $table->decimal('amount_untaxed', 18, 2)->default(0);
            $table->decimal('amount_tax', 18, 2)->default(0);
            $table->decimal('amount_total', 18, 2)->default(0);
            $table->decimal('amount_paid', 18, 2)->default(0);
            $table->decimal('amount_return', 18, 2)->default(0);
            $table->boolean('to_invoice')->default(false);
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->restrictOnDelete();
            $table->boolean('is_refund')->default(false);
            $table->foreignId('refunded_order_id')->nullable()->constrained('pos_orders')->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->auditColumns();
            $table->timestamps();

            $table->index(['session_id', 'state']);
        });

        Schema::create('pos_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('pos_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('description');
            // Negatif untuk retur.
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_price', 18, 2);
            $table->decimal('discount', 5, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('tax_amount', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);
            $table->foreignId('refunded_line_id')->nullable()->constrained('pos_order_lines')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('pos_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('pos_orders')->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained('pos_payment_methods')->restrictOnDelete();
            // Kembalian dicatat sebagai pembayaran tunai negatif (is_change), seperti Odoo.
            $table->decimal('amount', 18, 2);
            $table->boolean('is_change')->default(false);
            $table->timestamp('payment_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_payments');
        Schema::dropIfExists('pos_order_lines');
        Schema::dropIfExists('pos_orders');
        Schema::dropIfExists('pos_cash_moves');
        Schema::dropIfExists('pos_sessions');
        Schema::dropIfExists('pos_config_payment_method');
        Schema::dropIfExists('pos_configs');
        Schema::dropIfExists('pos_payment_methods');
    }
};
