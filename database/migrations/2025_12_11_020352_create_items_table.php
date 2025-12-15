<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jalankan migrasi. Tabel Item akan menggabungkan BahanMentah dan ProdukJadi.
     */
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();

            // --- Field Wajib (dari Produk Jadi & Bahan Mentah) ---
            $table->string('nama', 100);
            $table->string('sku', 50)->nullable()->unique(); // SKU mungkin hanya untuk Produk Jadi
            $table->string('satuan', 20)->nullable();

            // --- Stok & Nilai (Dari Keduanya) ---
            $table->decimal('stok_saat_ini', 10, 2)->default(0); // Stok untuk Bahan Mentah / Produk Jadi
            $table->decimal('stok_minimum', 10, 2)->default(0); // Untuk notifikasi restock
            $table->decimal('harga_jual', 10, 2)->default(0); // Untuk Produk Jadi (Harga Jual/Nilai Item)

            // --- Sortly Fields (Penanda dan Custom Data) ---
            $table->enum('jenis_item', ['bahan_mentah', 'produk_jadi', 'asset'])->default('bahan_mentah');
            $table->string('pemasok', 100)->nullable(); // Dipertahankan, meskipun Sortly menyembunyikannya secara default
            $table->text('note')->nullable(); // Untuk Note
            $table->json('tags')->nullable(); // Untuk Tags (disimpan sebagai JSON Array)
            $table->text('custom_fields')->nullable(); // Untuk Custom Field (misal: disimpan sebagai JSON)

            // --- Fitur Sortly ---
            $table->string('image_link')->nullable(); // Untuk link gambar (export ke CSV/PDF)

            $table->timestamps();
        });
    }

    /**
     * Batalkan migrasi.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
