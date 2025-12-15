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

            // 1. Hapus Foreign Key lama
            $table->dropForeign(['produk_jadi_id']);

            // 2. Ubah nama kolom lama
            $table->renameColumn('produk_jadi_id', 'item_id');
        });

        Schema::table('transaksis', function (Blueprint $table) {

            // 3. Tambahkan Foreign Key baru yang menunjuk ke tabel 'items'
            $table->foreign('item_id')
                  ->references('id')
                  ->on('items')
                  ->onDelete('restrict'); // Restrict: Jangan hapus item jika masih ada transaksinya

        });
    }

    /**
     * Batalkan migrasi.
     */
    public function down(): void
    {
        Schema::table('transaksis', function (Blueprint $table) {
            // Hapus foreign key baru
            $table->dropForeign(['item_id']);

            // Kembalikan nama kolom
            $table->renameColumn('item_id', 'produk_jadi_id');
        });
    }
};
