<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('transaksis', function (Blueprint $table) {
            // Kita pastikan kolom yang ada hanya id, item_id, folder_id, catatan, dan timestamps
            if (!Schema::hasColumn('transaksis', 'item_id')) {
                $table->foreignId('item_id')->nullable()->after('id')->constrained('items')->onDelete('cascade');
            }
            if (!Schema::hasColumn('transaksis', 'folder_id')) {
                $table->foreignId('folder_id')->nullable()->after('item_id')->constrained('folders')->onDelete('set null');
            }
        });
    }

    public function down(): void {
        Schema::table('transaksis', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
            $table->dropForeign(['folder_id']);
            $table->dropColumn(['item_id', 'folder_id']);
        });
    }
};
