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
        Schema::create('bahan_mentah', function (Blueprint $table) {
            $table->id(); // Kunci Utama (id)
            $table->string('nama', 100)->unique();
            $table->string('satuan', 20); // Contoh: kg, gram, butir
            $table->decimal('stok_saat_ini', 10, 2)->default(0.00);
            $table->decimal('stok_minimum', 10, 2)->default(0.00);
            $table->string('pemasok', 100)->nullable();

            // Default Laravel
            $table->timestamps();
        });
    }

    /**
     * Batalkan migrasi.
     */
    public function down(): void
    {
        Schema::dropIfExists('bahan_mentah');
    }
};
