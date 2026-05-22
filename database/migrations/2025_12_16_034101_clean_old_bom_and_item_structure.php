<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Jalankan migrasi.
     */
    public function up(): void
    {
        // 1. HAPUS TABEL daftar_bahans
        // Kita harus menonaktifkan Foreign Key Check karena ada potensi FK lain yang terikat.
        Schema::disableForeignKeyConstraints();
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TABLE IF EXISTS daftar_bahans CASCADE;');
        } else {
            Schema::dropIfExists('daftar_bahans');
        }
        Schema::enableForeignKeyConstraints();

        // 2. HAPUS KOLOM jenis_item dari items
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('jenis_item');
        });
    }

    /**
     * Batalkan migrasi.
     */
    public function down(): void
    {
        // Jika rollback:

        // 1. Tambahkan kembali kolom jenis_item (Wajib untuk logika lama)
        Schema::table('items', function (Blueprint $table) {
            $table->enum('jenis_item', ['bahan_mentah', 'produk_jadi', 'asset'])->default('bahan_mentah');
        });

        // 2. Tabel daftar_bahans tidak dibuat ulang, karena proses rollback harus diikuti dengan
        // migrasi lama yang dikembalikan.
    }
};
