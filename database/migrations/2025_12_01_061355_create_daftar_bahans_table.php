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
        Schema::create('daftar_bahans', function (Blueprint $table) {
            $table->id();

            // Kunci Asing 1: Menghubungkan ke produk_jadi
            $table->foreignId('produk_jadi_id')
                  ->constrained('produk_jadis') // Merujuk ke tabel 'produk_jadi'
                  ->onUpdate('cascade')
                  ->onDelete('cascade');

            // Kunci Asing 2: Menghubungkan ke bahan_mentah
            $table->foreignId('bahan_mentah_id')
                  ->constrained('bahan_mentahs') // Merujuk ke tabel 'bahan_mentah'
                  ->onUpdate('cascade')
                  ->onDelete('cascade');

            $table->decimal('jumlah_digunakan', 10, 3); // Jumlah bahan per 1 unit produk jadi

            // Default Laravel
            $table->timestamps();

            // Indeks unik untuk mencegah entri duplikat (misal: Roti A hanya boleh punya satu baris Tepung)
            $table->unique(['produk_jadi_id', 'bahan_mentah_id']);
        });
    }

    /**
     * Batalkan migrasi.
     */
    public function down(): void
    {
        Schema::dropIfExists('daftar_bahans');
    }
};
