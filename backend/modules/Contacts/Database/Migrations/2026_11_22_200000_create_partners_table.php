<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partners', function (Blueprint $table) {
            $table->id();
            $table->string('type', 10)->default('company'); // company | person
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('partners')->nullOnDelete();
            $table->string('ref', 64)->nullable()->comment('Kode internal');
            $table->string('job_title')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('mobile', 32)->nullable();
            $table->string('website')->nullable();
            $table->string('tax_id', 32)->nullable()->comment('NPWP / NIK');
            $table->string('street')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip', 16)->nullable();
            $table->string('country')->nullable();
            $table->boolean('is_customer')->default(false);
            $table->boolean('is_vendor')->default(false);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->auditColumns();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'name']);
            $table->index('ref');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partners');
    }
};
