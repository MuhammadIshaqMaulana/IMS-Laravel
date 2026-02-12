<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transaksis', function (Blueprint $table) {
            // Kita drop kolomnya di sini
            $table->dropColumn(['jumlah_produksi', 'tanggal_produksi']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transaksis', function (Blueprint $table) {
            // Ini buat jaga-jaga kalau lo mau rollback (undo)
            $table->integer('jumlah_produksi')->nullable();
            $table->date('tanggal_produksi')->nullable();
        });
    }
};
