<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('tax_id', 32)->nullable()->comment('NPWP');
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('website')->nullable();
            $table->string('street')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip', 16)->nullable();
            $table->string('country')->default('Indonesia');
            $table->char('currency', 3)->default('IDR');
            $table->auditColumns();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
