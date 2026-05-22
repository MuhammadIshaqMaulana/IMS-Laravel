<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // Tambahkan ini

return new class extends Migration
{
    /**
     * Jalankan migrasi.
     */
    public function up(): void
    {
        // Peringatan: Sebelum menjalankan ini, pastikan tabel daftar_bahan dan transaksi
        // sudah di-rollback atau dimodifikasi agar tidak lagi memiliki Foreign Key
        // yang menunjuk ke tabel ini.

        // START FIX: Menonaktifkan pengecekan kunci asing sementara
        Schema::disableForeignKeyConstraints();

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP TABLE IF EXISTS bahan_mentahs CASCADE;');
            DB::statement('DROP TABLE IF EXISTS produk_jadis CASCADE;');
        } else {
            Schema::dropIfExists('bahan_mentahs');
            Schema::dropIfExists('produk_jadis');
        }

        // END FIX: Mengaktifkan kembali pengecekan kunci asing
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Batalkan migrasi (rollback).
     */
    public function down(): void
    {
        // Dalam kasus rollback, kita TIDAK membuat ulang tabel lama,
        // karena arsitektur baru sudah menggunakan tabel 'items'.
        // Ini adalah langkah satu arah untuk menghapus warisan.
    }
};
