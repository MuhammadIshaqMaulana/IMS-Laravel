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
            // Soft Delete sudah ditambahkan di migrasi sebelumnya. Tambahkan if not exists.
            if (!Schema::hasColumn('items', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('transaksis', function (Blueprint $table) {
            if (!Schema::hasColumn('transaksis', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    /**
     * Batalkan migrasi.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('transaksis', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
