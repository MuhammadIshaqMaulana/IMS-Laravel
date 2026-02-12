<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('folders', function (Blueprint $table) {
            $table->integer('items_count')->default(0)->after('path');

        });
    }

    public function down(): void {
        Schema::table('folders', function (Blueprint $table) {
            $table->dropColumn('items_count');
        });
    }
};
