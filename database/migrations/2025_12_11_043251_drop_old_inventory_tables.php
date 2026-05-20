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
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        Schema::dropIfExists('bahan_mentahs');
        Schema::dropIfExists('produk_jadis');

        // END FIX: Mengaktifkan kembali pengecekan kunci asing
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
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
