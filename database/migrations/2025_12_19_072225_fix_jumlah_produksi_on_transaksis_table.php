<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalankan migrasi.
     */
    public function up(): void
    {
        Schema::table('transaksis', function (Blueprint $table) {
            // Mengubah kolom menjadi integer (signed) agar bisa menyimpan angka negatif
            $table->integer('jumlah_produksi')->change();
        });
    }

    /**
     * Batalkan migrasi.
     */
    public function down(): void
    {
        Schema::table('transaksis', function (Blueprint $table) {
            // Kembalikan ke unsigned jika di-rollback
            $table->unsignedInteger('jumlah_produksi')->change();
        });
    }
};
