<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('items', function (Blueprint $table) {
            $table->decimal('harga_beli', 10, 2)->default(0)->after('harga_jual');

        });
    }

    public function down(): void {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('harga_beli');
        });
    }
};
