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
        Schema::table('items', function (Blueprint $table) {

            // 1. Kolom Material (BOM)
            $table->json('materials')->nullable()->after('note');

            // 2. Kolom Hierarki (Folder dan Varian)
            $table->unsignedBigInteger('folder_id')->nullable()->after('materials');
            $table->unsignedBigInteger('parent_id')->nullable()->after('folder_id');

            // Tambahkan Foreign Keys (menunjuk ke item itu sendiri untuk hierarki)
            $table->foreign('folder_id')->references('id')->on('items')->onDelete('set null');
            $table->foreign('parent_id')->references('id')->on('items')->onDelete('cascade');
        });
    }

    /**
     * Batalkan migrasi.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {

            $table->dropForeign(['folder_id']);
            $table->dropForeign(['parent_id']);

            $table->dropColumn('materials');
            $table->dropColumn('folder_id');
            $table->dropColumn('parent_id');
        });
    }
};
