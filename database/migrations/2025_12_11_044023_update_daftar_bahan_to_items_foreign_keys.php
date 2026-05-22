<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // Tambahkan ini

return new class extends Migration
{
    /**
     * Jalankan migrasi.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE daftar_bahans DROP CONSTRAINT IF EXISTS daftar_bahans_produk_jadi_id_foreign');
            DB::statement('ALTER TABLE daftar_bahans DROP CONSTRAINT IF EXISTS daftar_bahans_bahan_mentah_id_foreign');
        } else {
            Schema::table('daftar_bahans', function (Blueprint $table) {
                try {
                    $table->dropForeign(['produk_jadi_id']);
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), '1091') === false) throw $e;
                }

                try {
                    $table->dropForeign(['bahan_mentah_id']);
                } catch (\Exception $e) {
                    if (strpos($e->getMessage(), '1091') === false) throw $e;
                }
            });
        }

        // --- BLOK KEDUA: RENAME KOLOM ---

        Schema::table('daftar_bahans', function (Blueprint $table) {

            // 2. Ubah nama kolom lama (hanya bisa dilakukan setelah FK hilang)
            if (Schema::hasColumn('daftar_bahans', 'produk_jadi_id')) {
                $table->renameColumn('produk_jadi_id', 'item_produk_id');
            }
            if (Schema::hasColumn('daftar_bahans', 'bahan_mentah_id')) {
                $table->renameColumn('bahan_mentah_id', 'item_bahan_id');
            }

        });

        // --- BLOK KETIGA: TAMBAH FOREIGN KEY BARU KE ITEMS ---

        Schema::table('daftar_bahans', function (Blueprint $table) {

            // 3. Tambahkan Foreign Key baru yang menunjuk ke tabel 'items'
            // Kita harus pastikan kolom sudah ada sebelum membuat FK
            if (Schema::hasColumn('daftar_bahans', 'item_produk_id')) {
                $table->foreign('item_produk_id')
                      ->references('id')
                      ->on('items')
                      ->onDelete('cascade');
            }

            if (Schema::hasColumn('daftar_bahans', 'item_bahan_id')) {
                $table->foreign('item_bahan_id')
                      ->references('id')
                      ->on('items')
                      ->onDelete('cascade');
            }
        });
    }

    /**
     * Batalkan migrasi.
     */
    public function down(): void
    {
        Schema::table('daftar_bahans', function (Blueprint $table) {
            // Hapus foreign keys baru
            try { $table->dropForeign(['item_produk_id']); } catch (\Exception $e) {}
            try { $table->dropForeign(['item_bahan_id']); } catch (\Exception $e) {}
        });

        Schema::table('daftar_bahans', function (Blueprint $table) {
            // Kembalikan nama kolom
            if (Schema::hasColumn('daftar_bahans', 'item_produk_id')) {
                $table->renameColumn('item_produk_id', 'produk_jadi_id');
            }
            if (Schema::hasColumn('daftar_bahans', 'item_bahan_id')) {
                $table->renameColumn('item_bahan_id', 'bahan_mentah_id');
            }
        });
    }
};
