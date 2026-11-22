<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequences', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('module')->nullable();
            $table->string('prefix')->default('');
            $table->string('suffix')->default('');
            $table->unsignedTinyInteger('padding')->default(4);
            $table->unsignedBigInteger('next_number')->default(1);
            $table->string('reset', 10)->default('yearly'); // never | yearly | monthly
            $table->string('last_reset_key', 10)->nullable();
            $table->auditColumns();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');
    }
};
