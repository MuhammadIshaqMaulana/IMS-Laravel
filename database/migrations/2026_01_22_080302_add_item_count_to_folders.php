php artisan make:migration add_indexes_to_items_table<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('items', function (Blueprint $table) {
            // Index ini yang bikin pencarian FOLDER dan NAMA jadi secepat kilat
            $table->index('folder_id');
            $table->index('nama');
            $table->index('deleted_at');
        });
    }

    public function down(): void {
        Schema::table('items', function (Blueprint $table) {
            $table->dropIndex(['folder_id', 'nama', 'deleted_at']);
        });
    }
};
