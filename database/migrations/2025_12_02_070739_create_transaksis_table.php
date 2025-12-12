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
        // Nama tabel diubah menjadi 'transaksis'
        Schema::create('transaksis', function (Blueprint $table) {
            $table->id();

            // Kunci Asing ke Produk yang diproduksi
            $table->foreignId('produk_jadi_id')
                  ->constrained('produk_jadis')
                  ->onUpdate('cascade')
                  ->onDelete('restrict');

            $table->unsignedInteger('jumlah_produksi'); // Berapa unit produk yang dibuat
            $table->dateTime('tanggal_produksi');
            $table->text('catatan')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Batalkan migrasi.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaksis');
    }
};
