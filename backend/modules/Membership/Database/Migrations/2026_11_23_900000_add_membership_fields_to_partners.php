<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Memperluas tabel milik modul Kontak. Saat Keanggotaan di-uninstall,
 * down() menghapus kolom ini sehingga tabel Kontak kembali seperti semula.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->string('member_number', 30)->nullable()->unique();
            $table->string('member_level', 20)->nullable();
            $table->date('member_since')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropUnique(['member_number']);
            $table->dropColumn(['member_number', 'member_level', 'member_since']);
        });
    }
};
