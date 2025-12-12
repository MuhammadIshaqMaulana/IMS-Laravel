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
        Schema::create('produk_jadis', function (Blueprint $table) {
            $table->id(); // Kunci Utama (id)
            $table->string('nama', 100)->unique();
            $table->string('sku', 50)->unique();
            $table->decimal('harga_jual', 10, 2);
            $table->unsignedInteger('stok_di_tangan')->default(0); // Stok produk jadi (integer)
            $table->boolean('aktif')->default(true);

            // Default Laravel
            $table->timestamps();
        });
    }

    /**
     * Batalkan migrasi.
     */
    public function down(): void
    {
        Schema::dropIfExists('produk_jadis');
    }
};
